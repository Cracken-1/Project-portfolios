<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Get doctor ID from URL
$docid = $_GET['docid'] ?? null;
if (!$docid) {
    header("location: billing.php");
    exit();
}

// Get doctor details
$doctor = $database->query("SELECT * FROM doctor WHERE docid = '$docid'")->fetch_assoc();
if (!$doctor) {
    header("location: billing.php");
    exit();
}

// Date range filter
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Get doctor's billing details
$billing_details = $database->query("
    SELECT i.*, p.pname, a.appodate, a.appotime
    FROM invoices i
    JOIN appointment a ON i.appoid = a.appoid
    JOIN patient p ON a.pid = p.pid
    WHERE a.docid = '$docid'
    AND i.invoice_date BETWEEN '$start_date' AND '$end_date'
    ORDER BY i.invoice_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Calculate summary
$summary = [
    'total_invoices' => 0,
    'total_amount' => 0,
    'total_paid' => 0,
    'total_pending' => 0,
    'total_overdue' => 0
];

foreach ($billing_details as $invoice) {
    $summary['total_invoices']++;
    $summary['total_amount'] += $invoice['amount'];
    
    if ($invoice['status'] == 'paid') {
        $summary['total_paid'] += $invoice['amount'];
    } else {
        $summary['total_pending'] += $invoice['amount'];
        if (strtotime($invoice['due_date']) < time()) {
            $summary['total_overdue'] += $invoice['amount'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Billing Details</title>
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
        
        .doctor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .doctor-info h2 {
            margin: 0;
            color: #2c3e50;
        }
        
        .doctor-info p {
            margin: 5px 0 0;
            color: white;
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
    </style>
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="billing-container">
            <!-- Doctor Header -->
            <div class="doctor-header">
                <div class="doctor-info">
                    <h2><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($doctor['docname']); ?></h2>
                    <p><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                </div>
                <div>
                    <a href="generate_doctor_report.php?docid=<?php echo $docid; ?>" class="btn-print" target="_blank">
                        <i class="fas fa-file-pdf"></i> Generate Full Report
                    </a>
                </div>
            </div>
            
            <!-- Date Filter -->
            <div class="filter-bar">
                <form method="GET" class="date-filter">
                    <input type="hidden" name="docid" value="<?php echo $docid; ?>">
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
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card total">
                    <h3><?php echo $summary['total_invoices']; ?></h3>
                    <p>Total Invoices</p>
                </div>
                <div class="summary-card paid">
                    <h3>Ksh. <?php echo number_format($summary['total_paid'], 2); ?></h3>
                    <p>Total Paid</p>
                </div>
                <div class="summary-card pending">
                    <h3>Ksh. <?php echo number_format($summary['total_pending'], 2); ?></h3>
                    <p>Pending Payments</p>
                </div>
                <div class="summary-card overdue">
                    <h3>Ksh. <?php echo number_format($summary['total_overdue'], 2); ?></h3>
                    <p>Overdue Payments</p>
                </div>
            </div>
            
            <!-- Billing Details Table -->
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Appointment Date</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($billing_details as $invoice): ?>
                        <tr>
                            <td><?php echo $invoice['invoice_id']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($invoice['invoice_date'])); ?></td>
                            <td><?php echo htmlspecialchars($invoice['pname']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($invoice['appodate'])) . ' at ' . date('g:i A', strtotime($invoice['appotime'])); ?></td>
                            <td>Ksh. <?php echo number_format($invoice['amount'], 2); ?></td>
                            <td><?php echo date('M j, Y', strtotime($invoice['due_date'])); ?></td>
                            <td>
                                <?php 
                                $status_class = 'status-' . strtolower($invoice['status']);
                                if ($invoice['status'] == 'pending' && strtotime($invoice['due_date']) < time()) {
                                    $status_class = 'status-overdue';
                                }
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $invoice['status']; ?>
                                    <?php if ($invoice['status'] == 'pending' && strtotime($invoice['due_date']) < time()): ?>
                                        (Overdue)
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="print_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn-print" target="_blank">
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
</body>
</html>