<?php
// Connect to database
$conn = new mysqli("localhost", "root", "", "hms_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$docname = $_POST['docname'];
$docemail = $_POST['docemail'];
$docage = $_POST['docage'];
$docgender = $_POST['docgender'];
$specialization = $_POST['specialization'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash password

// Insert into doctors table
$sql = "INSERT INTO doctors (docname, docemail, docage, docgender, specialization, password)
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssisss", $docname, $docemail, $docage, $docgender, $specialization, $password);

if ($stmt->execute()) {
    echo "Doctor added successfully!";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
