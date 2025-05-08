<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Fetch doctor details for editing
if (isset($_GET['edit_id'])) {
    $docid = $_GET['edit_id'];
    $stmt = $database->prepare("SELECT * FROM doctor WHERE docid = ?");
    $stmt->bind_param("i", $docid);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result->fetch_assoc();
    $stmt->close();
}

// Update Doctor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_doctor'])) {
    $docid = $_POST['docid'];
    $docname = $_POST['docname'];
    $docemail = $_POST['docemail'];
    $docage = $_POST['docage'];
    $docgender = $_POST['docgender'];
    $specialization = $_POST['specialization'];
    
    // Check if password is being updated
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $database->prepare("UPDATE doctor SET docname = ?, docemail = ?, docage = ?, docgender = ?, specialization = ?, password = ? WHERE docid = ?");
        $stmt->bind_param("ssisssi", $docname, $docemail, $docage, $docgender, $specialization, $password, $docid);
    } else {
        $stmt = $database->prepare("UPDATE doctor SET docname = ?, docemail = ?, docage = ?, docgender = ?, specialization = ? WHERE docid = ?");
        $stmt->bind_param("ssissi", $docname, $docemail, $docage, $docgender, $specialization, $docid);
    }
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Doctor updated successfully!";
        header("Location: doctors.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating doctor: " . $database->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Doctor</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        
        .doctor-form {
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
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: #4a6cf7;
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1rem;
            background-color: white;
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
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle i {
            position: absolute;
            right: 15px;
            top: 40px;
            cursor: pointer;
            color: #6c757d;
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
    </style>
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-user-md"></i> Edit Doctor</h1>
            <a href="doctors.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Doctors
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

        <form method="POST" class="doctor-form">
            <input type="hidden" name="docid" value="<?php echo $doctor['docid']; ?>">
            
            <div class="form-group">
                <label for="docname">Full Name</label>
                <input type="text" id="docname" name="docname" class="form-control" 
                       value="<?php echo htmlspecialchars($doctor['docname']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="docemail">Email Address</label>
                <input type="email" id="docemail" name="docemail" class="form-control" 
                       value="<?php echo htmlspecialchars($doctor['docemail']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="docage">Age</label>
                <input type="number" id="docage" name="docage" class="form-control" 
                       value="<?php echo $doctor['docage']; ?>" min="25" max="80" required>
            </div>
            
            <div class="form-group">
                <label for="docgender">Gender</label>
                <select id="docgender" name="docgender" class="form-select" required>
                    <option value="Male" <?php echo ($doctor['docgender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($doctor['docgender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($doctor['docgender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <div class="form-group full-width">
                <label for="specialization">Specialization</label>
                <input type="text" id="specialization" name="specialization" class="form-control" 
                       value="<?php echo htmlspecialchars($doctor['specialization']); ?>" required>
            </div>
            
            <div class="form-group password-toggle">
                <label for="password">New Password (leave blank to keep current)</label>
                <input type="password" id="password" name="password" class="form-control">
                <i class="fas fa-eye" id="togglePassword"></i>
            </div>
            
            <div class="form-group full-width">
                <button type="submit" name="update_doctor" class="btn-submit">
                    Update Doctor
                </button>
            </div>
        </form>
    </div>

    <?php include("../includes/footer.php"); ?>

    <script>
        // Password toggle functionality
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
        
        // Form validation
        const form = document.querySelector('.doctor-form');
        form.addEventListener('submit', function(e) {
            const email = document.getElementById('docemail').value;
            if (!validateEmail(email)) {
                alert('Please enter a valid email address');
                e.preventDefault();
            }
        });
        
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    </script>
</body>
</html>