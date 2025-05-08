<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");
$docid = $_SESSION['userid'];

// Validate request
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['action'])) {
    header("location: refill_requests.php");
    exit();
}

$refill_id = (int)$_GET['id'];
$action = $_GET['action'];

// Verify the doctor has permission to process this request
$stmt = $database->prepare("
    SELECT pr.* 
    FROM prescription_refills pr
    JOIN prescription p ON pr.prescription_id = p.prescription_id
    JOIN appointment a ON p.appoid = a.appoid
    WHERE pr.refill_id = ? AND a.docid = ?
");
$stmt->bind_param("ii", $refill_id, $docid);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    header("location: refill_requests.php");
    exit();
}

// Process the action
if ($action == 'approve') {
    $status = 'approved';
    $message = "Refill request approved successfully.";
    
    // Update the prescription's remaining refills if needed
    $database->query("
        UPDATE prescription 
        SET refills_remaining = refills_remaining + {$request['refill_quantity']}
        WHERE prescription_id = {$request['prescription_id']}
    ");
} elseif ($action == 'deny') {
    $status = 'denied';
    $message = "Refill request denied.";
} else {
    header("location: refill_requests.php");
    exit();
}

// Update the refill request
$stmt = $database->prepare("
    UPDATE prescription_refills 
    SET status = ?, processed_date = NOW(), processed_by = ?
    WHERE refill_id = ?
");
$stmt->bind_param("sii", $status, $docid, $refill_id);
$stmt->execute();
$stmt->close();

$_SESSION['refill_message'] = $message;
header("location: refill_requests.php");
exit();
?>