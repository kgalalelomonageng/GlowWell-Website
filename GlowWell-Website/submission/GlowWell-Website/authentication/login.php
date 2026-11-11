<?php
session_start();
require "../config/db.php";

if (isset($_SESSION["user_id"])) {
    header("Location: " . (($_SESSION["role"] ?? "") === "admin" ? "../admin/dashboard.php" : "../customer/dashboard.php"));
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $emailInput = $_POST["email"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $token = $_POST["csrf_token"] ?? "";
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : "";
    $password = is_string($passwordInput) ? $passwordInput : "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Your session has expired. Please refresh the page and try again.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === "") {
        $message = "Enter a valid email address and password.";
    } else {
        $stmt = $pdo->prepare(
            "SELECT user_id, fullname, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {
            session_regenerate_id(true);
            $_SESSION["user_id"] = (int) $user["user_id"];
            $_SESSION["fullname"] = $user["fullname"];
            $_SESSION["role"] = $user["role"];
            unset($_SESSION["csrf_token"]);

            header("Location: " . ($user["role"] === "admin" ? "../admin/dashboard.php" : "../customer/dashboard.php"));
            exit;
        }

        $message = "We couldn't sign you in with those details. Please check your email and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | GlowWell</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head>
<body class="auth-page">
<main class="auth-shell">
    <aside class="auth-aside">
        <a class="navbar-brand text-white" href="../index.php">
            <span class="brand-mark"><i class="fa-solid fa-spa" aria-hidden="true"></i></span>
            GlowWell
        </a>
        <div>
            <span class="eyebrow">A softer kind of self-care</span>
            <h2>Your time to feel good starts here.</h2>
            <p>Come in, slow down, and let our team take care of the details.</p>
        </div>
        <div class="auth-quote">A little time for you starts here.</div>
    </aside>
    <section class="auth-form-panel">
        <form class="auth-form" method="post" action="login.php">
            <span class="eyebrow">Welcome back</span>
            <h1>Sign in to GlowWell</h1>
            <p class="text-muted mb-4">Pick up where your self-care left off.</p>
            <?php if ($message !== ""): ?>
                <div class="alert alert-warning" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
            <?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
            <div class="mb-3">
                <label class="form-label" for="email">Email address</label>
                <input class="form-control" id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>" required>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
            </div>
            <button class="btn btn-primary w-100" type="submit">Sign in</button>
            <p class="text-muted text-center mt-4 mb-0">New to GlowWell? <a class="fw-bold text-decoration-none" href="register.php">Create an account</a></p>
            <p class="text-center mt-3 mb-0"><a class="small text-muted text-decoration-none" href="../index.php"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i> Back to home</a></p>
        </form>
    </section>
</main>
</body>
</html>
