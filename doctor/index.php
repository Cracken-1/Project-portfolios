<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");

// Fetch doctor details
$docid = $_SESSION['userid'];
$stmt = $database->prepare("SELECT * FROM doctor WHERE docid = ?");
$stmt->bind_param("i", $docid);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch upcoming appointments with patient details
$today = date('Y-m-d');
$stmt = $database->prepare("
    SELECT a.*, p.pid, p.pname, p.pemail, p.page, p.pgender
    FROM appointment a
    JOIN patient p ON a.pid = p.pid
    WHERE a.docid = ? AND a.appodate >= ?
    ORDER BY a.appodate ASC, a.appotime ASC
    LIMIT 10
");
$stmt->bind_param("is", $docid, $today);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Test with simplified query first
$stmt = $database->prepare("
    SELECT 
        id, 
        start_time, 
        end_time
    FROM 
        doctor_schedules
    WHERE 
        doctor_id = ? 
        AND day_of_week = UPPER(DAYNAME(CURDATE())) 
        AND is_active = 1
    ORDER BY 
        start_time ASC
");
$stmt->bind_param("i", $docid);
$stmt->execute();
$todays_schedule = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch statistics with patient relationships
$stmt = $database->prepare("
    SELECT 
        COUNT(*) as total_appointments,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending,
        COUNT(DISTINCT a.pid) as unique_patients
    FROM appointment a
    WHERE a.docid = ?
");
$stmt->bind_param("i", $docid);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get top patients (most appointments)
$stmt = $database->prepare("
    SELECT p.pid, p.pname, COUNT(a.appoid) as appointment_count
    FROM appointment a
    JOIN patient p ON a.pid = p.pid
    WHERE a.docid = ?
    GROUP BY p.pid
    ORDER BY appointment_count DESC
    LIMIT 5
");
$stmt->bind_param("i", $docid);
$stmt->execute();
$top_patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Billing functions with patient relationships
function getDoctorRevenue($docid) {
    global $database;
    $stmt = $database->prepare("
        SELECT COALESCE(SUM(i.amount), 0) as revenue, 
               COUNT(DISTINCT i.invoice_id) as invoice_count,
               COUNT(DISTINCT a.pid) as patient_count
        FROM invoices i
        JOIN appointment a ON i.appoid = a.appoid
        WHERE a.docid = ? AND i.status = 'paid'
    ");
    $stmt->bind_param("i", $docid);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

function getPendingPayments($docid) {
    global $database;
    $stmt = $database->prepare("
        SELECT COALESCE(SUM(i.amount), 0) as pending,
               GROUP_CONCAT(DISTINCT p.pname) as patients
        FROM invoices i
        JOIN appointment a ON i.appoid = a.appoid
        JOIN patient p ON a.pid = p.pid
        WHERE a.docid = ? AND i.status = 'pending' AND i.due_date >= CURDATE()
    ");
    $stmt->bind_param("i", $docid);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

function getRecentInvoices($docid, $limit = 5) {
    global $database;
    $invoices = [];
    $stmt = $database->prepare("
        SELECT i.*, p.pname, p.pemail, a.appodate, a.appotime
        FROM invoices i
        JOIN appointment a ON i.appoid = a.appoid
        JOIN patient p ON a.pid = p.pid
        WHERE a.docid = ?
        ORDER BY i.invoice_date DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $docid, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $invoices[] = $row;
    }
    $stmt->close();
    return $invoices;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard</title>
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
</head>
<body>
    <?php include("../includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="dashboard-header">
            <h1><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($doctor['docname']); ?>'s Dashboard</h1>
            <p>Specialization: <?php echo htmlspecialchars($doctor['specialization']); ?></p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_appointments']; ?></h3>
                    <p>Total Appointments</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['unique_patients']; ?></h3>
                    <p>Unique Patients</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['completed']; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
        </div>

        <!-- Today's Schedule -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2><i class="fas fa-calendar-day"></i> Today's Schedule</h2>
                <span><?php echo date('l, F j, Y'); ?></span>
            </div>
            
            <?php if (!empty($todays_schedule)): ?>
                <div class="schedule-slots">
                    <?php foreach ($todays_schedule as $slot): ?>
                        <div class="schedule-slot">
                            <div class="slot-time">
                                <?php echo date('g:i A', strtotime($slot['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($slot['end_time'])); ?>
                            </div>
                            <div class="slot-details">
                                Max Appointments: <?php echo $slot['max_appointments']; ?>
                                <?php if (!empty($slot['patient_appointments'])): ?>
                                    <div class="patient-appointments">
                                        <strong>Booked:</strong> <?php echo $slot['patient_appointments']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-data">No schedule slots for today.</p>
            <?php endif; ?>
        </div>
<!-- Pending Refill Requests -->
<div class="dashboard-section">
    <div class="section-header">
        <h2><i class="fas fa-prescription-bottle-alt"></i> Pending Refill Requests</h2>
        <a href="refill_requests.php" class="btn-view-all">View All</a>
    </div>
    
    <?php
    // Fetch pending refill requests
    $stmt = $database->prepare("
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
        WHERE a.docid = ? AND pr.status = 'pending'
        ORDER BY pr.request_date DESC
        LIMIT 3
    ");
    $stmt->bind_param("i", $docid);
    $stmt->execute();
    $refill_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    ?>
    
    <?php if (!empty($refill_requests)): ?>
        <div class="refill-requests-list">
            <?php foreach ($refill_requests as $request): ?>
                <div class="refill-request-card">
                    <div class="request-header">
                        <h3><?php echo htmlspecialchars($request['pname']); ?></h3>
                        <span class="request-date">
                            <?php echo date('M j, Y', strtotime($request['request_date'])); ?>
                        </span>
                    </div>
                    <div class="request-body">
                        <div class="medication-info">
                            <p><strong>Medication:</strong> <?php echo htmlspecialchars($request['medication_name']); ?></p>
                            <p><strong>Dosage:</strong> <?php echo htmlspecialchars($request['dosage']); ?></p>
                            <p><strong>Refills Requested:</strong> <?php echo $request['refill_quantity']; ?></p>
                        </div>
                        <?php if (!empty($request['request_notes'])): ?>
                            <div class="request-notes">
                                <p><strong>Notes:</strong> <?php echo htmlspecialchars($request['request_notes']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="request-actions">
                        <a href="process_refill.php?id=<?php echo $request['refill_id']; ?>&action=approve" class="btn-approve">
                            <i class="fas fa-check"></i> Approve
                        </a>
                        <a href="process_refill.php?id=<?php echo $request['refill_id']; ?>&action=deny" class="btn-deny">
                            <i class="fas fa-times"></i> Deny
                        </a>
                        <a href="view_prescription.php?id=<?php echo $request['prescription_id']; ?>" class="btn-view">
                            <i class="fas fa-file-prescription"></i> View Original
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="no-data">No pending refill requests.</p>
    <?php endif; ?>
</div>
        <!-- Upcoming Appointments -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2><i class="fas fa-calendar-check"></i> Upcoming Appointments</h2>
                <a href="appointments.php" class="btn-view-all">View All</a>
            </div>
            
            <?php if (!empty($appointments)): ?>
                <div class="appointments-list">
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="appointment-card">
                            <div class="appointment-header">
                                <h3><?php echo date('D, M j', strtotime($appointment['appodate'])); ?> at <?php echo date('g:i A', strtotime($appointment['appotime'])); ?></h3>
                                <span class="status-badge <?php echo strtolower($appointment['status']); ?>">
                                    <?php echo $appointment['status']; ?>
                                </span>
                            </div>
                            <div class="appointment-body">
                                <div class="patient-info">
                                    <h4><?php echo htmlspecialchars($appointment['pname']); ?></h4>
                                    <p>Reason: <?php echo htmlspecialchars($appointment['apporeason'] ?? 'Not specified'); ?></p>
                                </div>
                                <div class="patient-details">
                                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['pemail']); ?></p>
                                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['pphone']); ?></p>
                                    <p><?php echo $appointment['page']; ?> years old, <?php echo $appointment['pgender']; ?></p>
                                </div>
                            </div>
                            <div class="appointment-actions">
                                <a href="view_appointment.php?id=<?php echo $appointment['appoid']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="edit_appointment.php?id=<?php echo $appointment['appoid']; ?>" class="btn-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-data">No upcoming appointments found.</p>
            <?php endif; ?>
        </div>

        <!-- Top Patients -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2><i class="fas fa-star"></i> Your Top Patients</h2>
            </div>
            
            <?php if (!empty($top_patients)): ?>
                <div class="patients-grid">
                    <?php foreach ($top_patients as $patient): ?>
                        <div class="patient-card">
                            <div class="patient-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="patient-info">
                                <h3><?php echo htmlspecialchars($patient['pname']); ?></h3>
                                <p><?php echo $patient['appointment_count']; ?> appointments</p>
                            </div>
                            <a href="patient_records.php?id=<?php echo $patient['pid']; ?>" class="btn-view">
                                <i class="fas fa-file-medical"></i> Records
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-data">No patient data available.</p>
            <?php endif; ?>
        </div>

        <!-- Billing Summary -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2><i class="fas fa-file-invoice-dollar"></i> Billing Summary</h2>
                <a href="billing.php" class="btn-view-all">View All</a>
            </div>
            
            <?php 
            $revenue = getDoctorRevenue($docid);
            $pending = getPendingPayments($docid);
            $invoices = getRecentInvoices($docid);
            ?>
            
            <div class="billing-stats">
                <div class="billing-stat">
                    <h3>Ksh. <?php echo number_format($revenue['revenue'], 2); ?></h3>
                    <p>Total Revenue</p>
                    <small><?php echo $revenue['invoice_count']; ?> invoices from <?php echo $revenue['patient_count']; ?> patients</small>
                </div>
                <div class="billing-stat">
                    <h3>Ksh. <?php echo number_format($pending['pending'], 2); ?></h3>
                    <p>Pending Payments</p>
                    <?php if (!empty($pending['patients'])): ?>
                        <small>From: <?php echo $pending['patients']; ?></small>
                    <?php endif; ?>
                </div>
            </div>
            
            <h3>Recent Invoices</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><?php echo $invoice['invoice_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($invoice['pname']); ?></strong>
                                <small><?php echo $invoice['pemail']; ?></small>
                            </td>
                            <td><?php echo $invoice['invoice_date']; ?></td>
                            <td>$<?php echo number_format($invoice['amount'], 2); ?></td>
                            <td>
                                <span class="status-badge <?php echo strtolower($invoice['status']); ?>">
                                    <?php echo $invoice['status']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="view_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>

    <script>
        // Chart for appointments by status
        const ctx = document.getElementById('appointmentsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending', 'Cancelled'],
                datasets: [{
                    data: [<?php echo $stats['completed']; ?>, <?php echo $stats['pending']; ?>, 0],
                    backgroundColor: [
                        '#28a745',
                        '#ffc107',
                        '#dc3545'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>