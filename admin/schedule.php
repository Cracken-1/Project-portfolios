<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Add Schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_schedule'])) {
    $docid = $_POST['docid'];
    $scheduledate = $_POST['scheduledate'];
    $scheduletime = $_POST['scheduletime'];
    $title = $_POST['title'];

    $sql = "INSERT INTO schedule (docid, scheduledate, scheduletime, title) VALUES ('$docid', '$scheduledate', '$scheduletime', '$title')";
    if ($database->query($sql)) {
        echo "<script>alert('Schedule added successfully!');</script>";
    } else {
        echo "<script>alert('Error adding schedule.');</script>";
    }
}

// Delete Schedule
if (isset($_GET['delete_id'])) {
    $scheduleid = $_GET['delete_id'];
    $sql = "DELETE FROM schedule WHERE scheduleid = $scheduleid";
    if ($database->query($sql)) {
        echo "<script>alert('Schedule deleted successfully!');</script>";
    } else {
        echo "<script>alert('Error deleting schedule.');</script>";
    }
}

// Fetch all schedules
$schedules = $database->query("
    SELECT s.scheduleid, d.docname, s.scheduledate, s.scheduletime, s.title 
    FROM schedule s
    JOIN doctor d ON s.docid = d.docid
    ORDER BY s.scheduledate DESC
");

// Fetch doctors for dropdown
$doctors = $database->query("SELECT * FROM doctor");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <h1>Manage Schedules</h1>

        <!-- Add Schedule Form -->
        <form method="POST" class="schedule-form">
            <h2>Add New Schedule</h2>
            <label for="docid">Doctor:</label>
            <select id="docid" name="docid" required>
                <?php
                while ($row = $doctors->fetch_assoc()) {
                    echo "<option value='{$row['docid']}'>{$row['docname']}</option>";
                }
                ?>
            </select>
            <label for="scheduledate">Date:</label>
            <input type="date" id="scheduledate" name="scheduledate" required>
            <label for="scheduletime">Time:</label>
            <input type="time" id="scheduletime" name="scheduletime" required>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required>
            <button type="submit" name="add_schedule">Add Schedule</button>
        </form>

        <!-- List of Schedules -->
        <h2>Schedule List</h2>
        <table class="schedule-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Doctor</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Title</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($schedules->num_rows > 0) {
                    while ($row = $schedules->fetch_assoc()) {
                        echo "<tr>
                            <td>{$row['scheduleid']}</td>
                            <td>{$row['docname']}</td>
                            <td>{$row['scheduledate']}</td>
                            <td>{$row['scheduletime']}</td>
                            <td>{$row['title']}</td>
                            <td>
                                <a href='edit_schedule.php?edit_id={$row['scheduleid']}'>Edit</a>
                                <a href='schedule.php?delete_id={$row['scheduleid']}' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                            </td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6'>No schedules found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>