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

$message = "";
$messageType = "success";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";
    $appointmentId = filter_var($_POST["appointment_id"] ?? "", FILTER_VALIDATE_INT);
    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Your session has expired. Please refresh the page and try again.";
        $messageType = "warning";
    } elseif (!$appointmentId) {
        $message = "That appointment could not be found.";
        $messageType = "warning";
    } else {
        $cancel = $pdo->prepare(
            "UPDATE appointments
             SET status = 'Cancelled'
             WHERE appointment_id = ?
               AND user_id = ?
               AND status = 'Pending'
               AND appointment_date >= ?"
        );
        $cancel->execute([$appointmentId, (int) $_SESSION["user_id"], date("Y-m-d")]);
        if ($cancel->rowCount()) {
            $_SESSION["appointment_message"] = "Your appointment has been cancelled.";
            header("Location: my_appointment.php");
            exit;
        }
        $message = "Only your upcoming pending appointments can be cancelled online. Contact the clinic if you need help.";
        $messageType = "warning";
    }
}

if (isset($_SESSION["appointment_message"])) {
    $message = $_SESSION["appointment_message"];
    $messageType = "success";
    unset($_SESSION["appointment_message"]);
}

$stmt = $pdo->prepare(
    "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status,
            s.service_name, st.fullname AS staff_name
     FROM appointments a
     INNER JOIN services s ON a.service_id = s.service_id
     INNER JOIN staff st ON a.staff_id = st.staff_id
     WHERE a.user_id = ?
     ORDER BY a.appointment_date DESC, a.appointment_time DESC"
);
$stmt->execute([(int) $_SESSION["user_id"]]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "My appointments";
include "../includes/header.php";
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-3 mb-4">
    <div>
        <span class="eyebrow">Your GlowWell visits</span>
        <h1 class="mt-2 mb-1">My appointments</h1>
        <p class="text-muted mb-0">All your time for you, together in one place.</p>
    </div>
    <a class="btn btn-primary" href="book_appointment.php"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i> Book a visit</a>
</div>
<?php if ($message !== ""): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, "UTF-8") ?>" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
<?php endif; ?>
<div class="card overflow-hidden">
    <?php if ($appointments): ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Treatment</th>
                        <th scope="col">Team member</th>
                        <th scope="col">Date &amp; time</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($appointment["service_name"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($appointment["staff_name"], ENT_QUOTES, "UTF-8") ?></td>
                            <td>
                                <div><?= date("D, j M Y", strtotime($appointment["appointment_date"])) ?></div>
                                <small class="text-muted"><?= date("g:i A", strtotime($appointment["appointment_time"])) ?></small>
                            </td>
                            <td><span class="badge-status <?= strtolower($appointment["status"]) ?>"><?= htmlspecialchars($appointment["status"], ENT_QUOTES, "UTF-8") ?></span></td>
                            <td>
                                <?php if ($appointment["status"] === "Pending" && $appointment["appointment_date"] >= date("Y-m-d")): ?>
                                    <form method="post" action="my_appointment.php" onsubmit="return confirm('Cancel this appointment?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
                                        <input type="hidden" name="appointment_id" value="<?= (int) $appointment["appointment_id"] ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Cancel</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="service-icon mx-auto"><i class="fa-regular fa-calendar" aria-hidden="true"></i></div>
            <h2 class="h5">No appointments yet</h2>
            <p class="mb-3">Your next little reset is waiting.</p>
            <a class="btn btn-primary" href="book_appointment.php">Explore treatments</a>
        </div>
    <?php endif; ?>
</div>
<?php include "../includes/footer.php"; ?>
