<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Fetch data for status cards
$today = date('Y-m-d');
$patientCount = $database->query("SELECT COUNT(*) as count FROM patient")->fetch_assoc()['count'];
$doctorCount = $database->query("SELECT COUNT(*) as count FROM doctor")->fetch_assoc()['count'];
$appointmentCount = $database->query("SELECT COUNT(*) as count FROM appointment WHERE appodate >= '$today'")->fetch_assoc()['count'];
$sessionCount = $database->query("SELECT COUNT(*) as count FROM doctor_schedules WHERE is_active = 1")->fetch_assoc()['count'];
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/includes.css">


</head>
<body>
<?php include("../includes/header.php"); ?>
<?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <h1>Admin Dashboard</h1>
        <p>Welcome back, Administrator! Here's a quick overview of your hospital management system.</p>

        <!-- Status Cards -->
        <div class="status-cards">
            <!-- Doctors Card -->
            <div class="status-card">
                <h2><?php echo $doctorCount; ?></h2>
                <p>Doctors</p>
                <div class="icon">
                    <i class="fas fa-user-md"></i> <!-- FontAwesome Doctors Icon -->
                </div>
            </div>

            <!-- Patients Card -->
            <div class="status-card">
                <h2><?php echo $patientCount; ?></h2>
                <p>Patients</p>
                <div class="icon">
                    <i class="fas fa-users"></i> <!-- FontAwesome Patients Icon -->
                </div>
            </div>

            <!-- Appointments Card -->
            <div class="status-card">
                <h2><?php echo $appointmentCount; ?></h2>
                <p>Appointments</p>
                <div class="icon">
                    <i class="fas fa-calendar-check"></i> <!-- FontAwesome Appointments Icon -->
                </div>
            </div>

            <!-- Schedules Card -->
            <div class="status-card">
                <h2><?php echo $sessionCount; ?></h2>
                <p>Schedules</p>
                <div class="icon">
                    <i class="fas fa-calendar-alt"></i> <!-- FontAwesome Schedules Icon -->
                </div>
            </div>
        </div>
      
        <!-- Upcoming Appointments -->
        <div class="upcoming-section">
            <h3><i class="fas fa-calendar-check"></i> Upcoming Appointments</h3>
            <table class="upcoming-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-id-badge"></i> Appointment ID</th>
                        <th><i class="fas fa-user"></i> Patient Name</th>
                        <th><i class="fas fa-user-md"></i> Doctor</th>
                        <th><i class="fas fa-clock"></i> Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $nextWeek = date('Y-m-d', strtotime('+1 week'));
                    $appointments = $database->query("
                        SELECT a.appoid, p.pname, d.docname, a.appodate, a.appotime 
                        FROM appointment a
                        JOIN patient p ON a.pid = p.pid
                        JOIN doctor d ON a.docid = d.docid
                        WHERE a.appodate BETWEEN '$today' AND '$nextWeek'
                        ORDER BY a.appodate ASC
                    ");

                    if ($appointments->num_rows > 0) {
                        while ($row = $appointments->fetch_assoc()) {
                            echo "<tr>
                                <td>{$row['appoid']}</td>
                                <td>{$row['pname']}</td>
                                <td>{$row['docname']}</td>
                                <td>{$row['appodate']} {$row['appotime']}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'><i class='fas fa-info-circle'></i> No upcoming appointments found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Upcoming Sessions -->
        <div class="upcoming-section">
            <h3><i class="fas fa-calendar-alt"></i> Upcoming Sessions</h3>
            <table class="upcoming-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-bookmark"></i> Session Title</th>
                        <th><i class="fas fa-user-md"></i> Doctor</th>
                        <th><i class="fas fa-clock"></i> Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sessions = $database->query("
                        SELECT s.title, d.docname, s.scheduledate, s.scheduletime 
                        FROM schedule s
                        JOIN doctor d ON s.docid = d.docid
                        WHERE s.scheduledate BETWEEN '$today' AND '$nextWeek'
                        ORDER BY s.scheduledate ASC
                    ");

                    if ($sessions->num_rows > 0) {
                        while ($row = $sessions->fetch_assoc()) {
                            echo "<tr>
                                <td>{$row['title']}</td>
                                <td>{$row['docname']}</td>
                                <td>{$row['scheduledate']} {$row['scheduletime']}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3'><i class='fas fa-info-circle'></i> No upcoming sessions found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>
    <script src="../js/main.js"></script>
</body>
</html>