<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Fetch appointment details for editing
if (isset($_GET['edit_id'])) {
    $appoid = $_GET['edit_id'];
    $sql = "SELECT * FROM appointment WHERE appoid = $appoid";
    $result = $database->query($sql);
    $appointment = $result->fetch_assoc();
}

// Update Appointment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_appointment'])) {
    $appoid = $_POST['appoid'];
    $pid = $_POST['pid'];
    $docid = $_POST['docid'];
    $appodate = $_POST['appodate'];
    $appotime = $_POST['appotime'];

    $sql = "UPDATE appointment SET pid = '$pid', docid = '$docid', appodate = '$appodate', appotime = '$appotime' WHERE appoid = $appoid";
    if ($database->query($sql)) {
        echo "<script>alert('Appointment updated successfully!');</script>";
        header("location: appointment.php");
    } else {
        echo "<script>alert('Error updating appointment.');</script>";
    }
}

// Fetch patients and doctors for dropdowns
$patients = $database->query("SELECT * FROM patient");
$doctors = $database->query("SELECT * FROM doctor");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Appointment</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <h1>Edit Appointment</h1>

        <!-- Edit Appointment Form -->
        <form method="POST" class="appointment-form">
            <input type="hidden" name="appoid" value="<?php echo $appointment['appoid']; ?>">
            <label for="pid">Patient:</label>
            <select id="pid" name="pid" required>
                <?php
                while ($row = $patients->fetch_assoc()) {
                    $selected = ($row['pid'] == $appointment['pid']) ? 'selected' : '';
                    echo "<option value='{$row['pid']}' $selected>{$row['pname']}</option>";
                }
                ?>
            </select>
            <label for="docid">Doctor:</label>
            <select id="docid" name="docid" required>
                <?php
                while ($row = $doctors->fetch_assoc()) {
                    $selected = ($row['docid'] == $appointment['docid']) ? 'selected' : '';
                    echo "<option value='{$row['docid']}' $selected>{$row['docname']}</option>";
                }
                ?>
            </select>
            <label for="appodate">Date:</label>
            <input type="date" id="appodate" name="appodate" value="<?php echo $appointment['appodate']; ?>" required>
            <label for="appotime">Time:</label>
            <input type="time" id="appotime" name="appotime" value="<?php echo $appointment['appotime']; ?>" required>
            <button type="submit" name="update_appointment">Update Appointment</button>
        </form>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>