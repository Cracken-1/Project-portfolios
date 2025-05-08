<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Get invoice ID from URL
$invoice_id = $_GET['id'] ?? null;
if (!$invoice_id) {
    header("location: billing.php");
    exit();
}

// Get invoice details
$invoice = $database->query("
    SELECT i.*, p.pname, p.pphoneno, p.pemail, 
           d.docname, d.specialization,
           a.appodate, a.appotime
    FROM invoices i
    JOIN appointment a ON i.appoid = a.appoid
    JOIN patient p ON a.pid = p.pid
    JOIN doctor d ON a.docid = d.docid
    WHERE i.invoice_id = '$invoice_id'
")->fetch_assoc();

if (!$invoice) {
    header("location: billing.php");
    exit();
}

// Format dates
$invoice_date = date('F j, Y', strtotime($invoice['invoice_date']));
$due_date = date('F j, Y', strtotime($invoice['due_date']));
$appointment_date = date('F j, Y', strtotime($invoice['appodate']));
$appointment_time = date('g:i A', strtotime($invoice['appotime']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $invoice_id; ?></title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .clinic-info h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 24px;
        }
        
        .clinic-info p {
            margin: 5px 0 0;
            color: #7f8c8d;
        }
        
        .invoice-title {
            text-align: right;
        }
        
        .invoice-title h1 {
            margin: 0;
            color: #4a6cf7;
            font-size: 28px;
        }
        
        .invoice-title .status-badge {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-paid { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .patient-info, .doctor-info {
            flex: 1;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            margin: 0 0 10px;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .info-box p {
            margin: 5px 0;
            color: #34495e;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .invoice-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
        }
        
        .invoice-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .text-right {
            text-align: right;
        }
        
        .total-section {
            display: flex;
            justify-content: flex-end;
        }
        
        .total-box {
            width: 300px;
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .total-row.total {
            font-weight: bold;
            font-size: 1.1rem;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .invoice-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-print {
            background: #4a6cf7;
            color: white;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        @media print {
            body {
                background: none;
                padding: 0;
            }
            
            .invoice-container {
                box-shadow: none;
                padding: 0;
            }
            
            .action-buttons {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="clinic-info">
                <h2>Meridian Medical Clinic</h2>
                <p>123 Health Street, Nairobi, Kenya</p>
                <p>Phone: +254 700 123456 | Email: info@medicalclinic.com</p>
            </div>
            <div class="invoice-title">
                <h1>INVOICE #<?php echo $invoice_id; ?></h1>
                <span class="status-badge status-<?php 
                    echo strtolower($invoice['status']);
                    if ($invoice['status'] == 'pending' && strtotime($invoice['due_date']) < time()) {
                        echo 'overdue';
                    }
                ?>">
                    <?php echo $invoice['status']; ?>
                    <?php if ($invoice['status'] == 'pending' && strtotime($invoice['due_date']) < time()): ?>
                        (Overdue)
                    <?php endif; ?>
                </span>
                <p>Date: <?php echo $invoice_date; ?></p>
                <p>Due: <?php echo $due_date; ?></p>
            </div>
        </div>
        
        <!-- Invoice Details -->
        <div class="invoice-details">
            <div class="patient-info">
                <div class="info-box">
                    <h3>BILL TO</h3>
                    <p><strong><?php echo htmlspecialchars($invoice['pname']); ?></strong></p>
                    <p><?php echo htmlspecialchars($invoice['pphoneno']); ?></p>
                    <p>Email: <?php echo htmlspecialchars($invoice['pemail']); ?></p>
                </div>
            </div>
            <div class="doctor-info">
                <div class="info-box">
                    <h3>PROVIDER</h3>
                    <p><strong>Dr. <?php echo htmlspecialchars($invoice['docname']); ?></strong></p>
                    <p><?php echo htmlspecialchars($invoice['specialization']); ?></p>

                </div>
            </div>
        </div>
        
        <!-- Appointment Details -->
        <div class="info-box">
            <h3>APPOINTMENT DETAILS</h3>
            <p><strong>Date:</strong> <?php echo $appointment_date; ?> at <?php echo $appointment_time; ?></p>
        </div>
        
        <!-- Invoice Items -->
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Medical Consultation and Treatment</td>
                    <td class="text-right">Ksh. <?php echo number_format($invoice['amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Total Section -->
        <div class="total-section">
            <div class="total-box">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>Ksh. <?php echo number_format($invoice['amount'], 2); ?></span>
                </div>
                <div class="total-row">
                    <span>Tax (0%):</span>
                    <span>Ksh. 0.00</span>
                </div>
                <div class="total-row total">
                    <span>Total Amount:</span>
                    <span>Ksh. <?php echo number_format($invoice['amount'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Payment Instructions -->
        <div class="info-box">
            <h3>PAYMENT INSTRUCTIONS</h3>
            <p>Please make payment by the due date to avoid any service interruptions.</p>
            <p><strong>Payment Methods:</strong> M-Pesa, Credit Card, Bank Transfer</p>
            <p><strong>M-Pesa Paybill:</strong> 123456 | Account: Invoice #<?php echo $invoice_id; ?></p>
        </div>
        
        <!-- Invoice Footer -->
        <div class="invoice-footer">
            <p>Thank you for choosing our medical services. Please contact us if you have any questions.</p>
            <p>Medical Clinic &copy; <?php echo date('Y'); ?></p>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="billing.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Billing
            </a>
            <a href="javascript:window.print()" class="btn btn-print">
                <i class="fas fa-print"></i> Print Invoice
            </a>
        </div>
    </div>
</body>
</html>