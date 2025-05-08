<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

// Include database connection
include("../db/db.php"); // Make sure this path is correct

$message = ''; // To display success or error messages

// --- Handle Add Medicine Inventory Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_medicine'])) {
    // Sanitize and validate input
    $medicine_name = $database->real_escape_string($_POST['medicine_name']);
    $category = $database->real_escape_string($_POST['category']);
    $current_stock = (int)$_POST['current_stock'];
    $reorder_level = (int)$_POST['reorder_level'];
    $expiry_date = $database->real_escape_string($_POST['expiry_date']); // Assuming expiry date is used

    // Basic validation
    if (empty($medicine_name) || empty($category) || $current_stock < 0 || $reorder_level < 0 || empty($expiry_date)) {
        $message = '<div class="alert error">Please fill in all required fields and ensure stock levels are non-negative.</div>';
    } else {
        // Check if medicine already exists (optional, you might want to update stock instead)
        $check_query = $database->query("SELECT * FROM medicine_inventory WHERE medicine_name = '$medicine_name'");
        if ($check_query->num_rows > 0) {
            $message = '<div class="alert warning">Medicine already exists. Consider updating the stock instead.</div>';
        } else {
            // Insert into database
            $insert_query = "INSERT INTO medicine_inventory (medicine_name, category, current_stock, reorder_level, expiry_date)
                             VALUES ('$medicine_name', '$category', $current_stock, $reorder_level, '$expiry_date')";

            if ($database->query($insert_query)) {
                $message = '<div class="alert success">Medicine added successfully!</div>';
            } else {
                $message = '<div class="alert error">Error adding medicine: ' . $database->error . '</div>';
            }
        }
    }
}

// --- Handle Add Equipment Inventory Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_equipment'])) {
    // Sanitize and validate input
    $equipment_name = $database->real_escape_string($_POST['equipment_name']);
    $location = $database->real_escape_string($_POST['location']);
    $purchase_date = $database->real_escape_string($_POST['purchase_date']);
    $last_maintenance_date = $database->real_escape_string($_POST['last_maintenance_date']);
    $next_maintenance_date = $database->real_escape_string($_POST['next_maintenance_date']);

    // Basic validation
    if (empty($equipment_name) || empty($location) || empty($purchase_date) || empty($last_maintenance_date) || empty($next_maintenance_date)) {
         $message = '<div class="alert error">Please fill in all required fields for equipment.</div>';
    } else {
         // Insert into database
        $insert_query = "INSERT INTO equipment_inventory (equipment_name, location, purchase_date, last_maintenance_date, next_maintenance_date)
                         VALUES ('$equipment_name', '$location', '$purchase_date', '$last_maintenance_date', '$next_maintenance_date')";

        if ($database->query($insert_query)) {
            $message = '<div class="alert success">Equipment added successfully!</div>';
        } else {
            $message = '<div class="alert error">Error adding equipment: ' . $database->error . '</div>';
        }
    }
}


// --- Fetch Inventory Data for Display ---
$medicine_inventory = $database->query("SELECT * FROM medicine_inventory ORDER BY medicine_name ASC")->fetch_all(MYSQLI_ASSOC);
$equipment_inventory = $database->query("SELECT * FROM equipment_inventory ORDER BY equipment_name ASC")->fetch_all(MYSQLI_ASSOC);


// Close the database connection
$database->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Inventory</title>
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
        }
        .main-content {
            padding: 30px;
            margin-left: 250px; /* Adjust based on your sidebar width */
            transition: margin-left 0.3s ease;
        }
        .inventory-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin-bottom: 30px;
        }
        .inventory-container h1 {
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

        /* Form Styling */
        .form-section {
            background: #ecf0f1; /* Light background for form section */
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
         .form-section h3 {
             font-size: 1.6rem;
             color: #34495e;
             margin-top: 0;
             margin-bottom: 20px;
             border-bottom: 1px solid #bdc3c7;
             padding-bottom: 10px;
         }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); /* Responsive grid for form inputs */
            gap: 20px;
        }

        .form-group {
            margin-bottom: 15px; /* Space between form groups */
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 0.95rem;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box; /* Include padding and border in element's total width */
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

         .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group input[type="date"]:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.2);
        }

        .form-actions {
            margin-top: 20px;
            text-align: right; /* Align button to the right */
        }

        .btn-submit {
            padding: 12px 25px;
            background: #2ecc71; /* Green submit button */
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.1s ease;
        }

        .btn-submit:hover {
            background: #27ae60;
            transform: translateY(-1px); /* Slight press effect */
        }

        .btn-submit:active {
             transform: translateY(0);
        }


        /* Inventory Tables */
         .inventory-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .inventory-table th, .inventory-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .inventory-table th {
            background: #bdc3c7;
            font-weight: 600;
            color: #34495e;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        .inventory-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .inventory-table tbody tr:hover {
            background-color: #f0f0f0;
        }

        .inventory-table .action-buttons .btn {
             padding: 5px 10px;
             font-size: 0.8rem;
        }

         /* Status Indicators */
        .status-low, .status-due-soon, .status-overdue {
            color: #e74c3c; /* Red for low/due soon/overdue */
            font-weight: 600;
        }

        .status-ok, .status-operational {
            color: #2ecc71; /* Green for ok/operational */
            font-weight: 600;
        }

        /* Alerts */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
        }
        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
         .alert.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }


        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
             .inventory-container h1 {
                font-size: 1.8rem;
            }
            .section-title {
                flex-direction: column;
                align-items: flex-start;
            }
             .section-title h2 {
                font-size: 1.5rem;
            }
            .form-grid {
                grid-template-columns: 1fr; /* Stack columns */
            }
             .inventory-table th, .inventory-table td {
                 padding: 10px 15px;
            }
        }
    </style>
</head>
<body>
    <?php include("../includes/header.php"); // Make sure this path is correct ?>
    <?php include("../includes/sidebar.php"); // Make sure this path is correct ?>

    <div class="main-content">
        <div class="inventory-container">
            <h1><i class="fas fa-boxes"></i> Inventory Management</h1>

            <?php echo $message; // Display messages ?>

            <div class="form-section">
                <h3><i class="fas fa-plus-circle"></i> Add New Medicine</h3>
                <form action="" method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="medicine_name">Medicine Name:</label>
                            <input type="text" id="medicine_name" name="medicine_name" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category:</label>
                             <select id="category" name="category" required>
                                 <option value="">Select Category</option>
                                 <option value="Antibiotics">Antibiotics</option>
                                 <option value="Pain Relievers">Pain Relievers</option>
                                 <option value="Antihistamines">Antihistamines</option>
                                 <option value="Antidepressants">Antidepressants</option>
                                 <option value="Blood Pressure">Blood Pressure</option>
                                 <option value="Diabetes">Diabetes</option>
                                 <option value="Other">Other</option>
                             </select>
                        </div>
                        <div class="form-group">
                            <label for="current_stock">Current Stock:</label>
                            <input type="number" id="current_stock" name="current_stock" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="reorder_level">Reorder Level:</label>
                            <input type="number" id="reorder_level" name="reorder_level" min="0" required>
                        </div>
                         <div class="form-group">
                            <label for="expiry_date">Expiry Date:</label>
                            <input type="date" id="expiry_date" name="expiry_date" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add_medicine" class="btn-submit"><i class="fas fa-plus"></i> Add Medicine</button>
                    </div>
                </form>
            </div>

             <div class="form-section">
                <h3><i class="fas fa-plus-circle"></i> Add New Equipment</h3>
                <form action="" method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="equipment_name">Equipment Name:</label>
                            <input type="text" id="equipment_name" name="equipment_name" required>
                        </div>
                        <div class="form-group">
                            <label for="location">Location:</label>
                            <input type="text" id="location" name="location" required>
                        </div>
                        <div class="form-group">
                            <label for="purchase_date">Purchase Date:</label>
                            <input type="date" id="purchase_date" name="purchase_date" required>
                        </div>
                         <div class="form-group">
                            <label for="last_maintenance_date">Last Maintenance Date:</label>
                            <input type="date" id="last_maintenance_date" name="last_maintenance_date" required>
                        </div>
                         <div class="form-group">
                            <label for="next_maintenance_date">Next Maintenance Date:</label>
                            <input type="date" id="next_maintenance_date" name="next_maintenance_date" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add_equipment" class="btn-submit"><i class="fas fa-plus"></i> Add Equipment</button>
                    </div>
                </form>
            </div>


            <div class="section-title">
                <h2><i class="fas fa-pills"></i> Medicine Inventory</h2>
                 <div class="section-actions">
                    </div>
            </div>
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Reorder</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($medicine_inventory)): ?>
                        <?php foreach ($medicine_inventory as $medicine): ?>
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
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit_medicine.php?id=<?php echo $medicine['medicine_id']; ?>" class="btn btn-view" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="delete_medicine.php?id=<?php echo $medicine['medicine_id']; ?>" class="btn btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this medicine?');"><i class="fas fa-trash-alt"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7">No medicine inventory data found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="section-title">
                <h2><i class="fas fa-tools"></i> Equipment Inventory</h2>
                 <div class="section-actions">
                    </div>
            </div>
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Equipment</th>
                        <th>Location</th>
                        <th>Purchase Date</th>
                        <th>Last Maintenance</th>
                        <th>Next Maintenance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($equipment_inventory)): ?>
                        <?php foreach ($equipment_inventory as $equipment): ?>
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
                                <td>
                                    <div class="action-buttons">
                                         <a href="edit_equipment.php?id=<?php echo $equipment['equipment_id']; ?>" class="btn btn-view" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="delete_equipment.php?id=<?php echo $equipment['equipment_id']; ?>" class="btn btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this equipment?');"><i class="fas fa-trash-alt"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7">No equipment inventory data found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>


        </div>
    </div>

    <?php include("../includes/footer.php"); // Make sure this path is correct ?>
</body>
</html>
