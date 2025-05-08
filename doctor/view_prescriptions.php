<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

require_once("../db/db.php");
$docid = $_SESSION['userid'];

// Get prescription ID from URL
if (!isset($_GET['id'])) {
    header("location: prescription.php");
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
    header("location: prescription.php");
    exit();
}

$prescription = $result->fetch_assoc();

include("../includes/header.php");
include("../includes/sidebar.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Prescription - Doctor Panel</title>
    <link rel="stylesheet" href="../css/prescription.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="view-prescription-main">
        <div class="view-prescription-container">
            <div class="view-prescription-header">
                <h1><i class="fas fa-prescription-bottle-alt"></i> Prescription Details</h1>
                <div class="view-prescription-actions">
                    <a href="print_prescription.php?id=<?= $prescription['prescription_id'] ?>" class="view-btn-print" target="_blank">
                        <i class="fas fa-print"></i> Print
                    </a>
                    <a href="prescription.php" class="view-btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Prescriptions
                    </a>
                </div>
            </div>

            <div class="view-prescription-card">
                <div class="view-prescription-meta">
                    <div class="view-prescription-date">
                        <strong>Prescription Date:</strong>
                        <?= date('F j, Y', strtotime($prescription['prescription_date'])) ?>
                    </div>
                    <div class="view-prescription-id">
                        <strong>Prescription ID:</strong>
                        #<?= $prescription['prescription_id'] ?>
                    </div>
                </div>

                <div class="view-prescription-patient">
                    <h2><i class="fas fa-user-injured"></i> Patient Information</h2>
                    <div class="view-patient-details">
                        <div class="view-patient-name">
                            <strong>Name:</strong> <?= htmlspecialchars($prescription['pname']) ?>
                        </div>
                        <div class="view-patient-age">
                            <strong>Age:</strong> <?= $prescription['page'] ?? 'N/A' ?> years
                        </div>
                        <div class="view-patient-gender">
                            <strong>Gender:</strong> <?= isset($prescription['pgender']) ? ucfirst($prescription['pgender']) : 'N/A' ?>
                        </div>
                    </div>
                </div>

                <div class="view-prescription-doctor">
                    <h2><i class="fas fa-user-md"></i> Prescribing Physician</h2>
                    <div class="view-doctor-details">
                        <div class="view-doctor-name">
                            <strong>Name:</strong> Dr. <?= htmlspecialchars($prescription['docname']) ?>
                        </div>
                        <div class="view-doctor-specialization">
                            <strong>Specialization:</strong> <?= htmlspecialchars($prescription['specialization']) ?>
                        </div>
                    </div>
                </div>

                <div class="view-prescription-appointment">
                    <h2><i class="fas fa-calendar-check"></i> Appointment Details</h2>
                    <div class="view-appointment-details">
                        <div class="view-appointment-date">
                            <strong>Date:</strong> <?= date('F j, Y', strtotime($prescription['appodate'])) ?>
                        </div>
                        <div class="view-appointment-time">
                            <strong>Time:</strong> <?= date('h:i A', strtotime($prescription['appotime'])) ?>
                        </div>
                        <div class="view-appointment-reason">
                            <strong>Reason:</strong> <?= htmlspecialchars($prescription['reason']) ?>
                        </div>
                    </div>
                </div>

                <div class="view-prescription-medication">
                    <h2><i class="fas fa-pills"></i> Medication</h2>
                    <div class="view-medication-content">
                        <?= nl2br(htmlspecialchars($prescription['medication'])) ?>
                    </div>
                </div>

                <div class="view-prescription-instructions">
                    <h2><i class="fas fa-info-circle"></i> Instructions</h2>
                    <div class="view-instructions-content">
                        <?= nl2br(htmlspecialchars($prescription['instructions'])) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>