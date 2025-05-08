<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");
$docid = $_SESSION['userid'];

// Check if patient ID is provided
if (!isset($_GET['id'])) {
    header("location: patients.php");
    exit();
}

$patient_id = $_GET['id'];

// Fetch patient details
$sql = "SELECT p.*, 
               (SELECT COUNT(*) FROM appointment WHERE pid = p.pid AND docid = ?) as total_visits,
               (SELECT MAX(appodate) FROM appointment WHERE pid = p.pid AND docid = ?) as last_visit
        FROM patient p
        WHERE p.pid = ?";
$stmt = $database->prepare($sql);
$stmt->bind_param("iii", $docid, $docid, $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    $_SESSION['error'] = "Patient not found";
    header("location: patients.php");
    exit();
}

// Fetch patient's medical history
$history_sql = "SELECT * FROM medical_history WHERE pid = ? ORDER BY record_date DESC";
$history_stmt = $database->prepare($history_sql);
$history_stmt->bind_param("i", $patient_id);
$history_stmt->execute();
$medical_history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch recent appointments
$appointments_sql = "SELECT a.*, d.docname 
                     FROM appointment a
                     JOIN doctor d ON a.docid = d.docid
                     WHERE a.pid = ? AND a.docid = ?
                     ORDER BY a.appodate DESC LIMIT 5";
$appointments_stmt = $database->prepare($appointments_sql);
$appointments_stmt->bind_param("ii", $patient_id, $docid);
$appointments_stmt->execute();
$appointments = $appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include("../includes/header.php");
include("../includes/sidebar.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Profile - <?= htmlspecialchars($patient['pname']) ?></title>
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .patient-profile {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        .profile-sidebar {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
        }
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #4e73df;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            margin: 0 auto 20px;
        }
        .patient-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #4e73df;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 13px;
            color: #6c757d;
        }
        .section-title {
            font-size: 18px;
            margin: 25px 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e6ed;
        }
        .history-item {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .history-date {
            font-size: 13px;
            color: #6c757d;
        }
        @media (max-width: 992px) {
            .patient-profile {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="section-header">
            <h1><i class="fas fa-user-injured"></i> Patient Profile</h1>
            <a href="patients.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>

        <div class="patient-profile">
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <?= strtoupper(substr($patient['pname'], 0, 1)) ?>
                </div>
                <h2 class="text-center"><?= htmlspecialchars($patient['pname']) ?></h2>
                <p class="text-center text-muted">Patient since <?= date('M Y', strtotime($patient['created_at'])) ?></p>
                
                <div class="patient-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?= $patient['total_visits'] ?></div>
                        <div class="stat-label">Visits</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?= $patient['last_visit'] ? date('M j', strtotime($patient['last_visit'])) : 'N/A' ?>
                        </div>
                        <div class="stat-label">Last Visit</div>
                    </div>
                </div>

                <div class="patient-info">
                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['pemail']) ?></p>
                    <p><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['pphoneno']) ?></p>
                    <p><i class="fas fa-birthday-cake"></i> <?= date('F j, Y', strtotime($patient['pdob'])) ?></p>
                    <p><i class="fas fa-venus-mars"></i> <?= $patient['pgender'] ?? 'Not specified' ?></p>
                    <p><i class="fas fa-tint"></i> <?= $patient['pbloodgroup'] ?? 'Not specified' ?></p>
                </div>
            </div>

            <div class="profile-content">
                <h3 class="section-title"><i class="fas fa-history"></i> Medical History</h3>
                
                <?php if (!empty($medical_history)): ?>
                    <?php foreach ($medical_history as $record): ?>
                        <div class="history-item">
                            <h4><?= htmlspecialchars($record['condition_name']) ?></h4>
                            <p class="history-date">
                                <?= date('F j, Y', strtotime($record['record_date'])) ?> | 
                                <?= $record['chronic'] ? 'Chronic Condition' : 'Temporary Condition' ?>
                            </p>
                            <p><?= htmlspecialchars($record['notes']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">No medical history recorded</div>
                <?php endif; ?>

                <h3 class="section-title"><i class="fas fa-calendar-alt"></i> Recent Appointments</h3>
                
                <?php if (!empty($appointments)): ?>
                    <div class="appointments-list">
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="history-item">
                                <h4>Appointment on <?= date('F j, Y', strtotime($appointment['appodate'])) ?></h4>
                                <p><strong>Time:</strong> <?= date('g:i a', strtotime($appointment['appotime'])) ?></p>
                                <p><strong>Status:</strong> <?= ucfirst($appointment['status']) ?></p>
                                <?php if (!empty($appointment['appodesc'])): ?>
                                    <p><strong>Notes:</strong> <?= htmlspecialchars($appointment['appodesc']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No appointment history</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>