<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");
$docid = $_SESSION['userid'];
$perPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$search_term = $_GET['search'] ?? '';
$offset = ($page - 1) * $perPage;

// Count total patients (for pagination)
$count_sql = "
    SELECT COUNT(DISTINCT p.pid) as total
    FROM patient p
    JOIN appointment a ON p.pid = a.pid
    WHERE a.docid = ?
";
if (!empty($search_term)) {
    $count_sql .= " AND (p.pname LIKE CONCAT('%', ?, '%') OR p.pemail LIKE CONCAT('%', ?, '%'))";
}

$count_stmt = $database->prepare($count_sql);
if (!empty($search_term)) {
    $count_stmt->bind_param("ssss", $docid, $search_term, $search_term, $search_term);
} else {
    $count_stmt->bind_param("s", $docid);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_patients = $count_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_patients / $perPage);

// Fetch paginated patients
$patients = [];
$sql = "
    SELECT 
        p.*,
        COUNT(a.appoid) as total_visits,
        MAX(a.appodate) as last_visit,
        (SELECT COUNT(*) FROM prescription pr 
         JOIN appointment ap ON pr.appoid = ap.appoid 
         WHERE ap.pid = p.pid AND ap.docid = ?) as total_prescriptions,
        (SELECT COUNT(*) FROM invoices i 
         JOIN appointment ai ON i.appoid = ai.appoid 
         WHERE ai.pid = p.pid AND ai.docid = ? AND i.status = 'pending') as pending_payments
    FROM patient p
    JOIN appointment a ON p.pid = a.pid
    WHERE a.docid = ?
";

if (!empty($search_term)) {
    $sql .= " AND (p.pname LIKE CONCAT('%', ?, '%') OR p.pemail LIKE CONCAT('%', ?, '%') OR p.pphone LIKE CONCAT('%', ?, '%'))";
}

$sql .= " GROUP BY p.pid ORDER BY p.pname ASC LIMIT ? OFFSET ?";

if (!empty($search_term)) {
    $stmt = $database->prepare($sql);
    $stmt->bind_param("iiisssii", $docid, $docid, $docid, $search_term, $search_term, $search_term, $perPage, $offset);
} else {
    $stmt = $database->prepare($sql);
    $stmt->bind_param("iiiii", $docid, $docid, $docid, $perPage, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients</title>
    <link rel="stylesheet" href="../css/doctor-styles.css">
    <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  
</head>
<body>
<?php include("../includes/header.php"); ?>
<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
    <div class="section-header">
        <h1><i class="fas fa-users"></i> My Patients</h1>
        <a href="patient_report.php" class="btn-generate">
            <i class="fas fa-file-pdf"></i> Generate Report
        </a>
    </div>

    <div class="search-container">
        <form method="GET" class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Search patients..." value="<?php echo htmlspecialchars($search_term); ?>">
        </form>
    </div>

    <div class="patients-container">
        <?php if (!empty($patients)): ?>
            <?php foreach ($patients as $patient): ?>
                <div class="patient-card">
                    <div class="patient-header">
                        <div class="patient-avatar">
                            <?php echo strtoupper(substr($patient['pname'], 0, 1)); ?>
                        </div>
                        <div class="patient-info">
                            <h3 class="patient-name"><?php echo htmlspecialchars($patient['pname']); ?></h3>
                            <p class="patient-contact">
                                <i class="fas fa-envelope"></i> 
                                <a href="mailto:<?php echo $patient['pemail']; ?>"><?php echo $patient['pemail']; ?></a> |
        
                            </p>
                        </div>
                    </div>

                    <div class="patient-stats">
                        <div class="stat-item">
                            <p class="stat-value"><?php echo $patient['total_visits']; ?></p>
                            <p class="stat-label">Total Visits</p>
                        </div>
                        <div class="stat-item">
                            <p class="stat-value"><?php echo $patient['total_prescriptions']; ?></p>
                            <p class="stat-label">Prescriptions</p>
                        </div>
                        <div class="stat-item">
                            <p class="stat-value"><?php echo $patient['pending_payments']; ?></p>
                            <p class="stat-label">Pending Payments</p>
                        </div>
                        <div class="stat-item">
                            <p class="stat-value"><?php echo $patient['last_visit'] ? date('M j, Y', strtotime($patient['last_visit'])) : 'Never'; ?></p>
                            <p class="stat-label">Last Visit</p>
                        </div>
                    </div>

                    <div class="patient-details">
                        <p><strong>Gender:</strong> <?php echo $patient['pgender'] ?? 'Not specified'; ?></p>
                        <p><strong>Blood Group:</strong> <?php echo $patient['pbloodgroup'] ?? 'Not specified'; ?></p>
                    </div>

                    <div class="patient-actions">
                        <a href="view_patient.php?id=<?php echo $patient['pid']; ?>" class="btn-action btn-view" title="View Profile">
                            <i class="fas fa-eye"></i> View Profile
                        </a>
                        <a href="patient_records.php?id=<?php echo $patient['pid']; ?>" class="btn-action btn-records" title="Medical Records">
                            <i class="fas fa-file-medical"></i> Records
                        </a>
                        <a href="patient_appointments.php?id=<?php echo $patient['pid']; ?>" class="btn-action btn-view" title="Appointments">
                            <i class="fas fa-calendar-check"></i> Appointments
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-patients">
                <i class="fas fa-user-slash" style="font-size: 3rem; margin-bottom: 15px;"></i>
                <h3>No patients found</h3>
                <p>You currently have no patients matching your search criteria.</p>
                <?php if (!empty($search_term)): ?>
                    <a href="patients.php" class="btn-generate" style="margin-top: 15px;">
                        <i class="fas fa-times"></i> Clear Search
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                   class="<?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php include("../includes/footer.php"); ?>
</body>
</html>
