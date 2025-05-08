<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Add Appointment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_appointment'])) {
    $pid = $_POST['pid'];
    $docid = $_POST['docid'];
    $appodate = $_POST['appodate'];
    $appotime = $_POST['appotime'];

    $sql = "INSERT INTO appointment (pid, docid, appodate, appotime) VALUES ('$pid', '$docid', '$appodate', '$appotime')";
    if ($database->query($sql)) {
        echo "<script>alert('Appointment added successfully!');</script>";
    } else {
        echo "<script>alert('Error adding appointment.');</script>";
    }
}

// Delete Appointment
if (isset($_GET['delete_id'])) {
    $appoid = $_GET['delete_id'];
    $sql = "DELETE FROM appointment WHERE appoid = $appoid";
    if ($database->query($sql)) {
        echo "<script>alert('Appointment deleted successfully!');</script>";
    } else {
        echo "<script>alert('Error deleting appointment.');</script>";
    }
}

// Fetch all appointments
$appointments = $database->query("
    SELECT a.appoid, p.pname, d.docname, a.appodate, a.appotime 
    FROM appointment a
    JOIN patient p ON a.pid = p.pid
    JOIN doctor d ON a.docid = d.docid
    ORDER BY a.appodate DESC
");

// Fetch patients and doctors for dropdowns
$patients = $database->query("SELECT * FROM patient");
$doctors = $database->query("SELECT * FROM doctor");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <h1>Manage Appointments</h1>

        <!-- Add Appointment Form -->
        <form method="POST" class="appointment-form">
            <h2>Add New Appointment</h2>
            <label for="pid">Patient:</label>
            <select id="pid" name="pid" required>
                <?php
                while ($row = $patients->fetch_assoc()) {
                    echo "<option value='{$row['pid']}'>{$row['pname']}</option>";
                }
                ?>
            </select>
            <label for="docid">Doctor:</label>
            <select id="docid" name="docid" required>
                <?php
                while ($row = $doctors->fetch_assoc()) {
                    echo "<option value='{$row['docid']}'>{$row['docname']}</option>";
                }
                ?>
            </select>
            <label for="appodate">Date:</label>
            <input type="date" id="appodate" name="appodate" required>
            <label for="appotime">Time:</label>
            <input type="time" id="appotime" name="appotime" required>
            <button type="submit" name="add_appointment">Add Appointment</button>
        </form>

        <!-- List of Appointments -->
        <h2>Appointment List</h2>
        <table class="appointment-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($appointments->num_rows > 0) {
                    while ($row = $appointments->fetch_assoc()) {
                        echo "<tr>
                            <td>{$row['appoid']}</td>
                            <td>{$row['pname']}</td>
                            <td>{$row['docname']}</td>
                            <td>{$row['appodate']}</td>
                            <td>{$row['appotime']}</td>
                            <td>
                                <a href='edit_appointment.php?edit_id={$row['appoid']}'>Edit</a>
                                <a href='appointment.php?delete_id={$row['appoid']}' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                            </td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6'>No appointments found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>