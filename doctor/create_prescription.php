<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");
$patient_id = $_SESSION['userid'];

// Fetch patient details
$patient = $database->query("SELECT * FROM patient WHERE pid = $patient_id")->fetch_assoc();

// Fetch all doctors with their details
$doctors = $database->query("
    SELECT docid, docname, specialization 
    FROM doctor
    ORDER BY docname
");

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_appointment'])) {
    $docid = $_POST['doctor'];
    $appodate = $_POST['date'];
    $appotime = $_POST['time'];
    
    // Basic validation
    $errors = [];
    if (empty($docid)) $errors[] = "Please select a doctor";
    if (empty($appodate)) $errors[] = "Please select a date";
    if (empty($appotime)) $errors[] = "Please select a time";
    
    if (empty($errors)) {
        // Check if date is in the future
        $selected_datetime = strtotime("$appodate $appotime");
        if ($selected_datetime < time()) {
            $errors[] = "Please select a future date and time";
        } else {
            // Insert into appointments table
            $stmt = $database->prepare("INSERT INTO appointment (pid, docid, appodate, appotime, status) VALUES (?, ?, ?, ?, 'Scheduled')");
            $stmt->bind_param("iiss", $patient_id, $docid, $appodate, $appotime);
            
            if ($stmt->execute()) {
                $success = "Appointment booked successfully with Dr. " . 
                          getDoctorName($docid) . " on " . 
                          date('F j, Y', strtotime($appodate)) . " at " . 
                          date('g:i A', strtotime($appotime));
                // Refresh data
                header("Refresh:2");
            } else {
                $errors[] = "Error booking appointment: " . $database->error;
            }
            $stmt->close();
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

// Helper function to get doctor name
function getDoctorName($docid) {
    global $database;
    $result = $database->query("SELECT docname FROM doctor WHERE docid = $docid");
    return $result->fetch_assoc()['docname'];
}

// Fetch all appointments for this patient with doctor details
$appointments = $database->query("
    SELECT a.appoid, d.docid, d.docname, d.specialization, 
           a.appodate, a.appotime, a.status
    FROM appointment a
    JOIN doctor d ON a.docid = d.docid
    WHERE a.pid = $patient_id
    ORDER BY a.appodate DESC, a.appotime DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment</title>
    <link rel="stylesheet" href="../css/includes.css">
    <style>
        .main-content {
            padding: 2rem;
            margin-left: 250px;
            background-color: #f5f7fa;
            min-height: calc(100vh - 70px);
        }
        .booking-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            max-width: 800px;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        select, input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        button {
            background: #3498db;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        button:hover {
            background: #2980b9;
        }
        .appointments-list {
            margin-top: 2rem;
        }
        .appointment-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .doctor-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .doctor-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        .doctor-details {
            line-height: 1.4;
        }
        .doctor-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .doctor-specialty {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        .appointment-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
        }
        .appointment-time {
            font-weight: 600;
        }
        .appointment-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .status-scheduled {
            background: #e3f2fd;
            color: #1976d2;
        }
        .status-completed {
            background: #e8f5e9;
            color: #388e3c;
        }
        .status-cancelled {
            background: #ffebee;
            color: #d32f2f;
        }
        .message {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
        }
        .success {
            background: #e8f8f5;
            color: #27ae60;
            border-left: 4px solid #2ecc71;
        }
        .error {
            background: #fdecea;
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #7f8c8d;
        }
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #bdc3c7;
        }
    </style>
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="booking-section">
            <h1>Book New Appointment</h1>
            
            <?php if (isset($success)): ?>
                <div class="message success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="doctor">Select Doctor</label>
                    <select id="doctor" name="doctor" required>
                        <option value="">-- Choose a Doctor --</option>
                        <?php while ($doctor = $doctors->fetch_assoc()): ?>
                            <option value="<?php echo $doctor['docid']; ?>">
                                Dr. <?php echo $doctor['docname']; ?> 
                                (<?php echo $doctor['specialization']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date">Appointment Date</label>
                    <input type="date" id="date" name="date" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="time">Appointment Time</label>
                    <input type="time" id="time" name="time" min="08:00" max="18:00" required>
                </div>
                
                <button type="submit" name="book_appointment">Book Appointment</button>
            </form>
        </div>
        
        <div class="booking-section">
            <h2>Your Appointments</h2>
            
            <div class="appointments-list">
                <?php if ($appointments->num_rows > 0): ?>
                    <?php while ($appointment = $appointments->fetch_assoc()): ?>
                        <div class="appointment-card">
                            <div class="doctor-info">
                                <div class="doctor-avatar">
                                    <?php echo substr($appointment['docname'], 0, 1); ?>
                                </div>
                                <div class="doctor-details">
                                    <div class="doctor-name">Dr. <?php echo $appointment['docname']; ?></div>
                                    <div class="doctor-specialty"><?php echo $appointment['specialization']; ?></div>
                                </div>
                            </div>
                            
                            <div class="appointment-meta">
                                <div class="appointment-time">
                                    <?php echo date('l, F j, Y', strtotime($appointment['appodate'])); ?>
                                    at <?php echo date('g:i A', strtotime($appointment['appotime'])); ?>
                                </div>
                                <div class="appointment-status status-<?php echo strtolower($appointment['status']); ?>">
                                    <?php echo $appointment['status']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>You have no appointments yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>

    <script>
        // Set minimum time to current time if date is today
        document.getElementById('date').addEventListener('change', function() {
            const timeInput = document.getElementById('time');
            const selectedDate = new Date(this.value);
            const today = new Date();
            
            if (selectedDate.toDateString() === today.toDateString()) {
                const currentHour = today.getHours();
                const currentMinute = today.getMinutes();
                timeInput.min = `${currentHour.toString().padStart(2, '0')}:${currentMinute.toString().padStart(2, '0')}`;
            } else {
                timeInput.min = '08:00';
            }
        });
    </script>
</body>
</html>