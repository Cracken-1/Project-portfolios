<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

require_once("../db/db.php");
$docid = $_SESSION['userid'];

// Handle status updates and deletions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $appoid = $database->real_escape_string($_POST['appoid']);
        $status = $database->real_escape_string($_POST['status']);
        
        $updateSql = "UPDATE appointment SET status = '$status' WHERE appoid = '$appoid' AND docid = '$docid'";
        if ($database->query($updateSql)) {
            $_SESSION['success'] = "Appointment status updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating appointment: " . $database->error;
        }
    }
    elseif (isset($_POST['delete_appointment'])) {
        $appoid = $database->real_escape_string($_POST['appoid']);
        
        $deleteSql = "DELETE FROM appointment WHERE appoid = '$appoid' AND docid = '$docid'";
        if ($database->query($deleteSql)) {
            $_SESSION['success'] = "Appointment deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting appointment: " . $database->error;
        }
    }
    header("Location: view_appointments.php");
    exit();
}

// Get doctor details
$doctor = $database->query("SELECT * FROM doctor WHERE docid = '$docid'")->fetch_assoc();

// Get appointments with patient details
$appointments = $database->query("
    SELECT a.*, p.pname, p.page, p.pgender 
    FROM appointment a
    JOIN patient p ON a.pid = p.pid
    WHERE a.docid = '$docid'
    ORDER BY a.appodate DESC, a.appotime DESC
");

include("../includes/header.php");
include("../includes/sidebar.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Appointments</title>
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="doctor-main-content">
        <div class="doctor-container">
            <h1><i class="fas fa-calendar-alt"></i> My Appointments</h1>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <div class="appointments-container">
                <?php if ($appointments->num_rows > 0): ?>
                    <?php while ($appt = $appointments->fetch_assoc()): ?>
                        <div class="appointment-card status-<?= strtolower($appt['status']) ?>">
                            <div class="appointment-header">
                                <div class="patient-info">
                                    <h3><?= htmlspecialchars($appt['pname']) ?></h3>
                                    <p class="patient-meta">
                                        <?= $appt['page'] ?? 'N/A' ?> yrs, 
                                        <?= isset($appt['pgender']) ? ucfirst($appt['pgender']) : 'N/A' ?>
                                    </p>
                                </div>
                                <div class="appointment-status <?= strtolower($appt['status']) ?>">
                                    <?= $appt['status'] ?>
                                </div>
                            </div>
                            
                            <div class="appointment-details">
                                <p class="appointment-time">
                                    <i class="fas fa-calendar-day"></i> <?= date('F j, Y', strtotime($appt['appodate'])) ?>
                                    <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($appt['appotime'])) ?>
                                </p>
                                
                                <?php if (!empty($appt['reason'])): ?>
                                    <div class="appointment-reason">
                                        <strong>Reason:</strong> <?= htmlspecialchars($appt['reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="appointment-actions">
                                <form method="POST" class="status-form">
                                    <input type="hidden" name="appoid" value="<?= $appt['appoid'] ?>">
                                    <select name="status" class="status-select" onchange="this.form.submit()">
                                        <option value="Pending" <?= $appt['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Confirmed" <?= $appt['status'] == 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="Completed" <?= $appt['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="Cancelled" <?= $appt['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                                
                                <form method="POST" class="delete-form">
                                    <input type="hidden" name="appoid" value="<?= $appt['appoid'] ?>">
                                    <button type="submit" name="delete_appointment" class="btn-delete" 
                                            onclick="return confirm('Are you sure you want to delete this appointment?')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-appointments">
                        <i class="fas fa-calendar-times"></i>
                        <p>No appointments scheduled</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>