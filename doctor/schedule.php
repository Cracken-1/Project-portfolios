<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");
$docid = $_SESSION['userid'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $duration = $_POST['duration'];
    $description = $_POST['description'] ?? '';
    
    $stmt = $database->prepare("
        INSERT INTO schedule (docid, title, scheduledate, scheduletime, duration, description)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssis", $docid, $title, $date, $time, $duration, $description);
    
    if ($stmt->execute()) {
        header("Location: schedule.php?success=Schedule added successfully");
        exit();
    } else {
        $error = "Failed to add schedule. Please try again.";
    }
    $stmt->close();
}

// Fetch schedule
$schedules = [];
$stmt = $database->prepare("
    SELECT * FROM schedule 
    WHERE docid = ? 
    ORDER BY scheduledate DESC, scheduletime DESC
");
$stmt->bind_param("i", $docid);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule</title>
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="section-header">
            <h1><i class="fas fa-calendar-alt"></i> My Schedule</h1>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Add Schedule Form -->
        <div class="add-schedule-form">
            <h2><i class="fas fa-plus-circle"></i> Add New Schedule</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="date" id="date" name="date" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="time">Time</label>
                        <input type="time" id="time" name="time" required>
                    </div>
                    <div class="form-group">
                        <label for="duration">Duration (minutes)</label>
                        <input type="number" id="duration" name="duration" min="15" value="30" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-generate">
                        <i class="fas fa-save"></i> Save Schedule
                    </button>
                </div>
            </form>
        </div>

        <!-- Schedule List -->
        <div class="schedule-container">
            <h2><i class="fas fa-calendar-check"></i> Upcoming Schedules</h2>
            <?php if (!empty($schedules)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($schedule['title']); ?></td>
                                <td><?php echo $schedule['scheduledate']; ?></td>
                                <td><?php echo $schedule['scheduletime']; ?></td>
                                <td><?php echo $schedule['duration']; ?> mins</td>
                                <td><?php echo htmlspecialchars($schedule['description'] ?? 'N/A'); ?></td>
                                <td class="actions">
                                    <a href="edit_schedule.php?id=<?php echo $schedule['scheduleid']; ?>" class="btn-edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_schedule.php?id=<?php echo $schedule['scheduleid']; ?>" class="btn-delete" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-schedules">No schedules found. Add your first schedule above.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Date picker with minimum date of today
        flatpickr("#date", {
            minDate: "today",
            dateFormat: "Y-m-d"
        });
    </script>
</body>
</html>