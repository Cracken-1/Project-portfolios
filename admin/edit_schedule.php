<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Fetch schedule details for editing
if (isset($_GET['edit_id'])) {
    $scheduleid = $_GET['edit_id'];
    $sql = "SELECT * FROM schedule WHERE scheduleid = $scheduleid";
    $result = $database->query($sql);
    $schedule = $result->fetch_assoc();
}

// Update Schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_schedule'])) {
    $scheduleid = $_POST['scheduleid'];
    $docid = $_POST['docid'];
    $scheduledate = $_POST['scheduledate'];
    $scheduletime = $_POST['scheduletime'];
    $title = $_POST['title'];

    $sql = "UPDATE schedule SET docid = '$docid', scheduledate = '$scheduledate', scheduletime = '$scheduletime', title = '$title' WHERE scheduleid = $scheduleid";
    if ($database->query($sql)) {
        echo "<script>alert('Schedule updated successfully!');</script>";
        header("location: schedule.php");
    } else {
        echo "<script>alert('Error updating schedule.');</script>";
    }
}

// Fetch doctors for dropdown
$doctors = $database->query("SELECT * FROM doctor");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Schedule</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <h1>Edit Schedule</h1>

        <!-- Edit Schedule Form -->
        <form method="POST" class="schedule-form">
            <input type="hidden" name="scheduleid" value="<?php echo $schedule['scheduleid']; ?>">
            <label for="docid">Doctor:</label>
            <select id="docid" name="docid" required>
                <?php
                while ($row = $doctors->fetch_assoc()) {
                    $selected = ($row['docid'] == $schedule['docid']) ? 'selected' : '';
                    echo "<option value='{$row['docid']}' $selected>{$row['docname']}</option>";
                }
                ?>
            </select>
            <label for="scheduledate">Date:</label>
            <input type="date" id="scheduledate" name="scheduledate" value="<?php echo $schedule['scheduledate']; ?>" required>
            <label for="scheduletime">Time:</label>
            <input type="time" id="scheduletime" name="scheduletime" value="<?php echo $schedule['scheduletime']; ?>" required>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" value="<?php echo $schedule['title']; ?>" required>
            <button type="submit" name="update_schedule">Update Schedule</button>
        </form>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>