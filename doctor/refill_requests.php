<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");
$docid = $_SESSION['userid'];

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$valid_statuses = ['all', 'pending', 'approved', 'denied'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'all';
}

// Build the query
$query = "
    SELECT pr.*, p.pname, p.pemail, pres.prescription_date, 
           med.medication_name, med.dosage
    FROM prescription_refills pr
    JOIN prescription pres ON pr.prescription_id = pres.prescription_id
    JOIN appointment a ON pres.appoid = a.appoid
    JOIN patient p ON a.pid = p.pid
    JOIN (
        SELECT prescription_id, 
               JSON_UNQUOTE(JSON_EXTRACT(medication, '$[0].name')) as medication_name,
               JSON_UNQUOTE(JSON_EXTRACT(medication, '$[0].dosage')) as dosage
        FROM prescription
    ) med ON pres.prescription_id = med.prescription_id
    WHERE a.docid = ?
";

if ($status_filter != 'all') {
    $query .= " AND pr.status = ?";
}

$query .= " ORDER BY pr.request_date DESC";

$stmt = $database->prepare($query);

if ($status_filter != 'all') {
    $stmt->bind_param("is", $docid, $status_filter);
} else {
    $stmt->bind_param("i", $docid);
}

$stmt->execute();
$refill_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Refill Requests - Doctor Dashboard</title>
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .status-filter {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .status-filter a {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            background: #f0f0f0;
        }
        
        .status-filter a.active {
            background: #3498db;
            color: white;
        }
        
        .refill-requests-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .refill-requests-table th, 
        .refill-requests-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .refill-requests-table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .status-badge.pending {
            background: #FFF3CD;
            color: #856404;
        }
        
        .status-badge.approved {
            background: #D4EDDA;
            color: #155724;
        }
        
        .status-badge.denied {
            background: #F8D7DA;
            color: #721C24;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-buttons a {
            padding: 5px 10px;
            border-radius: 3px;
            text-decoration: none;
            font-size: 0.9em;
        }
        
        .btn-approve {
            background: #28a745;
            color: white;
        }
        
        .btn-deny {
            background: #dc3545;
            color: white;
        }
        
        .btn-view {
            background: #17a2b8;
            color: white;
        }
    </style>
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="dashboard-header">
            <h1><i class="fas fa-prescription-bottle-alt"></i> Prescription Refill Requests</h1>
        </div>

        <div class="status-filter">
            <a href="?status=all" class="<?php echo $status_filter == 'all' ? 'active' : ''; ?>">All Requests</a>
            <a href="?status=pending" class="<?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?status=approved" class="<?php echo $status_filter == 'approved' ? 'active' : ''; ?>">Approved</a>
            <a href="?status=denied" class="<?php echo $status_filter == 'denied' ? 'active' : ''; ?>">Denied</a>
        </div>

        <?php if (!empty($refill_requests)): ?>
            <table class="refill-requests-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Medication</th>
                        <th>Request Date</th>
                        <th>Refills</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($refill_requests as $request): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($request['pname']); ?></strong>
                                <small><?php echo htmlspecialchars($request['pemail']); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($request['medication_name']); ?>
                                <small><?php echo htmlspecialchars($request['dosage']); ?></small>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($request['request_date'])); ?></td>
                            <td><?php echo $request['refill_quantity']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $request['status']; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td class="action-buttons">
                                <?php if ($request['status'] == 'pending'): ?>
                                    <a href="process_refill.php?id=<?php echo $request['refill_id']; ?>&action=approve" class="btn-approve">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="process_refill.php?id=<?php echo $request['refill_id']; ?>&action=deny" class="btn-deny">
                                        <i class="fas fa-times"></i> Deny
                                    </a>
                                <?php endif; ?>
                                <a href="view_prescription.php?id=<?php echo $request['prescription_id']; ?>" class="btn-view">
                                    <i class="fas fa-file-prescription"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">No refill requests found.</p>
        <?php endif; ?>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>