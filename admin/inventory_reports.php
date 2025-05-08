<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

// Include database connection
include("../db/db.php"); // Make sure this path is correct

// --- Report Generation Logic ---
// Similar to prescription reports, this is a basic example.
// Real-world reports would need more filtering, pagination, export options, etc.

// Fetch all medicine inventory for the report
$medicine_inventory_report = $database->query("SELECT * FROM medicine_inventory ORDER BY medicine_name ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch all equipment inventory for the report
$equipment_inventory_report = $database->query("SELECT * FROM equipment_inventory ORDER BY equipment_name ASC")->fetch_all(MYSQLI_ASSOC);

// Calculate summary statistics for medicine inventory
$medicine_stats = $database->query("
    SELECT
        COUNT(*) as total_medicines,
        SUM(current_stock) as total_stock,
        COUNT(CASE WHEN current_stock <= reorder_level THEN 1 END) as low_stock_items
    FROM medicine_inventory
")->fetch_assoc();

// Calculate summary statistics for equipment inventory
$equipment_stats = $database->query("
    SELECT
        COUNT(*) as total_equipment,
        COUNT(CASE WHEN next_maintenance_date <= CURDATE() THEN 1 END) as overdue_maintenance,
        COUNT(CASE WHEN next_maintenance_date > CURDATE() AND next_maintenance_date <= CURDATE() + INTERVAL 7 DAY THEN 1 END) as due_soon_maintenance
    FROM equipment_inventory
")->fetch_assoc();


// Close the database connection
$database->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Report</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7f6;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        .report-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin: 20px auto;
            max-width: 1000px;
        }
        .report-container h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #2c3e50;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
         .section-title {
            margin: 30px 0 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .section-title h2 {
             margin: 0;
             padding-bottom: 0;
             border-bottom: none;
             font-size: 1.8rem;
             font-weight: 600;
             color: #2c3e50;
             display: flex;
             align-items: center;
             gap: 10px;
        }
        .report-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background-color: #ecf0f1;
            border-radius: 8px;
        }
        .summary-item h3 {
            margin: 0;
            font-size: 1.5rem;
            color: #34495e;
        }
        .summary-item p {
            margin: 5px 0 0;
            font-size: 0.9rem;
            color: #555;
        }
        .report-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .report-table th, .report-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .report-table th {
            background: #bdc3c7;
            font-weight: 600;
            color: #34495e;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
         .report-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .report-table tbody tr:hover {
            background-color: #f0f0f0;
        }

         /* Status Indicators */
        .status-low, .status-due-soon, .status-overdue {
            color: #e74c3c; /* Red */
            font-weight: 600;
        }

        .status-ok, .status-operational {
            color: #2ecc71; /* Green */
            font-weight: 600;
        }

        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #95a5a6;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 1rem;
            transition: background-color 0.2s ease;
        }
        .back-button:hover {
            background: #7f8c8d;
        }

         /* Print specific styles */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            .report-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                border: none;
            }
             .back-button {
                display: none; /* Hide back button when printing */
            }
             .report-table th {
                background-color: #ccc !important; /* Ensure header background prints */
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <h1><i class="fas fa-file-alt"></i> Inventory Report</h1>

        <div class="section-title">
            <h2><i class="fas fa-pills"></i> Medicine Inventory Summary</h2>
        </div>
        <div class="report-summary-grid">
            <div class="summary-item">
                <h3><?php echo $medicine_stats['total_medicines']; ?></h3>
                <p>Total Medicine Types</p>
            </div>
             <div class="summary-item">
                <h3><?php echo $medicine_stats['total_stock']; ?></h3>
                <p>Total Stock Units</p>
            </div>
             <div class="summary-item">
                <h3 class="status-low"><?php echo $medicine_stats['low_stock_items']; ?></h3>
                <p>Low Stock Items</p>
            </div>
        </div>

        <div class="section-title">
             <h2>Medicine Inventory Details</h2>
        </div>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Reorder</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($medicine_inventory_report)): ?>
                    <?php foreach ($medicine_inventory_report as $medicine): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($medicine['medicine_name']); ?></td>
                            <td><?php echo htmlspecialchars($medicine['category']); ?></td>
                            <td><?php echo $medicine['current_stock']; ?></td>
                            <td><?php echo $medicine['reorder_level']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($medicine['expiry_date'])); ?></td>
                            <td>
                                <?php if ($medicine['current_stock'] <= $medicine['reorder_level']): ?>
                                    <span class="status-low">Low Stock</span>
                                <?php else: ?>
                                    <span class="status-ok">In Stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">No medicine inventory data found for this report.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="section-title">
            <h2><i class="fas fa-tools"></i> Equipment Inventory Summary</h2>
        </div>
        <div class="report-summary-grid">
            <div class="summary-item">
                <h3><?php echo $equipment_stats['total_equipment']; ?></h3>
                <p>Total Equipment Types</p>
            </div>
             <div class="summary-item">
                <h3 class="status-low"><?php echo $equipment_stats['overdue_maintenance']; ?></h3>
                <p>Overdue Maintenance</p>
            </div>
             <div class="summary-item">
                <h3 class="status-due-soon"><?php echo $equipment_stats['due_soon_maintenance']; ?></h3>
                <p>Maintenance Due Soon</p>
            </div>
        </div>

        <div class="section-title">
             <h2>Equipment Inventory Details</h2>
        </div>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Equipment</th>
                    <th>Location</th>
                    <th>Purchase Date</th>
                    <th>Last Maintenance</th>
                    <th>Next Maintenance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($equipment_inventory_report)): ?>
                    <?php foreach ($equipment_inventory_report as $equipment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($equipment['equipment_name']); ?></td>
                            <td><?php echo htmlspecialchars($equipment['location']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($equipment['purchase_date'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($equipment['last_maintenance_date'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($equipment['next_maintenance_date'])); ?></td>
                            <td>
                                <?php
                                $today = new DateTime();
                                $next_maintenance = new DateTime($equipment['next_maintenance_date']);
                                $interval = $today->diff($next_maintenance);
                                $days = (int)$interval->format('%r%a');

                                if ($days <= 0) {
                                    echo '<span class="status-low status-overdue">Overdue</span>';
                                } elseif ($days <= 7) {
                                    echo '<span class="status-due-soon">Due Soon (' . $days . ' days)</span>';
                                } else {
                                    echo '<span class="status-ok status-operational">Operational</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">No equipment inventory data found for this report.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>


        <a href="prescriptions_inventory.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    </div>
</body>
</html>
