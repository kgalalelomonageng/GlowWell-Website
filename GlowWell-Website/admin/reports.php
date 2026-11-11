<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../authentication/login.php");
    exit;
}

$fromInput = $_GET["from"] ?? "";
$toInput = $_GET["to"] ?? "";
$statusInput = $_GET["status"] ?? "";
$isDate = static function ($value): bool {
    if (!is_string($value)) {
        return false;
    }
    $date = DateTime::createFromFormat("!Y-m-d", $value);
    return $date && $date->format("Y-m-d") === $value;
};
$invalidDate = ($fromInput !== "" && !$isDate($fromInput)) || ($toInput !== "" && !$isDate($toInput));
$from = $isDate($fromInput) ? $fromInput : "";
$to = $isDate($toInput) ? $toInput : "";
$status = is_string($statusInput) && in_array($statusInput, ["Pending", "Confirmed", "Completed", "Cancelled"], true) ? $statusInput : "";

$conditions = [];
$parameters = [];
if ($from !== "") {
    $conditions[] = "a.appointment_date >= ?";
    $parameters[] = $from;
}
if ($to !== "") {
    $conditions[] = "a.appointment_date <= ?";
    $parameters[] = $to;
}
if ($status !== "") {
    $conditions[] = "a.status = ?";
    $parameters[] = $status;
}
$where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";
if ($invalidDate || ($from !== "" && $to !== "" && $from > $to)) {
    $where .= $conditions ? " AND 1 = 0" : "WHERE 1 = 0";
}
$reportQuery = $pdo->prepare(
    "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status,
            u.fullname AS customer, s.service_name, s.price,
            st.fullname AS staff_name
     FROM appointments a
     INNER JOIN users u ON a.user_id = u.user_id
     INNER JOIN services s ON a.service_id = s.service_id
     INNER JOIN staff st ON a.staff_id = st.staff_id
     $where
     ORDER BY a.appointment_date DESC, a.appointment_time DESC"
);
$reportQuery->execute($parameters);
$rows = $reportQuery->fetchAll(PDO::FETCH_ASSOC);

if (is_string($_GET["export"] ?? null) && $_GET["export"] === "csv") {
    $safeValue = static function (string $value): string {
        return preg_match('/^[\\x00-\\x20]*[=+@\\-]/', $value) ? "'" . $value : $value;
    };

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"glowwell-appointments-report-" . date("Y-m-d") . ".csv\"");
    $output = fopen("php://output", "w");
    if ($output === false) {
        throw new RuntimeException("Unable to create the CSV report.");
    }
    fputcsv($output, ["Date", "Time", "Customer", "Treatment", "Staff member", "Status", "Price (R)"], ",", "\"", "");
    foreach ($rows as $row) {
        fputcsv($output, [
            $safeValue($row["appointment_date"]),
            $safeValue(substr($row["appointment_time"], 0, 5)),
            $safeValue($row["customer"]),
            $safeValue($row["service_name"]),
            $safeValue($row["staff_name"]),
            $safeValue($row["status"]),
            number_format((float) $row["price"], 2, ".", "")
        ], ",", "\"", "");
    }
    fclose($output);
    exit;
}

$total = count($rows);
$completed = 0;
$cancelled = 0;
$revenue = 0.0;
foreach ($rows as $row) {
    if ($row["status"] === "Completed") {
        $completed++;
        $revenue += (float) $row["price"];
    }
    if ($row["status"] === "Cancelled") {
        $cancelled++;
    }
}

$pageTitle = "Reports";
include "../includes/header.php";
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-3 mb-4">
    <div>
        <span class="eyebrow">Clinic insights</span>
        <h1 class="mt-2 mb-1">Appointment reports</h1>
        <p class="text-muted mb-0">Filter booking activity and export the results as CSV.</p>
    </div>
    <a class="btn btn-outline-primary" href="dashboard.php"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i> Overview</a>
</div>

<section class="card p-4 mb-4">
    <form class="row g-3 align-items-end" method="get" action="reports.php">
        <div class="col-sm-6 col-lg-3">
            <label class="form-label" for="from">From</label>
            <input class="form-control" id="from" name="from" type="date" value="<?= htmlspecialchars($from, ENT_QUOTES, "UTF-8") ?>">
        </div>
        <div class="col-sm-6 col-lg-3">
            <label class="form-label" for="to">To</label>
            <input class="form-control" id="to" name="to" type="date" value="<?= htmlspecialchars($to, ENT_QUOTES, "UTF-8") ?>">
        </div>
        <div class="col-sm-6 col-lg-3">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (["Pending", "Confirmed", "Completed", "Cancelled"] as $reportStatus): ?>
                    <option value="<?= $reportStatus ?>" <?= $status === $reportStatus ? "selected" : "" ?>><?= $reportStatus ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Apply filters</button>
            <a class="btn btn-outline-primary" href="reports.php">Clear</a>
        </div>
    </form>
    <?php if ($invalidDate): ?>
        <div class="alert alert-warning mt-3 mb-0" role="alert">Enter valid calendar dates to filter the report.</div>
    <?php elseif ($from !== "" && $to !== "" && $from > $to): ?>
        <div class="alert alert-warning mt-3 mb-0" role="alert">The start date must be on or before the end date.</div>
    <?php endif; ?>
</section>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><article class="card dashboard-card"><h5>Matching appointments</h5><h2><?= $total ?></h2></article></div>
    <div class="col-sm-6 col-xl-3"><article class="card dashboard-card"><h5>Completed visits</h5><h2><?= $completed ?></h2></article></div>
    <div class="col-sm-6 col-xl-3"><article class="card dashboard-card"><h5>Cancelled visits</h5><h2><?= $cancelled ?></h2></article></div>
    <div class="col-sm-6 col-xl-3"><article class="card dashboard-card"><h5>Completed service value</h5><h2 class="h3 mt-3">R <?= number_format($revenue, 2) ?></h2></article></div>
</div>

<section class="card overflow-hidden">
    <div class="p-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
        <div>
            <span class="eyebrow">Filtered results</span>
            <h2 class="h4 mt-2 mb-0">Appointment details</h2>
        </div>
        <a class="btn btn-outline-primary" href="reports.php?<?= http_build_query(array_filter(["from" => $from, "to" => $to, "status" => $status, "export" => "csv"])) ?>">
            <i class="fa-solid fa-download me-1" aria-hidden="true"></i> Download CSV
        </a>
    </div>
    <?php if ($rows): ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th scope="col">Date &amp; time</th><th scope="col">Customer</th><th scope="col">Treatment</th><th scope="col">Staff</th><th scope="col">Status</th><th scope="col">Value</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><div><?= date("D, j M Y", strtotime($row["appointment_date"])) ?></div><small class="text-muted"><?= date("g:i A", strtotime($row["appointment_time"])) ?></small></td>
                            <td><?= htmlspecialchars($row["customer"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($row["service_name"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($row["staff_name"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><span class="badge-status <?= strtolower($row["status"]) ?>"><?= htmlspecialchars($row["status"], ENT_QUOTES, "UTF-8") ?></span></td>
                            <td>R <?= number_format((float) $row["price"], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">No appointments match these filters.</div>
    <?php endif; ?>
</section>
<?php include "../includes/footer.php"; ?>
