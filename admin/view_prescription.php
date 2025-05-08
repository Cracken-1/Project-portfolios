<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
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
    <title>View Prescription #<?php echo isset($prescription_details['prescription_id']) ? htmlspecialchars($prescription_details['prescription_id']) : 'N/A'; ?></title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7f6;
            color: #333;
            line-height: 1.6;
        }
        .main-content {
            padding: 30px;
            margin-left: 250px; /* Adjust based on your sidebar width */
            transition: margin-left 0.3s ease;
        }
        .view-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin-bottom: 30px;
        }
        .view-container h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .prescription-details p {
            margin-bottom: 15px;
            font-size: 1rem;
            color: #555;
        }
        .prescription-details p strong {
            color: #333;
            font-weight: 600;
            display: inline-block;
            width: 150px; /* Align labels */
        }
        .medications-list {
            margin-top: 25px;
            border-top: 1px solid #e0e0e0;
            padding-top: 20px;
        }
        .medications-list h3 {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .medication-item {
            background: #ecf0f1;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .medication-item p {
            margin: 5px 0;
            font-size: 0.95rem;
        }
        .medication-item p strong {
             font-weight: 600;
             color: #34495e;
        }
        .back-button {
            display: inline-block;
            margin-top: 30px;
            padding: 10px 20px;
            background: #95a5a6;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 1rem;
            transition: background-color 0.2s ease;
        }
        .back-button:hover {
            background: #7f8c8d;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .view-container h1 {
                font-size: 1.8rem;
            }
            .prescription-details p strong {
                width: auto;
                display: block;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <?php include("../includes/header.php"); // Make sure this path is correct ?>
    <?php include("../includes/sidebar.php"); // Make sure this path is correct ?>

    <div class="main-content">
        <div class="view-container">
            <?php if ($prescription_details): ?>
                <h1><i class="fas fa-file-medical-alt"></i> Prescription Details #<?php echo htmlspecialchars($prescription_details['prescription_id']); ?></h1>

                <div class="prescription-details">
                    <p><strong>Prescription Date:</strong> <?php echo date('M j, Y', strtotime($prescription_details['prescription_date'])); ?></p>
                    <p><strong>Patient Name:</strong> <?php echo htmlspecialchars($prescription_details['pname']); ?></p>
                    <p><strong>Doctor Name:</strong> Dr. <?php echo htmlspecialchars($prescription_details['docname']); ?></p>
                    <p><strong>Appointment Date:</strong> <?php echo date('M j, Y', strtotime($prescription_details['appodate'])); ?></p>
                    <p><strong>Appointment Time:</strong> <?php echo date('h:i A', strtotime($prescription_details['appotime'])); ?></p>
                    <p><strong>Medications:</strong> <?php echo nl2br(htmlspecialchars($prescription_details['medication'])); ?></p>
                </div>

                <div class="medications-list">
                    <h3>Medications</h3>
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

            <?php else: ?>
                <h1><i class="fas fa-exclamation-circle"></i> Prescription Not Found</h1>
                <p>The requested prescription could not be found.</p>
            <?php endif; ?>

            <a href="prescriptions.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

        </div>
    </div>

    <?php include("../includes/footer.php"); // Make sure this path is correct ?>
</body>
</html>
