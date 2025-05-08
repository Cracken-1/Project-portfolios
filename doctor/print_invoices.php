<?php
session_start();
require_once("../db/db.php");

// Check if user is logged in
if (!isset($_SESSION["user"])) {
    header("location: ../login/login-other.php");
    exit();
}

// Check if invoice ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No invoice specified";
    header("location: invoices.php");
    exit();
}

$invoice_id = $_GET['id'];
$userid = $_SESSION['userid'];
$usertype = $_SESSION['usertype'];

// Fetch invoice details
$sql = "SELECT i.*, p.pname, p.pemail, p.pphoneno, 
               d.dname AS doctor_name, d.demail AS doctor_email,
               a.appodate, a.appotime, a.apponote
        FROM invoices i
        JOIN appointment a ON i.appoid = a.appoid
        JOIN patient p ON a.pid = p.pid
        JOIN doctor d ON a.docid = d.docid
        WHERE i.invoice_id = ?";

// Add permission check based on user type
if ($usertype == 'd') {
    $sql .= " AND a.docid = ?";
} elseif ($usertype == 'p') {
    $sql .= " AND a.pid = ?";
}

$stmt = $database->prepare($sql);

if ($usertype == 'a') {
    $stmt->bind_param("i", $invoice_id);
} else {
    $stmt->bind_param("ii", $invoice_id, $userid);
}

$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    $_SESSION['error'] = "Invoice not found or you don't have permission to view it";
    header("location: invoices.php");
    exit();
}

// Fetch invoice items
$sql_items = "SELECT * FROM invoice_items WHERE invoice_id = ?";
$stmt_items = $database->prepare($sql_items);
$stmt_items->bind_param("i", $invoice_id);
$stmt_items->execute();
$items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $invoice['invoice_number'] ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .receipt-container {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 30px;
            position: relative;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #3498db;
            margin: 0;
            font-size: 28px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .receipt-number {
            position: absolute;
            top: 20px;
            right: 30px;
            font-weight: bold;
            color: #555;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-weight: bold;
            color: #3498db;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .detail-item strong {
            display: inline-block;
            width: 120px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 10px;
            border-bottom: 2px solid #ddd;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .total-section {
            text-align: right;
            margin-top: 20px;
            font-size: 18px;
        }
        .total-amount {
            font-weight: bold;
            font-size: 22px;
            color: #2c3e50;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 14px;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .print-only {
            display: none;
        }
        @media print {
            body {
                padding: 0;
            }
            .receipt-container {
                border: none;
                padding: 0;
            }
            .no-print {
                display: none;
            }
            .print-only {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <h1>Meridian Patient Receipt</h1>
            <p>Meridian Medical Center</p>
            <p>123 Healthcare Street, Nairobi, Kenya</p>
            <p>Phone: +254 700 123456 | Email: info@meridianmedical.com</p>
        </div>
        
        <div class="receipt-number">
            Receipt #: <?= $invoice['invoice_number'] ?>
        </div>
        
        <div class="details-grid">
            <div class="section">
                <div class="section-title">Patient Information</div>
                <div class="detail-item"><strong>Name:</strong> <?= htmlspecialchars($invoice['pname']) ?></div>
                <div class="detail-item"><strong>Email:</strong> <?= htmlspecialchars($invoice['pemail']) ?></div>
                <div class="detail-item"><strong>Phone:</strong> <?= htmlspecialchars($invoice['pphoneno']) ?></div>
            </div>
            
            <div class="section">
                <div class="section-title">Appointment Details</div>
                <div class="detail-item"><strong>Date:</strong> <?= date('F j, Y', strtotime($invoice['appodate'])) ?></div>
                <div class="detail-item"><strong>Time:</strong> <?= date('g:i A', strtotime($invoice['appotime'])) ?></div>
                <div class="detail-item"><strong>Doctor:</strong> Dr. <?= htmlspecialchars($invoice['doctor_name']) ?></div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Receipt Items</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['description']) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>Ksh <?= number_format($item['unit_price'], 2) ?></td>
                            <td>Ksh <?= number_format($item['quantity'] * $item['unit_price'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="total-section">
            <div>
                <span>Subtotal:</span>
                <span>Ksh <?= number_format($invoice['amount'], 2) ?></span>
            </div>
            <?php if ($invoice['tax_amount'] > 0): ?>
            <div>
                <span>Tax:</span>
                <span>Ksh <?= number_format($invoice['tax_amount'], 2) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($invoice['discount_amount'] > 0): ?>
            <div>
                <span>Discount:</span>
                <span>-Ksh <?= number_format($invoice['discount_amount'], 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="total-amount">
                <span>Total Paid:</span>
                <span>Ksh <?= number_format($invoice['total_amount'], 2) ?></span>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Payment Information</div>
            <div class="detail-item"><strong>Status:</strong> 
                <span style="text-transform: capitalize; font-weight: bold; color: 
                    <?= $invoice['status'] == 'paid' ? '#27ae60' : 
                       ($invoice['status'] == 'pending' ? '#f39c12' : '#e74c3c') ?>">
                    <?= $invoice['status'] ?>
                </span>
            </div>
            <?php if ($invoice['payment_method']): ?>
            <div class="detail-item"><strong>Payment Method:</strong> 
                <?= ucfirst(str_replace('_', ' ', $invoice['payment_method'])) ?>
            </div>
            <?php endif; ?>
            <?php if ($invoice['payment_date']): ?>
            <div class="detail-item"><strong>Payment Date:</strong> 
                <?= date('F j, Y', strtotime($invoice['payment_date'])) ?>
            </div>
            <?php endif; ?>
            <?php if ($invoice['notes']): ?>
            <div class="detail-item"><strong>Notes:</strong> 
                <?= htmlspecialchars($invoice['notes']) ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>Thank you for choosing Meridian Medical Center</p>
            <p>This is an official receipt. Please retain for your records.</p>
            <p class="print-only">Generated on <?= date('F j, Y \a\t g:i A') ?></p>
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background-color: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <a href="invoices.php" style="display: inline-block; margin-left: 10px; padding: 10px 20px; background-color: #95a5a6; color: white; text-decoration: none; border-radius: 4px;">
            <i class="fas fa-arrow-left"></i> Back to Invoices
        </a>
    </div>
    
    <script>
        // Automatically trigger print dialog when page loads (optional)
        window.onload = function() {
            // Uncomment below line to automatically open print dialog
            // window.print();
        };
    </script>
</body>
</html>