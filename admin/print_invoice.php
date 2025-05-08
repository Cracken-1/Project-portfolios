<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid Invoice ID.";
    exit();
}

$invoice_id = $_GET['id'];

// Fetch invoice details
$invoice_data = $database->query("
    SELECT i.*, p.pname, d.docname
    FROM invoices i
    JOIN appointment a ON i.appoid = a.appoid
    JOIN patient p ON a.pid = p.pid
    JOIN doctor d ON a.docid = d.docid
    WHERE i.invoice_id = $invoice_id
")->fetch_assoc();

if (!$invoice_data) {
    echo "Invoice not found.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo $invoice_data['invoice_id']; ?></title>
    <link rel="stylesheet" href="../css/print.css" media="print">
    <link rel="stylesheet" href="../css/includes.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .invoice-container { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; max-width: 800px; margin: 20px auto; }
        .invoice-header { text-align: center; margin-bottom: 30px; }
        .invoice-details { margin-bottom: 20px; display: flex; justify-content: space-between; }
        .patient-doctor-info { flex: 1; }
        .invoice-info { flex: 1; text-align: right; }
        .invoice-info p { margin: 5px 0; }
        .billing-items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .billing-items th, .billing-items td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .billing-items th { background-color: #f8f9fa; font-weight: bold; }
        .total-amount { text-align: right; font-size: 1.2rem; font-weight: bold; }
        .status-badge { padding: 8px 15px; border-radius: 25px; font-size: 0.9rem; font-weight: 500; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .print-button { background-color: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        @media print {
            .print-button { display: none; }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <h2>Meridian Hospital - Invoice</h2>
            <p><strong>Invoice #:</strong> <?php echo $invoice_data['invoice_id']; ?></p>
        </div>

        <div class="invoice-details">
            <div class="patient-doctor-info">
                <h3>Patient Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($invoice_data['pname']); ?></p>
                <p><strong>ID:</strong> <?php // You might want to fetch patient ID ?></p>
            </div>
            <div class="patient-doctor-info">
                <h3>Doctor Information</h3>
                <p><strong>Name:</strong> Dr. <?php echo htmlspecialchars($invoice_data['docname']); ?></p>
                <p><strong>Specialty:</strong> <?php // You might want to fetch doctor specialty ?></p>
            </div>
            <div class="invoice-info">
                <p><strong>Date:</strong> <?php echo date('M j, Y', strtotime($invoice_data['invoice_date'])); ?></p>
                <p><strong>Due Date:</strong> <?php echo date('M j, Y', strtotime($invoice_data['due_date'])); ?></p>
                <p><strong>Status:</strong>
                    <?php
                    $status_class = 'status-' . strtolower($invoice_data['status']);
                    if ($invoice_data['status'] == 'pending' && strtotime($invoice_data['due_date']) < time()) {
                        $status_class = 'status-overdue';
                    }
                    ?>
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo htmlspecialchars(ucfirst($invoice_data['status'])); ?>
                        <?php if ($invoice_data['status'] == 'pending' && strtotime($invoice_data['due_date']) < time()): ?>
                            (Overdue)
                        <?php endif; ?>
                    </span>
                </p>
            </div>
        </div>

        <h3>Billing Items</h3>
        <table class="billing-items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit Price (Ksh)</th>
                    <th>Amount (Ksh)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Consultation Fee</td>
                    <td>1</td>
                    <td><?php echo number_format($invoice_data['amount'], 2); ?></td>
                    <td><?php echo number_format($invoice_data['amount'], 2); ?></td>
                </tr>
                </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="total-amount"><strong>Total Amount:</strong></td>
                    <td class="total-amount">Ksh. <?php echo number_format($invoice_data['amount'], 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <div style="text-align: right;">
            <button class="print-button" onclick="window.print()">Print Invoice</button>
        </div>
    </div>
</body>
</html>