<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add Doctor
    if (isset($_POST['add_doctor'])) {
        try {
            // Validate inputs
            $docid = intval($_POST['docid']);
            $docname = trim($_POST['docname']);
            $docemail = trim($_POST['docemail']);
            $docage = intval($_POST['docage']);
            $docgender = $_POST['docgender'];
            $specialization = trim($_POST['specialization']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            // Basic validation
            if (empty($docname)  || empty($docid)  || empty($docname) || empty($docemail) || empty($docgender) || empty($specialization) || empty($password)) {
                throw new Exception("All fields are required");
            }

            if (!filter_var($docemail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }

            if ($docage < 25 || $docage > 70) {
                throw new Exception("Doctor age must be between 25-70");
            }

            if ($password !== $confirm_password) {
                throw new Exception("Passwords do not match");
            }

            if (strlen($password) < 8) {
                throw new Exception("Password must be at least 8 characters");
            }

            // Check if email exists
            $stmt = $database->prepare("SELECT docid FROM doctor WHERE docemail = ?");
            $stmt->bind_param("s", $docemail);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Email already exists");
            }

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert doctor with prepared statement
            $stmt = $database->prepare("INSERT INTO doctor (docname, docemail, docage, docgender, specialization, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssisss", $docname, $docemail, $docage, $docgender, $specialization, $hashed_password);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Doctor added successfully!";
            } else {
                throw new Exception("Error adding doctor: " . $database->error);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $_POST;
        }
        header("Location: doctors.php");
        exit();
    }
}

// Delete Doctor
if (isset($_GET['delete_id'])) {
    try {
        $docid = intval($_GET['delete_id']);
        
        $stmt = $database->prepare("DELETE FROM doctor WHERE docid = ?");
        $stmt->bind_param("i", $docid);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Doctor deleted successfully!";
        } else {
            throw new Exception("Error deleting doctor");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: doctors.php");
    exit();
}

// Fetch all doctors
$doctors = $database->query("SELECT * FROM doctor ORDER BY docname ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <h1>Manage Doctors</h1>
        
        <!-- Display messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="card-container">
            <!-- Doctor Registration Card -->
            <div class="info-card">
                <div class="info-header">
                    <i class="fas fa-user-md"></i>
                    <h2>Doctor Registration</h2>
                </div>
                <form method="POST" class="info-form">
                    <div class="form-item">
                        <label for="docname">Full Name</label>
                        <input type="text" id="docname" name="docname" placeholder="Dr. John Doe" 
                               value="<?php echo isset($_SESSION['form_data']['docname']) ? htmlspecialchars($_SESSION['form_data']['docname']) : ''; ?>" required>
                        <?php unset($_SESSION['form_data']['docname']); ?>
                    </div>
                    <div class="form-item">
                        <label for="docemail">Email</label>
                        <input type="email" id="docemail" name="docemail" placeholder="doctor@example.com" 
                               value="<?php echo isset($_SESSION['form_data']['docemail']) ? htmlspecialchars($_SESSION['form_data']['docemail']) : ''; ?>" required>
                        <?php unset($_SESSION['form_data']['docemail']); ?>
                    </div>

                    <div class="form-item">
                        <label for="docid">Doctor ID</label>
                        <input type="number" id="docid" name="docid" placeholder="35" min="25" max="70" 
                               value="<?php echo isset($_SESSION['form_data']['docid']) ? htmlspecialchars($_SESSION['form_data']['docid']) : ''; ?>" required>
                        <?php unset($_SESSION['form_data']['docid']); ?>
                    </div>
                    
                    <div class="form-item">
                        <label for="docage">Age</label>
                        <input type="number" id="docage" name="docage" placeholder="35" min="25" max="70" 
                               value="<?php echo isset($_SESSION['form_data']['docage']) ? htmlspecialchars($_SESSION['form_data']['docage']) : ''; ?>" required>
                        <?php unset($_SESSION['form_data']['docage']); ?>
                    </div>
                    <div class="form-item">
                        <label for="docgender">Gender</label>
                        <select id="docgender" name="docgender" required>
                            <option value="">Select</option>
                            <option value="Male" <?php echo (isset($_SESSION['form_data']['docgender']) && $_SESSION['form_data']['docgender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($_SESSION['form_data']['docgender']) && $_SESSION['form_data']['docgender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (isset($_SESSION['form_data']['docgender']) && $_SESSION['form_data']['docgender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <?php unset($_SESSION['form_data']['docgender']); ?>
                    </div>
                    <div class="form-item">
                        <label for="specialization">Specialization</label>
                        <input type="text" id="specialization" name="specialization" placeholder="Cardiology" 
                               value="<?php echo isset($_SESSION['form_data']['specialization']) ? htmlspecialchars($_SESSION['form_data']['specialization']) : ''; ?>" required>
                        <?php unset($_SESSION['form_data']['specialization']); ?>
                    </div>
                    <div class="form-item">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                    <div class="form-item">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                    </div>
                    <button type="submit" name="add_doctor" class="form-btn">Add Doctor</button>
                </form>
            </div>
        </div>

        <!-- List of Doctors -->
        <h2>Doctor List</h2>
        <div class="table-responsive">
            <table class="doctor-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Specialization</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($doctors->num_rows > 0): ?>
                        <?php while ($row = $doctors->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['docid']); ?></td>
                                <td><?php echo htmlspecialchars($row['docname']); ?></td>
                                <td><?php echo htmlspecialchars($row['docemail']); ?></td>
                                <td><?php echo htmlspecialchars($row['docage']); ?></td>
                                <td><?php echo htmlspecialchars($row['docgender']); ?></td>
                                <td><?php echo htmlspecialchars($row['specialization']); ?></td>
                                <td class="actions">
                                    <a href="edit_doctor.php?edit_id=<?php echo htmlspecialchars($row['docid']); ?>" class="btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="doctors.php?delete_id=<?php echo htmlspecialchars($row['docid']); ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this doctor?')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No doctors found</td>
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
            const age = document.getElementById('docage').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                e.preventDefault();
            }
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters!');
                e.preventDefault();
            }
            
            if (age < 25 || age > 70) {
                alert('Doctor age must be between 25-70!');
                e.preventDefault();
            }
        });
    </script>
</body>
</html>