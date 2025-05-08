<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");

$reportType = $_POST['report_type'] ?? '';
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';
$docid = $_SESSION['userid'];
$reportTitle = "";
$data = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    switch ($reportType) {
        case 'appointments':
            $reportTitle = "Meridian Hospital Appointments Report";
            $stmt = $database->prepare("
                SELECT a.appoid, p.pname, a.appodate, a.status
                FROM appointment a
                JOIN patient p ON a.pid = p.pid
                WHERE a.docid = ? AND a.appodate BETWEEN ? AND ?
                ORDER BY a.appodate DESC
            ");
            $stmt->bind_param("iss", $docid, $startDate, $endDate);
            break;

        case 'patient_visits':
            $reportTitle = "Meridian Hospital Patient Visit Report";
            $stmt = $database->prepare("
                SELECT p.pname, v.visit_date, v.notes
                FROM visit v
                JOIN patient p ON v.pid = p.pid
                WHERE v.docid = ? AND v.visit_date BETWEEN ? AND ?
                ORDER BY v.visit_date DESC
            ");
            $stmt->bind_param("iss", $docid, $startDate, $endDate);
            break;

        case 'schedule':
            $reportTitle = "Meridian Hospital Doctor's Schedule Report";
            $stmt = $database->prepare("
                SELECT title, scheduled_date, start_time, end_time
                FROM schedule
                WHERE docid = ? AND scheduled_date BETWEEN ? AND ?
                ORDER BY scheduled_date DESC
            ");
            $stmt->bind_param("iss", $docid, $startDate, $endDate);
            break;

        case 'billing':
            $reportTitle = "Meridian Hospital Billing Report";
            $stmt = $database->prepare("
                SELECT i.invoice_id, p.pname, i.amount, i.status, i.invoice_date, i.due_date
                FROM invoices i
                JOIN appointment a ON i.appoid = a.appoid
                JOIN patient p ON a.pid = p.pid
                WHERE a.docid = ? AND i.invoice_date BETWEEN ? AND ?
                ORDER BY i.invoice_date DESC
            ");
            $stmt->bind_param("iss", $docid, $startDate, $endDate);
            break;

        default:
            echo "Invalid report type selected.";
            exit();
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
}

$dateGenerated = date("Y-m-d H:i:s");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $reportTitle; ?></title>
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
</head>
<body>
    <div class="report-header">
        <img src="../img/logo.png" alt="HMS Logo">
        <h2><?php echo $reportTitle; ?></h2>
        <p class="report-meta">Generated on: <?php echo $dateGenerated; ?></p>
    </div>

    <!-- Report Filter Form -->
    <div class="form-container">
        <form action="generate_report.php" method="POST">
            <label for="report-type">Select Report Type:</label>
            <select name="report_type" id="report-type" required>
                <option value="appointments" <?php echo ($reportType == 'appointments') ? 'selected' : ''; ?>>Appointments Report</option>
                <option value="patient_visits" <?php echo ($reportType == 'patient_visits') ? 'selected' : ''; ?>>Patient Visits</option>
                <option value="schedule" <?php echo ($reportType == 'schedule') ? 'selected' : ''; ?>>Schedule Report</option>
                <option value="billing" <?php echo ($reportType == 'billing') ? 'selected' : ''; ?>>Billing Report</option>
            </select>
            <label for="start_date">Start Date:</label>
            <input type="date" name="start_date" value="<?php echo $startDate; ?>" required>
            <label for="end_date">End Date:</label>
            <input type="date" name="end_date" value="<?php echo $endDate; ?>" required>
            <button type="submit">Generate Report</button>
        </form>
    </div>

    <?php if (empty($data)): ?>
        <p class="no-data">No data found for this report.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <?php foreach (array_keys($data[0]) as $col): ?>
                        <th><?php echo ucfirst(str_replace("_", " ", $col)); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?php echo htmlspecialchars($cell); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="no-print" style="text-align:center; margin-top: 30px;">
        <button onclick="window.print()" class="btn-generate">🖨️ Print Report</button>
        <a href="index.php" class="btn-generate">← Back to Dashboard</a>
    </div>
</body>
</html>
