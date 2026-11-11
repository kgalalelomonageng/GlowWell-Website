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

$message = "";
$messageType = "warning";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $appointmentId = filter_var($_POST["appointment_id"] ?? "", FILTER_VALIDATE_INT);
    $actionInput = $_POST["action"] ?? "";
    $action = is_string($actionInput) ? $actionInput : "";
    $token = $_POST["csrf_token"] ?? "";
    $nextStatus = [
        "confirm" => "Confirmed",
        "complete" => "Completed",
        "cancel" => "Cancelled"
    ][$action] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Your session has expired. Please refresh the page and try again.";
    } elseif (!$appointmentId || $nextStatus === "") {
        $message = "That appointment action is not valid.";
    } else {
        if ($action === "confirm") {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'Confirmed' WHERE appointment_id = ? AND status = 'Pending'");
        } elseif ($action === "complete") {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE appointment_id = ? AND status = 'Confirmed'");
        } else {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ? AND status IN ('Pending', 'Confirmed')");
        }
        $stmt->execute([$appointmentId]);
        header("Location: appointments.php");
        exit;
    }
}

$appointments = $pdo->query(
    "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status,
            u.fullname AS customer, u.email AS customer_email, u.phone AS customer_phone,
            s.service_name, st.fullname AS staff_name
     FROM appointments a
     INNER JOIN users u ON a.user_id = u.user_id
     INNER JOIN services s ON a.service_id = s.service_id
     INNER JOIN staff st ON a.staff_id = st.staff_id
     ORDER BY a.appointment_date DESC, a.appointment_time DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Appointments";
include "../includes/header.php";
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-3 mb-4">
    <div>
        <span class="eyebrow">Clinic schedule</span>
        <h1 class="mt-2 mb-1">Appointments</h1>
        <p class="text-muted mb-0">Review bookings and keep each visit moving smoothly.</p>
    </div>
    <a class="btn btn-outline-primary" href="dashboard.php"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i> Overview</a>
</div>
<?php if ($message !== ""): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, "UTF-8") ?>" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
<?php endif; ?>

<section class="card overflow-hidden">
    <?php if ($appointments): ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Customer contact</th>
                        <th scope="col">Treatment</th>
                        <th scope="col">Team member</th>
                        <th scope="col">Date &amp; time</th>
                        <th scope="col">Status</th>
                        <th scope="col">Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($appointment["customer"], ENT_QUOTES, "UTF-8") ?></div>
                                <a class="small" href="mailto:<?= htmlspecialchars($appointment["customer_email"], ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars($appointment["customer_email"], ENT_QUOTES, "UTF-8") ?></a>
                                <?php if ($appointment["customer_phone"] !== ""): ?>
                                    <div><a class="small text-muted" href="tel:<?= htmlspecialchars($appointment["customer_phone"], ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars($appointment["customer_phone"], ENT_QUOTES, "UTF-8") ?></a></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($appointment["service_name"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($appointment["staff_name"], ENT_QUOTES, "UTF-8") ?></td>
                            <td>
                                <div><?= date("D, j M Y", strtotime($appointment["appointment_date"])) ?></div>
                                <small class="text-muted"><?= date("g:i A", strtotime($appointment["appointment_time"])) ?></small>
                            </td>
                            <td><span class="badge-status <?= strtolower($appointment["status"]) ?>"><?= htmlspecialchars($appointment["status"], ENT_QUOTES, "UTF-8") ?></span></td>
                            <td>
                                <?php if (in_array($appointment["status"], ["Pending", "Confirmed"], true)): ?>
                                    <form class="d-flex flex-wrap gap-1" method="post" action="appointments.php">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
                                        <input type="hidden" name="appointment_id" value="<?= (int) $appointment["appointment_id"] ?>">
                                        <?php if ($appointment["status"] === "Pending"): ?>
                                            <button class="btn btn-outline-primary btn-sm" name="action" value="confirm" type="submit">Confirm</button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-primary btn-sm" name="action" value="complete" type="submit">Complete</button>
                                        <?php endif; ?>
                                        <button class="btn btn-outline-danger btn-sm" name="action" value="cancel" type="submit">Cancel</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">No action needed</span>
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
            <p class="mb-0">New bookings will appear here.</p>
        </div>
    <?php endif; ?>
</section>
<?php include "../includes/footer.php"; ?>
