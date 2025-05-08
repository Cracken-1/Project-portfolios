<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

if (!isset($_GET['pid']) || !is_numeric($_GET['pid'])) {
    echo "Invalid Patient ID.";
    exit();
}

$patient_id = $_GET['pid'];

// Fetch patient information
$patient_info = $database->query("SELECT pid, pname FROM patient WHERE pid = $patient_id")->fetch_assoc();

if (!$patient_info) {
    echo "Patient not found.";
    exit();
}

// Fetch billing details for the patient
$billing_details = $database->query("
    SELECT i.*, a.appodate, d.docname
    FROM invoices i
    JOIN appointment a ON i.appoid = a.appoid
    JOIN doctor d ON a.docid = d.docid
    WHERE a.pid = $patient_id
    ORDER BY i.invoice_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$total_billed = 0;
$total_paid = 0;
$total_pending = 0;

foreach ($billing_details as $bill) {
    $total_billed += $bill['amount'];
    if ($bill['status'] == 'paid') {
        $total_paid += $bill['amount'];
    } else {
        $total_pending += $bill['amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Statement</title>
    <link rel="stylesheet" href="../css/print.css" media="print">
    <link rel="stylesheet" href="../css/includes.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .statement-container { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
        .header { text-align: center; margin-bottom: 20px; }
        .patient-info { margin-bottom: 20px; }
        .patient-info p { margin: 5px 0; }
        .billing-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .billing-table th, .billing-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .billing-table th { background-color: #f8f9fa; font-weight: bold; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .totals { text-align: right; margin-top: 20px; }
        .print-button { background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        @media print {
            .print-button { display: none; }
        }
    </style>
</head>
<body>
    <div class="statement-container">
        <div class="header">
            <h2>Meridian Hospital - Patient Billing Statement</h2>
        </div>
        <div class="patient-info">
            <h3>Patient Information</h3>
            <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($patient_info['pid']); ?></p>
            <p><strong>Patient Name:</strong> <?php echo htmlspecialchars($patient_info['pname']); ?></p>
            <p><strong>Statement Date:</strong> <?php echo date('M j, Y'); ?></p>
        </div>

        <h3>Billing Details</h3>
        <table class="billing-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Doctor</th>
                    <th>Description</th>
                    <th>Amount (Ksh)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($billing_details)): ?>
                    <tr><td colspan="6">No billing details found for this patient.</td></tr>
                <?php else: ?>
                    <?php foreach ($billing_details as $bill): ?>
                        <tr>
                            <td><?php echo $bill['invoice_id']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($bill['invoice_date'])); ?></td>
                            <td>Dr. <?php echo htmlspecialchars($bill['docname']); ?></td>
                            <td>Appointment on <?php echo date('M j, Y', strtotime($bill['appodate'])); ?></td>
                            <td><?php echo number_format($bill['amount'], 2); ?></td>
                            <td>
                                <?php
                                $status_class = 'status-' . strtolower($bill['status']);
                                if ($bill['status'] == 'pending' && strtotime($bill['due_date']) < time()) {
                                    $status_class = 'status-overdue';
                                }
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($bill['status']); ?>
                                    <?php if ($bill['status'] == 'pending' && strtotime($bill['due_date']) < time()): ?>
                                        (Overdue)
                                    <?php endif; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals">
            <p><strong>Total Billed:</strong> Ksh. <?php echo number_format($total_billed, 2); ?></p>
            <p><strong>Total Paid:</strong> Ksh. <?php echo number_format($total_paid, 2); ?></p>
            <p><strong>Total Pending:</strong> Ksh. <?php echo number_format($total_pending, 2); ?></p>
        </div>

        <button class="print-button" onclick="window.print()">Print Statement</button>
    </div>
</body>
</html>