<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");
$docid = $_SESSION['userid'];

if (!isset($_GET['id'])) {
    header("location: patients.php");
    exit();
}

$patient_id = $_GET['id'];

// Verify patient belongs to this doctor
$verify_sql = "SELECT p.pname, p.pphoneno, p.pdob FROM appointment a
               JOIN patient p ON a.pid = p.pid
               WHERE a.pid = ? AND a.docid = ? LIMIT 1";
$verify_stmt = $database->prepare($verify_sql);
$verify_stmt->bind_param("ii", $patient_id, $docid);
$verify_stmt->execute();
$patient = $verify_stmt->get_result()->fetch_assoc();

if (!$patient) {
    $_SESSION['error'] = "Patient not found in your records";
    header("location: patients.php");
    exit();
}

// Fetch appointments with additional details
$appointments_sql = "SELECT a.*, 
                    TIMESTAMPDIFF(YEAR, p.pdob, CURDATE()) as patient_age,
                    p.pname as patient_name
                    FROM appointment a
                    JOIN patient p ON a.pid = p.pid
                    WHERE a.pid = ? AND a.docid = ?
                    ORDER BY a.appodate DESC, a.appotime DESC";
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
    <title>Appointments - Dr. <?= $_SESSION['username'] ?></title>
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .appointments-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .appointment-card {
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 20px;
            padding: 20px;
            border-bottom: 1px solid #eee;
            align-items: start;
        }
        .appointment-date {
            text-align: center;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
        }
        .appointment-day {
            font-size: 28px;
            font-weight: 600;
            color: #4e73df;
            line-height: 1;
        }
        .appointment-month {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .appointment-details {
            flex: 1;
        }
        .appointment-details h3 {
            margin: 0 0 8px;
            color: #2c3e50;
        }
        .appointment-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 10px;
        }
        .appointment-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: #6c757d;
        }
        .appointment-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .status-scheduled {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .appointment-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 150px;
        }
        .btn-action {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-view {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .btn-view:hover {
            background-color: #bbdefb;
        }
        .btn-telemed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .btn-telemed:hover {
            background-color: #c8e6c9;
        }
        .btn-message {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        .btn-message:hover {
            background-color: #e1bee7;
        }
        .btn-edit {
            background-color: #fff8e1;
            color: #ff8f00;
        }
        .btn-edit:hover {
            background-color: #ffecb3;
        }
        .empty-appointments {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .empty-appointments i {
            font-size: 50px;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        @media (max-width: 768px) {
            .appointment-card {
                grid-template-columns: 1fr;
            }
            .appointment-date {
                display: flex;
                align-items: center;
                gap: 20px;
                text-align: left;
            }
            .appointment-day {
                font-size: 24px;
            }
            .appointment-actions {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="section-header">
            <h1><i class="fas fa-calendar-alt"></i> Patient Appointments</h1>
            <div>
                <a href="patients.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Patients
                </a>
                <a href="book_appointment.php?pid=<?= $patient_id ?>" class="btn-primary">
                    <i class="fas fa-plus"></i> New Appointment
                </a>
            </div>
        </div>

        <div class="patient-info-card">
            <div class="patient-avatar">
                <?= strtoupper(substr($patient['pname'], 0, 1)) ?>
            </div>
            <div class="patient-info">
                <h2><?= htmlspecialchars($patient['pname']) ?></h2>
                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['pphoneno']) ?></p>
                <p><i class="fas fa-birthday-cake"></i> <?= date('F j, Y', strtotime($patient['pdob'])) ?> 
                (<?= floor((time() - strtotime($patient['pdob'])) / 31556926) ?> years)</p>
            </div>
        </div>

        <div class="appointments-container">
            <?php if (!empty($appointments)): ?>
                <?php foreach ($appointments as $appointment): ?>
                    <div class="appointment-card">
                        <div class="appointment-date">
                            <div class="appointment-day">
                                <?= date('d', strtotime($appointment['appodate'])) ?>
                            </div>
                            <div class="appointment-month">
                                <?= date('M', strtotime($appointment['appodate'])) ?>
                            </div>
                        </div>
                        
                        <div class="appointment-details">
                            <span class="appointment-status status-<?= strtolower($appointment['status']) ?>">
                                <?= $appointment['status'] ?>
                            </span>
                            <h3><?= htmlspecialchars($appointment['reason'] ?? 'General Consultation') ?></h3>
                            
                            <div class="appointment-meta">
                                <div class="appointment-meta-item">
                                    <i class="fas fa-clock"></i>
                                    <?= date('g:i A', strtotime($appointment['appotime'])) ?>
                                </div>
                                <div class="appointment-meta-item">
                                    <i class="fas fa-user-md"></i>
                                    Dr. <?= $_SESSION['username'] ?>
                                </div>
                                <?php if ($appointment['diagnosis']): ?>
                                    <div class="appointment-meta-item">
                                        <i class="fas fa-diagnoses"></i>
                                        Diagnosis Recorded
                                    </div>
                                <?php endif; ?>
                                <?php if ($appointment['prescription']): ?>
                                    <div class="appointment-meta-item">
                                        <i class="fas fa-prescription-bottle-alt"></i>
                                        Prescription Issued
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($appointment['status'] == 'Completed' && $appointment['diagnosis']): ?>
                                <div class="diagnosis-summary">
                                    <p><strong>Diagnosis:</strong> <?= nl2br(htmlspecialchars(substr($appointment['diagnosis'], 0, 150))) ?><?= strlen($appointment['diagnosis']) > 150 ? '...' : '' ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="appointment-actions">
                            <a href="view_appointment.php?id=<?= $appointment['appoid'] ?>" class="btn-action btn-view">
                                <i class="fas fa-eye"></i> Details
                            </a>
                            <?php if ($appointment['status'] == 'Scheduled'): ?>
                                <a href="edit_appointment.php?id=<?= $appointment['appoid'] ?>" class="btn-action btn-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="telemedicine.php?appointment=<?= $appointment['appoid'] ?>" class="btn-action btn-telemed">
                                    <i class="fas fa-video"></i> Start Session
                                </a>
                            <?php endif; ?>
                            <a href="#" class="btn-action btn-message send-message-btn" 
                               data-patient-id="<?= $patient_id ?>"
                               data-patient-name="<?= htmlspecialchars($patient['pname']) ?>">
                                <i class="fas fa-envelope"></i> Message
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-appointments">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Appointments Found</h3>
                    <p>This patient doesn't have any appointments scheduled yet.</p>
                    <a href="book_appointment.php?pid=<?= $patient_id ?>" class="btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-plus"></i> Schedule First Appointment
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Message Modal (hidden by default) -->
    <div id="messageModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-envelope"></i> Send Message</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="messageForm">
                    <input type="hidden" name="patient_id" id="modalPatientId">
                    <input type="hidden" name="doctor_id" value="<?= $docid ?>">
                    
                    <div class="form-group">
                        <label for="messageSubject">Subject</label>
                        <input type="text" name="subject" id="messageSubject" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="messageContent">Message</label>
                        <textarea name="content" id="messageContent" rows="6" class="form-control" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="urgent"> Mark as urgent
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                        <button type="button" class="btn-cancel modal-close">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>

    <script>
        // Message modal functionality
        document.querySelectorAll('.send-message-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const patientId = this.dataset.patientId;
                const patientName = this.dataset.patientName;
                
                document.getElementById('modalPatientId').value = patientId;
                document.querySelector('#messageModal h3').innerHTML = 
                    `<i class="fas fa-envelope"></i> Send Message to ${patientName}`;
                
                document.getElementById('messageModal').style.display = 'block';
            });
        });

        // Close modal
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('messageModal').style.display = 'none';
            });
        });

        // Form submission
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Message sent successfully!');
                    document.getElementById('messageModal').style.display = 'none';
                    this.reset();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while sending the message.');
            });
        });
    </script>
</body>
</html>