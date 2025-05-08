<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");
$docid = $_SESSION['userid'];

// Fetch doctor profile
$stmt = $database->prepare("
    SELECT * FROM doctor 
    WHERE docid = ?
");
$stmt->bind_param("i", $docid);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $docname = $_POST['docname'];
    $docemail = $_POST['docemail'];
    $docid = $_POST['docid'];
    $specialization = $_POST['specialization'];
    $docpassword = !empty($_POST['docpassword']) ? password_hash($_POST['docpassword'], PASSWORD_DEFAULT) : null;

    if ($docpassword) {
        $stmt = $database->prepare("
            UPDATE doctor SET 
            docname = ?, 
            docemail = ?, 
            specialization = ?,
            docpassword = ?
            WHERE docid = ?
        ");
        $stmt->bind_param("ssssi", $docname, $docemail, $specialization, $docpassword, $docid);
    } else {
        $stmt = $database->prepare("
            UPDATE doctor SET 
            docname = ?, 
            docemail = ?, 
            specialization = ?
            WHERE docid = ?
        ");
        $stmt->bind_param("sssi", $docname, $docemail, $specialization, $docid);
    }

    if ($stmt->execute()) {
        $_SESSION['user'] = $docname;
        $success = "Profile updated successfully!";
        // Refresh doctor data
        $stmt = $database->prepare("SELECT * FROM doctor WHERE docid = ?");
        $stmt->bind_param("i", $docid);
        $stmt->execute();
        $doctor = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $error = "Failed to update profile. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings</title>
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="profile-header">
            <h1><i class="fas fa-user-cog"></i> Profile Settings</h1>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="profile-image">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3>Dr. <?php echo htmlspecialchars($doctor['docname']); ?></h3>
                <p><?php echo htmlspecialchars($doctor['specialization']); ?></p>
            </div>

            <div class="profile-form">
                <form method="POST">
                    <div class="form-group">
                        <label for="docname">Full Name</label>
                        <input type="text" id="docname" name="docname" value="<?php echo htmlspecialchars($doctor['docname']); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="docemail">Email</label>
                            <input type="email" id="docemail" name="docemail" value="<?php echo htmlspecialchars($doctor['docemail']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="docid">Doc ID</label>
                            <input type="number" id="docid" name="docid" value="<?php echo htmlspecialchars($doctor['docid']); ?>" readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="specialization">Specialty</label>
                        <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($doctor['specialization']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="docpassword">New Password (leave blank to keep current)</label>
                        <input type="password" id="docpassword" name="docpassword">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>