<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Fetch patient details for editing
if (isset($_GET['edit_id'])) {
    $pid = $_GET['edit_id'];
    $stmt = $database->prepare("SELECT * FROM patient WHERE pid = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();
    $stmt->close();
}

// Update patient
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_patient'])) {
    $pid = $_POST['pid'];
    $pname = $_POST['pname'];
    $page = $_POST['page'];
    $pgender = $_POST['pgender'];
    $pphoneno = $_POST['pphoneno'];
    $pemail = $_POST['pemail'];
    $registered_date = $_POST['registered_date'];

    // Validate inputs
    if (empty($pname) || empty($pemail) || empty($pphoneno) || empty($registered_date)) {
        $_SESSION['error'] = "All required fields must be filled";
        header("Location: edit_patient.php?edit_id=".$pid);
        exit();
    }

    if (!filter_var($pemail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format";
        header("Location: edit_patient.php?edit_id=".$pid);
        exit();
    }

    if (!preg_match('/^[0-9]{10,15}$/', $pphoneno)) {
        $_SESSION['error'] = "Phone number must be 10-15 digits";
        header("Location: edit_patient.php?edit_id=".$pid);
        exit();
    }

    if ($page < 1 || $page > 120) {
        $_SESSION['error'] = "Age must be between 1-120";
        header("Location: edit_patient.php?edit_id=".$pid);
        exit();
    }

    // Check if email exists for another patient
    $stmt = $database->prepare("SELECT pid FROM patient WHERE pemail = ? AND pid != ?");
    $stmt->bind_param("si", $pemail, $pid);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Email already in use by another patient";
        header("Location: edit_patient.php?edit_id=".$pid);
        exit();
    }

    // Check if phone number exists for another patient
    $stmt = $database->prepare("SELECT pid FROM patient WHERE pphoneno = ? AND pid != ?");
    $stmt->bind_param("si", $pphoneno, $pid);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Phone number already in use by another patient";
        header("Location: edit_patient.php?edit_id=".$pid);
        exit();
    }

    // Update patient with prepared statement
    $stmt = $database->prepare("UPDATE patient SET pname = ?, page = ?, pgender = ?, pphoneno = ?, pemail = ?, registered_date = ? WHERE pid = ?");
    $stmt->bind_param("sississ", $pname, $page, $pgender, $pphoneno, $pemail, $registered_date, $pid);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Patient updated successfully!";
        header("Location: patients.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating patient: " . $database->error;
        header("Location: edit_patient.php?edit_id=".$pid);
        exit();
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .main-content {
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 30px auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .page-header h1 {
            margin: 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }

        .page-header h1 i {
            margin-right: 10px;
            color: #4a6cf7;
        }

        .back-btn {
            background: #6c757d;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .back-btn i {
            margin-right: 8px;
        }

        .patient-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1rem;
            background: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: #4a6cf7;
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.1);
        }

        .btn-submit {
            background: #4a6cf7;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: background 0.3s;
            grid-column: span 2;
        }

        .btn-submit:hover {
            background: #3a5bd9;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            grid-column: span 2;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .date-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
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
        <div class="page-header">
            <h1><i class="fas fa-user-injured"></i> Edit Patient</h1>
            <a href="patients.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="patient-form">
            <input type="hidden" name="pid" value="<?php echo $patient['pid']; ?>">

            <div class="form-group">
                <label for="pname">Full Name</label>
                <input type="text" id="pname" name="pname" class="form-control"
                    value="<?php echo htmlspecialchars($patient['pname']); ?>" required>
            </div>

            <div class="form-group">
                <label for="page">Age</label>
                <input type="number" id="page" name="page" class="form-control"
                    value="<?php echo $patient['page']; ?>" min="1" max="120" required>
            </div>

            <div class="form-group">
                <label for="pemail">Email</label>
                <input type="email" id="pemail" name="pemail" class="form-control"
                    value="<?php echo htmlspecialchars($patient['pemail']); ?>" required>
            </div>

            <div class="form-group">
                <label for="pphoneno">Phone Number</label>
                <input type="tel" id="pphoneno" name="pphoneno" class="form-control"
                    value="<?php echo htmlspecialchars($patient['pphoneno']); ?>" required>
            </div>

            <div class="form-group">
                <label for="pgender">Gender</label>
                <select id="pgender" name="pgender" class="form-select" required>
                    <option value="Male" <?php echo ($patient['pgender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($patient['pgender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($patient['pgender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="registered_date">Registration Date</label>
                <div class="date-input-group">
                    <input type="datetime-local" id="registered_date" name="registered_date" class="form-control"
                        value="<?php echo date('Y-m-d\TH:i', strtotime($patient['registered_date'])); ?>" required>
                    <button type="button" id="set-today-btn">Today</button>
                </div>
            </div>

            <div class="form-group full-width">
                <button type="submit" name="update_patient" class="btn-submit">
                    Update Patient
                </button>
            </div>
        </form>
    </div>

    <?php include("../includes/footer.php"); ?>

    <!-- FontAwesome for icons -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    
    <script>
        // Set today's date/time button
        document.getElementById('set-today-btn').addEventListener('click', function() {
            const now = new Date();
            // Format as YYYY-MM-DDTHH:MM (datetime-local input format)
            const formatted = now.toISOString().slice(0, 16);
            document.getElementById('registered_date').value = formatted;
        });
        
        // Client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const phone = document.getElementById('pphoneno').value;
            const email = document.getElementById('pemail').value;
            
            if (!/^\d{10,15}$/.test(phone)) {
                alert('Phone number must be 10-15 digits!');
                e.preventDefault();
            }
            
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email address!');
                e.preventDefault();
            }
        });
    </script>
</body>
</html>