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
$docid = filter_input(INPUT_GET, 'docid', FILTER_VALIDATE_INT);
$start_date_input = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING);
$end_date_input = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING);

// Redirect if docid is missing or invalid
if (!$docid) {
    // Optionally, add a message to the session before redirecting
    // $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid or missing Doctor ID.'];
    header("location: billing.php"); // Redirect to a general billing page or doctor selection page
    exit();
}

// Validate date formats (basic check) and set defaults
$start_date = ($start_date_input && preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date_input))
              ? $start_date_input
              : date('Y-m-01'); // Default to start of current month

$end_date = ($end_date_input && preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date_input))
            ? $end_date_input
            : date('Y-m-t'); // Default to end of current month


// --- Fetch Doctor Details ---
// Use prepared statement
$stmt_doctor = $database->prepare("SELECT docname, specialization FROM doctor WHERE docid = ?");
if (!$stmt_doctor) {
    die("Database prepare error (doctor details): " . $database->error);
}
$stmt_doctor->bind_param("i", $docid);
$stmt_doctor->execute();
$doctor_result = $stmt_doctor->get_result();
$doctor = $doctor_result->fetch_assoc();
$stmt_doctor->close();

// Redirect if doctor not found
if (!$doctor) {
    // Optionally, add a message
    // $_SESSION['message'] = ['type' => 'error', 'text' => 'Doctor with ID ' . $docid . ' not found.'];
    header("location: billing.php");
    exit();
}

// --- Fetch Doctor's Billing Details ---
// Use prepared statement
$stmt_billing = $database->prepare("
    SELECT i.invoice_id, i.invoice_date, i.amount, i.status, i.due_date, p.pname, a.appodate, a.appotime
    FROM invoices i
    JOIN appointment a ON i.appoid = a.appoid
    JOIN patient p ON a.pid = p.pid
    WHERE a.docid = ?
    AND i.invoice_date BETWEEN ? AND ?
    ORDER BY i.invoice_date DESC
");
if (!$stmt_billing) {
    die("Database prepare error (billing details): " . $database->error);
}
$stmt_billing->bind_param("iss", $docid, $start_date, $end_date);
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
    'total_overdue' => 0.0
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
    // Add handling for other potential statuses if needed (e.g., 'cancelled', 'refunded')
}

$database->close(); // Close connection after fetching all data

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Billing Report - Dr. <?php echo htmlspecialchars($doctor['docname']); ?></title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
            line-height: 1.5;
            background-color: #f4f4f4; /* Light background for contrast */
        }
        .report-container {
            max-width: 850px; /* Slightly wider for detailed table */
            margin: 20px auto;
            padding: 25px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
        }
        h1 { font-size: 1.8em; }
        h2 { font-size: 1.5em; margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        h3 { font-size: 1.3em; margin-top: 25px; text-align: left; }
        .date-range {
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
            padding: 8px; /* Adjusted padding */
            text-align: left;
            font-size: 0.95em; /* Slightly smaller font for tables */
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        /* Specific table styles */
        table.doctor-info td:first-child { width: 30%; font-weight: bold; }
        table.summary-table td:first-child { width: 60%; }
        table.summary-table td:last-child { text-align: right; font-weight: bold; }
        table.details-table th { white-space: nowrap; }
        table.details-table td { vertical-align: top; }
        .status-paid { color: #28a745; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-overdue { color: #dc3545; font-weight: bold; }

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
                font-size: 9pt; /* Smaller font for print */
                background-color: #fff; /* White background for print */
            }
            .report-container {
                border: none;
                box-shadow: none;
                max-width: 100%;
                margin: 0;
                padding: 10mm; /* Add some padding for print margins */
            }
            h1, h2, h3 {
                margin-top: 15px;
                margin-bottom: 10px;
                text-align: left; /* Align headers left for print */
                border-bottom: none; /* Remove borders in print */
            }
             h1 { text-align: center; } /* Keep main title centered */
            .date-range {
                 margin-bottom: 15px;
                 text-align: center;
            }
            table th, table td {
                 padding: 5px; /* Tighter padding */
                 font-size: 8.5pt;
            }
            table {
                page-break-inside: auto; /* Allow tables to break across pages */
            }
            tr {
                page-break-inside: avoid; /* Try to keep rows together */
                page-break-after: auto;
            }
            thead {
                display: table-header-group; /* Repeat table headers on each page */
            }
            footer {
                margin-top: 20px;
                padding-top: 10px;
                font-size: 7pt;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <h1>Meridian Hospital - Doctor Billing Report</h1>
        <p class="date-range">
            <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?>
        </p>

        <h2>Doctor Information</h2>
        <table class="doctor-info">
            <tr>
                <td>Name:</td>
                <td>Dr. <?php echo htmlspecialchars($doctor['docname']); ?></td>
            </tr>
            <tr>
                <td>Specialization:</td>
                <td><?php echo htmlspecialchars($doctor['specialization'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Contact:</td>
                <td><?php echo htmlspecialchars($doctor['doctel'] ?? 'N/A'); ?></td>
            </tr>
        </table>

        <h2>Billing Summary</h2>
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
                    <td>Total Amount Invoiced</td>
                    <td>Ksh <?php echo number_format($summary['total_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td>Total Amount Paid</td>
                    <td>Ksh <?php echo number_format($summary['total_paid'], 2); ?></td>
                </tr>
                <tr>
                    <td>Total Pending Payments</td>
                    <td>Ksh <?php echo number_format($summary['total_pending'], 2); ?></td>
                </tr>
                <tr>
                    <td>Total Overdue Payments</td>
                    <td>Ksh <?php echo number_format($summary['total_overdue'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <h2>Detailed Transactions</h2>
        <?php if (!empty($billing_details)): ?>
            <table class="details-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Inv. Date</th>
                        <th>Patient</th>
                        <th>Appt. Date</th>
                        <th>Amount (Ksh)</th>
                        <th>Status</th>
                        <th>Due Date</th>
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
                            <td><?php echo htmlspecialchars($invoice['pname']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($invoice['appodate'])); ?></td>
                            <td style="text-align: right;"><?php echo number_format($invoice['amount'], 2); ?></td>
                            <td class="<?php echo $status_class; ?>"><?php echo $display_status; ?></td>
                            <td><?php echo !empty($invoice['due_date']) ? date('M j, Y', strtotime($invoice['due_date'])) : 'N/A'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #777; margin-top: 20px;">No billing transactions found for this doctor in the selected period.</p>
        <?php endif; ?>

        <footer>
            Generated on <?php echo date('F j, Y \a\t H:i:s'); ?>
        </footer>

    </div> <div style="text-align: center; margin-top: 20px;" class="no-print">
         <button onclick="window.print();">Print/Save Report</button>
    </div>

    <script>
        // --- Trigger Print Dialog on Load ---
        window.onload = function() {
            // No chart rendering needed here, so print can be triggered sooner
            // Add a very small delay just in case of slow rendering elements
            setTimeout(function() {
                window.print();
            }, 100); // Shorter delay
        };
    </script>

</body>
</html>
