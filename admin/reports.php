<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Handle PDF export request
if (isset($_POST['export_pdf'])) {
    // Fetch patient data
    $patients = $database->query("SELECT * FROM patient");
    $patient_count = $patients->num_rows;
    
    // Generate HTML for PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Meridian Medical Center - Patient Report</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                color: #333;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #4CAF50;
                padding-bottom: 15px;
            }
            .hospital-title {
                color: #4CAF50;
                font-size: 22px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .report-title {
                font-size: 18px;
                margin-bottom: 10px;
            }
            .report-date {
                font-size: 14px;
                color: #666;
                margin-bottom: 15px;
            }
            .stat-card {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                text-align: center;
            }
            .stat-value {
                font-size: 24px;
                font-weight: bold;
                color: #2c3e50;
            }
            .stat-label {
                color: #7f8c8d;
                font-size: 14px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            th {
                background-color: #4CAF50;
                color: white;
                text-align: left;
                padding: 8px;
            }
            td {
                padding: 8px;
                border-bottom: 1px solid #ddd;
            }
            tr:nth-child(even) {
                background-color: #f2f2f2;
            }
            .footer {
                text-align: center;
                margin-top: 30px;
                font-size: 12px;
                color: #777;
                border-top: 1px solid #eee;
                padding-top: 10px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="hospital-title">Meridian Medical Center</div>
            <div class="report-title">Patient Report</div>
            <div class="report-date">Generated on: ' . date('F j, Y, H:i:s') . '</div>
        </div>

        <div class="stat-card">
            <div class="stat-value">' . $patient_count . '</div>
            <div class="stat-label">Total Patients Registered</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Patient ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Registration Date</th>
                </tr>
            </thead>
            <tbody>';
    
    while ($row = $patients->fetch_assoc()) {
        $regDate = isset($row['registered_date']) ? date('M j, Y', strtotime($row['registered_date'])) : 'N/A';
        $html .= '
                <tr>
                    <td>' . $row['pid'] . '</td>
                    <td>' . $row['pname'] . '</td>
                    <td>' . $row['pemail'] . '</td>
                    <td>' . $row['pphoneno'] . '</td>
                    <td>' . $regDate . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>

        <div class="footer">
            Confidential - For internal use only<br>
            Meridian Medical Center &copy; ' . date('Y') . '
        </div>
    </body>
    </html>';

    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Meridian_Patient_Report_' . date('Y-m-d') . '.pdf"');
    
    // Use a command-line tool like wkhtmltopdf to convert HTML to PDF
    $tmpHtml = tempnam(sys_get_temp_dir(), 'pres');
    file_put_contents($tmpHtml, $html);
    $tmpPdf = tempnam(sys_get_temp_dir(), 'pres');
    
    // Path to wkhtmltopdf executable - adjust this to your server's configuration
    $wkhtmltopdfPath = '"C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe"'; 
    
    $command = "$wkhtmltopdfPath --quiet --print-media-type $tmpHtml $tmpPdf";
    system($command, $return);
    
    if ($return === 0 && file_exists($tmpPdf)) {
        readfile($tmpPdf);
        unlink($tmpHtml);
        unlink($tmpPdf);
        exit();
    } else {
        // Fallback: Offer HTML download if PDF generation fails
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="Meridian_Patient_Report_' . date('Y-m-d') . '.html"');
        echo $html;
        exit();
    }
}

// Fetch data for reports
$today = date('Y-m-d');
$appointments = $database->query("
    SELECT a.appoid, p.pname, d.docname, a.appodate, a.appotime 
    FROM appointment a
    JOIN patient p ON a.pid = p.pid
    JOIN doctor d ON a.docid = d.docid
    ORDER BY a.appodate DESC
");

$patients = $database->query("SELECT * FROM patient");
$patient_count = $patients->num_rows; // Get total patient count

$doctors = $database->query("SELECT * FROM doctor");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .report-section {
            margin-bottom: 30px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .export-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .export-btn:hover {
            background: #45a049;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        .report-filter {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .report-filter label {
            margin-right: 10px;
            font-weight: bold;
        }
        .report-filter input,
        .report-filter select {
            padding: 8px;
            margin-right: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .report-filter button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .report-filter button:hover {
            background: #45a049;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        .report-table th {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            text-align: left;
        }
        .report-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .report-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <h1>Reports</h1>

        <!-- Filter Form -->
        <form method="GET" class="report-filter">
            <h2>Filter Reports</h2>
            <label for="start_date">Start Date:</label>
            <input type="date" id="start_date" name="start_date">
            <label for="end_date">End Date:</label>
            <input type="date" id="end_date" name="end_date">
            <label for="patient">Patient:</label>
            <select id="patient" name="patient">
                <option value="">All Patients</option>
                <?php
                while ($row = $patients->fetch_assoc()) {
                    echo "<option value='{$row['pid']}'>{$row['pname']}</option>";
                }
                ?>
            </select>
            <label for="doctor">Doctor:</label>
            <select id="doctor" name="doctor">
                <option value="">All Doctors</option>
                <?php
                while ($row = $doctors->fetch_assoc()) {
                    echo "<option value='{$row['docid']}'>{$row['docname']}</option>";
                }
                ?>
            </select>
            <button type="submit">Filter</button>
        </form>

        <!-- Patient Count Report -->
        <div class="report-section">
            <div class="report-header">
                <h2>Patient Statistics</h2>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="export_pdf" class="export-btn">Export to PDF</button>
                </form>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?= $patient_count ?></div>
                <div class="stat-label">Total Patients Registered</div>
            </div>
            
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Registration Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $patients->data_seek(0); // Reset pointer to beginning
                    if ($patients->num_rows > 0) {
                        while ($row = $patients->fetch_assoc()) {
                            $regDate = isset($row['registered_date']) ? date('M j, Y', strtotime($row['registered_date'])) : 'N/A';
                            echo "<tr>
                                <td>{$row['pid']}</td>
                                <td>{$row['pname']}</td>
                                <td>{$row['pemail']}</td>
                                <td>{$row['pphoneno']}</td>
                                <td>{$regDate}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>No patients found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Appointment Report -->
        <div class="report-section">
            <div class="report-header">
                <h2>Appointment History</h2>
                <form method="POST" action="export.php" style="display: inline;">
                    <button type="submit" name="export_appointments" class="export-btn">Export to CSV</button>
                </form>
            </div>
            
            <table class="report-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($appointments->num_rows > 0) {
                        while ($row = $appointments->fetch_assoc()) {
                            echo "<tr>
                                <td>{$row['appoid']}</td>
                                <td>{$row['pname']}</td>
                                <td>{$row['docname']}</td>
                                <td>" . date('M j, Y', strtotime($row['appodate'])) . "</td>
                                <td>{$row['appotime']}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>No appointments found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>