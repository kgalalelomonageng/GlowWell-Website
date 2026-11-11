<?php
session_start();
require "config/db.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$services = $pdo->query(
    "SELECT service_name, description, duration, price
     FROM services
     ORDER BY service_id ASC
     LIMIT 3"
)->fetchAll();

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fbfaf7">
    <meta name="description" content="Make space for yourself at GlowWell. Discover thoughtful beauty and wellness treatments, and book your next visit.">
    <title>GlowWell | A little time for you</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <span class="brand-mark"><i class="fa-solid fa-spa" aria-hidden="true"></i></span>
            GlowWell
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landingNavigation" aria-controls="landingNavigation" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="landingNavigation">
            <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <a class="nav-link" href="#treatments">Treatments</a>
                <a class="nav-link" href="#our-approach">Our approach</a>
                <?php if (isset($_SESSION["user_id"])): ?>
                    <a class="btn btn-primary ms-lg-2" href="<?= ($_SESSION["role"] ?? "") === "admin" ? "admin/dashboard.php" : "customer/dashboard.php" ?>">Go to my space</a>
                    <form class="ms-lg-2" method="post" action="authentication/logout.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
                        <button class="btn btn-outline-primary" type="submit">Sign out</button>
                    </form>
                <?php else: ?>
                    <a class="nav-link" href="authentication/login.php">Sign in</a>
                    <a class="btn btn-primary ms-lg-2" href="authentication/register.php">Create an account</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main>
    <section class="container pt-4 pt-lg-5">
        <div class="hero-section">
            <div class="hero-content">
                <span class="eyebrow">Beauty, balance &amp; a moment to yourself</span>
                <h1>Feel good in your own skin.</h1>
                <p>Thoughtful treatments, caring hands, and a calm space to pause. Your next feel-good moment starts at GlowWell.</p>
                <div class="hero-actions">
                    <a class="btn btn-light btn-lg" href="<?= isset($_SESSION["user_id"]) ? "customer/book_appointment.php" : "authentication/register.php" ?>">Book your visit <i class="fa-solid fa-arrow-right ms-2" aria-hidden="true"></i></a>
                    <a class="btn btn-outline-light btn-lg" href="#treatments">Explore treatments</a>
                </div>
            </div>
        </div>
    </section>

    <section class="container section-space" id="treatments">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
            <div>
                <span class="eyebrow">Made for your moment</span>
                <h2 class="mt-2 mb-1">A little care goes a long way.</h2>
                <p class="text-muted mb-0">Find the treatment that feels right for you.</p>
            </div>
            <?php if (!empty($_SESSION["user_id"])): ?>
                <a class="btn btn-outline-primary" href="customer/book_appointment.php">See all treatments</a>
            <?php endif; ?>
        </div>
        <div class="row g-4">
            <?php if ($services): ?>
                <?php foreach ($services as $service): ?>
                    <div class="col-md-6 col-lg-4">
                        <article class="card service-card">
                            <div class="service-icon"><i class="fa-solid fa-leaf" aria-hidden="true"></i></div>
                            <h3><?= escape($service["service_name"]) ?></h3>
                            <p><?= escape($service["description"] ?: "A restorative treatment, thoughtfully tailored to you.") ?></p>
                            <div class="service-meta">
                                <span><?= (int) $service["duration"] ?> min</span>
                                <strong>R <?= number_format((float) $service["price"], 2) ?></strong>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="card empty-state">Our treatments are being refreshed. Please check back soon.</div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="container pb-5" id="our-approach">
        <div class="card p-4 p-lg-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <span class="eyebrow">Your wellbeing, our priority</span>
                    <h2 class="mt-2 mb-3">Come as you are. Leave feeling more like you.</h2>
                    <p class="text-muted mb-0">From the first hello to your last deep breath, our team makes every visit personal, welcoming, and unhurried.</p>
                </div>
                <div class="col-lg-5 text-lg-end">
                    <a class="btn btn-primary btn-lg" href="<?= isset($_SESSION["user_id"]) ? "customer/book_appointment.php" : "authentication/register.php" ?>">Find your time</a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container d-flex flex-column flex-sm-row justify-content-between gap-2">
        <span>&copy; <?= date("Y") ?> GlowWell Beauty Clinic</span>
        <span>A little time for you starts here.</span>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
