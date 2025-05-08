<?php
session_start();
require_once("../db/db.php");

// Check if user is logged in as doctor
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

$docid = $_SESSION['userid'];

// Fetch appointments that don't have invoices yet
$sql = "SELECT a.appoid, a.appodate, a.appotime, p.pname 
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        LEFT JOIN invoices i ON a.appoid = i.appoid
        WHERE a.docid = ? AND i.invoice_id IS NULL
        ORDER BY a.appodate DESC";
$stmt = $database->prepare($sql);
$stmt->bind_param("i", $docid);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include("../includes/header.php");
include("../includes/sidebar.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Appointment for Invoice - Doctor Panel</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/invoices.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .appointments-container {
            max-width: 1000px;
            margin: 20px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .appointments-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .appointments-table th, 
        .appointments-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .appointments-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #34495e;
        }
        
        .appointments-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .btn-create-invoice {
            padding: 6px 12px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .btn-create-invoice:hover {
            background-color: #2980b9;
        }
        
        .no-appointments {
            text-align: center;
            padding: 40px 20px;
            color: #95a5a6;
        }
        
        .no-appointments i {
            font-size: 48px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="appointments-container">
            <div class="page-header">
                <h1>Select Appointment for Invoice</h1>
                <a href="invoices.php" class="btn-create">
                    <i class="fas fa-arrow-left"></i> Back to Invoices
                </a>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($appointments)): ?>
                <div class="no-appointments">
                    <i class="fas fa-calendar-times"></i>
                    <p>No appointments available for invoicing</p>
                    <p>All appointments already have invoices or none are scheduled</p>
                </div>
            <?php else: ?>
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($appointment['appodate'])) ?></td>
                                <td><?= date('h:i A', strtotime($appointment['appotime'])) ?></td>
                                <td><?= htmlspecialchars($appointment['pname']) ?></td>
                                <td>
                                    <a href="create_invoice.php?appoid=<?= $appointment['appoid'] ?>" class="btn-create-invoice">
                                        <i class="fas fa-file-invoice"></i> Create Invoice
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>