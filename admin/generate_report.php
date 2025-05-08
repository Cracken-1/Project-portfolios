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
// Use filter_input for better security
$start_date_input = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING);
$end_date_input = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING);

// Validate date formats (basic check) and set defaults
$start_date = ($start_date_input && preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date_input))
              ? $start_date_input
              : date('Y-m-01'); // Default to start of current month

$end_date = ($end_date_input && preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date_input))
            ? $end_date_input
            : date('Y-m-t'); // Default to end of current month

// --- Fetch Billing Summary ---
// Use prepared statements to prevent SQL injection
$stmt_summary = $database->prepare("
    SELECT
        COUNT(*) as total_invoices,
        SUM(amount) as total_amount,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
        SUM(CASE WHEN status = 'pending' AND due_date < CURDATE() THEN amount ELSE 0 END) as total_overdue
    FROM invoices
    WHERE invoice_date BETWEEN ? AND ?
");

if (!$stmt_summary) {
     die("Database prepare error (summary): " . $database->error);
}

$stmt_summary->bind_param("ss", $start_date, $end_date);
$stmt_summary->execute();
$summary_result = $stmt_summary->get_result();
$billing_summary = $summary_result->fetch_assoc();
// Initialize defaults if no results
$billing_summary = array_merge([
    'total_invoices' => 0, 'total_amount' => 0, 'total_paid' => 0,
    'total_pending' => 0, 'total_overdue' => 0
], $billing_summary ?? []);
$stmt_summary->close();


// --- Fetch Monthly Revenue Data for Chart ---
// Calculate the start date for the 12-month period ending with the selected end_date
$chart_start_date = date('Y-m-01', strtotime("$end_date -11 months"));

$stmt_monthly = $database->prepare("
    SELECT
        DATE_FORMAT(invoice_date, '%Y-%m') as month,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_amount,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount
    FROM invoices
    WHERE invoice_date BETWEEN ? AND ?
    GROUP BY month
    ORDER BY month ASC
");

if (!$stmt_monthly) {
     die("Database prepare error (monthly): " . $database->error);
}

$stmt_monthly->bind_param("ss", $chart_start_date, $end_date);
$stmt_monthly->execute();
$monthly_result = $stmt_monthly->get_result();

// Prepare data for Chart.js
$chart_labels = [];
$chart_paid_data = [];
$chart_pending_data = [];

// Generate all months in the range to ensure no gaps in the chart
$period = new DatePeriod(
     new DateTime($chart_start_date),
     new DateInterval('P1M'),
     (new DateTime($end_date))->modify('+1 month') // Include the end month
);

$revenue_map = [];
while ($row = $monthly_result->fetch_assoc()) {
    $revenue_map[$row['month']] = $row;
}

foreach ($period as $date) {
    $month_key = $date->format('Y-m');
    $chart_labels[] = $date->format('M Y'); // Format like 'May 2025'

    if (isset($revenue_map[$month_key])) {
        $chart_paid_data[] = (float) $revenue_map[$month_key]['paid_amount'];
        $chart_pending_data[] = (float) $revenue_map[$month_key]['pending_amount'];
    } else {
        $chart_paid_data[] = 0;
        $chart_pending_data[] = 0;
    }
}

$stmt_monthly->close();
$database->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Report - <?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .report-container {
            max-width: 800px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #fff;
        }
        h1, h2 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
        }
        h1 {
            font-size: 1.8em;
        }
        h2 {
            font-size: 1.5em;
            margin-top: 30px;
        }
        .date-range {
            text-align: center;
            font-size: 1.1em;
            color: #555;
            margin-bottom: 30px;
        }
        table.summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        table.summary-table th, table.summary-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        table.summary-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        table.summary-table td:last-child {
            text-align: right;
            font-weight: bold;
        }
        .chart-container {
            width: 100%;
            max-width: 750px; /* Limit chart width */
            margin: 20px auto; /* Center chart */
            position: relative; /* Needed for Chart.js responsiveness */
            height: 40vh; /* Responsive height */
            min-height: 300px; /* Minimum height */
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
                margin: 0; /* Remove body margin for printing */
                font-size: 10pt; /* Adjust font size for print */
            }
            .report-container {
                border: none; /* Remove border for print */
                box-shadow: none; /* Remove shadow for print */
                max-width: 100%; /* Use full width */
                padding: 0;
            }
            h1, h2 {
                margin-top: 20px;
                margin-bottom: 15px;
            }
            .date-range {
                 margin-bottom: 20px;
            }
            table.summary-table th, table.summary-table td {
                 padding: 6px; /* Reduce padding for print */
            }
            .chart-container {
                height: 350px; /* Fixed height for print consistency */
                width: 95%; /* Adjust width slightly */
                page-break-inside: avoid; /* Try to keep chart on one page */
            }
            footer {
                margin-top: 30px;
                padding-top: 10px;
                font-size: 8pt;
            }
            /* Hide elements not needed for print (add specific classes/IDs if necessary) */
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <h1>Billing Report</h1>
        <p class="date-range">
            <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?>
        </p>

        <h2>Summary</h2>
        <table class="summary-table">
            <tr>
                <th>Metric</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>Total Invoices Generated</td>
                <td><?php echo number_format($billing_summary['total_invoices']); ?></td>
            </tr>
            <tr>
                <td>Total Amount Invoiced</td>
                <td>Ksh <?php echo number_format($billing_summary['total_amount'] ?? 0, 2); ?></td>
            </tr>
            <tr>
                <td>Total Amount Paid</td>
                <td>Ksh <?php echo number_format($billing_summary['total_paid'] ?? 0, 2); ?></td>
            </tr>
            <tr>
                <td>Total Pending Payments</td>
                <td>Ksh <?php echo number_format($billing_summary['total_pending'] ?? 0, 2); ?></td>
            </tr>
            <tr>
                <td>Total Overdue Payments</td>
                <td>Ksh <?php echo number_format($billing_summary['total_overdue'] ?? 0, 2); ?></td>
            </tr>
        </table>

        <h2>Revenue Trend (Last 12 Months)</h2>
        <div class="chart-container">
            <canvas id="revenueChart"></canvas>
        </div>

        <footer>
            Generated on <?php echo date('F j, Y \a\t H:i:s'); ?>
        </footer>

    </div> <div style="text-align: center; margin-top: 20px;" class="no-print">
         <button onclick="window.print();">Print/Save Report</button>
    </div>

    <script>
        // --- Chart.js Configuration ---
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'bar', // Bar chart
            data: {
                labels: <?php echo json_encode($chart_labels); ?>, // Month labels from PHP
                datasets: [
                    {
                        label: 'Paid Amount (Ksh)',
                        data: <?php echo json_encode($chart_paid_data); ?>, // Paid data from PHP
                        backgroundColor: 'rgba(40, 167, 69, 0.7)', // Green with transparency
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Pending Amount (Ksh)',
                        data: <?php echo json_encode($chart_pending_data); ?>, // Pending data from PHP
                        backgroundColor: 'rgba(255, 193, 7, 0.7)', // Yellow with transparency
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true, // Make chart responsive
                maintainAspectRatio: false, // Allow chart to fill container height
                scales: {
                    y: {
                        beginAtZero: true, // Start Y-axis at 0
                        ticks: {
                            // Format Y-axis ticks as currency
                            callback: function(value, index, values) {
                                return 'Ksh ' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                         // Stack the bars for paid and pending within each month
                         stacked: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top', // Position the legend at the top
                    },
                    tooltip: {
                        callbacks: {
                            // Format tooltip values as currency
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += 'Ksh ' + context.parsed.y.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // --- Trigger Print Dialog on Load ---
        window.onload = function() {
            // Add a small delay to ensure the chart renders fully before printing
            setTimeout(function() {
                window.print();
            }, 500); // Delay of 500 milliseconds (adjust if needed)
        };
    </script>

</body>
</html>
