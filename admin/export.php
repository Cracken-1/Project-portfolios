<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Fetch appointment data
$appointments = $database->query("
    SELECT a.appoid, p.pname, d.docname, a.appodate, a.appotime 
    FROM appointment a
    JOIN patient p ON a.pid = p.pid
    JOIN doctor d ON a.docid = d.docid
    ORDER BY a.appodate DESC
");

// Set headers for CSV file
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="appointment_history.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, ['ID', 'Patient', 'Doctor', 'Date', 'Time']);

// Write CSV rows
while ($row = $appointments->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit();