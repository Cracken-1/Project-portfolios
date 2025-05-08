<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

include("../db/db.php");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_medicine'])) {
        // Add new medicine logic
    } elseif (isset($_POST['update_medicine'])) {
        // Update medicine logic
    }
}

// Fetch inventory data
$medicines = $database->query("SELECT * FROM medicine_inventory ORDER BY medicine_name")->fetch_all(MYSQLI_ASSOC);
$equipment = $database->query("SELECT * FROM equipment_inventory ORDER BY equipment_name")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Main Container */
        .inventory-container {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Card Styles */
        .inventory-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
            transition: transform 0.3s ease;
        }
        
        .inventory-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .card-title {
            font-size: 1.25rem;
            color: #2c3e50;
            margin: 0;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-in-stock {
            background: #d4edda;
            color: #155724;
        }
        
        .status-low-stock {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Action Buttons */
        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.875rem;
            margin-right: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #4a6cf7;
            color: white;
            border: none;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
        }
        
        /* Inventory Grid */
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            /* [Rest of your modal styles] */
        }
    </style>
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="inventory-container">
        <h1><i class="fas fa-warehouse"></i> Inventory Management</h1>
        
        <!-- Medicine Inventory Card -->
        <div class="inventory-card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-pills"></i> Medicine Inventory</h2>
                <button class="btn-primary" onclick="openMedicineModal()">
                    <i class="fas fa-plus"></i> Add Medicine
                </button>
            </div>
            
            <div class="inventory-grid">
                <?php foreach ($medicines as $medicine): ?>
                    <div class="inventory-card">
                        <h3><?php echo htmlspecialchars($medicine['medicine_name']); ?></h3>
                        <p><strong>Category:</strong> <?php echo htmlspecialchars($medicine['category']); ?></p>
                        <p><strong>Stock:</strong> <?php echo $medicine['current_stock']; ?></p>
                        
                        <?php if ($medicine['current_stock'] <= $medicine['reorder_level']): ?>
                            <span class="status-badge status-low-stock">Low Stock</span>
                        <?php else: ?>
                            <span class="status-badge status-in-stock">In Stock</span>
                        <?php endif; ?>
                        
                        <div class="action-buttons" style="margin-top: 1rem;">
                            <button class="action-btn btn-secondary">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="action-btn btn-danger">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Equipment Inventory Card -->
        <div class="inventory-card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-tools"></i> Equipment Inventory</h2>
                <button class="btn-primary" onclick="openEquipmentModal()">
                    <i class="fas fa-plus"></i> Add Equipment
                </button>
            </div>
            
            <div class="inventory-grid">
                <?php foreach ($equipment as $item): ?>
                    <div class="inventory-card">
                        <h3><?php echo htmlspecialchars($item['equipment_name']); ?></h3>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($item['location']); ?></p>
                        <p><strong>Last Maintenance:</strong> <?php echo date('M j, Y', strtotime($item['last_maintenance_date'])); ?></p>
                        
                        <div class="action-buttons" style="margin-top: 1rem;">
                            <button class="action-btn btn-secondary">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="action-btn btn-danger">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modals would go here -->
    <script>
        // JavaScript functions for modals
        function openMedicineModal() {
            // Implementation for medicine modal
        }
        
        function openEquipmentModal() {
            // Implementation for equipment modal
        }
    </script>
</body>
</html>