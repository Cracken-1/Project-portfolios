<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login.php");
    exit();
}

include("../db/db.php");

// Date range filter
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Fetch billing summary
$billing_summary = $database->query("
    SELECT 
        COUNT(*) as total_invoices,
        SUM(amount) as total_amount,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
        SUM(CASE WHEN status = 'pending' AND due_date < CURDATE() THEN amount ELSE 0 END) as total_overdue
    FROM invoices
    WHERE invoice_date BETWEEN '$start_date' AND '$end_date'
")->fetch_assoc();

// Fetch doctor billing statistics
$doctor_billing = $database->query("
    SELECT d.docid, d.docname, specialization,
           COUNT(i.invoice_id) as invoice_count,
           SUM(i.amount) as total_amount,
           SUM(CASE WHEN i.status = 'paid' THEN i.amount ELSE 0 END) as paid_amount,
           SUM(CASE WHEN i.status = 'pending' THEN i.amount ELSE 0 END) as pending_amount
    FROM doctor d
    LEFT JOIN appointment a ON d.docid = a.docid
    LEFT JOIN invoices i ON a.appoid = i.appoid
    WHERE i.invoice_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY d.docid
    ORDER BY total_amount DESC
")->fetch_all(MYSQLI_ASSOC);

// Fetch patient billing statistics
$patient_billing = $database->query("
    SELECT p.pid, p.pname, 
           COUNT(i.invoice_id) as invoice_count,
           SUM(i.amount) as total_amount,
           SUM(CASE WHEN i.status = 'paid' THEN i.amount ELSE 0 END) as paid_amount,
           SUM(CASE WHEN i.status = 'pending' THEN i.amount ELSE 0 END) as pending_amount
    FROM patient p
    LEFT JOIN appointment a ON p.pid = a.pid
    LEFT JOIN invoices i ON a.appoid = i.appoid
    WHERE i.invoice_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY p.pid
    ORDER BY total_amount DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

// Fetch recent transactions
$recent_transactions = $database->query("
    SELECT i.*, p.pname, d.docname
    FROM invoices i
    JOIN appointment a ON i.appoid = a.appoid
    JOIN patient p ON a.pid = p.pid
    JOIN doctor d ON a.docid = d.docid
    ORDER BY i.invoice_date DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Billing Management</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .billing-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .filter-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .date-filter {
            display: flex;
            gap: 15px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            padding: 20px;
            border-radius: 8px;
            color: white;
        }
        
        .summary-card.total { background: #4a6cf7; }
        .summary-card.paid { background: #28a745; }
        .summary-card.pending { background: #ffc107; color: #212529; }
        .summary-card.overdue { background: #dc3545; }
        
        .summary-card h3 {
            margin: 0;
            font-size: 1.8rem;
        }
        
        .summary-card p {
            margin: 5px 0 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .section-title {
            margin: 25px 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-export {
            background: #6c757d;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-export i {
            margin-right: 8px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-paid { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-view, .btn-print {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-view {
            background: #17a2b8;
            color: white;
        }
        
        .btn-print {
            background: #6c757d;
            color: white;
        }
        
        .btn-view i, .btn-print i {
            margin-right: 5px;
        }
        
        .chart-container {
            height: 300px;
            margin: 30px 0;
        }
    </style>
    <!-- Include Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="billing-container">
            <h1><i class="fas fa-file-invoice-dollar"></i> Billing Management</h1>
            
            <!-- Date Filter -->
            <div class="filter-bar">
                <form method="GET" class="date-filter">
                    <div>
                        <label for="start_date">From:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div>
                        <label for="end_date">To:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <button type="submit" class="btn-primary">Apply Filter</button>
                </form>
                <div>
                    <a href="generate_report.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn-export">
                        <i class="fas fa-file-export"></i> Export Report
                    </a>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card total">
                    <h3><?php echo $billing_summary['total_invoices']; ?></h3>
                    <p>Total Invoices</p>
                </div>
                <div class="summary-card paid">
                    <h3>Ksh. <?php echo number_format($billing_summary['total_paid'], 2); ?></h3>
                    <p>Total Paid</p>
                </div>
                <div class="summary-card pending">
                    <h3>Ksh. <?php echo number_format($billing_summary['total_pending'], 2); ?></h3>
                    <p>Pending Payments</p>
                </div>
                <div class="summary-card overdue">
                    <h3>Ksh. <?php echo number_format($billing_summary['total_overdue'], 2); ?></h3>
                    <p>Overdue Payments</p>
                </div>
            </div>
            
            <!-- Revenue Chart -->
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
            
            <!-- Doctor Billing -->
            <div class="section-title">
                <h2><i class="fas fa-user-md"></i> Doctor Billing Summary</h2>
                <a href="doctor_reports.php" class="btn-export">
                    <i class="fas fa-file-export"></i> Doctor Reports
                </a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Total Invoices</th>
                        <th>Total Amount</th>
                        <th>Paid Amount</th>
                        <th>Pending Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctor_billing as $doctor): ?>
                        <tr>
                            <td>Dr. <?php echo htmlspecialchars($doctor['docname']); ?></td>
                            <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                            <td><?php echo $doctor['invoice_count']; ?></td>
                            <td>Ksh. <?php echo number_format($doctor['total_amount'], 2); ?></td>
                            <td>Ksh. <?php echo number_format($doctor['paid_amount'], 2); ?></td>
                            <td>Ksh. <?php echo number_format($doctor['pending_amount'], 2); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="doctor_billing_detail.php?docid=<?php echo $doctor['docid']; ?>" class="btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="generate_doctor_report.php?docid=<?php echo $doctor['docid']; ?>" class="btn-print" target="_blank">
                                        <i class="fas fa-print"></i> Report
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Patient Billing -->
            <div class="section-title">
                <h2><i class="fas fa-user-injured"></i> Patient Billing Summary</h2>
                <a href="patient_reports.php" class="btn-export">
                    <i class="fas fa-file-export"></i> Patient Reports
                </a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Total Invoices</th>
                        <th>Total Amount</th>
                        <th>Paid Amount</th>
                        <th>Pending Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patient_billing as $patient): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($patient['pname']); ?></td>
                            <td><?php echo $patient['invoice_count']; ?></td>
                            <td>Ksh. <?php echo number_format($patient['total_amount'], 2); ?></td>
                            <td>Ksh. <?php echo number_format($patient['paid_amount'], 2); ?></td>
                            <td>Ksh. <?php echo number_format($patient['pending_amount'], 2); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="patient_billing_detail.php?pid=<?php echo $patient['pid']; ?>" class="btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="generate_patient_statement.php?pid=<?php echo $patient['pid']; ?>" class="btn-print" target="_blank">
                                        <i class="fas fa-file-invoice"></i> Statement
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Recent Transactions -->
            <div class="section-title">
                <h2><i class="fas fa-history"></i> Recent Transactions</h2>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_transactions as $transaction): ?>
                        <tr>
                            <td><?php echo $transaction['invoice_id']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($transaction['invoice_date'])); ?></td>
                            <td><?php echo htmlspecialchars($transaction['pname']); ?></td>
                            <td>Dr. <?php echo htmlspecialchars($transaction['docname']); ?></td>
                            <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                            <td>
                                <?php 
                                $status_class = 'status-' . strtolower($transaction['status']);
                                if ($transaction['status'] == 'pending' && strtotime($transaction['due_date']) < time()) {
                                    $status_class = 'status-overdue';
                                }
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $transaction['status']; ?>
                                    <?php if ($transaction['status'] == 'pending' && strtotime($transaction['due_date']) < time()): ?>
                                        (Overdue)
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view_invoice.php?id=<?php echo $transaction['invoice_id']; ?>" class="btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="print_invoice.php?id=<?php echo $transaction['invoice_id']; ?>" class="btn-print" target="_blank">
                                        <i class="fas fa-print"></i> Print
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>

    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Paid Amount',
                    data: [12000, 19000, 15000, 18000, 22000, 19000, 25000, 21000, 18000, 22000, 19000, 23000],
                    backgroundColor: '#28a745',
                    borderColor: '#28a745',
                    borderWidth: 1
                }, {
                    label: 'Pending Amount',
                    data: [3000, 5000, 4000, 6000, 5000, 4000, 7000, 5000, 4000, 6000, 5000, 4000],
                    backgroundColor: '#ffc107',
                    borderColor: '#ffc107',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Revenue Breakdown'
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>