<?php
session_start();
// Redirect if not logged in as an admin
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php"); // Ensure this path is correct
    exit();
}

// Include database connection
include("../db/db.php"); // Ensure this path is correct

// --- Input Validation and Sanitization ---
$pid = filter_input(INPUT_GET, 'pid', FILTER_VALIDATE_INT);
$start_date_input = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING);
$end_date_input = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING);

// Redirect if pid is missing or invalid
if (!$pid) {
    // $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid or missing Patient ID.'];
    header("location: billing.php"); // Redirect to a general billing page or patient selection page
    exit();
}

// Validate date formats (basic check) and set defaults
$start_date = ($start_date_input && preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date_input))
              ? $start_date_input
              : date('Y-m-d', strtotime('-3 months')); // Default to last 3 months

$end_date = ($end_date_input && preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date_input))
            ? $end_date_input
            : date('Y-m-d'); // Default to today


// --- Fetch Patient Details ---
// Use prepared statement
// Ensure column names (pname, paddress, ptel, pemail) match your patient table schema
$stmt_patient = $database->prepare("SELECT pname, paddress, ptel, pemail FROM patient WHERE pid = ?");
if (!$stmt_patient) {
    die("Database prepare error (patient details): " . $database->error);
}
$stmt_patient->bind_param("i", $pid);
$stmt_patient->execute();
$patient_result = $stmt_patient->get_result();
$patient = $patient_result->fetch_assoc();
$stmt_patient->close();

// Redirect if patient not found
if (!$patient) {
    // $_SESSION['message'] = ['type' => 'error', 'text' => 'Patient with ID ' . $pid . ' not found.'];
    header("location: billing.php");
    exit();
}

// --- Fetch Patient's Billing Details ---
// Use prepared statement
$stmt_billing = $database->prepare("
    SELECT i.invoice_id, i.invoice_date, i.amount, i.status, i.due_date,
           d.docname, d.specialization, a.appodate, a.appotime
    FROM invoices i
    JOIN appointment a ON i.appoid = a.appoid
    JOIN doctor d ON a.docid = d.docid
    WHERE a.pid = ?
    AND i.invoice_date BETWEEN ? AND ?
    ORDER BY i.invoice_date DESC
");
if (!$stmt_billing) {
    die("Database prepare error (billing details): " . $database->error);
}
$stmt_billing->bind_param("iss", $pid, $start_date, $end_date);
$stmt_billing->execute();
$billing_result = $stmt_billing->get_result();
$billing_details = $billing_result->fetch_all(MYSQLI_ASSOC);
$stmt_billing->close();


// --- Calculate Summary ---
// Initialize summary array
$summary = [
    'total_invoices' => 0,
    'total_amount' => 0.0,
    'total_paid' => 0.0,
    'total_pending' => 0.0,
    'total_overdue' => 0.0,
    'balance' => 0.0 // Balance is the total pending amount
];

// Calculate summary figures from fetched details
foreach ($billing_details as $invoice) {
    $summary['total_invoices']++;
    $amount = (float) $invoice['amount']; // Ensure amount is float
    $summary['total_amount'] += $amount;

    if (strtolower($invoice['status']) == 'paid') {
        $summary['total_paid'] += $amount;
    } elseif (strtolower($invoice['status']) == 'pending') {
        $summary['total_pending'] += $amount;
        // Check if overdue (compare timestamps for accuracy)
        if (!empty($invoice['due_date']) && strtotime($invoice['due_date']) < time()) {
            $summary['total_overdue'] += $amount;
        }
    }
    // Add handling for other potential statuses if needed
}

// Current balance is the sum of all pending invoices for this patient within the period
$summary['balance'] = $summary['total_pending'];

$database->close(); // Close connection after fetching all data

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Statement - <?php echo htmlspecialchars($patient['pname']); ?></title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
            line-height: 1.5;
            background-color: #f4f4f4;
        }
        .report-container {
            max-width: 850px;
            margin: 20px auto;
            padding: 25px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1, h2 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
        }
        h1 { font-size: 1.8em; }
        h2 { font-size: 1.5em; margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 5px; text-align: left; }
        h1 + p { /* Style for date range below main title */
            text-align: center;
            font-size: 1.1em;
            color: #555;
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 0.95em;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        /* Specific table styles */
        table.patient-info td:first-child { width: 25%; font-weight: bold; }
        table.summary-table td:first-child { width: 60%; }
        table.summary-table td:last-child { text-align: right; font-weight: bold; }
        table.summary-table tr:last-child td { font-size: 1.1em; background-color: #f9f9f9; } /* Highlight balance */
        table.details-table th { white-space: nowrap; }
        table.details-table td { vertical-align: top; }
        .status-paid { color: #28a745; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-overdue { color: #dc3545; font-weight: bold; }

        .payment-instructions {
            margin-top: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 5px;
        }
        .payment-instructions h3 {
             margin-top: 0;
             margin-bottom: 10px;
             font-size: 1.2em;
             text-align: left;
             border-bottom: 1px solid #ddd;
             padding-bottom: 5px;
        }
        .payment-instructions ul {
            list-style: disc;
            margin-left: 20px;
            padding-left: 0;
        }
        .payment-instructions li {
            margin-bottom: 5px;
        }

        footer {
            text-align: center;
            margin-top: 40px;
            font-size: 0.9em;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }

        /* Print-specific styles */
        @media print {
            body {
                margin: 0;
                font-size: 9pt;
                background-color: #fff;
            }
            .report-container {
                border: none;
                box-shadow: none;
                max-width: 100%;
                margin: 0;
                padding: 10mm;
            }
            h1, h2, h3 {
                margin-top: 15px;
                margin-bottom: 10px;
                text-align: left;
                border-bottom: none;
            }
             h1 { text-align: center; }
             h1 + p { margin-bottom: 15px; text-align: center; }
            table th, table td {
                 padding: 5px;
                 font-size: 8.5pt;
            }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
            .payment-instructions { background-color: #fff; border: 1px solid #ccc; padding: 10px; margin-top: 20px;}
            .payment-instructions h3 { font-size: 1.1em; }
            footer { margin-top: 20px; padding-top: 10px; font-size: 7pt; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <h1>Patient Statement</h1>
        <p>
            For Period: <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?>
        </p>

        <h2>Patient Information</h2>
        <table class="patient-info">
            <tr>
                <td>Name:</td>
                <td><?php echo htmlspecialchars($patient['pname']); ?></td>
            </tr>
            <tr>
                <td>Address:</td>
                <td><?php echo htmlspecialchars($patient['paddress'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Contact:</td>
                <td>
                    <?php echo htmlspecialchars($patient['ptel'] ?? 'N/A'); ?>
                    <?php if (!empty($patient['pemail'])): ?>
                         | <?php echo htmlspecialchars($patient['pemail']); ?>
                    <?php endif; ?>
                </td>
            </tr>
             <tr>
                <td>Patient ID:</td>
                <td><?php echo $pid; ?></td>
            </tr>
        </table>

        <h2>Account Summary</h2>
        <table class="summary-table">
             <thead>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total Invoices in Period</td>
                    <td><?php echo number_format($summary['total_invoices']); ?></td>
                </tr>
                <tr>
                    <td>Total Charges in Period</td>
                    <td>Ksh <?php echo number_format($summary['total_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td>Total Payments Received in Period</td>
                    <td>Ksh <?php echo number_format($summary['total_paid'], 2); ?></td>
                </tr>
                <tr>
                    <td>Pending Payments (from this period)</td>
                    <td>Ksh <?php echo number_format($summary['total_pending'], 2); ?></td>
                </tr>
                 <tr>
                    <td>**Current Balance (Outstanding)**</td>
                    <td>**Ksh <?php echo number_format($summary['balance'], 2); ?>**</td>
                </tr>
            </tbody>
        </table>

        <h2>Transaction Details</h2>
        <?php if (!empty($billing_details)): ?>
            <table class="details-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Inv. Date</th>
                        <th>Provider</th>
                        <th>Service Date</th>
                        <th>Amount (Ksh)</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($billing_details as $invoice):
                        $status = strtolower($invoice['status']);
                        $status_class = 'status-' . $status;
                        $display_status = ucfirst($status);

                        if ($status == 'pending' && !empty($invoice['due_date']) && strtotime($invoice['due_date']) < time()) {
                            $status_class = 'status-overdue';
                            $display_status = 'Overdue';
                        }
                    ?>
                        <tr>
                            <td><?php echo $invoice['invoice_id']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($invoice['invoice_date'])); ?></td>
                            <td>Dr. <?php echo htmlspecialchars($invoice['docname']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($invoice['appodate'])); ?></td>
                            <td style="text-align: right;"><?php echo number_format($invoice['amount'], 2); ?></td>
                            <td><?php echo !empty($invoice['due_date']) ? date('M j, Y', strtotime($invoice['due_date'])) : 'N/A'; ?></td>
                            <td class="<?php echo $status_class; ?>"><?php echo $display_status; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #777; margin-top: 20px;">No transactions found for this patient in the selected period.</p>
        <?php endif; ?>

        <div class="payment-instructions">
            <h3>Payment Instructions</h3>
            <p>Please make payment for the outstanding balance by the respective due dates to avoid service interruptions.</p>
            <p><strong>Payment Methods:</strong></p>
            <ul>
                <li><strong>M-Pesa:</strong> Paybill <strong>123456</strong> | Account: <strong><?php echo $pid; ?></strong></li>
                <li><strong>Credit Card:</strong> Visit our clinic reception or use our online payment portal (if available).</li>
                <li><strong>Bank Transfer:</strong> Medical Clinic | Account #<strong>1234567890</strong> | Kenya Commercial Bank (KCB) - Clinic Branch</li>
            </ul>
            <p>For any questions regarding this statement, please contact our billing department:</p>
            <p>Email: billing@yourclinicdomain.com | Phone: +254 7XX XXX XXX</p>
        </div>


        <footer>
            Generated on <?php echo date('F j, Y \a\t H:i:s'); ?>
        </footer>

    </div> <div style="text-align: center; margin-top: 20px;" class="no-print">
         <button onclick="window.print();">Print/Save Statement</button>
    </div>

    <script>
        // --- Trigger Print Dialog on Load ---
        window.onload = function() {
            // Add a very small delay just in case of slow rendering elements
            setTimeout(function() {
                window.print();
            }, 100); // Shorter delay
        };
    </script>

</body>
</html>
