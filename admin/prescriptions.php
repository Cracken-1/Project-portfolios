<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login/login-admin.php");
    exit();
}

// Include database connection
include("../db/db.php"); // Make sure this path is correct

// --- Data Fetching ---
// Fetch prescription statistics
$prescription_stats = $database->query("
    SELECT
        COUNT(*) as total_prescriptions,
        COUNT(DISTINCT doctor_id) as doctors_prescribing,
        COUNT(DISTINCT patient_id) as patients_with_prescriptions,
        SUM(total_medicines) as total_medicines_prescribed
    FROM (
        SELECT
            p.appoid,
            d.docid as doctor_id,
            a.pid as patient_id,
            -- Count medicines by checking the JSON structure
            JSON_LENGTH(p.medication) as total_medicines
        FROM prescription p
        JOIN appointment a ON p.appoid = a.appoid
        JOIN doctor d ON a.docid = d.docid
        -- Grouping by prescription_id might not be necessary if appoid is unique per prescription,
        -- but keeping it ensures distinct prescriptions are counted if there's a one-to-many appoid-prescription relation (unlikely but safe).
        GROUP BY p.prescription_id
    ) as prescription_details
")->fetch_assoc();

// Fetch recent prescriptions
// Added prescription_id to the select for potential actions (like delete)
$recent_prescriptions = $database->query("
    SELECT p.*, d.docname, pt.pname, a.appodate
    FROM prescription p
    JOIN appointment a ON p.appoid = a.appoid
    JOIN doctor d ON a.docid = d.docid
    JOIN patient pt ON a.pid = pt.pid
    ORDER BY p.prescription_date DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Fetch medicine inventory
$medicine_inventory = $database->query("
    SELECT * FROM medicine_inventory
    ORDER BY current_stock ASC
    LIMIT 15
")->fetch_all(MYSQLI_ASSOC);

// Fetch equipment inventory
$equipment_inventory = $database->query("
    SELECT * FROM equipment_inventory
    ORDER BY last_maintenance_date ASC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Fetch most prescribed medicines
// Assuming 'prescribed_medicines' is a table linking prescriptions to individual medicine items
// If medication is stored as JSON in the prescription table, this query needs adjustment.
// The original query seems to assume a separate table. Keeping it as is for now.
$top_medicines = $database->query("
    SELECT
        medicine_name,
        COUNT(*) as prescription_count,
        SUM(quantity) as total_quantity
    FROM prescribed_medicines -- This table needs to exist and link to prescriptions
    GROUP BY medicine_name
    ORDER BY prescription_count DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// --- Handle Deletion Request (Conceptual) ---
// Note: A real-world delete would involve a separate PHP file
// (e.g., delete_prescription.php) that handles the POST or GET request,
// validates the user, sanitizes the input ID, performs the DELETE query,
// and redirects back or sends a JSON response.
// The code below is just a placeholder to show where it would go IF
// this file also handled the delete logic (not recommended for security/structure).
/*
if (isset($_GET['delete_id'])) {
    $delete_id = $database->real_escape_string($_GET['delete_id']);
    // Perform deletion query
    $delete_success = $database->query("DELETE FROM prescription WHERE prescription_id = '$delete_id'");
    if ($delete_success) {
        // Redirect back to refresh the page, maybe with a success message
        header("location: prescriptions_inventory.php?status=deleted");
        exit();
    } else {
        // Handle error
        header("location: prescriptions_inventory.php?status=error");
        exit();
    }
}
*/

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Prescriptions & Inventory</title>
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Basic Reset and Typography */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7f6; /* Light grey background */
            color: #333;
            line-height: 1.6;
        }

        .main-content {
            padding: 30px;
            margin-left: 250px; /* Adjust based on your sidebar width */
            transition: margin-left 0.3s ease;
        }

        /* Container Styling */
        .dashboard-container {
            background: #ffffff; /* White background for main content area */
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08); /* Softer, larger shadow */
            padding: 30px;
            margin-bottom: 30px;
        }

        h1, h2, h3 {
            color: #2c3e50; /* Darker blue-grey for headings */
            margin-bottom: 20px;
        }

        h1 {
            font-size: 2.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 2px solid #e0e0e0; /* Subtle bottom border */
            padding-bottom: 15px;
        }

        h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-top: 30px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        h3 {
             font-size: 1.4rem;
             font-weight: 600;
             margin-bottom: 15px;
        }


        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* Adjusted min width */
            gap: 25px; /* Increased gap */
            margin-bottom: 40px;
        }

        .stat-card {
            padding: 25px; /* Increased padding */
            border-radius: 10px; /* Slightly more rounded corners */
            color: white;
            position: relative; /* For potential icons/graphics */
            overflow: hidden; /* Hide overflowing pseudo-elements */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
             transform: translateY(-5px); /* Slight lift effect */
             box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        /* Stat Card Colors - Using a professional palette */
        .stat-card.prescriptions { background: linear-gradient(45deg, #4a6cf7, #6a8cfd); } /* Blue gradient */
        .stat-card.doctors { background: linear-gradient(45deg, #1abc9c, #16a085); } /* Teal gradient */
        .stat-card.patients { background: linear-gradient(45deg, #2ecc71, #27ae60); } /* Green gradient */
        .stat-card.medicines { background: linear-gradient(45deg, #9b59b6, #8e44ad); } /* Purple gradient */

        .stat-card h3 {
            margin: 0;
            font-size: 2rem; /* Larger number */
            font-weight: 700;
            color: white; /* Ensure text is white */
        }

        .stat-card p {
            margin: 5px 0 0;
            font-size: 1rem; /* Slightly larger text */
            opacity: 0.95; /* Better readability */
            color: rgba(255, 255, 255, 0.9); /* Slightly transparent white */
        }

        /* Add a subtle pattern or icon to stat cards */
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZD0iTTAgMHYxMDBoMTAwVjBIMHptNSA1aDkwdjkwSDVWMXoiIGZpbGw9IiMwMDAiIGZpbGwtb3BhY2l0eT0iMC4wNSIvPjwvc3ZnPg=='); /* Subtle pattern */
            opacity: 0.2;
            pointer-events: none;
        }


        /* Section Titles and Actions */
        .section-title {
            margin: 30px 0 20px; /* Adjusted spacing */
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            gap: 15px; /* Gap between title and buttons */
        }

        .section-title h2 {
             margin: 0; /* Reset margin for h2 inside flex container */
             padding-bottom: 0; /* Reset padding */
             border-bottom: none; /* Remove border */
        }

        .section-actions {
            display: flex;
            gap: 10px; /* Gap between action buttons */
            flex-wrap: wrap;
        }


        /* Data Tables */
        .data-table {
            width: 100%;
            border-collapse: separate; /* Use separate to allow border-radius on cells */
            border-spacing: 0; /* Remove space between borders */
            margin-bottom: 30px;
            border: 1px solid #e0e0e0; /* Outer border */
            border-radius: 8px; /* Rounded corners for the table */
            overflow: hidden; /* Hide overflowing content */
        }

        .data-table th, .data-table td {
            padding: 15px 20px; /* Increased padding */
            text-align: left;
            border-bottom: 1px solid #eee; /* Lighter border */
        }

        .data-table th {
            background: #ecf0f1; /* Light grey header background */
            font-weight: 600;
            color: #34495e; /* Darker text */
            text-transform: uppercase; /* Uppercase headers */
            font-size: 0.9rem;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none; /* Remove bottom border on last row */
        }

        .data-table tbody tr:hover {
            background-color: #f8f9fa; /* Highlight on hover */
        }


        /* Status Indicators */
        .status-low, .status-due-soon {
            color: #e74c3c; /* Red for low/due soon */
            font-weight: 600;
        }

        .status-ok, .status-operational {
            color: #2ecc71; /* Green for ok/operational */
            font-weight: 600;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 15px; /* Adjusted padding */
            border-radius: 6px; /* More rounded buttons */
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            transition: background-color 0.2s ease, opacity 0.2s ease;
            border: none; /* Remove default button border */
        }

        .btn i {
            margin-right: 8px; /* Increased space for icon */
        }

        .btn-view {
            background: #3498db; /* Blue */
            color: white;
        }

        .btn-print {
            background: #95a5a6; /* Grey */
            color: white;
        }

        .btn-delete {
            background: #e74c3c; /* Red */
            color: white;
        }

        .btn-view:hover { background: #2980b9; }
        .btn-print:hover { background: #7f8c8d; }
        .btn-delete:hover { background: #c0392b; }

         .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }


        /* Prescription Cards Grid */
        .prescription-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Cards with min width 300px */
            gap: 25px;
            margin-bottom: 30px;
        }

        .prescription-card {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.06);
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Push footer to bottom */
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .prescription-card:hover {
             transform: translateY(-3px);
             box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .card-header h4 {
            margin: 0;
            font-size: 1.1rem;
            color: #34495e;
        }

        .card-body p {
            margin: 8px 0;
            font-size: 0.95rem;
            color: #555;
        }

        .card-body p strong {
            color: #333;
            font-weight: 600;
        }

        .card-footer {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end; /* Align buttons to the right */
            gap: 10px;
        }

        /* Search/Filter Input */
        .filter-input {
            margin-bottom: 20px;
            padding: 12px 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            width: 100%;
            box-sizing: border-box; /* Include padding and border in element's total width */
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .filter-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.2);
        }


        /* Inventory Grid */
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); /* Adjusted min width */
            gap: 25px;
            margin-top: 30px;
        }

        .inventory-section {
            background: #ecf0f1; /* Light background for inventory sections */
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .inventory-section h3 {
             margin-top: 0;
             display: flex;
             align-items: center;
             gap: 8px;
        }


        /* Chart Container */
        .chart-container {
            height: 350px; /* Adjusted height */
            margin: 40px 0;
            background: #ffffff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        /* Usage Rate Bar */
        .usage-bar-container {
            display: flex;
            align-items: center;
        }

        .usage-bar {
            width: 120px; /* Fixed width for the bar */
            background: #e0e0e0;
            height: 12px; /* Thicker bar */
            margin-right: 10px;
            border-radius: 6px;
            overflow: hidden; /* Hide overflowing fill */
        }

        .usage-bar-fill {
            height: 100%;
            background: #3498db; /* Blue fill */
            border-radius: 6px;
            transition: width 0.5s ease; /* Smooth transition for fill */
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0; /* Remove margin on smaller screens */
                padding: 20px;
            }

            .stats-grid, .inventory-grid, .prescription-cards-grid {
                grid-template-columns: 1fr; /* Stack columns */
            }

            .section-title {
                flex-direction: column;
                align-items: flex-start;
            }

            .section-actions {
                width: 100%;
                justify-content: flex-start;
                margin-top: 10px;
            }

            .data-table th, .data-table td {
                 padding: 10px 15px; /* Reduce padding */
            }

            .btn {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
        }

    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include("../includes/header.php"); // Make sure this path is correct ?>
    <?php include("../includes/sidebar.php"); // Make sure this path is correct ?>

    <div class="main-content">
        <div class="dashboard-container">
            <h1><i class="fas fa-prescription-bottle-alt"></i> Prescriptions & Inventory Management</h1>

            <div class="stats-grid">
                <div class="stat-card prescriptions">
                    <h3><?php echo $prescription_stats['total_prescriptions']; ?></h3>
                    <p>Total Prescriptions</p>
                </div>
                <div class="stat-card doctors">
                    <h3><?php echo $prescription_stats['doctors_prescribing']; ?></h3>
                    <p>Doctors Prescribing</p>
                </div>
                <div class="stat-card patients">
                    <h3><?php echo $prescription_stats['patients_with_prescriptions']; ?></h3>
                    <p>Patients With Prescriptions</p>
                </div>
                <div class="stat-card medicines">
                    <h3><?php echo $prescription_stats['total_medicines_prescribed']; ?></h3>
                    <p>Total Medicines Prescribed</p>
                </div>
            </div>

            <div class="chart-container">
                <canvas id="medicineChart"></canvas>
            </div>

            <div class="section-title">
                <h2><i class="fas fa-history"></i> Recent Prescriptions</h2>
                <div class="section-actions">
                     <input type="text" id="prescriptionFilter" class="filter-input" placeholder="Search prescriptions...">
                     <a href="prescription_reports.php" class="btn btn-print">
                         <i class="fas fa-file-export"></i> Generate Report
                     </a>
                </div>
            </div>

            <div class="prescription-cards-grid" id="prescriptionCardsContainer">
                <?php if (!empty($recent_prescriptions)): ?>
                    <?php foreach ($recent_prescriptions as $rx): ?>
                        <div class="prescription-card">
                            <div class="card-header">
                                <h4>Prescription #<?php echo htmlspecialchars($rx['prescription_id']); ?></h4>
                                <span><?php echo date('M j, Y', strtotime($rx['prescription_date'])); ?></span>
                            </div>
                            <div class="card-body">
                                <p><strong>Patient:</strong> <?php echo htmlspecialchars($rx['pname']); ?></p>
                                <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($rx['docname']); ?></p>
                                <p><strong>Appointment Date:</strong> <?php echo date('M j, Y', strtotime($rx['appodate'])); ?></p>
                                <p>
                                    <strong>Medications:</strong>
                                    <?php
                                    $meds = json_decode($rx['medication'], true);
                                    if (!empty($meds)) {
                                        $med_list = array_map(function($m) {
                                            return htmlspecialchars($m['name']);
                                        }, $meds);
                                        echo implode(', ', $med_list);
                                    } else {
                                        echo 'No medications listed';
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="card-footer">
                                <a href="view_prescription.php?id=<?php echo $rx['prescription_id']; ?>" class="btn btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="print_prescription.php?id=<?php echo $rx['prescription_id']; ?>" class="btn btn-print" target="_blank">
                                    <i class="fas fa-print"></i> Print
                                </a>
                                <a href="delete_prescription.php?id=<?php echo $rx['prescription_id']; ?>"
                                   class="btn btn-delete"
                                   onclick="return confirm('Are you sure you want to delete Prescription #<?php echo htmlspecialchars($rx['prescription_id']); ?>? This action cannot be undone.');">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No recent prescriptions found.</p>
                <?php endif; ?>
            </div>


            <div class="section-title">
                <h2><i class="fas fa-clipboard-list"></i> Inventory Overview</h2>
                <div class="section-actions">
                    <a href="inventory_managements.php" class="btn btn-view">
                        <i class="fas fa-cog"></i> Manage Inventory
                    </a>
                    <a href="inventory_reports.php" class="btn btn-print">
                        <i class="fas fa-file-export"></i> Inventory Report
                    </a>
                </div>
            </div>

            <div class="inventory-grid">
                <div class="inventory-section">
                    <h3><i class="fas fa-pills"></i> Medicine Inventory</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Category</th>
                                <th>Stock</th>
                                <th>Reorder</th>
                                <th>Status</th>
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
                                <tr><td colspan="5">No medicine inventory data found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="inventory-section">
                    <h3><i class="fas fa-tools"></i> Equipment Inventory</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Location</th>
                                <th>Last Maint.</th>
                                <th>Next Maint.</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($equipment_inventory)): ?>
                                <?php foreach ($equipment_inventory as $equipment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($equipment['equipment_name']); ?></td>
                                        <td><?php echo htmlspecialchars($equipment['location']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($equipment['last_maintenance_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($equipment['next_maintenance_date'])); ?></td>
                                        <td>
                                            <?php
                                            $today = new DateTime();
                                            $next_maintenance = new DateTime($equipment['next_maintenance_date']);
                                            $interval = $today->diff($next_maintenance);
                                            $days = (int)$interval->format('%r%a'); // Get signed number of days

                                            if ($days <= 0) { // Due or overdue
                                                echo '<span class="status-low">Overdue</span>';
                                            } elseif ($days <= 7) { // Due within 7 days
                                                echo '<span class="status-due-soon">Due Soon (' . $days . ' days)</span>';
                                            } else { // Operational
                                                echo '<span class="status-ok">Operational</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5">No equipment inventory data found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section-title">
                <h2><i class="fas fa-chart-line"></i> Top Prescribed Medicines</h2>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Prescription Count</th>
                        <th>Total Quantity</th>
                        <th>Current Stock</th>
                        <th>Usage Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($top_medicines)): ?>
                        <?php foreach ($top_medicines as $medicine):
                            // NOTE: The current_stock here is a placeholder (rand).
                            // In a real system, you MUST fetch this from your medicine_inventory table
                            // by joining or looking up the medicine name.
                            // Example (requires modification of the $top_medicines query or a separate lookup):
                            // $stock_lookup = $database->query("SELECT current_stock FROM medicine_inventory WHERE medicine_name = '" . $database->real_escape_string($medicine['medicine_name']) . "'")->fetch_assoc();
                            // $current_stock = $stock_lookup ? $stock_lookup['current_stock'] : 0;
                            $current_stock = rand(50, 200); // Placeholder - REPLACE THIS

                            $usage_rate = ($current_stock > 0) ? ($medicine['total_quantity'] / $current_stock) * 100 : 0;
                            $usage_rate = min($usage_rate, 100); // Cap at 100% for the bar display
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($medicine['medicine_name']); ?></td>
                                <td><?php echo $medicine['prescription_count']; ?></td>
                                <td><?php echo $medicine['total_quantity']; ?></td>
                                <td><?php echo $current_stock; ?></td>
                                <td>
                                    <div class="usage-bar-container">
                                        <div class="usage-bar">
                                            <div class="usage-bar-fill" style="width: <?php echo number_format($usage_rate, 1); ?>%;"></div>
                                        </div>
                                        <?php echo number_format($usage_rate, 1); ?>%
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                         <tr><td colspan="5">No top prescribed medicines data found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

        </div>
    </div>

    <?php include("../includes/footer.php"); // Make sure this path is correct ?>

    <script>
        // --- Chart.js Initialization ---
        const ctx = document.getElementById('medicineChart').getContext('2d');
        const medicineChart = new Chart(ctx, {
            type: 'bar',
            data: {
                // NOTE: This data is static example data.
                // To make this dynamic, you need to fetch prescription counts by category
                // from your database using PHP and pass the data to this script.
                labels: ['Antibiotics', 'Pain Relievers', 'Antihistamines', 'Antidepressants', 'Blood Pressure', 'Diabetes', 'Other'],
                datasets: [{
                    label: 'Prescriptions This Month',
                    data: [120, 90, 75, 50, 60, 45, 80], // Example data - REPLACE WITH DYNAMIC DATA
                    backgroundColor: '#3498db', // Blue
                    borderColor: '#3498db',
                    borderWidth: 1,
                    borderRadius: 4 // Rounded bars
                }, {
                    label: 'Prescriptions Last Month',
                    data: [100, 80, 70, 45, 55, 40, 75], // Example data - REPLACE WITH DYNAMIC DATA
                    backgroundColor: '#95a5a6', // Grey
                    borderColor: '#95a5a6',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Allow chart to fill container height
                plugins: {
                    title: {
                        display: true,
                        text: 'Medicine Prescription Trends by Category',
                        font: {
                            size: 16,
                            weight: '600'
                        },
                        color: '#333'
                    },
                    legend: {
                        labels: {
                            font: {
                                size: 12
                            },
                            color: '#555'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.7)',
                        bodyFont: {
                            size: 12
                        },
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString(); // Format numbers with commas
                            },
                            font: {
                                size: 10
                            },
                            color: '#555'
                        },
                        grid: {
                            color: '#e0e0e0' // Lighter grid lines
                        }
                    },
                    x: {
                         ticks: {
                            font: {
                                size: 10
                            },
                            color: '#555'
                        },
                        grid: {
                            display: false // Hide x-axis grid lines
                        }
                    }
                }
            }
        });

        // --- Client-side Prescription Filter ---
        const filterInput = document.getElementById('prescriptionFilter');
        const prescriptionCardsContainer = document.getElementById('prescriptionCardsContainer');
        const prescriptionCards = prescriptionCardsContainer.getElementsByClassName('prescription-card');

        filterInput.addEventListener('keyup', function() {
            const filterValue = filterInput.value.toLowerCase();

            for (let i = 0; i < prescriptionCards.length; i++) {
                const card = prescriptionCards[i];
                // Get all text content from the card
                const cardText = card.textContent || card.innerText;

                if (cardText.toLowerCase().indexOf(filterValue) > -1) {
                    card.style.display = ""; // Show the card
                } else {
                    card.style.display = "none"; // Hide the card
                }
            }
        });

    </script>
</body>
</html>
