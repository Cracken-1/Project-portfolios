<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_schedule'])) {
        // Add new schedule
        $doctor_id = $_POST['doctor_id'];
        $day_of_week = $_POST['day_of_week'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $max_appointments = $_POST['max_appointments'];
        
        $stmt = $database->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, max_appointments) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $doctor_id, $day_of_week, $start_time, $end_time, $max_appointments);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['message'] = "Schedule added successfully!";
        header("Location: schedules.php");
        exit();
    } elseif (isset($_POST['update_schedule'])) {
        // Update existing schedule
        $schedule_id = $_POST['schedule_id'];
        $day_of_week = $_POST['day_of_week'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $max_appointments = $_POST['max_appointments'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $database->prepare("UPDATE doctor_schedules SET day_of_week = ?, start_time = ?, end_time = ?, max_appointments = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("ssssii", $day_of_week, $start_time, $end_time, $max_appointments, $is_active, $schedule_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['message'] = "Schedule updated successfully!";
        header("Location: schedules.php");
        exit();
    } elseif (isset($_POST['delete_schedule'])) {
        // Delete schedule
        $schedule_id = $_POST['schedule_id'];
        
        $stmt = $database->prepare("DELETE FROM doctor_schedules WHERE id = ?");
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['message'] = "Schedule deleted successfully!";
        header("Location: schedules.php");
        exit();
    }
}

// Fetch all schedules with doctor information
$schedules = $database->query("
    SELECT ds.*, d.docname, specialization 
    FROM doctor_schedules ds
    JOIN doctor d ON ds.doctor_id = d.docid
    ORDER BY FIELD(ds.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), ds.start_time
")->fetch_all(MYSQLI_ASSOC);

// Fetch all doctors for dropdown
$doctors = $database->query("SELECT docid, docname, specialization FROM doctor ORDER BY docname")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Schedules</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .schedules-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .schedule-header h2 {
            margin: 0;
            color: #2c3e50;
        }
        
        .btn-add {
            background: #4a6cf7;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-add i {
            margin-right: 8px;
        }
        
        .schedules-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .schedules-table th, .schedules-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .schedules-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .schedules-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-active {
            color: #28a745;
            font-weight: 500;
        }
        
        .status-inactive {
            color: #dc3545;
            font-weight: 500;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit, .btn-delete {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-edit {
            background: #ffc107;
            color: #212529;
            text-decoration: none;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .btn-edit i, .btn-delete i {
            margin-right: 5px;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 50%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            margin: 0;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
            background-color: white;
        }
        
        .form-check {
            display: flex;
            align-items: center;
        }
        
        .form-check-input {
            margin-right: 10px;
        }
        
        .btn-submit {
            background: #4a6cf7;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="schedules-container">
            <div class="schedule-header">
                <h2><i class="fas fa-calendar-alt"></i> Doctor Schedules Management</h2>
                <button class="btn-add" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Schedule
                </button>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>

            <table class="schedules-table">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Day</th>
                        <th>Time Slot</th>
                        <th>Max Appointments</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): ?>
                        <tr>
                            <td>Dr. <?php echo htmlspecialchars($schedule['docname']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['specialization']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['day_of_week']); ?></td>
                            <td>
                                <?php 
                                echo date('g:i A', strtotime($schedule['start_time'])) . 
                                ' - ' . 
                                date('g:i A', strtotime($schedule['end_time'])); 
                                ?>
                            </td>
                            <td><?php echo $schedule['max_appointments']; ?></td>
                            <td>
                                <span class="<?php echo $schedule['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $schedule['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-edit" onclick="openEditModal(
                                        <?php echo $schedule['id']; ?>,
                                        '<?php echo $schedule['day_of_week']; ?>',
                                        '<?php echo $schedule['start_time']; ?>',
                                        '<?php echo $schedule['end_time']; ?>',
                                        <?php echo $schedule['max_appointments']; ?>,
                                        <?php echo $schedule['is_active']; ?>,
                                        <?php echo $schedule['doctor_id']; ?>
                                    )">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                        <button type="submit" name="delete_schedule" class="btn-delete" onclick="return confirm('Are you sure you want to delete this schedule?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Schedule</h3>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label for="doctor_id">Doctor</label>
                    <select class="form-select" id="doctor_id" name="doctor_id" required>
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['docid']; ?>">
                                Dr. <?php echo htmlspecialchars($doctor['docname']); ?> (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="day_of_week">Day of Week</label>
                    <select class="form-select" id="day_of_week" name="day_of_week" required>
                        <option value="">Select Day</option>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                        <option value="Sunday">Sunday</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="start_time">Start Time</label>
                    <input type="time" class="form-control" id="start_time" name="start_time" required>
                </div>
                <div class="form-group">
                    <label for="end_time">End Time</label>
                    <input type="time" class="form-control" id="end_time" name="end_time" required>
                </div>
                <div class="form-group">
                    <label for="max_appointments">Maximum Appointments</label>
                    <input type="number" class="form-control" id="max_appointments" name="max_appointments" min="1" value="10" required>
                </div>
                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                        <label for="is_active">Active Schedule</label>
                    </div>
                </div>
                <button type="submit" name="add_schedule" class="btn-submit">Add Schedule</button>
            </form>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Schedule</h3>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" id="edit_schedule_id" name="schedule_id">
                <div class="form-group">
                    <label for="edit_doctor_id">Doctor</label>
                    <select class="form-select" id="edit_doctor_id" name="doctor_id" disabled>
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['docid']; ?>">
                                Dr. <?php echo htmlspecialchars($doctor['docname']); ?> (<?php echo htmlspecialchars($doctor['docspecialty']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_day_of_week">Day of Week</label>
                    <select class="form-select" id="edit_day_of_week" name="day_of_week" required>
                        <option value="">Select Day</option>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                        <option value="Sunday">Sunday</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_start_time">Start Time</label>
                    <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                </div>
                <div class="form-group">
                    <label for="edit_end_time">End Time</label>
                    <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                </div>
                <div class="form-group">
                    <label for="edit_max_appointments">Maximum Appointments</label>
                    <input type="number" class="form-control" id="edit_max_appointments" name="max_appointments" min="1" required>
                </div>
                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label for="edit_is_active">Active Schedule</label>
                    </div>
                </div>
                <button type="submit" name="update_schedule" class="btn-submit">Update Schedule</button>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function openEditModal(id, day, startTime, endTime, maxAppointments, isActive, doctorId) {
            document.getElementById('edit_schedule_id').value = id;
            document.getElementById('edit_day_of_week').value = day;
            document.getElementById('edit_start_time').value = startTime;
            document.getElementById('edit_end_time').value = endTime;
            document.getElementById('edit_max_appointments').value = maxAppointments;
            document.getElementById('edit_is_active').checked = isActive;
            document.getElementById('edit_doctor_id').value = doctorId;
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>