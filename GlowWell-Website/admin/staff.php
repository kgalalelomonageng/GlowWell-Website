<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../authentication/login.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = $_SESSION["staff_message"] ?? "";
$messageType = $_SESSION["staff_message_type"] ?? "success";
unset($_SESSION["staff_message"], $_SESSION["staff_message_type"]);
$weekdays = [1 => "Monday", 2 => "Tuesday", 3 => "Wednesday", 4 => "Thursday", 5 => "Friday", 6 => "Saturday", 7 => "Sunday"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $message = "";
    $messageType = "success";
    $token = $_POST["csrf_token"] ?? "";
    $actionInput = $_POST["action"] ?? "";
    $action = is_string($actionInput) ? $actionInput : "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Your session has expired. Please refresh the page and try again.";
        $messageType = "warning";
    } elseif (in_array($action, ["create", "update"], true)) {
        $id = filter_var($_POST["staff_id"] ?? "", FILTER_VALIDATE_INT);
        $nameInput = $_POST["fullname"] ?? "";
        $phoneInput = $_POST["phone"] ?? "";
        $specialityInput = $_POST["speciality"] ?? "";
        $name = is_string($nameInput) ? trim($nameInput) : "";
        $phone = is_string($phoneInput) ? trim($phoneInput) : "";
        $speciality = is_string($specialityInput) ? trim($specialityInput) : "";
        $status = $_POST["status"] ?? "Active";

        if (($action === "update" && !$id) || $name === "" || strlen($name) > 100 || strlen($phone) > 20 || strlen($speciality) > 100 || !in_array($status, ["Active", "Inactive"], true)) {
            $message = "Enter a valid name, status, and contact details within their field limits.";
            $messageType = "warning";
        } else {
            if ($action === "create") {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("INSERT INTO staff (fullname, phone, speciality, status) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $phone, $speciality, $status]);
                    $staffId = (int) $pdo->lastInsertId();
                    if ($status === "Active") {
                        $schedule = $pdo->prepare("INSERT INTO staff_schedules (staff_id, weekday, start_time, end_time) VALUES (?, ?, '09:00:00', '18:00:00')");
                        for ($weekday = 1; $weekday <= 6; $weekday++) {
                            $schedule->execute([$staffId, $weekday]);
                        }
                    }
                    $pdo->commit();
                    $message = "Team member added with a Monday-to-Saturday schedule. You can adjust their hours below.";
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            } else {
                $stmt = $pdo->prepare("UPDATE staff SET fullname = ?, phone = ?, speciality = ?, status = ? WHERE staff_id = ?");
                $stmt->execute([$name, $phone, $speciality, $status, $id]);
                if ($stmt->rowCount() === 0) {
                    $exists = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE staff_id = ?");
                    $exists->execute([$id]);
                    if (!(int) $exists->fetchColumn()) {
                        $message = "That team member could not be found.";
                        $messageType = "warning";
                    }
                }
                if ($message === "") {
                    $message = "Team member details updated.";
                }
            }

            if ($messageType === "success") {
                $_SESSION["staff_message"] = $message;
                header("Location: staff.php");
                exit;
            }
        }
    } elseif ($action === "delete") {
        $staffId = filter_var($_POST["staff_id"] ?? "", FILTER_VALIDATE_INT);
        if (!$staffId) {
            $message = "That team member could not be found.";
            $messageType = "warning";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM staff WHERE staff_id = ?");
                $stmt->execute([$staffId]);
                $_SESSION["staff_message"] = $stmt->rowCount() ? "Team member deleted." : "That team member could not be found.";
            } catch (PDOException $exception) {
                if ($exception->getCode() === "23000" || str_contains(strtolower($exception->getMessage()), "constraint")) {
                    $_SESSION["staff_message"] = "This team member has appointment history. Mark them inactive instead of deleting them.";
                    $_SESSION["staff_message_type"] = "warning";
                } else {
                    throw $exception;
                }
            }
            header("Location: staff.php");
            exit;
        }
    } elseif ($action === "schedule") {
        $staffId = filter_var($_POST["staff_id"] ?? "", FILTER_VALIDATE_INT);
        $schedules = $_POST["schedule"] ?? [];
        $scheduleRows = [];
        $valid = (bool) $staffId && is_array($schedules);

        if ($valid) {
            $staffCheck = $pdo->prepare("SELECT staff_id FROM staff WHERE staff_id = ?");
            $staffCheck->execute([$staffId]);
            $valid = (bool) $staffCheck->fetchColumn();

            foreach ($schedules as $weekday => $hours) {
                if (!is_array($hours) || !isset($weekdays[(int) $weekday]) || !isset($hours["enabled"])) {
                    continue;
                }
                $start = $hours["start"] ?? "";
                $end = $hours["end"] ?? "";
                $validStart = is_string($start) && preg_match("/^(?:[01]\\d|2[0-3]):[0-5]\\d$/", $start);
                $validEnd = is_string($end) && preg_match("/^(?:[01]\\d|2[0-3]):[0-5]\\d$/", $end);
                if (!$validStart || !$validEnd || $start >= $end) {
                    $valid = false;
                    break;
                }
                $scheduleRows[] = [(int) $weekday, $start, $end];
            }
        }

        if (!$valid) {
            $message = "Select valid opening and closing times for each working day.";
            $messageType = "warning";
        } else {
            $pdo->beginTransaction();
            try {
                $delete = $pdo->prepare("DELETE FROM staff_schedules WHERE staff_id = ?");
                $delete->execute([$staffId]);
                $insert = $pdo->prepare("INSERT INTO staff_schedules (staff_id, weekday, start_time, end_time) VALUES (?, ?, ?, ?)");
                foreach ($scheduleRows as [$weekday, $start, $end]) {
                    $insert->execute([$staffId, $weekday, "$start:00", "$end:00"]);
                }
                $pdo->commit();
                $_SESSION["staff_message"] = "Staff schedule saved.";
                header("Location: staff.php");
                exit;
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }
    } else {
        $message = "That team action is not valid.";
        $messageType = "warning";
    }
}

$editId = filter_var($_GET["edit"] ?? "", FILTER_VALIDATE_INT);
$editMember = null;
if ($editId) {
    $editQuery = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ?");
    $editQuery->execute([$editId]);
    $editMember = $editQuery->fetch(PDO::FETCH_ASSOC) ?: null;
}

$staff = $pdo->query("SELECT * FROM staff ORDER BY fullname ASC")->fetchAll(PDO::FETCH_ASSOC);
$schedules = $pdo->query("SELECT * FROM staff_schedules ORDER BY staff_id, weekday")->fetchAll(PDO::FETCH_ASSOC);
$schedulesByStaff = [];
foreach ($schedules as $schedule) {
    $schedulesByStaff[(int) $schedule["staff_id"]][(int) $schedule["weekday"]] = $schedule;
}

$pageTitle = "Team";
include "../includes/header.php";
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-3 mb-4">
    <div>
        <span class="eyebrow">Clinic management</span>
        <h1 class="mt-2 mb-1">Your team &amp; schedules</h1>
        <p class="text-muted mb-0">Manage staff details and the times customers can book.</p>
    </div>
    <a class="btn btn-outline-primary" href="dashboard.php"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i> Overview</a>
</div>

<?php if ($message !== ""): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, "UTF-8") ?>" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <section class="card p-4">
            <span class="eyebrow"><?= $editMember ? "Edit team" : "Grow your team" ?></span>
            <h2 class="h4 mt-2 mb-4"><?= $editMember ? "Update team member" : "Add a team member" ?></h2>
            <form method="post" action="staff.php<?= $editMember ? "?edit=" . (int) $editMember["staff_id"] : "" ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
                <input type="hidden" name="action" value="<?= $editMember ? "update" : "create" ?>">
                <?php if ($editMember): ?>
                    <input type="hidden" name="staff_id" value="<?= (int) $editMember["staff_id"] ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label" for="fullname">Full name</label>
                    <input class="form-control" id="fullname" name="fullname" type="text" maxlength="100" value="<?= htmlspecialchars($editMember["fullname"] ?? "", ENT_QUOTES, "UTF-8") ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" type="tel" maxlength="20" value="<?= htmlspecialchars($editMember["phone"] ?? "", ENT_QUOTES, "UTF-8") ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="speciality">Speciality</label>
                    <input class="form-control" id="speciality" name="speciality" type="text" maxlength="100" value="<?= htmlspecialchars($editMember["speciality"] ?? "", ENT_QUOTES, "UTF-8") ?>" placeholder="e.g. Facials">
                </div>
                <?php if ($editMember): ?>
                    <div class="mb-4">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <?php foreach (["Active", "Inactive"] as $status): ?>
                                <option value="<?= $status ?>" <?= $editMember["status"] === $status ? "selected" : "" ?>><?= $status ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <button class="btn btn-primary w-100" type="submit"><?= $editMember ? "Save changes" : "Add team member" ?></button>
                <?php if ($editMember): ?>
                    <a class="btn btn-outline-primary w-100 mt-2" href="staff.php">Cancel edit</a>
                <?php endif; ?>
            </form>
        </section>
    </div>
    <div class="col-lg-8">
        <?php if (!$staff): ?>
            <section class="card empty-state">No team members have been added yet.</section>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($staff as $member): ?>
                    <?php $memberId = (int) $member["staff_id"]; ?>
                    <section class="card p-4">
                        <div class="d-flex flex-column flex-sm-row justify-content-between gap-3 mb-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <h2 class="h5 mb-0"><?= htmlspecialchars($member["fullname"], ENT_QUOTES, "UTF-8") ?></h2>
                                    <span class="badge-status <?= strtolower($member["status"]) ?>"><?= htmlspecialchars($member["status"], ENT_QUOTES, "UTF-8") ?></span>
                                </div>
                                <div class="text-muted small mt-1"><?= htmlspecialchars($member["speciality"] ?? "", ENT_QUOTES, "UTF-8") ?><?= $member["phone"] ? " · " . htmlspecialchars($member["phone"], ENT_QUOTES, "UTF-8") : "" ?></div>
                            </div>
                            <div class="d-flex gap-2 align-self-start">
                                <a class="btn btn-outline-primary btn-sm" href="staff.php?edit=<?= $memberId ?>">Edit</a>
                                <form method="post" action="staff.php" onsubmit="return confirm('Delete this team member? Staff with appointment history must be marked inactive instead.')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="staff_id" value="<?= $memberId ?>">
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                                </form>
                            </div>
                        </div>
                        <form method="post" action="staff.php" class="schedule-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
                            <input type="hidden" name="action" value="schedule">
                            <input type="hidden" name="staff_id" value="<?= $memberId ?>">
                            <h3 class="h6 mb-3">Weekly availability</h3>
                            <?php foreach ($weekdays as $weekday => $day): ?>
                                <?php $hours = $schedulesByStaff[$memberId][$weekday] ?? null; ?>
                                <div class="row align-items-center g-2 mb-2">
                                    <div class="col-sm-4 col-lg-3">
                                        <div class="form-check">
                                            <input class="form-check-input schedule-toggle" type="checkbox" id="schedule-<?= $memberId ?>-<?= $weekday ?>" name="schedule[<?= $weekday ?>][enabled]" value="1" <?= $hours ? "checked" : "" ?>>
                                            <label class="form-check-label" for="schedule-<?= $memberId ?>-<?= $weekday ?>"><?= $day ?></label>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <input class="form-control form-control-sm schedule-time" aria-label="<?= $day ?> start time" name="schedule[<?= $weekday ?>][start]" type="time" value="<?= $hours ? htmlspecialchars(substr($hours["start_time"], 0, 5), ENT_QUOTES, "UTF-8") : "09:00" ?>" <?= $hours ? "" : "disabled" ?>>
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <input class="form-control form-control-sm schedule-time" aria-label="<?= $day ?> end time" name="schedule[<?= $weekday ?>][end]" type="time" value="<?= $hours ? htmlspecialchars(substr($hours["end_time"], 0, 5), ENT_QUOTES, "UTF-8") : "18:00" ?>" <?= $hours ? "" : "disabled" ?>>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <button class="btn btn-outline-primary btn-sm mt-3" type="submit">Save weekly schedule</button>
                        </form>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="../assets/js/staff.js"></script>
<?php include "../includes/footer.php"; ?>
