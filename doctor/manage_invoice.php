<?php
session_start();
require_once("../db/db.php");

// Check if user is logged in as doctor
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

$docid = $_SESSION['userid'];
$action = isset($_GET['action']) ? $_GET['action'] : 'view';
$invoice_id = isset($_GET['id']) ? $_GET['id'] : null;

// Handle form submission for editing invoice
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'edit') {
    $invoice_id = $_POST['invoice_id'];
    $status = $_POST['status'];
    $total_amount = $_POST['total_amount'];
    $notes = $_POST['notes'];
    
    $sql = "UPDATE invoices SET status = ?, total_amount = ?, notes = ? WHERE invoice_id = ?";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("sdsi", $status, $total_amount, $notes, $invoice_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Invoice updated successfully!";
        header("Location: invoices.php?action=view");
        exit();
    } else {
        $_SESSION['error'] = "Error updating invoice: " . $database->error;
    }
}

// Fetch invoice details for editing
if ($action == 'edit' && $invoice_id) {
    $sql = "SELECT i.*, p.pname, a.appodate 
            FROM invoices i
            JOIN appointment a ON i.appoid = a.appoid
            JOIN patient p ON a.pid = p.pid
            WHERE i.invoice_id = ? AND a.docid = ?";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("ii", $invoice_id, $docid);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    
    if (!$invoice) {
        $_SESSION['error'] = "Invoice not found or you don't have permission to access it";
        header("Location: invoices.php?action=view");
        exit();
    }
}

// Fetch all invoices for this doctor (for view mode)
$sql = "SELECT i.*, p.pname, a.appodate 
        FROM invoices i
        JOIN appointment a ON i.appoid = a.appoid
        JOIN patient p ON a.pid = p.pid
        WHERE a.docid = ?
        ORDER BY i.invoice_date DESC";
$stmt = $database->prepare($sql);
$stmt->bind_param("i", $docid);
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include("../includes/header.php");
include("../includes/sidebar.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $action == 'edit' ? 'Edit Invoice' : 'Invoices' ?> - Doctor Panel</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/invoices.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Edit Invoice Specific Styles */
        .edit-invoice-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .edit-invoice-container h2 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .invoice-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .invoice-details .detail-group {
            margin-bottom: 15px;
        }
        
        .invoice-details label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #34495e;
        }
        
        .invoice-details input, 
        .invoice-details select, 
        .invoice-details textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .invoice-details textarea {
            min-height: 100px;
        }
        
        .invoice-items {
            margin: 20px 0;
        }
        
        .invoice-items table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .invoice-items th, 
        .invoice-items td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .invoice-items th {
            background-color: #f8f9fa;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .btn-save {
            background-color: #3498db;
            color: white;
        }
        
        .btn-save:hover {
            background-color: #2980b9;
        }
        
        .btn-cancel {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-cancel:hover {
            background-color: #c0392b;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.paid {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-badge.cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <?php if ($action == 'edit' && $invoice): ?>
            <!-- Edit Invoice Section -->
            <div class="edit-invoice-container">
                <h2>Edit Invoice #<?= $invoice['invoice_number'] ?></h2>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="invoices.php?action=edit">
                    <input type="hidden" name="invoice_id" value="<?= $invoice['invoice_id'] ?>">
                    
                    <div class="invoice-details">
                        <div class="detail-group">
                            <label>Invoice Number</label>
                            <input type="text" value="<?= $invoice['invoice_number'] ?>" readonly>
                        </div>
                        
                        <div class="detail-group">
                            <label>Date</label>
                            <input type="text" value="<?= date('M j, Y', strtotime($invoice['invoice_date'])) ?>" readonly>
                        </div>
                        
                        <div class="detail-group">
                            <label>Patient</label>
                            <input type="text" value="<?= htmlspecialchars($invoice['pname']) ?>" readonly>
                        </div>
                        
                        <div class="detail-group">
                            <label>Appointment Date</label>
                            <input type="text" value="<?= date('M j, Y', strtotime($invoice['appodate'])) ?>" readonly>
                        </div>
                        
                        <div class="detail-group">
                            <label for="total_amount">Amount (Ksh)</label>
                            <input type="number" id="total_amount" name="total_amount" 
                                   value="<?= $invoice['total_amount'] ?>" step="0.01" min="0" required>
                        </div>
                        
                        <div class="detail-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <option value="paid" <?= $invoice['status'] == 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="pending" <?= $invoice['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="cancelled" <?= $invoice['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="invoice-items">
                        <h3>Invoice Items</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Fetch invoice items
                                $sql_items = "SELECT * FROM invoice_items WHERE invoice_id = ?";
                                $stmt_items = $database->prepare($sql_items);
                                $stmt_items->bind_param("i", $invoice['invoice_id']);
                                $stmt_items->execute();
                                $items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
                                
                                foreach ($items as $item): ?>
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
                    
                    <div class="detail-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes"><?= htmlspecialchars($invoice['notes']) ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <a href="invoices.php?action=view" class="btn btn-cancel">Cancel</a>
                        <button type="submit" class="btn btn-save">Save Changes</button>
                    </div>
                </form>
            </div>
            
        <?php else: ?>
            <!-- View Invoices Section -->
            <div class="page-header">
                <h1>Invoices</h1>
                <a href="create_invoice.php" class="btn-create">
                    <i class="fas fa-plus"></i> Create New Invoice
                </a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="invoices-container">
                <div class="filters">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search invoices...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="status-filter">
                        <label for="statusFilter">Filter by status:</label>
                        <select id="statusFilter">
                            <option value="all">All</option>
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="invoices-table">
                    <div class="table-header">
                        <div class="col-invoice">Invoice #</div>
                        <div class="col-date">Date</div>
                        <div class="col-patient">Patient</div>
                        <div class="col-amount">Amount</div>
                        <div class="col-status">Status</div>
                        <div class="col-actions">Actions</div>
                    </div>

                    <div class="table-body">
                        <?php foreach ($invoices as $invoice): ?>
                            <div class="table-row" data-status="<?= $invoice['status'] ?>">
                                <div class="col-invoice">
                                    <a href="invoices.php?action=view&id=<?= $invoice['invoice_id'] ?>">
                                        <?= $invoice['invoice_number'] ?>
                                    </a>
                                </div>
                                <div class="col-date">
                                    <?= date('M j, Y', strtotime($invoice['invoice_date'])) ?>
                                </div>
                                <div class="col-patient"><?= htmlspecialchars($invoice['pname']) ?></div>
                                <div class="col-amount">Ksh <?= number_format($invoice['total_amount'], 2) ?></div>
                                <div class="col-status">
                                    <span class="status-badge <?= $invoice['status'] ?>">
                                        <?= ucfirst($invoice['status']) ?>
                                    </span>
                                </div>
                                <div class="col-actions">
                                    <a href="invoices.php?action=view&id=<?= $invoice['invoice_id'] ?>" class="btn-view" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="invoices.php?action=edit&id=<?= $invoice['invoice_id'] ?>" class="btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="print_invoice.php?id=<?= $invoice['invoice_id'] ?>" class="btn-print" title="Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($invoices)): ?>
                            <div class="no-results">
                                <i class="fas fa-file-invoice"></i>
                                <p>No invoices found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include("../includes/footer.php"); ?>

    <script>
        // Filter functionality
        document.getElementById('statusFilter')?.addEventListener('change', function() {
            const status = this.value;
            const rows = document.querySelectorAll('.table-row');
            
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Search functionality
        document.getElementById('searchInput')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.table-row');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>