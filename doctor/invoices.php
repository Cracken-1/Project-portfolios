<?php
session_start();
require_once("../db/db.php");

// Check if user is logged in as doctor
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

$docid = $_SESSION['userid'];

// Fetch all invoices for this doctor
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
    <title>Invoices - Doctor Panel</title>
    <link rel="stylesheet" href="../css/invoices.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="main-content">
        <div class="page-header">
            <h1>Invoices</h1>
            <a href="select_appointment.php" class="btn-create">
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
                                <a href="view_invoice.php?id=<?= $invoice['invoice_id'] ?>" class="btn-view" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_invoices.php?id=<?= $invoice['invoice_id'] ?>" class="btn-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>

                                <a href="view_invoice.php?id=<?= $invoice['invoice_id'] ?>" class="btn-print" title="Print">
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
    </div>

    <?php include("../includes/footer.php"); ?>

    <script>
        // Filter functionality
        document.getElementById('statusFilter').addEventListener('change', function() {
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
        document.getElementById('searchInput').addEventListener('input', function() {
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