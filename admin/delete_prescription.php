<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

// Include database connection
include("../db/db.php"); // Make sure this path is correct

// Check if prescription ID is provided in the URL
if (isset($_GET['id'])) {
    // Sanitize the input ID to prevent SQL injection
    $prescription_id = $database->real_escape_string($_GET['id']);

    // Prepare and execute the deletion query
    // Using prepared statements is more secure, but for simplicity with mysqli,
    // we'll use real_escape_string here. A better approach for production
    // would be PDO with prepared statements.
    $delete_query = "DELETE FROM prescription WHERE prescription_id = '$prescription_id'";

    if ($database->query($delete_query)) {
        // Deletion successful
        // Redirect back to the prescriptions page with a success message (optional)
        header("location: prescriptions_inventory.php?status=deleted_success");
        exit();
    } else {
        // Error during deletion
        // Redirect back with an error message (optional)
        header("location: prescriptions_inventory.php?status=deleted_error&error=" . urlencode($database->error));
        exit();
    }
} else {
    // No prescription ID provided
    // Redirect back with an error message (optional)
    header("location: prescriptions_inventory.php?status=no_id");
    exit();
}

// Close the database connection
$database->close();
?>
