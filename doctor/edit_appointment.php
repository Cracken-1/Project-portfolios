<?php
session_start();
require_once("../db/db.php");

// Check if user is logged in as doctor
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

$docid = $_SESSION['userid'];

// Check if appointment ID is provided
if (!isset($_GET['id'])) {
    header("location: appointments.php");
    exit();
}

$appointment_id = $_GET['id'];

// Fetch appointment details with patient information
$sql = "SELECT a.*, p.pname, p.pemail, p.pphoneno, p.pdob, p.pgender, 
               d.docname, d.docemail
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        JOIN doctor d ON a.docid = d.docid
        WHERE a.appoid = ? AND a.docid = ?";
$stmt = $database->prepare($sql);
$stmt->bind_param("ii", $appointment_id, $docid);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    $_SESSION['error'] = "Appointment not found or you don't have permission to view it";
    header("location: appointments.php");
    exit();
}

// Fetch doctor's available time slots
$schedule_sql = "SELECT scheduleid, title, scheduledate, scheduletime
                 FROM schedule 
                 WHERE docid = ? AND scheduledate >= CURDATE()
                 ORDER BY scheduledate, scheduletime";
$schedule_stmt = $database->prepare($schedule_sql);
$schedule_stmt->bind_param("i", $docid);
$schedule_stmt->execute();
$schedules = $schedule_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $appodate = $_POST['appodate'];
    $appotime = $_POST['appotime'];
    $appodesc = $_POST['appodesc'];
    $status = $_POST['status'];
    $schedule_id = $_POST['schedule_id'];

    // Validate inputs
    if (empty($appodate) || empty($appotime)) {
        $_SESSION['error'] = "Please select both date and time";
    } else {
        // Update appointment
        $update_sql = "UPDATE appointment 
                       SET appodate = ?, appotime = ?, appodesc = ?, status = ?, scheduleid = ?
                       WHERE appoid = ? AND docid = ?";
        $update_stmt = $database->prepare($update_sql);
        $update_stmt->bind_param("ssssiii", $appodate, $appotime, $appodesc, $status, $schedule_id, $appointment_id, $docid);
        
        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Appointment updated successfully";
            header("location: appointments.php");
            exit();
        } else {
            $_SESSION['error'] = "Error updating appointment: " . $database->error;
        }
    }
}

include("../includes/header.php");
include("../includes/sidebar.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Appointment - Doctor Panel</title>
    <link rel="stylesheet" href="../css/form_styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
 
</head>
<body>
    <div class="main-content">
        <div class="page-header">
            <h1>Edit Appointment</h1>
            <a href="appointments.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Appointments
            </a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="post" action="edit_appointment.php?id=<?= $appointment_id ?>">
                <div class="patient-details">
                    <h3>Patient Information</h3>
                    <div class="detail-row">
                        <div class="detail-label">Patient Name:</div>
                        <div class="detail-value"><?= htmlspecialchars($appointment['pname']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Email:</div>
                        <div class="detail-value"><?= htmlspecialchars($appointment['pemail']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Phone:</div>
                        <div class="detail-value"><?= htmlspecialchars($appointment['pphoneno']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Date of Birth:</div>
                        <div class="detail-value"><?= date('F j, Y', strtotime($appointment['pdob'])) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Gender:</div>
                        <div class="detail-value"><?= htmlspecialchars($appointment['pgender']) ?></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="appodate">Appointment Date</label>
                    <input type="date" name="appodate" id="appodate" 
                           value="<?= $appointment['appodate'] ?>" required
                           min="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
    <label for="schedule_id">Select Available Time Slot</label>
    <select name="schedule_id" id="schedule_id" required>
        <option value="">Select a time slot</option>
        <?php
        $start_hour = 8;
        $end_hour = 17;
        $interval = 60; //minutes;

        for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
             $displayHour = ($hour > 12) ? $hour - 12 : $hour;
             $Meridiem = ($hour >= 12) ? "PM" : "AM";
            $startTime = sprintf("%02d:00", $hour);
            $endTime = sprintf("%02d:00", $hour + 1);
           ?>
            <option value="<?= $startTime ?>">
                <?= $displayHour ?>:00 <?= $Meridiem ?> - <?= ($displayHour + 1) ?>:00 <?= $Meridiem?>
            </option>
        <?php } ?>
    </select>
</div>

                <div class="form-group">
                    <label for="appotime">Appointment Time</label>
                    <input type="time" name="appotime" id="appotime" 
                           value="<?= date('H:i', strtotime($appointment['appotime'])) ?>" required>
                </div>

                <div class="form-group">
                    <label for="appodesc">Description</label>
                    <textarea name="appodesc" id="appodesc" rows="4"><?= htmlspecialchars($appointment['appodesc']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" required>
                        <option value="pending" <?= $appointment['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="confirmed" <?= $appointment['status'] == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="completed" <?= $appointment['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $appointment['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="appointments.php" class="btn btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>

    <script>
        // Auto-fill date and time when schedule is selected
        document.getElementById('schedule_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                document.getElementById('appodate').value = selectedOption.dataset.date;
                document.getElementById('appotime').value = selectedOption.dataset.start;
            }
        });

        // Validate that selected time is within schedule
        document.querySelector('form').addEventListener('submit', function(e) {
            const scheduleSelect = document.getElementById('schedule_id');
            const selectedOption = scheduleSelect.options[scheduleSelect.selectedIndex];
            const appodate = document.getElementById('appodate').value;
            const appotime = document.getElementById('appotime').value;
            
            if (selectedOption.value && 
                (appodate !== selectedOption.dataset.date || 
                 appotime < selectedOption.dataset.start || 
                 appotime > selectedOption.dataset.end)) {
                alert('Selected time must be within the chosen schedule slot');
                e.preventDefault();
            }
        });
    </script>
</body>
</html>