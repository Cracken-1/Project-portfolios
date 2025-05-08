<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");
$docid = $_SESSION['userid'];
$patient_id = $_GET['id'] ?? 0;

// Fetch patient details
$stmt = $database->prepare("
    SELECT * FROM patient 
    WHERE pid = ? AND pid IN (
        SELECT DISTINCT pid FROM appointment WHERE docid = ?
    )
");
$stmt->bind_param("ii", $patient_id, $docid);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    header("location: patients.php?error=Patient not found");
    exit();
}

// Fetch patient appointments
$appointments = [];
$stmt = $database->prepare("
    SELECT * FROM appointment 
    WHERE pid = ? AND docid = ?
    ORDER BY appodate DESC
");
$stmt->bind_param("ii", $patient_id, $docid);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}
$stmt->close();

// Fetch prescriptions
$prescriptions = [];
$stmt = $database->prepare("
    SELECT p.* FROM prescription p
    JOIN appointment a ON p.appoid = a.appoid
    WHERE a.pid = ? AND a.docid = ?
    ORDER BY p.prescription_date DESC
");
$stmt->bind_param("ii", $patient_id, $docid);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $prescriptions[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Report</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="report-header">
            <h1>
                <i class="fas fa-user"></i> 
                <?php echo htmlspecialchars($patient['pname']); ?> - Patient Report
            </h1>
            <a href="patients.php" class="btn-view-all">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>

        <div class="patient-report">
            <!-- Patient Information -->
            <section class="report-section">
                <h2><i class="fas fa-info-circle"></i> Patient Information</h2>
                <div class="patient-info-grid">
                    <div>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['pname']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['pemail']); ?></p>
                    </div>
                    <div>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['pphone']); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo $patient['pdob']; ?></p>
                    </div>
                </div>
            </section>

            <!-- Appointments -->
            <section class="report-section">
                <h2><i class="fas fa-calendar-check"></i> Appointments (<?php echo count($appointments); ?>)</h2>
                <?php if (!empty($appointments)): ?>
                    <div class="appointments-list">
                        <?php foreach ($appointments as $appt): ?>
                            <div class="appointment-item">
                                <p><strong><?php echo $appt['appodate']; ?> at <?php echo $appt['appotime']; ?></strong></p>
                                <p>Status: <span class="status-badge <?php echo strtolower($appt['status']); ?>">
                                    <?php echo $appt['status']; ?>
                                </span></p>
                                <p>Reason: <?php echo htmlspecialchars($appt['apporeason'] ?? 'N/A'); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No appointments found.</p>
                <?php endif; ?>
            </section>

            <!-- Prescriptions -->
            <section class="report-section">
                <h2><i class="fas fa-prescription"></i> Prescriptions (<?php echo count($prescriptions); ?>)</h2>
                <?php if (!empty($prescriptions)): ?>
                    <div class="prescriptions-list">
                        <?php foreach ($prescriptions as $rx): ?>
                            <div class="prescription-item">
                                <p><strong>Date:</strong> <?php echo $rx['prescription_date']; ?></p>
                                <?php 
                                $meds = json_decode($rx['medication'], true);
                                foreach ($meds as $med): ?>
                                    <p><?php echo htmlspecialchars($med['name']); ?> - 
                                    <?php echo htmlspecialchars($med['dosage']); ?></p>
                                <?php endforeach; ?>
                                <a href="view_prescription.php?id=<?php echo $rx['prescription_id']; ?>" class="btn-view">
                                    View Details
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No prescriptions found.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>