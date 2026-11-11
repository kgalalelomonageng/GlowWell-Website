<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "customer") {
    header("Location: ../authentication/login.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$services = $pdo->query("SELECT service_id, service_name, duration, price FROM services ORDER BY service_name")->fetchAll(PDO::FETCH_ASSOC);
$staff = $pdo->query("SELECT staff_id, fullname, speciality FROM staff WHERE status = 'Active' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
$schedules = $pdo->query("SELECT staff_id, weekday, start_time, end_time FROM staff_schedules")->fetchAll(PDO::FETCH_ASSOC);
$message = "";
$messageType = "danger";
$values = [
    "service_id" => "",
    "staff_id" => "",
    "appointment_date" => "",
    "appointment_time" => ""
];

if (!empty($_SESSION["booking_message"])) {
    $message = $_SESSION["booking_message"];
    $messageType = "success";
    unset($_SESSION["booking_message"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($values as $field => $default) {
        $input = $_POST[$field] ?? $default;
        $values[$field] = is_string($input) ? trim($input) : $default;
    }

    $serviceId = filter_var($values["service_id"], FILTER_VALIDATE_INT);
    $staffId = filter_var($values["staff_id"], FILTER_VALIDATE_INT);
    $date = DateTime::createFromFormat("!Y-m-d", $values["appointment_date"]);
    $validDate = $date && $date->format("Y-m-d") === $values["appointment_date"] && $date->format("Y-m-d") >= date("Y-m-d");
    $validTime = preg_match("/^(?:[01]\\d|2[0-3]):[0-5]\\d$/", $values["appointment_time"]) === 1;
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Your session has expired. Please refresh the page and try again.";
    } elseif (!$serviceId || !$staffId || !$validDate || !$validTime) {
        $message = "Choose a treatment, team member, valid date, and time.";
    } else {
        $customerCheck = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? FOR UPDATE");
        $staffCheck = $pdo->prepare("SELECT staff_id FROM staff WHERE staff_id = ? AND status = 'Active' FOR UPDATE");

        $pdo->beginTransaction();
        try {
            $customerCheck->execute([(int) $_SESSION["user_id"]]);
            $staffCheck->execute([$staffId]);
            $serviceCheck = $pdo->prepare("SELECT duration FROM services WHERE service_id = ?");
            $serviceCheck->execute([$serviceId]);
            $service = $serviceCheck->fetch(PDO::FETCH_ASSOC);

            if (!$customerCheck->fetchColumn()) {
                $message = "Your account is no longer available. Please sign in again.";
                $pdo->rollBack();
            } elseif (!$staffCheck->fetchColumn() || !$service) {
                $message = "That treatment or team member is no longer available. Please make another selection.";
                $pdo->rollBack();
            } else {
                $weekday = (int) $date->format("N");
                $scheduleQuery = $pdo->prepare(
                    "SELECT start_time, end_time
                     FROM staff_schedules
                     WHERE staff_id = ? AND weekday = ?"
                );
                $scheduleQuery->execute([$staffId, $weekday]);
                $schedule = $scheduleQuery->fetch(PDO::FETCH_ASSOC);
                $requestedStart = ((int) substr($values["appointment_time"], 0, 2) * 60)
                    + (int) substr($values["appointment_time"], 3, 2);
                $requestedEnd = $requestedStart + (int) $service["duration"];
                $shiftStart = $schedule ? ((int) substr($schedule["start_time"], 0, 2) * 60) + (int) substr($schedule["start_time"], 3, 2) : 0;
                $shiftEnd = $schedule ? ((int) substr($schedule["end_time"], 0, 2) * 60) + (int) substr($schedule["end_time"], 3, 2) : 0;

                if (!$schedule || $requestedStart < $shiftStart || $requestedEnd > $shiftEnd) {
                    $message = "The selected team member is not available for the full treatment at that time.";
                    $pdo->rollBack();
                } else {
                    $customerBusyQuery = $pdo->prepare(
                        "SELECT a.appointment_time, s.duration
                         FROM appointments a
                         INNER JOIN services s ON a.service_id = s.service_id
                         WHERE a.user_id = ?
                           AND a.appointment_date = ?
                           AND a.status <> 'Cancelled'"
                    );
                    $customerBusyQuery->execute([
                        (int) $_SESSION["user_id"],
                        $values["appointment_date"]
                    ]);
                    $customerOverlap = false;
                    foreach ($customerBusyQuery->fetchAll(PDO::FETCH_ASSOC) as $booking) {
                        $bookedStart = ((int) substr($booking["appointment_time"], 0, 2) * 60)
                            + (int) substr($booking["appointment_time"], 3, 2);
                        $bookedEnd = $bookedStart + (int) $booking["duration"];
                        if ($requestedStart < $bookedEnd && $bookedStart < $requestedEnd) {
                            $customerOverlap = true;
                            break;
                        }
                    }

                    if ($customerOverlap) {
                        $message = "You already have another appointment during that time. Please choose a different time.";
                        $pdo->rollBack();
                    } else {
                        $busyQuery = $pdo->prepare(
                            "SELECT a.appointment_time, s.duration
                             FROM appointments a
                             INNER JOIN services s ON a.service_id = s.service_id
                             WHERE a.staff_id = ?
                               AND a.appointment_date = ?
                               AND a.status <> 'Cancelled'"
                        );
                        $busyQuery->execute([$staffId, $values["appointment_date"]]);
                        $overlap = false;
                        foreach ($busyQuery->fetchAll(PDO::FETCH_ASSOC) as $booking) {
                            $bookedStart = ((int) substr($booking["appointment_time"], 0, 2) * 60)
                                + (int) substr($booking["appointment_time"], 3, 2);
                            $bookedEnd = $bookedStart + (int) $booking["duration"];
                            if ($requestedStart < $bookedEnd && $bookedStart < $requestedEnd) {
                                $overlap = true;
                                break;
                            }
                        }

                        if ($overlap) {
                            $message = "That team member's schedule overlaps another booking. Please choose another time.";
                            $pdo->rollBack();
                        } else {
                            $insert = $pdo->prepare(
                                "INSERT INTO appointments (user_id, service_id, staff_id, appointment_date, appointment_time)
                                 VALUES (?, ?, ?, ?, ?)"
                            );
                            $insert->execute([
                                (int) $_SESSION["user_id"],
                                $serviceId,
                                $staffId,
                                $values["appointment_date"],
                                $values["appointment_time"] . ":00"
                            ]);
                            $pdo->commit();

                            $_SESSION["booking_message"] = "Your appointment is booked. We look forward to seeing you.";
                            header("Location: book_appointment.php");
                            exit;
                        }
                    }
                }
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}

$pageTitle = "Book a visit";
include "../includes/header.php";
?>
<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="mb-4">
            <span class="eyebrow">A little time for you</span>
            <h1 class="mt-2 mb-2">Book your visit</h1>
            <p class="text-muted mb-0">Choose the treatment, team member, and time that feel right.</p>
        </div>
        <div class="card p-4 p-lg-5">
            <?php if ($message !== ""): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, "UTF-8") ?>" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
            <?php endif; ?>
            <?php if (!$services || !$staff): ?>
                <div class="alert alert-info mb-0" role="status">
                    Online booking is temporarily unavailable while we update our treatments and team. Please check back soon.
                </div>
            <?php else: ?>
                <form method="post" action="book_appointment.php" data-appointment-form>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
                    <div class="mb-4">
                        <label class="form-label" for="service_id">Choose a treatment</label>
                        <select class="form-select" id="service_id" name="service_id" required>
                            <option value="">Select a treatment</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= (int) $service["service_id"] ?>" <?= $values["service_id"] == $service["service_id"] ? "selected" : "" ?>>
                                    <?= htmlspecialchars($service["service_name"], ENT_QUOTES, "UTF-8") ?> · <?= (int) $service["duration"] ?> min · R <?= number_format((float) $service["price"], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="staff_id">Choose a team member</label>
                        <select class="form-select" id="staff_id" name="staff_id" required>
                            <option value="">Select a team member</option>
                            <?php foreach ($staff as $employee): ?>
                                <option value="<?= (int) $employee["staff_id"] ?>" <?= $values["staff_id"] == $employee["staff_id"] ? "selected" : "" ?>>
                                    <?= htmlspecialchars($employee["fullname"], ENT_QUOTES, "UTF-8") ?><?= $employee["speciality"] ? " · " . htmlspecialchars($employee["speciality"], ENT_QUOTES, "UTF-8") : "" ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label class="form-label" for="appointment_date">Date</label>
                            <input class="form-control" id="appointment_date" name="appointment_date" type="date" min="<?= date("Y-m-d") ?>" value="<?= htmlspecialchars($values["appointment_date"], ENT_QUOTES, "UTF-8") ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="appointment_time">Time</label>
                            <input class="form-control" id="appointment_time" name="appointment_time" type="time" value="<?= htmlspecialchars($values["appointment_time"], ENT_QUOTES, "UTF-8") ?>" required>
                        </div>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <button class="btn btn-primary" type="submit">Confirm appointment</button>
                        <a class="btn btn-outline-primary" href="dashboard.php">Back to my space</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
window.GlowWellSchedules = <?= json_encode($schedules, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.GlowWellServices = <?= json_encode($services, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="../assets/js/appointments.js"></script>
<?php include "../includes/footer.php"; ?>
