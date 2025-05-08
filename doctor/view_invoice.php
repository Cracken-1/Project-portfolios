<?php
session_start();
require_once("../db/db.php");

// Check if user is logged in as doctor
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

// Check if invoice ID is provided
if (!isset($_GET['id'])) {
    header("location: invoices.php");
    exit();
}

$invoice_id = $_GET['id'];
$docid = $_SESSION['userid'];

// Fetch invoice details with updated column names
$sql = "SELECT i.invoice_id, i.appoid, i.invoice_number, i.invoice_date, i.due_date, 
               i.amount, i.tax_amount, i.discount_amount, i.total_amount, i.status,
               i.payment_method, i.payment_date, i.notes, i.created_at, i.updated_at,
               p.pname, p.pemail, p.pphoneno as pphone, 
               a.appodate, a.appotime,
               d.docname as dname, d.docemail as demail
        FROM invoices i
        JOIN appointment a ON i.appoid = a.appoid
        JOIN patient p ON a.pid = p.pid
        JOIN doctor d ON a.docid = d.docid
        WHERE i.invoice_id = ? AND a.docid = ?";
$stmt = $database->prepare($sql);
$stmt->bind_param("ii", $invoice_id, $docid);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    $_SESSION['error'] = "Invoice not found or you don't have permission to view it";
    header("location: invoices.php");
    exit();
}

// Create a single default invoice item
$items = [[
    'description' => 'Medical Services',
    'quantity' => 1,
    'unit_price' => $invoice['amount']
]];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= $invoice['invoice_number'] ?> - Meridian Hospital</title>
    <link rel="stylesheet" href="../css/view_invoice.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>

    <div class="invoice-container" id="invoice-content">
        <div class="header">
            <div>
                <img src="../img/logo.png" alt="Meridian Hospital Logo" class="logo">
            </div>
            <div class="hospital-info">
                <div class="hospital-name">Meridian Hospital</div>
                <div>123 Hospital Way, Nairobi</div>
                <div>Phone: +254 700 123456</div>
                <div>Email: info@meridianhospital.com</div>
            </div>
        </div>

        <h1 class="invoice-title">Invoice Receipt</h1>

        <div class="invoice-info">
            <div class="info-box">
                <h3>Bill To</h3>
                <p><strong><?= htmlspecialchars($invoice['pname']) ?></strong></p>
                <p>Phone: <?= htmlspecialchars($invoice['pphone']) ?></p>
                <p>Email: <?= htmlspecialchars($invoice['pemail']) ?></p>
            </div>
            <div class="info-box">
                <h3>Invoice Details</h3>
                <p><strong>Invoice #:</strong> <?= $invoice['invoice_number'] ?></p>
                <p><strong>Date:</strong> <?= date('F j, Y', strtotime($invoice['invoice_date'])) ?></p>
                <p><strong>Due Date:</strong> <?= date('F j, Y', strtotime($invoice['due_date'])) ?></p>
                <p><strong>Appointment Date:</strong> <?= date('F j, Y', strtotime($invoice['appodate'])) ?></p>
                <p><strong>Appointment Time:</strong> <?= date('g:i a', strtotime($invoice['appotime'])) ?></p>
                <p><strong>Status:</strong> <span class="status-badge <?= $invoice['status'] ?>"><?= ucfirst($invoice['status']) ?></span></p>
            </div>
            <div class="info-box">
                <h3>Doctor</h3>
                <p><strong>Dr. <?= htmlspecialchars($invoice['dname']) ?></strong></p>
                <p>Email: <?= htmlspecialchars($invoice['demail']) ?></p>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($item['description']) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td>Ksh <?= number_format($item['unit_price'], 2) ?></td>
                        <td>Ksh <?= number_format($item['quantity'] * $item['unit_price'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total-section">
            <div>
                <strong>Subtotal:</strong> Ksh <?= number_format($invoice['amount'], 2) ?>
            </div>
            <?php if ($invoice['tax_amount'] > 0): ?>
            <div>
                <strong>Tax:</strong> Ksh <?= number_format($invoice['tax_amount'], 2) ?>
            </div>
            <?php endif; ?>
            <?php if ($invoice['discount_amount'] > 0): ?>
            <div>
                <strong>Discount:</strong> -Ksh <?= number_format($invoice['discount_amount'], 2) ?>
            </div>
            <?php endif; ?>
            <div class="total-row">
                <strong>Total Amount:</strong> Ksh <?= number_format($invoice['total_amount'], 2) ?>
            </div>
            <?php if ($invoice['status'] == 'paid' && $invoice['payment_date']): ?>
                <div>
                    <strong>Payment Date:</strong> <?= date('F j, Y', strtotime($invoice['payment_date'])) ?>
                </div>
                <?php if ($invoice['payment_method']): ?>
                    <div>
                        <strong>Payment Method:</strong> <?= ucfirst($invoice['payment_method']) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($invoice['notes'])): ?>
        <div class="notes-section">
            <h3>Notes</h3>
            <p><?= nl2br(htmlspecialchars($invoice['notes'])) ?></p>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p><strong>Thank you for choosing Meridian Hospital</strong></p>
            <p>If you have any questions about this invoice, please contact our billing department at billing@meridianhospital.com</p>
            <p>This is an automated system generated invoice. No signature required.</p>
            <p>Printed on: <?= date('F j, Y, g:i a') ?></p>
        </div>
    </div>

    <div class="action-buttons">
    <a href="invoices.php" class="btn btn-back">
        <i class="fas fa-arrow-left"></i> Back to Invoices
    </a>
        <button class="btn btn-download" id="download-pdf">
            <i class="fas fa-download"></i> Download as PDF
        </button>
        <button class="btn btn-print" id="print-invoice">
            <i class="fas fa-print"></i> Print Invoice
        </button>
    </div>
    <?php include("../includes/footer.php"); ?>
    <script>
        // Download as PDF
        document.getElementById('download-pdf').addEventListener('click', function() {
            const element = document.getElementById('invoice-content');
            const opt = {
                margin: 10,
                filename: 'Meridian_Hospital_Invoice_<?= $invoice['invoice_number'] ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            // Generate PDF
            html2pdf().set(opt).from(element).save();
        });

        // Print invoice
        document.getElementById('print-invoice').addEventListener('click', function() {
            window.print();
        });

        // Automatically trigger download when page loads (optional)
        // window.addEventListener('load', function() {
        //     document.getElementById('download-pdf').click();
        // });
    </script>
</body>
</html>