<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$databaseDriver = strtolower(getenv("GLOWWELL_DB_DRIVER") ?: "mysql");
$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

if ($databaseDriver !== "mysql") {
    http_response_code(500);
    die("GlowWell requires MySQL. Set GLOWWELL_DB_DRIVER to 'mysql' and configure the database connection in README.md.");
}

$host = getenv("GLOWWELL_DB_HOST") ?: "127.0.0.1";
$port = getenv("GLOWWELL_DB_PORT") ?: "3306";
$database = getenv("GLOWWELL_DB_NAME") ?: "glowwell";
$username = getenv("GLOWWELL_DB_USER") ?: "root";
$password = getenv("GLOWWELL_DB_PASSWORD");

if ($password === false) {
    $amppsPasswordFile = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "ampps"
        . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "my.conf";
    if (file_exists($amppsPasswordFile) && !is_readable($amppsPasswordFile)) {
        http_response_code(500);
        die("AMPPS MySQL credentials could not be read. Set GLOWWELL_DB_PASSWORD in the web-server environment. See README.md.");
    }

    $password = is_readable($amppsPasswordFile)
        ? trim((string) file_get_contents($amppsPasswordFile))
        : "";
}

if ($username === "") {
    http_response_code(500);
    die("MySQL username is missing. Configure GLOWWELL_DB_USER. See README.md.");
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $username,
        $password,
        $pdoOptions
    );
} catch (PDOException $exception) {
    error_log("GlowWell MySQL connection failed: " . $exception->getMessage());
    http_response_code(500);
    die("GlowWell could not connect to MySQL. Check the database settings in README.md and confirm the database server is running.");
}
