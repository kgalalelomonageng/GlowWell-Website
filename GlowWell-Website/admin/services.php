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
$messageType = "success";

if (isset($_POST["add_service"])) {
    $serviceNameInput = $_POST["service_name"] ?? "";
    $descriptionInput = $_POST["description"] ?? "";
    $serviceName = is_string($serviceNameInput) ? trim($serviceNameInput) : "";
    $description = is_string($descriptionInput) ? trim($descriptionInput) : "";
    $duration = filter_var($_POST["duration"] ?? "", FILTER_VALIDATE_INT);
    $price = filter_var($_POST["price"] ?? "", FILTER_VALIDATE_FLOAT);
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Your session has expired. Please refresh the page and try again.";
        $messageType = "warning";
    } elseif ($serviceName === "" || strlen($serviceName) > 100 || !$duration || $duration < 1 || $price === false || $price <= 0) {
        $message = "Enter a name, a positive duration, and a price greater than zero.";
        $messageType = "warning";
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO services (service_name, description, duration, price)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$serviceName, $description, $duration, $price]);
        $message = "Treatment added successfully.";
    }
}

if (isset($_POST["delete_service"])) {
    $serviceId = filter_var($_POST["service_id"] ?? "", FILTER_VALIDATE_INT);
    $token = $_POST["csrf_token"] ?? "";
    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Your session has expired. Please refresh the page and try again.";
        $messageType = "warning";
    } elseif ($serviceId) {
        try {
            $stmt = $pdo->prepare("DELETE FROM services WHERE service_id = ?");
            $stmt->execute([$serviceId]);
            if ($stmt->rowCount() === 0) {
                $message = "That treatment could not be found.";
                $messageType = "warning";
            } else {
                header("Location: services.php");
                exit;
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === "23000" || str_contains(strtolower($exception->getMessage()), "constraint")) {
                $message = "This treatment is part of appointment history. Update it or leave it in the menu instead of deleting it.";
                $messageType = "warning";
            } else {
                throw $exception;
            }
        }
    } else {
        $message = "That treatment could not be found.";
        $messageType = "warning";
    }
}

$services = $pdo->query("SELECT * FROM services ORDER BY service_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = "Treatments & pricing";
include "../includes/header.php";
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-3 mb-4">
    <div>
        <span class="eyebrow">Clinic management</span>
        <h1 class="mt-2 mb-1">Treatments &amp; pricing</h1>
        <p class="text-muted mb-0">Keep your treatment menu up to date.</p>
    </div>
    <a class="btn btn-outline-primary" href="dashboard.php"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i> Overview</a>
</div>

<?php if ($message !== ""): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, "UTF-8") ?>" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <section class="card p-4">
            <span class="eyebrow">New offering</span>
            <h2 class="h4 mt-2 mb-4">Add a treatment</h2>
            <form method="post" action="services.php" data-service-form>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
                <div class="mb-3">
                    <label class="form-label" for="service_name">Treatment name</label>
                    <input class="form-control" id="service_name" name="service_name" type="text" maxlength="100" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="duration">Duration (min)</label>
                        <input class="form-control" id="duration" name="duration" type="number" min="1" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="price">Price (R)</label>
                        <input class="form-control" id="price" name="price" type="number" min="0.01" step="0.01" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4" placeholder="Tell customers what to expect"></textarea>
                </div>
                <button class="btn btn-primary w-100" type="submit" name="add_service">Save treatment</button>
            </form>
        </section>
    </div>
    <div class="col-lg-8">
        <section class="card overflow-hidden">
            <div class="p-4 pb-2">
                <span class="eyebrow">Your menu</span>
                <h2 class="h4 mt-2 mb-0">Available treatments</h2>
            </div>
            <?php if ($services): ?>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr><th scope="col">Treatment</th><th scope="col">Duration</th><th scope="col">Price</th><th scope="col">Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($service["service_name"], ENT_QUOTES, "UTF-8") ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($service["description"] ?? "", ENT_QUOTES, "UTF-8") ?></small>
                                    </td>
                                    <td><?= (int) $service["duration"] ?> min</td>
                                    <td>R <?= number_format((float) $service["price"], 2) ?></td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-outline-primary btn-sm" href="edit_service.php?id=<?= (int) $service["service_id"] ?>">Edit</a>
                                        <form class="d-inline" method="post" action="services.php" onsubmit="return confirm('Delete this treatment?')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
                                            <input type="hidden" name="service_id" value="<?= (int) $service["service_id"] ?>">
                                            <button class="btn btn-outline-danger btn-sm" type="submit" name="delete_service">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">No treatments have been added yet.</div>
            <?php endif; ?>
        </section>
    </div>
</div>
<script src="../assets/js/services.js"></script>
<?php include "../includes/footer.php"; ?>
