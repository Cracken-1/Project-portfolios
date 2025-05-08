<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newPassword = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
    $docid = $_SESSION['userid'];

    // Update password in the database
    $stmt = $database->prepare("UPDATE doctor SET password = ? WHERE docid = ?");
    $stmt->bind_param("si", $newPassword, $docid);
    if ($stmt->execute()) {
        echo "<script>alert('Password changed successfully!');</script>";
        header("location: index.php");
        exit();
    } else {
        echo "<script>alert('Error changing password.');</script>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/doctor.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <h1>Change Password</h1>
        <p>You are using a default password. Please change your password to continue.</p>
        <form method="POST">
            <label for="new_password">New Password:</label>
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="new_password" name="new_password" required>
            <button type="submit">Change Password</button>
        </form>
    </div>
    <script src="../js/main.js"></script>
</body>
</html>