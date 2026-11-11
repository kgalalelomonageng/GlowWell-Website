<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "customer") {
    header("Location: ../authentication/login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$today = date("Y-m-d");
$stats = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN appointment_date >= ? AND status <> 'Cancelled' THEN 1 ELSE 0 END) AS upcoming
     FROM appointments
     WHERE user_id = ?"
);
$stats->execute([$today, $userId]);
$appointmentStats = $stats->fetch(PDO::FETCH_ASSOC);

$nextAppointment = $pdo->prepare(
    "SELECT a.appointment_date, a.appointment_time, a.status,
            s.service_name, st.fullname AS staff_name
     FROM appointments a
     INNER JOIN services s ON a.service_id = s.service_id
     INNER JOIN staff st ON a.staff_id = st.staff_id
     WHERE a.user_id = ?
       AND a.appointment_date >= ?
       AND a.status <> 'Cancelled'
     ORDER BY a.appointment_date ASC, a.appointment_time ASC
     LIMIT 1"
);
$nextAppointment->execute([$userId, $today]);
$next = $nextAppointment->fetch(PDO::FETCH_ASSOC);

$pageTitle = "My space";
include "../includes/header.php";
?>
<section class="mb-4">
    <span class="eyebrow">Your GlowWell space</span>
    <h1 class="mt-2 mb-2">Welcome back, <?= htmlspecialchars($_SESSION["fullname"] ?? "there", ENT_QUOTES, "UTF-8") ?>.</h1>
    <p class="text-muted mb-0">A moment for yourself is always a good idea.</p>
</section>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <article class="card dashboard-card">
            <h5>Total visits</h5>
            <h2><?= (int) ($appointmentStats["total"] ?? 0) ?></h2>
        </article>
    </div>
    <div class="col-sm-6 col-xl-3">
        <article class="card dashboard-card">
            <h5>Upcoming visits</h5>
            <h2><?= (int) ($appointmentStats["upcoming"] ?? 0) ?></h2>
        </article>
    </div>
    <div class="col-xl-6">
        <article class="card dashboard-card d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
            <div>
                <h5 class="mb-2">Ready for a reset?</h5>
                <p class="text-muted mb-0">Find a treatment and time that work for you.</p>
            </div>
            <a class="btn btn-primary flex-shrink-0" href="book_appointment.php">Book a visit <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></i></a>
        </article>
    </div>
</div>

<section class="card p-4 p-lg-5">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
        <div>
            <span class="eyebrow">On your calendar</span>
            <h2 class="h4 mt-2 mb-0">Your next visit</h2>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="my_appointment.php">All appointments</a>
    </div>
    <?php if ($next): ?>
        <div class="row align-items-center g-3 pt-3">
            <div class="col-md-7">
                <h3 class="h5 mb-1"><?= htmlspecialchars($next["service_name"], ENT_QUOTES, "UTF-8") ?></h3>
                <p class="text-muted mb-0">With <?= htmlspecialchars($next["staff_name"], ENT_QUOTES, "UTF-8") ?></p>
            </div>
            <div class="col-md-3">
                <div class="fw-bold"><?= date("D, j M Y", strtotime($next["appointment_date"])) ?></div>
                <div class="text-muted"><?= date("g:i A", strtotime($next["appointment_time"])) ?></div>
            </div>
            <div class="col-md-2 text-md-end">
                <span class="badge-status <?= strtolower($next["status"]) ?>"><?= htmlspecialchars($next["status"], ENT_QUOTES, "UTF-8") ?></span>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="service-icon mx-auto"><i class="fa-regular fa-calendar" aria-hidden="true"></i></div>
            <h3 class="h5">Nothing booked just yet</h3>
            <p class="mb-3">Your next moment of calm is waiting for you.</p>
            <a class="btn btn-primary" href="book_appointment.php">Explore treatments</a>
        </div>
    <?php endif; ?>
</section>
<?php include "../includes/footer.php"; ?>
