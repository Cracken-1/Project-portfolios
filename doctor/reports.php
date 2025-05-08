<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");
$docid = $_SESSION['userid'];

// Default report parameters
$report_type = $_GET['report_type'] ?? 'appointments';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Fetch report data based on type
$report_data = [];
$chart_labels = [];
$chart_values = [];

switch ($report_type) {
    case 'appointments':
        // Appointments report
        $stmt = $database->prepare("
            SELECT 
                DATE_FORMAT(appodate, '%Y-%m-%d') as day,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM appointment
            WHERE docid = ? AND appodate BETWEEN ? AND ?
            GROUP BY day
            ORDER BY day
        ");
        $stmt->bind_param("iss", $docid, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
            $chart_labels[] = $row['day'];
            $chart_values[] = $row['total'];
        }
        $stmt->close();
        break;
        
    case 'patient_visits':
        // Patient visits report
        $stmt = $database->prepare("
            SELECT 
                p.pid, 
                p.pname,
                COUNT(a.appoid) as visits,
                MIN(a.appodate) as first_visit,
                MAX(a.appodate) as last_visit
            FROM appointment a
            JOIN patient p ON a.pid = p.pid
            WHERE a.docid = ? AND a.appodate BETWEEN ? AND ?
            GROUP BY p.pid, p.pname
            ORDER BY visits DESC
        ");
        $stmt->bind_param("iss", $docid, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
            $chart_labels[] = $row['pname'];
            $chart_values[] = $row['visits'];
        }
        $stmt->close();
        break;
        
    case 'schedule':
        // Schedule report
        $stmt = $database->prepare("
            SELECT 
                DATE_FORMAT(scheduledate, '%Y-%m-%d') as day,
                COUNT(*) as total,
                SUM(TIME_TO_SEC(scheduletime)/3600 as total_hours
            FROM schedule
            WHERE docid = ? AND scheduledate BETWEEN ? AND ?
            GROUP BY day
            ORDER BY day
        ");
        $stmt->bind_param("iss", $docid, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
            $chart_labels[] = $row['day'];
            $chart_values[] = $row['total_hours'];
        }
        $stmt->close();
        break;
        
    case 'billing':
        // Billing report
        $stmt = $database->prepare("
            SELECT 
                DATE_FORMAT(i.invoice_date, '%Y-%m-%d') as day,
                COUNT(*) as invoices,
                SUM(i.amount) as total_amount,
                SUM(CASE WHEN i.status = 'paid' THEN i.amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN i.status = 'pending' THEN i.amount ELSE 0 END) as pending_amount
            FROM invoices i
            JOIN appointment a ON i.appoid = a.appoid
            WHERE a.docid = ? AND i.invoice_date BETWEEN ? AND ?
            GROUP BY day
            ORDER BY day
        ");
        $stmt->bind_param("iss", $docid, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
            $chart_labels[] = $row['day'];
            $chart_values[] = $row['total_amount'];
        }
        $stmt->close();
        break;
}

// Calculate summary statistics
$summary = [
    'total' => array_sum($chart_values),
    'average' => count($chart_values) > 0 ? array_sum($chart_values) / count($chart_values) : 0,
    'max' => count($chart_values) > 0 ? max($chart_values) : 0,
    'min' => count($chart_values) > 0 ? min($chart_values) : 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Reports</title>
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="reports-header">
            <h1><i class="fas fa-chart-bar"></i> Reports Dashboard</h1>
            <a href="generate_report.php?<?php echo http_build_query($_GET); ?>" class="btn-generate">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
        </div>

        <!-- Report Filter Form -->
        <div class="report-filters">
            <form method="get" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="report-type">Report Type</label>
                        <select id="report-type" name="report_type" class="report-select">
                            <option value="appointments" <?php echo $report_type == 'appointments' ? 'selected' : ''; ?>>Appointments</option>
                            <option value="patient_visits" <?php echo $report_type == 'patient_visits' ? 'selected' : ''; ?>>Patient Visits</option>
                            <option value="schedule" <?php echo $report_type == 'schedule' ? 'selected' : ''; ?>>Schedule</option>
                            <option value="billing" <?php echo $report_type == 'billing' ? 'selected' : ''; ?>>Billing</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="start-date">Start Date</label>
                        <input type="date" id="start-date" name="start_date" value="<?php echo $start_date; ?>" class="date-input">
                    </div>
                    <div class="form-group">
                        <label for="end-date">End Date</label>
                        <input type="date" id="end-date" name="end_date" value="<?php echo $end_date; ?>" class="date-input">
                    </div>
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-icon total">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="summary-info">
                    <h3><?php echo round($summary['total']); ?></h3>
                    <p>Total <?php echo ucfirst(str_replace('_', ' ', $report_type)); ?></p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon average">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="summary-info">
                    <h3><?php echo round($summary['average'], 2); ?></h3>
                    <p>Average Per Day</p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon max">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="summary-info">
                    <h3><?php echo round($summary['max']); ?></h3>
                    <p>Maximum</p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon min">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="summary-info">
                    <h3><?php echo round($summary['min']); ?></h3>
                    <p>Minimum</p>
                </div>
            </div>
        </div>

        <!-- Chart Visualization -->
        <div class="chart-container">
            <canvas id="reportChart"></canvas>
        </div>

        <!-- Data Table -->
        <div class="report-table-container">
            <h2><i class="fas fa-table"></i> Report Data</h2>
            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                            <?php if (!empty($report_data)): ?>
                                <?php foreach (array_keys($report_data[0]) as $column): ?>
                                    <th><?php echo ucfirst(str_replace('_', ' ', $column)); ?></th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <?php foreach ($row as $value): ?>
                                    <td>
                                        <?php 
                                        if (is_numeric($value) && strpos($value, '.') !== false) {
                                            echo number_format($value, 2);
                                        } elseif (is_numeric($value)) {
                                            echo number_format($value);
                                        } else {
                                            echo htmlspecialchars($value);
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="<?php echo count(array_keys($report_data[0] ?? [])); ?>" class="no-data">
                                    No data available for the selected period
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>

    <script>
        // Chart initialization
        const ctx = document.getElementById('reportChart').getContext('2d');
        const reportChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: '<?php echo ucfirst(str_replace("_", " ", $report_type)); ?>',
                    data: <?php echo json_encode($chart_values); ?>,
                    backgroundColor: '#4e73df',
                    borderColor: '#2e59d9',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: '<?php echo ucfirst(str_replace("_", " ", $report_type)) . " Report (" . $start_date . " to " . $end_date . ")"; ?>',
                        font: {
                            size: 16
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Count'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });

        // Auto-submit form when report type changes
        document.getElementById('report-type').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>