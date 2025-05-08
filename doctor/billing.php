<?php
session_start();
// Check if user is logged in and is a doctor
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php"); // Assuming this file establishes the $database connection
$docid = $_SESSION['userid'];

// --- Removed the initial $_GET['appoid'] check here ---
// This allows the billing page to load without requiring an appoid in the URL.

// Fetch billing summary
$stmt = $database->prepare("
    SELECT
        COUNT(DISTINCT i.invoice_id) as total_invoices,
        SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN i.status = 'pending' THEN i.total_amount ELSE 0 END) as total_pending,
        SUM(CASE WHEN i.status = 'pending' AND i.due_date < CURDATE() THEN i.total_amount ELSE 0 END) as total_overdue
    FROM invoices i
    JOIN appointment a ON i.appoid = a.appoid
    WHERE a.docid = ?
");
$stmt->bind_param("i", $docid);
$stmt->execute();
$billing_summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch recent invoices
$invoices = [];
$stmt = $database->prepare("
    SELECT i.*, p.pname
    FROM invoices i
    JOIN appointment a ON i.appoid = a.appoid
    JOIN patient p ON a.pid = p.pid
    WHERE a.docid = ?
    ORDER BY i.invoice_date DESC
    LIMIT 10
");
$stmt->bind_param("i", $docid);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $invoices[] = $row;
}
$stmt->close();

// Note: The openPaymentModal function needs to be defined in your scripts.js or similar file
// for the "Pay Now" buttons (if they appear for 'pending' invoices) to work.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Management</title>
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
     </head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="section-header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Billing Management</h1>
            <a href="create_invoice.php" class="btn-generate">
                <i class="fas fa-plus"></i> New Invoice
            </a>
        </div>

        <div class="billing-summary-cards">
            <div class="summary-card">
                <div class="summary-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="summary-info">
                    <h3><?php echo $billing_summary['total_invoices']; ?></h3>
                    <p>Total Invoices</p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="summary-info">
                    <h3>Ksh. <?php echo number_format($billing_summary['total_paid'], 2); ?></h3>
                    <p>Total Paid</p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="summary-info">
                    <h3>Ksh. <?php echo number_format($billing_summary['total_pending'], 2); ?></h3>
                    <p>Pending Payments</p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="summary-info">
                    <h3>Ksh. <?php echo number_format($billing_summary['total_overdue'], 2); ?></h3>
                    <p>Overdue Payments</p>
                </div>
            </div>
        </div>

        <div class="recent-invoices">
            <h2><i class="fas fa-history"></i> Recent Invoices</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No recent invoices found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['pname']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['invoice_date']); ?></td>
                                <td>Ksh. <?php echo number_format($invoice['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower(htmlspecialchars($invoice['status'])); ?>">
                                        <?php echo htmlspecialchars(ucfirst($invoice['status'])); ?>
                                        <?php if ($invoice['status'] == 'pending' && strtotime($invoice['due_date']) < time()): ?>
                                            (Overdue)
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($invoice['due_date']); ?></td>
                                <td class="actions">
                                    <a href="view_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="download_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn-download" target="_blank">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <?php if ($invoice['status'] == 'pending'): ?>
                                        <button class="btn-pay" onclick="openPaymentModal(<?php echo $invoice['invoice_id']; ?>, <?php echo $invoice['total_amount']; ?>)">
                                            <i class="fas fa-credit-card"></i> Pay Now
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>

    <script>
        // Placeholder function - you need to implement this logic
        function openPaymentModal(invoiceId, amount) {
            alert('Opening payment modal for Invoice ID: ' + invoiceId + ' Amount: Ksh. ' + amount.toFixed(2));
            // Implement your modal or redirect logic here
            // window.location.href = 'process_payment.php?invoice_id=' + invoiceId; // Example redirect
        }

        // Assuming you might have a view modal handled by JS as well
        // document.querySelectorAll('.btn-view').forEach(button => {
        //     button.addEventListener('click', function(e) {
        //         e.preventDefault(); // Prevent default link behavior if using JS modal
        //         const invoiceId = this.getAttribute('href').split('=')[1]; // Extract ID from href
        //         openViewModal(invoiceId); // Call your view modal function
        //     });
        // });
        // function openViewModal(invoiceId) {
        //     alert('Opening view modal for Invoice ID: ' + invoiceId);
        //     // Implement fetching and displaying invoice details in a modal
        // }
    </script>

</body>
</html>