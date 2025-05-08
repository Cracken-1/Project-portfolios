<?php
session_start();
require_once("../db/db.php");

// Check if user is logged in as doctor
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("location: invoices.php");
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Invalid CSRF token";
    header("location: invoices.php");
    exit();
}

$docid = $_SESSION['userid'];
$invoice_id = $_POST['invoice_id'];

// Verify the doctor owns this invoice
$check_sql = "SELECT i.invoice_id 
              FROM invoices i
              JOIN appointment a ON i.appoid = a.appoid
              WHERE i.invoice_id = ? AND a.docid = ?";
$check_stmt = $database->prepare($check_sql);
$check_stmt->bind_param("ii", $invoice_id, $docid);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows == 0) {
    $_SESSION['error'] = "Invoice not found or you don't have permission to edit it";
    header("location: invoices.php");
    exit();
}

// Get current invoice data for version history
$current_sql = "SELECT * FROM invoices WHERE invoice_id = ?";
$current_stmt = $database->prepare($current_sql);
$current_stmt->bind_param("i", $invoice_id);
$current_stmt->execute();
$current_data = $current_stmt->get_result()->fetch_assoc();

// Get current items for version history
$current_items_sql = "SELECT * FROM invoice_items WHERE invoice_id = ?";
$current_items_stmt = $database->prepare($current_items_sql);
$current_items_stmt->bind_param("i", $invoice_id);
$current_items_stmt->execute();
$current_items = $current_items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Create version history before updating
$version_data = [
    'invoice' => $current_data,
    'items' => $current_items
];

$version_sql = "INSERT INTO invoice_versions (invoice_id, version_data, edited_by) VALUES (?, ?, ?)";
$version_stmt = $database->prepare($version_sql);
$version_stmt->bind_param("isi", $invoice_id, json_encode($version_data), $docid);
$version_stmt->execute();

// Process the form data
$invoice_date = $database->real_escape_string($_POST['invoice_date']);
$due_date = $database->real_escape_string($_POST['due_date']);
$notes = $database->real_escape_string($_POST['notes']);
$status = $database->real_escape_string($_POST['status']);
$payment_date = isset($_POST['payment_date']) ? $database->real_escape_string($_POST['payment_date']) : null;
$payment_method = isset($_POST['payment_method']) ? $database->real_escape_string($_POST['payment_method']) : null;

// Calculate new totals from items
$amount = 0;
$items = $_POST['items'];

foreach ($items as $item) {
    $amount += $item['quantity'] * $item['unit_price'];
}

// Apply tax and discount
$tax_rate = floatval($_POST['tax_rate']);
$tax_amount = $amount * ($tax_rate / 100);
$discount_amount = floatval($_POST['discount_amount']);
$total_amount = $amount + $tax_amount - $discount_amount;

// Begin transaction
$database->begin_transaction();

try {
    // Update the invoice in the database
    $update_sql = "UPDATE invoices SET 
                   invoice_date = ?, 
                   due_date = ?, 
                   amount = ?, 
                   tax_amount = ?, 
                   discount_amount = ?, 
                   total_amount = ?, 
                   status = ?, 
                   payment_method = ?, 
                   payment_date = ?, 
                   notes = ?, 
                   updated_at = NOW(),
                   edited_by = ?
                   WHERE invoice_id = ?";
                   
    $update_stmt = $database->prepare($update_sql);
    $update_stmt->bind_param("ssdddsssssi", 
        $invoice_date, 
        $due_date, 
        $amount, 
        $tax_amount, 
        $discount_amount, 
        $total_amount, 
        $status, 
        $payment_method, 
        $payment_date, 
        $notes,
        $docid,
        $invoice_id
    );
    
    if (!$update_stmt->execute()) {
        throw new Exception("Error updating invoice: " . $database->error);
    }
    
    // Delete existing items
    $delete_items_sql = "DELETE FROM invoice_items WHERE invoice_id = ?";
    $delete_items_stmt = $database->prepare($delete_items_sql);
    $delete_items_stmt->bind_param("i", $invoice_id);
    
    if (!$delete_items_stmt->execute()) {
        throw new Exception("Error deleting invoice items: " . $database->error);
    }
    
    // Insert new items
    $insert_item_sql = "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price) VALUES (?, ?, ?, ?)";
    $insert_item_stmt = $database->prepare($insert_item_sql);
    
    foreach ($items as $item) {
        $description = $database->real_escape_string($item['description']);
        $quantity = floatval($item['quantity']);
        $unit_price = floatval($item['unit_price']);
        
        $insert_item_stmt->bind_param("isdd", $invoice_id, $description, $quantity, $unit_price);
        if (!$insert_item_stmt->execute()) {
            throw new Exception("Error inserting invoice item: " . $database->error);
        }
    }
    
    // Commit transaction
    $database->commit();
    
    $_SESSION['success'] = "Invoice updated successfully";
} catch (Exception $e) {
    $database->rollback();
    $_SESSION['error'] = $e->getMessage();
}

header("location: invoices.php");
exit();
?>