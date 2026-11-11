<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$pageTitle = $pageTitle ?? "GlowWell";
$role = $_SESSION["role"] ?? "";
$userName = $_SESSION["fullname"] ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, "UTF-8") ?> | GlowWell</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <span class="brand-mark"><i class="fa-solid fa-spa" aria-hidden="true"></i></span>
            GlowWell
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation" aria-controls="mainNavigation" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavigation">
            <?php if ($role === "admin"): ?>
                <div class="navbar-nav ms-auto me-lg-3">
                    <a class="nav-link" href="dashboard.php">Overview</a>
                    <a class="nav-link" href="appointments.php">Appointments</a>
                    <a class="nav-link" href="reports.php">Reports</a>
                    <a class="nav-link" href="services.php">Services</a>
                    <a class="nav-link" href="staff.php">Team</a>
                </div>
            <?php elseif ($role === "customer"): ?>
                <div class="navbar-nav ms-auto me-lg-3">
                    <a class="nav-link" href="../customer/dashboard.php">My space</a>
                    <a class="nav-link" href="../customer/book_appointment.php">Book a visit</a>
                    <a class="nav-link" href="../customer/my_appointment.php">Appointments</a>
                </div>
            <?php else: ?>
                <div class="navbar-nav ms-auto me-lg-3">
                    <a class="nav-link" href="../index.php">Home</a>
                </div>
            <?php endif; ?>
            <div class="d-flex align-items-center gap-3">
                <?php if ($userName !== ""): ?>
                    <span class="d-none d-lg-inline text-muted small">Hello, <?= htmlspecialchars($userName, ENT_QUOTES, "UTF-8") ?></span>
                    <form method="post" action="../authentication/logout.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
                        <button type="submit" class="btn btn-outline-primary btn-sm">Sign out</button>
                    </form>
                <?php else: ?>
                    <a href="../authentication/login.php" class="btn btn-outline-primary btn-sm">Sign in</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main class="container page-container">
