<?php
session_start();

// Check if user is logged in and is an admin
// Or you might allow doctors/patients to print their own prescriptions - adjust as needed
if (!isset($_SESSION["user"])) {
    header("location: ../login/login.php"); // Redirect to a general login if not admin
    exit();
}

// Include database connection
include("../db/db.php"); // Make sure this path is correct

$prescription_details = null;
$medications = [];

// Check if prescription ID is provided in the URL
if (isset($_GET['id'])) {
    // Sanitize the input ID
    $prescription_id = $database->real_escape_string($_GET['id']);

    // Fetch prescription details
    $query = "
        SELECT p.*, d.docname, pt.pname, a.appodate, a.appotime
        FROM prescription p
        JOIN appointment a ON p.appoid = a.appoid
        JOIN doctor d ON a.docid = d.docid
        JOIN patient pt ON a.pid = pt.pid
        WHERE p.prescription_id = '$prescription_id'
        LIMIT 1
    ";

    $result = $database->query($query);

    if ($result && $result->num_rows > 0) {
        $prescription_details = $result->fetch_assoc();
        // Decode the JSON medication data
        $medications = json_decode($prescription_details['medication'], true);
        if ($medications === null) {
            $medications = []; // Handle JSON decode errors
        }
    }
}

// Close the database connection
$database->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Prescription #<?php echo isset($prescription_details['prescription_id']) ? htmlspecialchars($prescription_details['prescription_id']) : 'N/A'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #fff; /* White background for printing */
        }
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
            border: 1px solid #ccc;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            background-color: #fff;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 2rem;
            color: #2c3e50;
        }
        .header p {
            margin: 5px 0 0;
            font-size: 1rem;
            color: #555;
        }
        .patient-info, .doctor-info, .prescription-info {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #ccc;
        }
        .patient-info h2, .doctor-info h2, .prescription-info h2 {
             font-size: 1.3rem;
             color: #34495e;
             margin-bottom: 10px;
        }
         .patient-info p, .doctor-info p, .prescription-info p {
            margin: 5px 0;
            font-size: 0.95rem;
         }
         .patient-info p strong, .doctor-info p strong, .prescription-info p strong {
             display: inline-block;
             width: 120px; /* Align labels */
             font-weight: 600;
         }
        .medications {
            margin-top: 30px;
        }
        .medications h2 {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 15px;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 10px;
        }
        .medication-item {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
        }
        .medication-item p {
            margin: 5px 0;
        }
         .medication-item p strong {
             font-weight: 600;
             color: #34495e;
             display: inline-block;
             width: 100px; /* Align labels */
         }
        .notes {
            margin-top: 30px;
            border-top: 1px dashed #ccc;
            padding-top: 20px;
        }
        .notes h2 {
            font-size: 1.3rem;
            color: #34495e;
            margin-bottom: 10px;
        }
        .notes p {
            font-style: italic;
            color: #555;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 0.9rem;
            color: #777;
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }

        /* Print specific styles */
        @media print {
            body {
                padding: 0;
                margin: 0;
            }
            .print-container {
                box-shadow: none;
                border: none;
                padding: 0;
            }
            .back-button {
                display: none; /* Hide back button when printing */
            }
        }

        /* Screen specific styles */
        @media screen {
            .print-button {
                display: block;
                margin: 20px auto;
                padding: 10px 20px;
                background: #3498db;
                color: white;
                border-radius: 6px;
                text-decoration: none;
                font-size: 1rem;
                text-align: center;
                cursor: pointer;
                transition: background-color 0.2s ease;
            }
            .print-button:hover {
                background: #2980b9;
            }
        }
    </style>
</head>
<body>
    <?php if ($prescription_details): ?>
        <div class="print-container">
            <div class="header">
                <h1>Meridian Hospital Prescription Report</h1>
                <p>Prescription ID: #<?php echo htmlspecialchars($prescription_details['prescription_id']); ?></p>
                <p>Date: <?php echo date('M j, Y', strtotime($prescription_details['prescription_date'])); ?></p>
            </div>

            <div class="patient-info">
                <h2>Patient Information</h2>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($prescription_details['pname']); ?></p>
                <p><strong>Date of Birth:</strong> <?php echo isset($prescription_details['dob']) ? date('M j, Y', strtotime($prescription_details['dob'])) : 'N/A'; ?></p>
                 <p><strong>Gender:</strong> <?php echo isset($prescription_details['pgender']) ? htmlspecialchars($prescription_details['gender']) : 'N/A'; ?></p>
            </div><p><strong>Contact:</strong> <?php echo isset($prescription_details['pphoneno']) ? htmlspecialchars($prescription_details['contact']) : 'N/A'; ?></p>

            <div class="doctor-info">
                <h2>Doctor Information</h2>
                <p><strong>Doctor Name:</strong> Dr. <?php echo htmlspecialchars($prescription_details['docname']); ?></p>
            </div>

             <div class="prescription-info">
                <h2>Appointment Details</h2>
                <p><strong>Appointment Date:</strong> <?php echo date('M j, Y', strtotime($prescription_details['appodate'])); ?></p>
                <p><strong>Appointment Time:</strong> <?php echo date('h:i A', strtotime($prescription_details['appotime'])); ?></p>
            </div>


            <div class="medications">
                <h2>Medications</h2>
                <?php if (!empty($medications)): ?>
                    <?php foreach ($medications as $med): ?>
                        <div class="medication-item">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($med['name']); ?></p>
                            <p><strong>Dosage:</strong> <?php echo htmlspecialchars($med['dosage']); ?></p>
                            <p><strong>Frequency:</strong> <?php echo htmlspecialchars($med['frequency']); ?></p>
                            <p><strong>Duration:</strong> <?php echo htmlspecialchars($med['duration']); ?></p>
                            <p><strong>Quantity:</strong> <?php echo htmlspecialchars($med['quantity']); ?></p>
                            <p><strong>Instructions:</strong> <?php echo nl2br(htmlspecialchars($med['instructions'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No medications listed for this prescription.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($prescription_details['notes'])): ?>
                <div class="notes">
                    <h2>Notes</h2>
                    <p><?php echo nl2br(htmlspecialchars($prescription_details['notes'])); ?></p>
                </div>
            <?php endif; ?>

            <div class="footer">
                <p>Generated on <?php echo date('M j, Y h:i A'); ?></p>
                <p>&copy; Meridian Hospital</p>
            </div>
        </div>

         <button class="print-button" onclick="window.print()"><i class="fas fa-print"></i> Print Prescription</button>


    <?php else: ?>
        <div class="print-container">
             <div class="header">
                <h1><i class="fas fa-exclamation-circle"></i> Prescription Not Found</h1>
             </div>
             <p style="text-align: center;">The requested prescription could not be found.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_SERVER['HTTP_REFERER'])): ?>
         <a href="<?php echo htmlspecialchars($_SERVER['HTTP_REFERER']); ?>" class="back-button" style="display: block; text-align: center;"><i class="fas fa-arrow-left"></i> Back</a>
    <?php endif; ?>

</body>
</html>
