<?php
session_start();

$token = $_POST["csrf_token"] ?? "";
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !is_string($token) || empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $token)) {
    http_response_code(400);
    exit("Invalid sign-out request. Refresh the page and try again.");
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();
header("Location: ../index.php");
exit;
