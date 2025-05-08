<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");
$docid = $_SESSION['userid'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $appoid = $database->real_escape_string($_POST['appoid']);
        $status = $database->real_escape_string($_POST['status']);
        
        $updateSql = "UPDATE appointment SET status = '$status' WHERE appoid = '$appoid' AND docid = '$docid'";
        if ($database->query($updateSql)) {
            $_SESSION['success'] = "Appointment status updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating appointment status: " . $database->error;
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

// Fetch doctor details
$doctor = $database->query("SELECT * FROM doctor WHERE docid = '$docid'")->fetch_assoc();

// Fetch all appointments with status
$appointments = $database->query("
    SELECT a.appoid, p.pname, a.appodate, a.appotime, a.status, a.reason
    FROM appointment a
    JOIN patient p ON a.pid = p.pid
    WHERE a.docid = '$docid'
    ORDER BY a.appodate DESC, a.appotime DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments</title>
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <h1><i class="fas fa-calendar-alt"></i> Manage Appointments</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <table class="appointment-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($appointments->num_rows > 0): ?>
                        <?php while ($row = $appointments->fetch_assoc()): ?>
                            <tr class="status-<?= strtolower($row['status']) ?>">
                                <td><?= $row['appoid'] ?></td>
                                <td><?= htmlspecialchars($row['pname']) ?></td>
                                <td><?= date('M j, Y', strtotime($row['appodate'])) ?></td>
                                <td><?= date('h:i A', strtotime($row['appotime'])) ?></td>
                                <td>
                                    <span class="status-badge <?= strtolower($row['status']) ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td><?= !empty($row['reason']) ? htmlspecialchars($row['reason']) : 'N/A' ?></td>
                                <td class="actions">
                                    <form method="POST" class="status-form">
                                        <input type="hidden" name="appoid" value="<?= $row['appoid'] ?>">
                                        <select name="status" class="status-select" onchange="this.form.submit()">
                                            <option value="Pending" <?= $row['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Confirmed" <?= $row['status'] == 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                            <option value="Completed" <?= $row['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                            <option value="Cancelled" <?= $row['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                    <form method="POST" class="delete-form">
                                        <input type="hidden" name="appoid" value="<?= $row['appoid'] ?>">
                                        <button type="submit" name="delete_appointment" class="btn-delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">No appointments found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>