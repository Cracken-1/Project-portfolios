<?php
session_start();

// Check if user is logged in as doctor
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

require_once("../db/db.php");
$docid = $_SESSION['userid'];

// Get prescription ID from URL
if (!isset($_GET['id'])) {
    header("location: view_prescriptions.php");
    exit();
}

$prescription_id = $database->real_escape_string($_GET['id']);

// Fetch prescription details
$sql = "SELECT p.*, pt.pname, pt.page, pt.pgender, a.appodate, a.appotime, a.reason, 
               d.docname, d.specialization
        FROM prescription p
        JOIN appointment a ON p.appoid = a.appoid
        JOIN patient pt ON a.pid = pt.pid
        JOIN doctor d ON a.docid = d.docid
        WHERE p.prescription_id = '$prescription_id' AND a.docid = '$docid'";
        
$result = $database->query($sql);
if ($result->num_rows == 0) {
    header("location: view_prescriptions.php");
    exit();
}

$prescription = $result->fetch_assoc();

// Clean and prepare text content
function cleanText($text) {
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = str_replace(
        ['â€"', 'â€™', 'â€˜', 'â€"', 'â€"'], 
        ['-', "'", "'", '-', '-'], 
        $text
    );
    return $text;
}

$medication = cleanText($prescription['medication']);
$instructions = cleanText($prescription['instructions']);

// Check if we're generating the PDF
if (isset($_GET['download'])) {
    // Generate HTML for PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Meridian Hospital Prescription</title>
        <style>
            @page {
                margin: 1cm;
                size: A4;
            }
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                color: #333;
                line-height: 1.5;
            }
           
            .hospital-title {
                color: #4CAF50;
                font-size: 22px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .receipt-info {
                margin: 15px 0;
            }
            .receipt-row {
                display: flex;
                justify-content: center;
                margin-bottom: 5px;
            }
            .receipt-label {
                font-weight: bold;
                width: 120px;
                text-align: right;
                padding-right: 10px;
            }
            .receipt-value {
                width: 120px;
                text-align: left;
            }
            .section {
                margin-bottom: 20px;
                page-break-inside: avoid;
            }
            .section-title {
                color: #4CAF50;
                font-size: 16px;
                font-weight: bold;
                border-bottom: 1px solid #eee;
                padding-bottom: 5px;
                margin-bottom: 10px;
            }
            .info-row {
                display: flex;
                margin-bottom: 8px;
            }
            .info-label {
                font-weight: bold;
                width: 150px;
            }
            .info-value {
                flex: 1;
            }
            .prescription-content {
                background: #f9f9f9;
                padding: 12px;
                border-radius: 5px;
                white-space: pre-wrap;
                margin-top: 8px;
                line-height: 1.6;
            }
            .signature-area {
                margin-top: 40px;
                text-align: right;
                padding-top: 20px;
                border-top: 1px dashed #ccc;
            }
            .hospital-footer {
                text-align: center;
                margin-top: 30px;
                font-size: 12px;
                color: #666;
            }
            .no-print {
                display: none;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="hospital-title">Meridian Medical Center</div>
            <div class="hospital-subtitle">Patient Prescription Receipt</div>
            
            <div class="receipt-info">
                <div class="receipt-row">
                    <div class="receipt-label">Receipt Number:</div>
                    <div class="receipt-value">REC-' . $prescription['prescription_id'] . '</div>
                </div>
                <div class="receipt-row">
                    <div class="receipt-label">Date Issued:</div>
                    <div class="receipt-value">' . date('F j, Y', strtotime($prescription['prescription_date'])) . '</div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Patient Information</div>
            <div class="info-row">
                <div class="info-label">Name:</div>
                <div class="info-value">' . htmlspecialchars($prescription['pname']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Age/Gender:</div>
                <div class="info-value">' . $prescription['page'] . ' years / ' . ucfirst($prescription['pgender']) . '</div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Prescribing Physician</div>
            <div class="info-row">
                <div class="info-label">Name:</div>
                <div class="info-value">Dr. ' . htmlspecialchars($prescription['docname']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Specialization:</div>
                <div class="info-value">' . htmlspecialchars($prescription['specialization']) . '</div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Medication</div>
            <div class="prescription-content">' . (htmlspecialchars($prescription['medication'])) . '</div>
        </div>

        <div class="section">
            <div class="section-title">Instructions</div>
            <div class="prescription-content">' . (htmlspecialchars($prescription['instructions'])) . '</div>
        </div>

        <div class="signature-area">
            Doctor Signature: _________________________
            <div style="margin-top: 5px;">Stamp</div>
        </div>

        <div class="hospital-footer">
            <div>Meridian Hospital</div>
            <div>123 Healthcare Street, Medical City</div>
            <div>Phone: (123) 456-7890 | Email: info@meridianmedical.com</div>
        </div>
    </body>
    </html>';

    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Meridian_Hospital_Prescription_'.$prescription['prescription_id'].'.pdf"');
    
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
        header('Content-Disposition: attachment; filename="Meridian_Hospital_Prescription_'.$prescription['prescription_id'].'.html"');
        echo $html;
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Prescription - Doctor Panel</title>
    <link rel="stylesheet" href="../css/prescription.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .view-prescription-main {
            padding: 20px;
            background: #f5f5f5;
        }
        .print-prescription-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .print-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #4CAF50;
        }
        .print-title {
            color: #4CAF50;
            font-size: 24px;
            margin-bottom: 5px;
        }
        .print-subtitle {
            color: #555;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .receipt-info-row {
            display: flex;
            justify-content: center;
            margin-bottom: 8px;
        }
        .receipt-label {
            font-weight: bold;
            width: 120px;
            text-align: right;
            padding-right: 10px;
        }
        .receipt-value {
            width: 120px;
            text-align: left;
        }
        .print-section {
            margin-bottom: 25px;
        }
        .print-section-title {
            color: #4CAF50;
            font-size: 18px;
            font-weight: bold;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            width: 150px;
            color: #555;
        }
        .info-value {
            flex: 1;
        }
        .prescription-content {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            white-space: pre-wrap;
            line-height: 1.6;
        }
        .signature-area {
            margin-top: 50px;
            text-align: right;
            padding-top: 20px;
            border-top: 1px dashed #ccc;
        }
        .hospital-footer {
            text-align: center;
            margin-top: 40px;
            font-style: italic;
            color: #777;
            font-size: 14px;
        }
        .print-actions {
            margin-top: 30px;
            text-align: center;
        }
        .btn-print {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-print:hover {
            background: #45a049;
        }
        .btn-back {
            background: #f44336;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-back:hover {
            background: #d32f2f;
        }
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .view-prescription-main {
                padding: 0;
                background: white;
            }
            .print-prescription-container {
                box-shadow: none;
                border: none;
                padding: 15px;
                max-width: 100%;
            }
            .print-actions {
                display: none;
            }
            .prescription-content {
                background: transparent;
                padding: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="view-prescription-main">
        <div class="print-prescription-container">
            <div class="print-header">
            <img src="../img/logo.png" alt="Hospital Logo" style="height: 50px; display: block; margin: 0 auto 10px auto;">
                <div class="print-title">Meridian Medical Center</div>
                <div class="print-subtitle">Patient Prescription Receipt</div>
                
                <div class="receipt-info-row">
                    <div class="receipt-label">Receipt Number:</div>
                    <div class="receipt-value">REC-<?= $prescription['prescription_id'] ?></div>
                </div>
                <div class="receipt-info-row">
                    <div class="receipt-label">Date Issued:</div>
                    <div class="receipt-value"><?= date('F j, Y', strtotime($prescription['prescription_date'])) ?></div>
                </div>
            </div>

            <div class="print-section">
                <div class="print-section-title"><i class="fas fa-user-injured"></i> Patient Information</div>
                <div class="info-row">
                    <div class="info-label">Name:</div>
                    <div class="info-value"><?= htmlspecialchars($prescription['pname']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Age/Gender:</div>
                    <div class="info-value"><?= $prescription['page'] ?> years / <?= ucfirst($prescription['pgender']) ?></div>
                </div>
            </div>

            <div class="print-section">
                <div class="print-section-title"><i class="fas fa-user-md"></i> Prescribing Physician</div>
                <div class="info-row">
                    <div class="info-label">Name:</div>
                    <div class="info-value">Dr. <?= htmlspecialchars($prescription['docname']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Specialization:</div>
                    <div class="info-value"><?= htmlspecialchars($prescription['specialization']) ?></div>
                </div>
            </div>

            <div class="print-section">
                <div class="print-section-title"><i class="fas fa-pills"></i> Medication</div>
                <div class="prescription-content"><?= nl2br(htmlspecialchars($prescription['medication'])) ?></div>
            </div>

            <div class="print-section">
                <div class="print-section-title"><i class="fas fa-info-circle"></i> Instructions</div>
                <div class="prescription-content"><?= nl2br(htmlspecialchars($prescription['instructions'])) ?></div>
            </div>

            <div class="signature-area">
                Doctor Signature: _________________________
                <div style="margin-top: 5px;">Stamp</div>
            </div>

            <div class="hospital-footer">
                <div>Meridian Hospital</div>
                <div>123 Healthcare Street, Medical City</div>
                <div>Phone: (123) 456-7890 | Email: info@meridianmedical.com</div>
            </div>

            <div class="print-actions">
                <a href="print_prescription.php?id=<?= $prescription['prescription_id'] ?>&download=1" class="btn-print">
                    <i class="fas fa-download"></i> Download PDF
                </a>
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Print Now
                </button>
                <a href="view_prescriptions.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Prescriptions
                </a>
            </div>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>