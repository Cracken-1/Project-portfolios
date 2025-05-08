<?php
// Database configuration
$host = 'localhost'; // Database host
$username = 'root'; // Database username
$password = ''; // Database password
$database = 'hms_db'; // Database name

// Create a connection to the database
$database = new mysqli($host, $username, $password, $database);

// Check for connection errors
if ($database->connect_error) {
    die("Connection failed: " . $database->connect_error);
}

// Set charset to UTF-8
$database->set_charset("utf8");
?>