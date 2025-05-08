<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add Patient
    if (isset($_POST['add_patient'])) {
        try {
            // Validate inputs
            $pname = trim($_POST['pname']);
            $pemail = trim($_POST['pemail']);
            $pphoneno = trim($_POST['pphoneno']);
            $page = intval($_POST['page']);
            $pgender = $_POST['pgender'];
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $registered_date = $_POST['registered_date'] ?? date('Y-m-d H:i:s');

            // Basic validation
            if (empty($pname) || empty($pemail) || empty($pphoneno) || empty($pgender) || empty($password)) {
                throw new Exception("All fields are required");
            }

            if (!filter_var($pemail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }

            if (!preg_match('/^[0-9]{10,15}$/', $pphoneno)) {
                throw new Exception("Phone number must be 10-15 digits");
            }

            if ($page < 1 || $page > 120) {
                throw new Exception("Age must be between 1-120");
            }

            if ($password !== $confirm_password) {
                throw new Exception("Passwords do not match");
            }

            if (strlen($password) < 8) {
                throw new Exception("Password must be at least 8 characters");
            }

            // Validate registration date if provided
            if (!empty($_POST['registered_date']) && !strtotime($_POST['registered_date'])) {
                throw new Exception("Invalid registration date format");
            }

            // Check if email exists
            $stmt = $database->prepare("SELECT pid FROM patient WHERE pemail = ?");
            $stmt->bind_param("s", $pemail);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Email already exists");
            }

            // Check if phone number exists
            $stmt = $database->prepare("SELECT pid FROM patient WHERE pphoneno = ?");
            $stmt->bind_param("s", $pphoneno);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Phone number already exists");
            }

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert patient with prepared statement
            $stmt = $database->prepare("INSERT INTO patient (pname, pemail, pphoneno, page, pgender, password, registered_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiss", $pname, $pemail, $pphoneno, $page, $pgender, $hashed_password, $registered_date);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Patient added successfully!";
            } else {
                throw new Exception("Error adding patient: " . $database->error);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $_POST;
        }
        header("Location: patients.php");
        exit();
    }
}

// Delete Patient
if (isset($_GET['delete_id'])) {
    try {
        $pid = intval($_GET['delete_id']);
        
        $stmt = $database->prepare("DELETE FROM patient WHERE pid = ?");
        $stmt->bind_param("i", $pid);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Patient deleted successfully!";
        } else {
            throw new Exception("Error deleting patient");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: patients.php");
    exit();
}

// Fetch all patients
$patients = $database->query("SELECT pid, pname, pemail, pphoneno, page, pgender, registered_date FROM patient ORDER BY registered_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .date-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .date-input-group input[type="date"] {
            flex: 1;
        }
        
        .date-input-group button {
            padding: 10px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .date-input-group button:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <h1>Manage Patients</h1>
        
        <!-- Display messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="card-container">
            <!-- Patient Registration Card -->
            <div class="info-card">
                <div class="info-header">
                    <i class="fas fa-user-injured"></i>
                    <h2>Patient Registration</h2>
                </div>
                <form method="POST" class="info-form">
                    <div class="form-item">
                        <label for="pname">Full Name</label>
                        <input type="text" id="pname" name="pname" placeholder="Jane Smith" 
                               value="<?php echo isset($_SESSION['form_data']['pname']) ? htmlspecialchars($_SESSION['form_data']['pname']) : ''; ?>" required>
                        <?php unset($_SESSION['form_data']['pname']); ?>
                    </div>
                    <div class="form-item">
                        <label for="pemail">Email</label>
                        <input type="email" id="pemail" name="pemail" placeholder="patient@example.com" 
                               value="<?php echo isset($_SESSION['form_data']['pemail']) ? htmlspecialchars($_SESSION['form_data']['pemail']) : ''; ?>" required>
                        <?php unset($_SESSION['form_data']['pemail']); ?>
                    </div>
                    <div class="form-item">
                        <label for="pphoneno">Phone Number</label>
                        <input type="tel" id="pphoneno" name="pphoneno" placeholder="1234567890" 
                               value="<?php echo isset($_SESSION['form_data']['pphoneno']) ? htmlspecialchars($_SESSION['form_data']['pphoneno']) : ''; ?>" required>
                        <?php unset($_SESSION['form_data']['pphoneno']); ?>
                    </div>
                    <div class="form-item">
                        <label for="page">Age</label>
                        <input type="number" id="page" name="page" placeholder="28" min="1" max="120" 
                               value="<?php echo isset($_SESSION['form_data']['page']) ? htmlspecialchars($_SESSION['form_data']['page']) : ''; ?>" required>
                        <?php unset($_SESSION['form_data']['page']); ?>
                    </div>
                    <div class="form-item">
                        <label for="pgender">Gender</label>
                        <select id="pgender" name="pgender" required>
                            <option value="">Select</option>
                            <option value="Male" <?php echo (isset($_SESSION['form_data']['pgender']) && $_SESSION['form_data']['pgender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($_SESSION['form_data']['pgender']) && $_SESSION['form_data']['pgender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (isset($_SESSION['form_data']['pgender']) && $_SESSION['form_data']['pgender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <?php unset($_SESSION['form_data']['pgender']); ?>
                    </div>
                    <div class="form-item">
                        <label for="registered_date">Registration Date</label>
                        <div class="date-input-group">
                            <input type="datetime-local" id="registered_date" name="registered_date" 
                                   value="<?php echo isset($_SESSION['form_data']['registered_date']) ? htmlspecialchars($_SESSION['form_data']['registered_date']) : ''; ?>">
                            <button type="button" id="set-today-btn">Today</button>
                        </div>
                        <small class="text-muted">Leave empty for current date/time</small>
                        <?php unset($_SESSION['form_data']['registered_date']); ?>
                    </div>
                    <div class="form-item">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                    <div class="form-item">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                    </div>
                    <button type="submit" name="add_patient" class="form-btn">Add Patient</button>
                </form>
            </div>
        </div>

        <!-- List of Patients -->
        <h2>Patient List</h2>
        <div class="table-responsive">
            <table class="patient-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Registered On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($patients->num_rows > 0): ?>
                        <?php while ($row = $patients->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['pid']); ?></td>
                                <td><?php echo htmlspecialchars($row['pname']); ?></td>
                                <td><?php echo htmlspecialchars($row['pemail']); ?></td>
                                <td><?php echo htmlspecialchars($row['pphoneno']); ?></td>
                                <td><?php echo htmlspecialchars($row['page']); ?></td>
                                <td><?php echo htmlspecialchars($row['pgender']); ?></td>
                                <td><?php echo date('M j, Y g:i a', strtotime($row['registered_date'])); ?></td>
                                <td class="actions">
                                    <a href="edit_patient.php?edit_id=<?php echo htmlspecialchars($row['pid']); ?>" class="btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="patients.php?delete_id=<?php echo htmlspecialchars($row['pid']); ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this patient?')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No patients found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>
    
    <script>
        // Simple client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const phone = document.getElementById('pphoneno').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                e.preventDefault();
            }
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters!');
                e.preventDefault();
            }
            
            if (!/^\d{10,15}$/.test(phone)) {
                alert('Phone number must be 10-15 digits!');
                e.preventDefault();
            }
        });
        
        // Set today's date/time button
        document.getElementById('set-today-btn').addEventListener('click', function() {
            const now = new Date();
            // Format as YYYY-MM-DDTHH:MM (datetime-local input format)
            const formatted = now.toISOString().slice(0, 16);
            document.getElementById('registered_date').value = formatted;
        });
    </script>
</body>
</html>