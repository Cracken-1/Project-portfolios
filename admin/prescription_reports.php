<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

// Include database connection
include("../db/db.php"); // Make sure this path is correct

// --- Report Generation Logic ---
// This is a basic example. Real-world reports would involve:
// - Filtering by date range, doctor, patient, medication, etc.
// - Pagination for large datasets
// - More complex aggregations and summaries
// - Export options (CSV, PDF)

// Fetch all prescriptions for the report (limit for demo purposes)
$all_prescriptions = $database->query("
    SELECT p.*, d.docname, pt.pname, a.appodate
    FROM prescription p
    JOIN appointment a ON p.appoid = a.appoid
    JOIN doctor d ON a.docid = d.docid
    JOIN patient pt ON a.pid = pt.pid
    ORDER BY p.prescription_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Calculate summary statistics for the report
$report_stats = $database->query("
     SELECT
        COUNT(*) as total_prescriptions,
        COUNT(DISTINCT doctor_id) as doctors_prescribing,
        COUNT(DISTINCT patient_id) as patients_with_prescriptions,
        SUM(total_medicines) as total_medicines_prescribed
    FROM (
        SELECT
            p.appoid,
            d.docid as doctor_id,
            a.pid as patient_id,
            JSON_LENGTH(p.medication) as total_medicines
        FROM prescription p
        JOIN appointment a ON p.appoid = a.appoid
        JOIN doctor d ON a.docid = d.docid
        GROUP BY p.prescription_id
    ) as prescription_details
")->fetch_assoc();


// Close the database connection
$database->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Report</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Include jsPDF library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7f6;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        .report-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin: 20px auto;
            max-width: 1000px;
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 15px;
        }
        .report-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }
        .report-actions {
            display: flex;
            gap: 15px;
        }
        .action-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .print-button {
            background-color: #3498db;
            color: white;
        }
        .print-button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(41, 128, 185, 0.3);
        }
        .back-button {
            background-color: #95a5a6;
            color: white;
        }
        .back-button:hover {
            background-color: #7f8c8d;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(127, 140, 141, 0.3);
        }
        .report-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background-color: #ecf0f1;
            border-radius: 8px;
        }
        .summary-item h3 {
            margin: 0;
            font-size: 1.5rem;
            color: #34495e;
        }
        .summary-item p {
            margin: 5px 0 0;
            font-size: 0.9rem;
            color: #555;
        }
        .report-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .report-table th, .report-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .report-table th {
            background: #3498db;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .report-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .report-table tbody tr:hover {
            background-color: #f0f0f0;
        }
        .report-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .report-meta {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        /* Print specific styles */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            .report-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                border: none;
            }
            .report-actions {
                display: none;
            }
            .report-table th {
                background-color: #3498db !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                color: white !important;
            }
            .report-summary {
                background-color: #ecf0f1 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }
    </style>
</head>
<body>
<?php include("../includes/header.php"); ?>
<?php include("../includes/sidebar.php"); ?>
    <div class="report-container" id="reportContent">
        <div class="report-header">
            <h1 class="report-title"><i class="fas fa-file-alt"></i> Meridian Hospital - Prescription Report</h1>
            <div class="report-actions">
                <button class="action-button print-button" onclick="generatePDF()">
                    <i class="fas fa-file-pdf"></i> Print to PDF
                </button>
                <a href="prescriptions_inventory.php" class="action-button back-button">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="report-summary">
            <div class="summary-item">
                <h3><?php echo $report_stats['total_prescriptions']; ?></h3>
                <p>Total Prescriptions</p>
            </div>
            <div class="summary-item">
                <h3><?php echo $report_stats['doctors_prescribing']; ?></h3>
                <p>Doctors Prescribing</p>
            </div>
            <div class="summary-item">
                <h3><?php echo $report_stats['patients_with_prescriptions']; ?></h3>
                <p>Patients With Prescriptions</p>
            </div>
            <div class="summary-item">
                <h3><?php echo $report_stats['total_medicines_prescribed']; ?></h3>
                <p>Total Medicines Prescribed</p>
            </div>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th>Prescription #</th>
                    <th>Date</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Appointment Date</th>
                    <th>Medications Count</th>
                    <th>Notes Preview</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_prescriptions)): ?>
                    <?php foreach ($all_prescriptions as $rx): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($rx['prescription_id']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($rx['prescription_date'])); ?></td>
                            <td><?php echo htmlspecialchars($rx['pname']); ?></td>
                            <td>Dr. <?php echo htmlspecialchars($rx['docname']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($rx['appodate'])); ?></td>
                            <td>
                                <?php
                                $meds = json_decode($rx['medication'], true);
                                echo is_array($meds) ? count($meds) : 0;
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars(substr($rx['instructions'], 0, 50)) . (strlen($rx['instructions']) > 50 ? '...' : ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7">No prescription data found for this report.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="report-footer">
            <div class="report-meta">
                <p>Report generated on: <?php echo date('F j, Y \a\t g:i A'); ?></p>
            </div>
        </div>
    </div>

    <script>
        // Function to generate PDF
        function generatePDF() {
            const { jsPDF } = window.jspdf;
            const element = document.getElementById('reportContent');
            
            // Show loading indicator
            const printButton = document.querySelector('.print-button');
            const originalText = printButton.innerHTML;
            printButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
            printButton.disabled = true;
            
            // Use html2canvas to capture the report
            html2canvas(element, {
                scale: 2, // Higher quality
                logging: false,
                useCORS: true,
                allowTaint: true
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jsPDF('p', 'mm', 'a4');
                const imgWidth = 210; // A4 width in mm
                const pageHeight = 295; // A4 height in mm
                const imgHeight = canvas.height * imgWidth / canvas.width;
                let heightLeft = imgHeight;
                let position = 0;
                
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                
                // Add new pages if content is longer than one page
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                // Save the PDF
                pdf.save('Prescription_Report_<?php echo date('Y-m-d'); ?>.pdf');
                
                // Restore button state
                printButton.innerHTML = originalText;
                printButton.disabled = false;
            }).catch(error => {
                console.error('Error generating PDF:', error);
                alert('Error generating PDF. Please try again.');
                printButton.innerHTML = originalText;
                printButton.disabled = false;
            });
        }
        
        // Alternative simple print function
        function printReport() {
            window.print();
        }
    </script>
</body>
</html>