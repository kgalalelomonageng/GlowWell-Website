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
$fullname = "";
$email = "";
$phone = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullnameInput = $_POST["fullname"] ?? "";
    $emailInput = $_POST["email"] ?? "";
    $phoneInput = $_POST["phone"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $confirmPasswordInput = $_POST["confirm_password"] ?? "";
    $token = $_POST["csrf_token"] ?? "";
    $fullname = is_string($fullnameInput) ? trim($fullnameInput) : "";
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : "";
    $phone = is_string($phoneInput) ? trim($phoneInput) : "";
    $password = is_string($passwordInput) ? $passwordInput : "";
    $confirmPassword = is_string($confirmPasswordInput) ? $confirmPasswordInput : "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Your session has expired. Please refresh the page and try again.";
    } elseif ($fullname === "" || strlen($fullname) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Enter your name and a valid email address.";
    } elseif (
        strlen($phone) > 20
        || preg_match('/^\+?[0-9().\-\s]+$/', $phone) !== 1
        || preg_match_all('/[0-9]/', $phone) < 7
        || preg_match_all('/[0-9]/', $phone) > 15
    ) {
        $message = "Enter a valid phone number with 7 to 15 digits.";
    } elseif (strlen($password) < 8) {
        $message = "Choose a password with at least 8 characters.";
    } elseif ($password !== $confirmPassword) {
        $message = "Your passwords do not match.";
    } else {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);

        if ($check->fetchColumn()) {
            $message = "An account with that email already exists. Please sign in instead.";
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO users (fullname, email, phone, password, role)
                 VALUES (?, ?, ?, ?, 'customer')"
            );
            $stmt->execute([$fullname, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);

            session_regenerate_id(true);
            $_SESSION["user_id"] = (int) $pdo->lastInsertId();
            $_SESSION["fullname"] = $fullname;
            $_SESSION["role"] = "customer";
            unset($_SESSION["csrf_token"]);

            header("Location: ../customer/dashboard.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create an account | GlowWell</title>
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
            <span class="eyebrow">Make space for yourself</span>
            <h2>Wellness looks good on you.</h2>
            <p>Create an account to explore treatments and keep all your visits in one place.</p>
        </div>
        <div class="auth-quote">Your next moment of calm is only a few clicks away.</div>
    </aside>
    <section class="auth-form-panel">
        <form class="auth-form" method="post" action="register.php">
            <span class="eyebrow">Start your GlowWell journey</span>
            <h1>Create your account</h1>
            <p class="text-muted mb-4">It only takes a moment to get started.</p>
            <?php if ($message !== ""): ?>
                <div class="alert alert-warning" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
            <?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
            <div class="mb-3">
                <label class="form-label" for="fullname">Full name</label>
                <input class="form-control" id="fullname" name="fullname" type="text" autocomplete="name" maxlength="100" placeholder="Your name" value="<?= htmlspecialchars($fullname, ENT_QUOTES, "UTF-8") ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email address</label>
                <input class="form-control" id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="phone">Phone number</label>
                <input class="form-control" id="phone" name="phone" type="tel" autocomplete="tel" maxlength="20" inputmode="tel" placeholder="+27 82 123 4567" value="<?= htmlspecialchars($phone, ENT_QUOTES, "UTF-8") ?>" aria-describedby="phone-hint" required>
                <div class="form-hint mt-1" id="phone-hint">Include your country code if you are outside South Africa.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" required>
                <div class="form-hint mt-1">Use at least 8 characters.</div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="confirm_password">Confirm password</label>
                <input class="form-control" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" placeholder="Enter your password again" required>
            </div>
            <button class="btn btn-primary w-100" type="submit">Create account</button>
            <p class="text-muted text-center mt-4 mb-0">Already have an account? <a class="fw-bold text-decoration-none" href="login.php">Sign in</a></p>
            <p class="text-center mt-3 mb-0"><a class="small text-muted text-decoration-none" href="../index.php"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i> Back to home</a></p>
        </form>
    </section>
</main>
</body>
</html>
