<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../authentication/login.php");
    exit;
}

$serviceId = filter_var($_GET["id"] ?? "", FILTER_VALIDATE_INT);
if (!$serviceId) {
    header("Location: services.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM services WHERE service_id = ?");
$stmt->execute([$serviceId]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header("Location: services.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nameInput = $_POST["service_name"] ?? "";
    $descriptionInput = $_POST["description"] ?? "";
    $name = is_string($nameInput) ? trim($nameInput) : "";
    $description = is_string($descriptionInput) ? trim($descriptionInput) : "";
    $duration = filter_var($_POST["duration"] ?? "", FILTER_VALIDATE_INT);
    $price = filter_var($_POST["price"] ?? "", FILTER_VALIDATE_FLOAT);
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Your session has expired. Please refresh the page and try again.";
    } elseif ($name === "" || strlen($name) > 100 || !$duration || $duration < 1 || $price === false || $price <= 0) {
        $message = "Enter a name, a positive duration, and a price greater than zero.";
    } else {
        $update = $pdo->prepare(
            "UPDATE services
             SET service_name = ?, description = ?, duration = ?, price = ?
             WHERE service_id = ?"
        );
        $update->execute([$name, $description, $duration, $price, $serviceId]);
        header("Location: services.php");
        exit;
    }
}

$pageTitle = "Edit treatment";
include "../includes/header.php";
?>
<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="mb-4">
            <span class="eyebrow">Treatment menu</span>
            <h1 class="mt-2 mb-1">Edit treatment</h1>
            <p class="text-muted mb-0">Update the details customers see when they book.</p>
        </div>
        <section class="card p-4 p-lg-5">
            <?php if ($message !== ""): ?>
                <div class="alert alert-warning" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
            <?php endif; ?>
            <form method="post" action="edit_service.php?id=<?= (int) $serviceId ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
                <div class="mb-3">
                    <label class="form-label" for="service_name">Treatment name</label>
                    <input class="form-control" id="service_name" name="service_name" type="text" maxlength="100" value="<?= htmlspecialchars($_POST["service_name"] ?? $service["service_name"], ENT_QUOTES, "UTF-8") ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($_POST["description"] ?? $service["description"], ENT_QUOTES, "UTF-8") ?></textarea>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <label class="form-label" for="duration">Duration (minutes)</label>
                        <input class="form-control" id="duration" name="duration" type="number" min="1" value="<?= htmlspecialchars($_POST["duration"] ?? (string) $service["duration"], ENT_QUOTES, "UTF-8") ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="price">Price (R)</label>
                        <input class="form-control" id="price" name="price" type="number" min="0.01" step="0.01" value="<?= htmlspecialchars($_POST["price"] ?? (string) $service["price"], ENT_QUOTES, "UTF-8") ?>" required>
                    </div>
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <button class="btn btn-primary" type="submit">Save changes</button>
                    <a class="btn btn-outline-primary" href="services.php">Cancel</a>
                </div>
            </form>
        </section>
    </div>
</div>
<?php include "../includes/footer.php"; ?>
