<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../authentication/login.php");
    exit;
}

$totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$totalStaff = (int) $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
$totalServices = (int) $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
$totalBookings = (int) $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$today = date("Y-m-d");
$todayQuery = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND status <> 'Cancelled'");
$todayQuery->execute([$today]);
$todayBookings = (int) $todayQuery->fetchColumn();

$pageTitle = "Admin overview";
include "../includes/header.php";
?>
<section class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <span class="eyebrow">Clinic overview</span>
        <h1 class="mt-2 mb-1">Good day, <?= htmlspecialchars($_SESSION["fullname"] ?? "team", ENT_QUOTES, "UTF-8") ?>.</h1>
        <p class="text-muted mb-0">Here’s what’s happening at GlowWell.</p>
    </div>
    <a class="btn btn-primary" href="appointments.php">Manage appointments <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></i></a>
</section>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <article class="card dashboard-card"><h5>Customers</h5><h2><?= $totalCustomers ?></h2></article>
    </div>
    <div class="col-sm-6 col-xl-3">
        <article class="card dashboard-card"><h5>Team members</h5><h2><?= $totalStaff ?></h2></article>
    </div>
    <div class="col-sm-6 col-xl-3">
        <article class="card dashboard-card"><h5>Treatments</h5><h2><?= $totalServices ?></h2></article>
    </div>
    <div class="col-sm-6 col-xl-3">
        <article class="card dashboard-card"><h5>All appointments</h5><h2><?= $totalBookings ?></h2></article>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <section class="card p-4 p-lg-5 h-100">
            <span class="eyebrow">Today at GlowWell</span>
            <div class="d-flex align-items-end gap-3 mt-2">
                <h2 class="display-4 fw-bold mb-0"><?= $todayBookings ?></h2>
                <p class="text-muted mb-2">appointments scheduled for today</p>
            </div>
            <hr class="my-4">
            <p class="text-muted mb-0">Keep your clinic running smoothly by checking today’s schedule and updating appointment statuses.</p>
            <a class="btn btn-outline-primary align-self-start mt-3" href="appointments.php">Open schedule</a>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="card p-4 p-lg-5 h-100">
            <span class="eyebrow">Quick access</span>
            <h2 class="h4 mt-2 mb-4">Manage your clinic</h2>
            <div class="d-grid gap-2">
                <a class="btn btn-outline-primary text-start" href="services.php"><i class="fa-solid fa-leaf me-2" aria-hidden="true"></i> Treatments &amp; pricing</a>
                <a class="btn btn-outline-primary text-start" href="staff.php"><i class="fa-solid fa-people-group me-2" aria-hidden="true"></i> Team members</a>
                <a class="btn btn-outline-primary text-start" href="appointments.php"><i class="fa-regular fa-calendar me-2" aria-hidden="true"></i> Appointment schedule</a>
                <a class="btn btn-outline-primary text-start" href="reports.php"><i class="fa-solid fa-chart-column me-2" aria-hidden="true"></i> Reports &amp; exports</a>
            </div>
        </section>
    </div>
</div>
<?php include "../includes/footer.php"; ?>
