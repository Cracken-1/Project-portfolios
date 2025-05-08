<?php
session_start();
if (!isset($_SESSION["user"]) || !in_array($_SESSION['usertype'], ['a', 'd', 'p'])) {
    header("location: ../login/login-other.php");
    exit();
}

include("../db/db.php");

// Determine user type for filtering (if needed)
$user_type = $_SESSION['usertype'];
$user_id = $_SESSION['userid'];

$log_sql = "SELECT cl.*,
                   CASE
                       WHEN cl.sender_type = 'd' THEN (SELECT dname FROM doctor WHERE docid = cl.sender_id)
                       WHEN cl.sender_type = 'p' THEN (SELECT pname FROM patient WHERE pid = cl.sender_id)
                       WHEN cl.sender_type = 'a' THEN (SELECT aname FROM admin WHERE adminid = cl.sender_id)
                       ELSE 'Unknown'
                   END AS sender_name,
                   CASE
                       WHEN cl.receiver_type = 'd' THEN (SELECT dname FROM doctor WHERE docid = cl.receiver_id)
                       WHEN cl.receiver_type = 'p' THEN (SELECT pname FROM patient WHERE pid = cl.receiver_id)
                       WHEN cl.receiver_type = 'a' THEN (SELECT aname FROM admin WHERE adminid = cl.receiver_id)
                       ELSE 'Unknown'
                   END AS receiver_name
            FROM communication_logs cl
            ORDER BY cl.timestamp DESC";

$log_stmt = $database->prepare($log_sql);
$log_stmt->execute();
$logs = $log_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include("../includes/header.php");
if ($user_type == 'a') {
    include("../includes/sidebar_admin.php"); // Assuming you have an admin sidebar
} elseif ($user_type == 'd') {
    include("../includes/sidebar.php"); // Doctor sidebar
} elseif ($user_type == 'p') {include("../includes/sidebar_patient.php"); // Patient sidebar
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication Logs</title>
    <link rel="stylesheet" href="../css/styles.css"> <link rel="stylesheet" href="../css/includes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .logs-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .log-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
            display: grid;
            grid-template-columns: 150px 1fr 150px;
            gap: 15px;
            align-items: center;
        }
        .log-item:last-child {
            border-bottom: none;
        }
        .log-timestamp {
            color: #6c757d;
            font-size: 0.9em;
        }
        .log-details {
            display: flex;
            flex-direction: column;
        }
        .log-type {
            font-weight: bold;
            color: #2c3e50;
        }
        .log-participants {
            font-size: 0.95em;
            color: #4a5568;
        }
        .log-message {
            margin-top: 5px;
            color: #343a40;
        }
        .log-urgent {
            color: #dc3545;
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .log-item {
                grid-template-columns: 1fr;
            }
            .log-timestamp, .log-participants {
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="section-header">
            <h1><i class="fas fa-history"></i> Communication Logs</h1>
        </div>

        <div class="logs-container">
            <?php if (!empty($logs)): ?>
                <?php foreach ($logs as $log): ?>
                    <div class="log-item">
                        <div class="log-timestamp">
                            <?= date('F j, Y h:i A', strtotime($log['timestamp'])) ?>
                        </div>
                        <div class="log-details">
                            <span class="log-type">
                                <?php
                                    if ($log['communication_type'] == 'chat') {
                                        echo '<i class="fas fa-comment-dots"></i> Chat Message';
                                    } elseif ($log['communication_type'] == 'video_call') {
                                        echo '<i class="fas fa-video"></i> Video Call';
                                    } elseif ($log['communication_type'] == 'audio_call') {
                                        echo '<i class="fas fa-phone"></i> Audio Call';
                                    } else {
                                        echo 'Unknown Communication';
                                    }
                                ?>
                                <?php if ($log['urgent']): ?>
                                    <span class="log-urgent">(Urgent)</span>
                                <?php endif; ?>
                            </span>
                            <div class="log-participants">
                                <strong>From:</strong> <?= htmlspecialchars($log['sender_name']) ?> (<?= strtoupper($log['sender_type']) ?>)
                                <br>
                                <strong>To:</strong> <?= htmlspecialchars($log['receiver_name']) ?> (<?= strtoupper($log['receiver_type']) ?>)
                            </div>
                            <?php if ($log['communication_type'] == 'chat' && !empty($log['message_content'])): ?>
                                <p class="log-message"><?= htmlspecialchars($log['message_content']) ?></p>
                            <?php endif; ?>
                            <?php if (($log['communication_type'] == 'video_call' || $log['communication_type'] == 'audio_call') && !empty($log['session_details'])): ?>
                                <p class="log-message">Session Details: <?= htmlspecialchars($log['session_details']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="log-actions">
                            </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No communication logs found.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php include("../includes/footer.php"); ?>
</body>
</html>