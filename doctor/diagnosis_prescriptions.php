<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

require_once("../db/db.php");
$docid = $_SESSION['userid'];

// Get doctor details
$doctor = $database->query("SELECT * FROM doctor WHERE docid = '$docid'")->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appoid = $database->real_escape_string($_POST['appoid']);
    $medication = $database->real_escape_string($_POST['medication']);
    $instructions = $database->real_escape_string($_POST['instructions']);
    
    $insert_sql = "INSERT INTO prescription 
                  (appoid, prescription_date, medication, instructions) 
                  VALUES ('$appoid', NOW(), '$medication', '$instructions')";
    
    if ($database->query($insert_sql)) {
        $update_sql = "UPDATE appointment SET status = 'Completed' WHERE appoid = '$appoid' AND docid = '$docid'";
        $database->query($update_sql);
        $success = "Prescription created successfully!";
    } else {
        $error = "Error creating prescription: " . $database->error;
    }
}

$appointments = [];
$sql = "SELECT a.*, p.pname 
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        WHERE a.docid = '$docid'
        ORDER BY a.appodate DESC, a.appotime DESC";
$result = $database->query($sql);
if ($result) {
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
}
// Fetch recent prescriptions
$prescriptions = [];
$sql = "SELECT p.*, pt.pname, a.appodate, a.appotime
        FROM prescription p
        JOIN appointment a ON p.appoid = a.appoid
        JOIN patient pt ON a.pid = pt.pid
        WHERE a.docid = '$docid'
        ORDER BY p.prescription_date DESC
        LIMIT 10";
$result = $database->query($sql);
if ($result) {
    $prescriptions = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $error = "Error fetching prescriptions: " . $database->error;
}

include("../includes/header.php");
include("../includes/sidebar.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Panel - Diagnosis & Prescription</title>
    <link rel="stylesheet" href="../css/prescription.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="diagnosis-main-content">
        <div class="diagnosis-container">
            <?php if (isset($success)): ?>
                <div class="diagnosis-alert diagnosis-alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="diagnosis-alert diagnosis-alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="diagnosis-card">
    <h2><i class="fas fa-calendar-check"></i> Upcoming Appointments</h2>
    <?php if (empty($appointments)): ?>
        <p class="no-data">No upcoming appointments found.</p>
    <?php else: ?>
        <div class="appointments-list">
            <?php foreach ($appointments as $appt): ?>
                <div class="appointment-item">
                    <div class="patient-info">
                        <span class="patient-name"><?= htmlspecialchars($appt['pname'] ?? 'N/A') ?></span>
                        <span class="patient-details">
                            <?= isset($appt['page']) ? $appt['page'] . ' yrs' : 'Age not specified' ?>, 
                            <?= isset($appt['pgender']) ? ucfirst($appt['pgender']) : 'Gender not specified' ?>
                        </span>
                    </div>
                    <div class="appointment-time">
                        <i class="fas fa-calendar-day"></i> <?= date('M j, Y', strtotime($appt['appodate'])) ?>
                        <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($appt['appotime'])) ?>
                    </div>
                    <div class="appointment-reason">
                        <i class="fas fa-comment-medical"></i> <?= htmlspecialchars($appt['reason'] ?? 'No reason specified') ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>


            <div class="diagnosis-card">
                <h2><i class="fas fa-prescription-bottle-alt"></i> Create New Prescription</h2>
                <form method="POST" class="diagnosis-form">
                <div class="diagnosis-form-group">
                    <label for="appoid"><i class="fas fa-calendar-check"></i> Select Appointment:</label>
                    <select id="appoid" name="appoid" class="diagnosis-form-control" required>
                        <option value="">-- Select Appointment --</option>
                        <?php foreach ($appointments as $appt): ?>
                            <option value="<?= htmlspecialchars($appt['appoid']) ?>">
                                <?= htmlspecialchars($appt['pname'] ?? 'Patient') ?> - 
                                <?= date('M j, Y', strtotime($appt['appodate'])) ?> at 
                                <?= date('h:i A', strtotime($appt['appotime'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                    
                    <div class="diagnosis-form-row">
                        <div class="diagnosis-form-group">
                            <label for="medication"><i class="fas fa-pills"></i> Medication:</label>
                            <textarea id="medication" name="medication" class="diagnosis-form-control" rows="4" required></textarea>
                        </div>
                        <div class="diagnosis-form-group">
                            <label for="instructions"><i class="fas fa-info-circle"></i> Instructions:</label>
                            <textarea id="instructions" name="instructions" class="diagnosis-form-control" rows="4" required></textarea>
                        </div>
                    </div>
                    
                    <div class="diagnosis-form-actions">
                        <button type="submit" class="diagnosis-btn-submit">
                            <i class="fas fa-save"></i> Save Prescription
                        </button>
                        <button type="button" class="diagnosis-btn-reset" onclick="loadTemplate()">
                            <i class="fas fa-file-medical"></i> Load Template
                        </button>
                    </div>
                </form>
            </div>

            <div class="diagnosis-card">
                <h2><i class="fas fa-history"></i> Recent Prescriptions</h2>
                <?php if (empty($prescriptions)): ?>
                    <p class="no-data">No prescriptions found.</p>
                <?php else: ?>
                    <div class="diagnosis-table-responsive">
                        <table class="diagnosis-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Patient</th>
                                    <th>Appointment</th>
                                    <th>Medication</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($prescriptions as $rx): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($rx['prescription_date'])) ?></td>
                                    <td><?= htmlspecialchars($rx['pname']) ?></td>
                                    <td><?= date('M j', strtotime($rx['appodate'])) ?> at <?= date('h:i A', strtotime($rx['appotime'])) ?></td>
                                    <td class="medication-cell">
                                        <?= nl2br(htmlspecialchars(substr($rx['medication'], 0, 50))) ?>
                                        <?php if (strlen($rx['medication']) > 50): ?>...<?php endif; ?>
                                    </td>
                                    <td class="diagnosis-actions">
                                        <a href="view_prescriptions.php?id=<?= $rx['prescription_id'] ?>" class="diagnosis-btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="print_prescription.php?id=<?= $rx['prescription_id'] ?>" class="diagnosis-btn-print" target="_blank">
                                            <i class="fas fa-print"></i> Print
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>
    
    <script>
    function loadTemplate() {
        document.getElementById('medication').value = `Amoxicillin 500mg capsules\nIbuprofen 400mg tablets`;
        document.getElementById('instructions').value = `Take 1 capsule every 8 hours for 7 days with food\nTake 1 tablet every 6-8 hours as needed for pain`;
    }
    
    function viewPrescription(id) {
        // In a real implementation, this would show a modal with full prescription details
        alert('Viewing prescription ID: ' + id);
        // Alternatively: window.location.href = 'view_prescription.php?id=' + id;
    }
    
    document.getElementById('appoid').addEventListener('change', function() {
        if (this.value) {
            document.getElementById('medication').focus();
        }
    });
    </script>
</body>
</html>