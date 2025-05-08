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

// Fetch invoice details
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

// Fetch invoice items
$items_sql = "SELECT * FROM invoice_items WHERE invoice_id = ?";
$items_stmt = $database->prepare($items_sql);
$items_stmt->bind_param("i", $invoice_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);

// If no items exist, create a default one
$items = [[
    'description' => 'Medical Services',
    'quantity' => 1,
    'unit_price' => $invoice['amount']
]];
// Generate edited reference number
$edited_ref = "ED-" . $invoice['invoice_number'] . "-" . date('Ymd');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Invoice #<?= $invoice['invoice_number'] ?> - Meridian Hospital</title>
    <link rel="stylesheet" href="../css/view_invoice.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Add styles for edited version notice */
        .edited-version {
            display: none;
        }
        @media print {
            .edited-version {
                display: block;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background-color: #fff3cd;
                color: #856404;
                padding: 10px;
                text-align: center;
                border-bottom: 2px dashed #ffeeba;
                font-weight: bold;
                z-index: 9999;
            }
            .edited-ref {
                position: fixed;
                top: 40px;
                right: 20px;
                background-color: #f8f9fa;
                padding: 5px 10px;
                border: 1px solid #dee2e6;
                font-size: 12px;
                z-index: 9999;
            }
        }
        
        /* Form styles */
        .form-group {
            margin-bottom: 15px;
        }
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn-remove-item {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
        }
        .btn-add {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<?php include("../includes/header.php"); ?>
<?php include("../includes/sidebar.php"); ?>

<form method="post" action="update_invoice.php" id="invoice-form">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
    <input type="hidden" name="csrf_token" value="<?= bin2hex(random_bytes(32)) ?>">

    <div class="invoice-container" id="invoice-content">
        <!-- Edited version notice (only shows when printing) -->
        <div class="edited-version">
            ⚠️ THIS IS AN EDITED VERSION OF THE ORIGINAL INVOICE - FOR REFERENCE ONLY ⚠️
        </div>
        <div class="edited-ref">
            Edited Ref: <?= $edited_ref ?>
        </div>

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

        <h1 class="invoice-title">Edit Invoice Receipt</h1>

        <div class="invoice-info">
            <div class="info-box">
                <h3>Bill To</h3>
                <p><strong><?= htmlspecialchars($invoice['pname']) ?></strong></p>
                <p>Phone: <?= htmlspecialchars($invoice['pphone']) ?></p>
                <p>Email: <?= htmlspecialchars($invoice['pemail']) ?></p>
            </div>
            <div class="info-box">
                <h3>Invoice Details</h3>
                <div class="form-group">
                    <label>Original Invoice #:</label>
                    <p><?= $invoice['invoice_number'] ?></p>
                </div>
                <div class="form-group">
                    <label>Edited Reference #:</label>
                    <p><?= $edited_ref ?></p>
                </div>
                <div class="form-group">
                    <label for="invoice_date">Date:</label>
                    <input type="date" name="invoice_date" id="invoice_date" value="<?= $invoice['invoice_date'] ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label for="due_date">Due Date:</label>
                    <input type="date" name="due_date" id="due_date" value="<?= $invoice['due_date'] ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label>Appointment Date:</label>
                    <p><?= date('F j, Y', strtotime($invoice['appodate'])) ?></p>
                </div>
                <div class="form-group">
                    <label>Appointment Time:</label>
                    <p><?= date('g:i a', strtotime($invoice['appotime'])) ?></p>
                </div>
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status" class="form-control">
                        <option value="pending" <?= $invoice['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="paid" <?= $invoice['status'] == 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="cancelled" <?= $invoice['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
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
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="items-container">
                <?php foreach ($items as $index => $item): ?>
                    <tr class="item-row">
                        <td><?= $index + 1 ?></td>
                        <td><input type="text" name="items[<?= $index ?>][description]" value="<?= htmlspecialchars($item['description']) ?>" class="form-control"></td>
                        <td><input type="number" name="items[<?= $index ?>][quantity]" value="<?= $item['quantity'] ?>" min="1" step="0.01" class="form-control quantity"></td>
                        <td><input type="number" name="items[<?= $index ?>][unit_price]" value="<?= $item['unit_price'] ?>" step="0.01" min="0" class="form-control unit-price"></td>
                        <td class="item-total">Ksh <?= number_format($item['quantity'] * $item['unit_price'], 2) ?></td>
                        <td><button type="button" class="btn-remove-item"><i class="fas fa-trash"></i></button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <button type="button" id="add-item" class="btn btn-add">
            <i class="fas fa-plus"></i> Add Item
        </button>

        <div class="total-section">
            <div class="form-group">
                <label for="tax_rate">Tax Rate (%):</label>
                <input type="number" name="tax_rate" id="tax_rate" value="<?= $invoice['tax_amount'] > 0 ? ($invoice['tax_amount'] / $invoice['amount'] * 100) : 0 ?>" step="0.01" min="0" max="100" class="form-control">
            </div>
            <div class="form-group">
                <label for="discount_amount">Discount Amount:</label>
                <input type="number" name="discount_amount" id="discount_amount" value="<?= $invoice['discount_amount'] ?>" step="0.01" min="0" class="form-control">
            </div>
            
            <div id="calculated-totals">
                <div>
                    <strong>Subtotal:</strong> <span id="subtotal">Ksh <?= number_format($invoice['amount'], 2) ?></span>
                </div>
                <div>
                    <strong>Tax:</strong> <span id="tax-amount">Ksh <?= number_format($invoice['tax_amount'], 2) ?></span>
                </div>
                <div>
                    <strong>Discount:</strong> <span id="discount-amount">-Ksh <?= number_format($invoice['discount_amount'], 2) ?></span>
                </div>
                <div class="total-row">
                    <strong>Total Amount:</strong> <span id="grand-total">Ksh <?= number_format($invoice['total_amount'], 2) ?></span>
                </div>
            </div>
            
            <?php if ($invoice['status'] == 'paid' && $invoice['payment_date']): ?>
                <div class="form-group">
                    <label for="payment_date">Payment Date:</label>
                    <input type="date" name="payment_date" id="payment_date" value="<?= $invoice['payment_date'] ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label for="payment_method">Payment Method:</label>
                    <select name="payment_method" id="payment_method" class="form-control">
                        <option value="">Select method</option>
                        <option value="cash" <?= $invoice['payment_method'] == 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="mpesa" <?= $invoice['payment_method'] == 'mpesa' ? 'selected' : '' ?>>M-Pesa</option>
                        <option value="credit_card" <?= $invoice['payment_method'] == 'credit_card' ? 'selected' : '' ?>>Credit Card</option>
                        <option value="bank_transfer" <?= $invoice['payment_method'] == 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="notes">Notes:</label>
            <textarea name="notes" id="notes" class="form-control"><?= htmlspecialchars($invoice['notes']) ?></textarea>
        </div>

        <div class="footer">
            <p><strong>Thank you for choosing Meridian Hospital</strong></p>
            <p>If you have any questions about this invoice, please contact our billing department at billing@meridianhospital.com</p>
            <p class="edited-notice">⚠️ THIS IS AN EDITED VERSION OF THE ORIGINAL INVOICE ⚠️</p>
            <p>Printed on: <?= date('F j, Y, g:i a') ?></p>
        </div>
    </div>

    <div class="action-buttons">
        <a href="invoices.php" class="btn btn-back">
            <i class="fas fa-arrow-left"></i> Back to Invoices
        </a>
        <button type="button" class="btn btn-download" id="download-pdf">
            <i class="fas fa-download"></i> Download as PDF
        </button>
        <button type="button" class="btn btn-print" id="print-invoice">
            <i class="fas fa-print"></i> Print Invoice
        </button>
        <button type="submit" class="btn btn-save" id="save-changes">
            <i class="fas fa-save"></i> Save Changes
        </button>
    </div>
</form>

<script>
    // Add item row
    $('#add-item').click(function() {
        const container = $('#items-container');
        const index = container.find('.item-row').length;
        
        const row = `
            <tr class="item-row">
                <td>${index + 1}</td>
                <td><input type="text" name="items[${index}][description]" value="" class="form-control"></td>
                <td><input type="number" name="items[${index}][quantity]" value="1" min="1" step="0.01" class="form-control quantity"></td>
                <td><input type="number" name="items[${index}][unit_price]" value="0" step="0.01" min="0" class="form-control unit-price"></td>
                <td class="item-total">Ksh 0.00</td>
                <td><button type="button" class="btn-remove-item"><i class="fas fa-trash"></i></button></td>
            </tr>
        `;
        
        container.append(row);
    });

    // Remove item row
    $(document).on('click', '.btn-remove-item', function() {
        $(this).closest('.item-row').remove();
        updateItemNumbers();
        updateInvoiceTotals();
    });

    // Calculate totals when quantities/prices change
    $(document).on('input', '.quantity, .unit-price', function() {
        const row = $(this).closest('.item-row');
        const quantity = parseFloat(row.find('.quantity').val()) || 0;
        const unitPrice = parseFloat(row.find('.unit-price').val()) || 0;
        const total = quantity * unitPrice;
        
        row.find('.item-total').text('Ksh ' + total.toFixed(2));
        updateInvoiceTotals();
    });

    // Calculate totals when tax or discount changes
    $(document).on('input', '#tax_rate, #discount_amount', function() {
        updateInvoiceTotals();
    });

    function updateItemNumbers() {
        $('#items-container .item-row').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    }

    function updateInvoiceTotals() {
        let subtotal = 0;
        
        $('.item-row').each(function() {
            const quantity = parseFloat($(this).find('.quantity').val()) || 0;
            const unitPrice = parseFloat($(this).find('.unit-price').val()) || 0;
            subtotal += quantity * unitPrice;
        });
        
        const taxRate = parseFloat($('#tax_rate').val()) || 0;
        const taxAmount = subtotal * (taxRate / 100);
        const discountAmount = parseFloat($('#discount_amount').val()) || 0;
        const grandTotal = subtotal + taxAmount - discountAmount;
        
        $('#subtotal').text('Ksh ' + subtotal.toFixed(2));
        $('#tax-amount').text('Ksh ' + taxAmount.toFixed(2));
        $('#discount-amount').text('-Ksh ' + discountAmount.toFixed(2));
        $('#grand-total').text('Ksh ' + grandTotal.toFixed(2));
    }

    // Download as PDF
    $('#download-pdf').click(function() {
        const element = document.getElementById('invoice-content');
        const opt = {
            margin: 10,
            filename: 'Meridian_Hospital_Edited_Invoice_<?= $edited_ref ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        // Generate PDF
        html2pdf().set(opt).from(element).save();
    });

    // Print invoice
    $('#print-invoice').click(function() {
        window.print();
    });
</script>
</body>
</html>