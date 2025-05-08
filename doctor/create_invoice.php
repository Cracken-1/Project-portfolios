<?php
session_start();
require_once("../db/db.php"); // Assuming this file establishes the $database connection

// Check if user is logged in as doctor
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../login/login-other.php");
    exit();
}

$docid = $_SESSION['userid']; // Get doctor ID from session

// Function to generate a unique invoice number
function generateInvoiceNumber() {
    // INV-YYYYMMDD-XXXXXX
    return 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Define appointment types and their prices
// This array maps the 'appointment_type' string stored in the database to a price.
$appointmentTypes = [
    'General Consultation' => 1500.00,
    'Specialist Consultation' => 3000.00,
    'Follow-up Visit' => 1000.00,
    'Emergency Consultation' => 5000.00,
    'Vaccination' => 2000.00,
    'Health Checkup' => 2500.00
    // Add any other appointment types and their corresponding prices here
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Handle Invoice Creation (POST request) ---
    // Ensure appoid is received from the hidden input
    if (!isset($_POST['appoid'])) {
         $_SESSION['error'] = "Appointment not selected for invoice creation.";
         header("location: billing.php"); // Or back to create_invoice.php to select again
         exit();
    }

    $appoid = filter_input(INPUT_POST, 'appoid', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $tax_amount = filter_input(INPUT_POST, 'tax_amount', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? 0.0;
    $discount_amount = filter_input(INPUT_POST, 'discount_amount', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? 0.0;
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $due_date = filter_input(INPUT_POST, 'due_date', FILTER_SANITIZE_STRING); // Basic sanitization, further validation if needed
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

    if (!$appoid || $amount === false || $amount < 0 || !$status || !$due_date) {
        $_SESSION['error'] = "Invalid data submitted. Please check all fields.";
        // It's better to redirect back to the form with the appoid to allow corrections
        header("location: create_invoice.php" . ($appoid ? "?appoid=" . $appoid : ""));
        exit();
    }

    // Validate due_date format (example: YYYY-MM-DD)
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $due_date)) {
        $_SESSION['error'] = "Invalid due date format. Please use YYYY-MM-DD.";
        header("location: create_invoice.php?appoid=" . $appoid);
        exit();
    }


    $total_amount = $amount + $tax_amount - $discount_amount;
    $invoice_date = date('Y-m-d');
    $invoice_number = generateInvoiceNumber();

    // Verify the appointment belongs to this doctor before creating invoice
    $check_sql = "SELECT appoid FROM appointment WHERE appoid = ? AND docid = ?";
    $check_stmt = $database->prepare($check_sql);
    $check_stmt->bind_param("ii", $appoid, $docid);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows == 0) {
        $_SESSION['error'] = "Invalid appointment selected or appointment does not belong to you.";
        header("location: billing.php"); // Or create_invoice.php
        exit();
    }
    $check_stmt->close();

    // Insert invoice
    $sql = "INSERT INTO invoices (
                appoid, invoice_number, invoice_date, due_date,
                amount, tax_amount, discount_amount, total_amount,
                status, notes, edited_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $database->prepare($sql);
    // Bind types: i (appoid), s (invoice_number), s (invoice_date), s (due_date),
    //             d (amount), d (tax_amount), d (discount_amount), d (total_amount),
    //             s (status), s (notes), i (edited_by/docid)
    $stmt->bind_param(
        "isssddddssi",
        $appoid, $invoice_number, $invoice_date, $due_date,
        $amount, $tax_amount, $discount_amount, $total_amount,
        $status, $notes, $docid // Set edited_by to the doctor ID creating it
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = "Invoice (".$invoice_number.") created successfully!";
        header("location: billing.php"); // Redirect back to the billing overview
    } else {
        $_SESSION['error'] = "Error creating invoice: " . $stmt->error;
        // Log the detailed error: error_log("Invoice creation error: " . $stmt->error);
        header("location: create_invoice.php?appoid=" . $appoid); // Redirect back to form with appoid
    }
    $stmt->close();
    $database->close();
    exit();

} else {
    // --- Display Create Invoice Form or Appointment Selection (GET request) ---

    // Check if an appointment ID is provided in the URL
    if (!isset($_GET['appoid'])) {
        // --- Display Appointment Selection Form ---

        // Fetch completed appointments for this doctor
        // Assumes 'status' in 'appointment' table is 'Completed'
        // Assumes 'appointment_type' column exists in 'appointment' table
        $completed_appointments = [];
        $sql_completed = "SELECT a.appoid, a.appodate, a.appotime, p.pname, a.reason, a.appointment_type
                          FROM appointment a
                          JOIN patient p ON a.pid = p.pid
                          WHERE a.docid = ? AND a.status = 'Completed' 
                          ORDER BY a.appodate DESC, a.appotime DESC";
        $stmt_completed = $database->prepare($sql_completed);
        $stmt_completed->bind_param("i", $docid);
        $stmt_completed->execute();
        $result_completed = $stmt_completed->get_result();
        while ($row = $result_completed->fetch_assoc()) {
            $completed_appointments[] = $row;
        }
        $stmt_completed->close();

        // Include header and sidebar
        // Make sure these paths are correct
        include("../includes/header.php");
        include("../includes/sidebar.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice - Select Appointment</title>
    <link rel="stylesheet" href="../css/includes.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Styles from your provided code for selection form */
        .main-content { padding: 20px; }
        .select-appointment-container {
            background: #fff; padding: 25px; border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 700px; margin: 20px auto;
        }
        .select-appointment-container h2 { text-align: center; margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .form-group select {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;
            box-sizing: border-box; background-color: #fff;
        }
        .btn-select, .btn-back-billing {
            display: inline-block; padding: 10px 20px; color: white;
            border: none; border-radius: 4px; cursor: pointer; text-align: center;
            text-decoration: none; font-size: 1em; margin-top: 10px;
        }
        .btn-select { background: #007bff; }
        .btn-select:hover { background: #0056b3; }
        .btn-back-billing { background-color: #6c757d; margin-left: 10px;}
        .btn-back-billing:hover { background-color: #545b62;}
        .no-appointments-message { text-align: center; color: #666; padding: 20px; font-size: 1.1em; }
        .button-group { margin-top: 20px; text-align: center;} /* For centering buttons if needed */
    </style>
</head>
<body>
    <div class="main-content">
        <div class="select-appointment-container">
            <h2><i class="fas fa-calendar-check"></i> Select Completed Appointment</h2>
            <?php if (isset($_SESSION['error'])): ?>
                <p style="color: red; text-align:center;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
            <?php endif; ?>
            <?php if (empty($completed_appointments)): ?>
                <p class="no-appointments-message">No completed appointments found for invoicing.</p>
                 <div class="button-group">
                    <a href="billing.php" class="btn-back-billing"><i class="fas fa-arrow-left"></i> Back to Billing</a>
                </div>
            <?php else: ?>
                <form method="GET" action="create_invoice.php">
                    <div class="form-group">
                        <label for="appoid_select">Select Appointment:</label>
                        <select id="appoid_select" name="appoid" required>
                            <option value="">-- Select an Appointment --</option>
                            <?php foreach ($completed_appointments as $appointment_option): ?>
                                <option value="<?= htmlspecialchars($appointment_option['appoid']) ?>">
                                    <?= htmlspecialchars(date('M j, Y, g:i a', strtotime($appointment_option['appodate'] . ' ' . $appointment_option['appotime']))) ?>
                                    - <?= htmlspecialchars($appointment_option['pname']) ?>
                                    (<?= htmlspecialchars($appointment_option['appointment_type'] ?? 'N/A Type') ?>: <?= htmlspecialchars($appointment_option['reason']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="button-group">
                        <button type="submit" class="btn-select"><i class="fas fa-arrow-right"></i> Proceed to Create Invoice</button>
                        <a href="billing.php" class="btn-back-billing"><i class="fas fa-arrow-left"></i> Back to Billing</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php include("../includes/footer.php"); // Ensure path is correct ?>
</body>
</html>
<?php
        $database->close();
        exit(); // Stop execution after showing the selection form
    }

    // --- Display Create Invoice Form (if appoid IS provided in URL) ---
    $appoid = filter_input(INPUT_GET, 'appoid', FILTER_VALIDATE_INT);
    if (!$appoid) {
        $_SESSION['error'] = "No appointment ID provided.";
        header("location: create_invoice.php"); // Redirect back to selection
        exit();
    }


    // Verify the appointment belongs to this doctor AND fetch its details including appointment_type
    $sql_appointment_details = "SELECT a.appoid, a.appodate, a.appotime, a.pid, p.pname, a.appointment_type, a.reason
                                FROM appointment a
                                JOIN patient p ON a.pid = p.pid
                                WHERE a.appoid = ? AND a.docid = ?";
    $stmt_appointment_details = $database->prepare($sql_appointment_details);
    $stmt_appointment_details->bind_param("ii", $appoid, $docid);
    $stmt_appointment_details->execute();
    $appointment = $stmt_appointment_details->get_result()->fetch_assoc();
    $stmt_appointment_details->close();

    if (!$appointment) {
        $_SESSION['error'] = "Invalid appointment selected or it does not belong to you.";
        header("location: create_invoice.php"); // Redirect back to selection
        exit();
    }

    // Calculate default amount based on appointment type using the new $appointmentTypes array
    $appointment_type_from_db = $appointment['appointment_type']; // This should be "General Consultation", etc.
    // Use 0.00 as default if type is not recognized or not set
    $default_amount = $appointmentTypes[$appointment_type_from_db] ?? 0.00;

    // Include header and sidebar
    // Make sure these paths are correct
    include("../includes/header.php");
    include("../includes/sidebar.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice for <?= htmlspecialchars($appointment['pname']) ?></title>
    <link rel="stylesheet" href="../css/includes.css"> <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Styles from your provided code for invoice form */
        .main-content { padding: 20px; }
        .invoice-form-container { /* Renamed for clarity */
            max-width: 800px; margin: 20px auto; padding: 25px;
            background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .invoice-form-container h2 { text-align: center; margin-bottom: 10px; color: #333;}
        .invoice-form-container .appointment-summary {
            background-color: #f8f9fa; padding: 10px; border-radius: 5px;
            margin-bottom: 20px; border: 1px solid #e9ecef; font-size: 0.95em;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px; border: 1px solid #ddd;
            border-radius: 4px; box-sizing: border-box;
        }
        .form-row { display: flex; gap: 20px; } /* Increased gap */
        .form-row .form-group { flex: 1; }
        .amount-summary {
            background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 20px 0;
            border: 1px solid #e0e0e0;
        }
        .amount-summary .form-group label { font-size: 1.1em; color: #333;}
        .amount-summary input[readonly] { background-color: #e9ecef; font-weight: bold; font-size: 1.1em; }

        .btn-submit, .btn-back {
            padding: 10px 20px; color: white; text-decoration: none;
            border: none; border-radius: 4px; cursor: pointer; font-size: 1em;
            margin-top: 10px; display: inline-flex; align-items: center;
        }
        .btn-submit { background: #28a745; } /* Green for create */
        .btn-submit:hover { background: #218838; }
        .btn-back { background: #6c757d; margin-left: 10px; }
        .btn-back:hover { background: #545b62; }
        .btn-submit i, .btn-back i { margin-right: 8px; }
        .button-group { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="invoice-form-container">
            <h2><i class="fas fa-file-invoice-dollar"></i> Create Invoice</h2>
            <?php if (isset($_SESSION['error'])): ?>
                <p style="color: red; text-align:center; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom:15px;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
            <?php endif; ?>

            <div class="appointment-summary">
                <strong>Patient:</strong> <?= htmlspecialchars($appointment['pname']) ?> (ID: <?= htmlspecialchars($appointment['pid']) ?>)<br>
                <strong>Appointment Date:</strong> <?= htmlspecialchars(date('M j, Y, g:i a', strtotime($appointment['appodate'] . ' ' . $appointment['appotime']))) ?><br>
                <strong>Type:</strong> <?= htmlspecialchars($appointment_type_from_db) ?>
                (Base Price: Ksh <?= number_format($default_amount, 2) ?>)<br>
                <strong>Reason:</strong> <?= htmlspecialchars($appointment['reason']) ?>
            </div>

            <form method="POST" action="create_invoice.php">
                <input type="hidden" name="appoid" value="<?= htmlspecialchars($appoid) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="invoice_date"><i class="fas fa-calendar-alt"></i> Invoice Date</label>
                        <input type="text" id="invoice_date" name="invoice_date" value="<?= date('Y-m-d') ?>" readonly style="background-color: #e9ecef;">
                    </div>
                    <div class="form-group">
                        <label for="due_date"><i class="fas fa-calendar-times"></i> Due Date</label>
                        <input type="text" id="due_date" name="due_date" required class="datepicker" placeholder="YYYY-MM-DD">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="amount"><i class="fas fa-money-bill-wave"></i> Base Amount (Ksh)</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0" required value="<?= htmlspecialchars(number_format($default_amount, 2, '.', '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="tax_amount"><i class="fas fa-percentage"></i> Tax Amount (Ksh)</label>
                        <input type="number" id="tax_amount" name="tax_amount" step="0.01" min="0" value="0.00">
                    </div>
                    <div class="form-group">
                        <label for="discount_amount"><i class="fas fa-tags"></i> Discount Amount (Ksh)</label>
                        <input type="number" id="discount_amount" name="discount_amount" step="0.01" min="0" value="0.00">
                    </div>
                </div>

                <div class="amount-summary">
                    <div class="form-group">
                        <label>Total Invoice Amount (Ksh)</label>
                        <input type="text" id="total_amount_display" readonly style="border:none; background-color:transparent; font-weight:bold; font-size:1.2em; color: #007bff;">
                        </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="status"><i class="fas fa-info-circle"></i> Status</label>
                        <select id="status" name="status" required>
                            <option value="pending" selected>Pending</option>
                            </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes"><i class="fas fa-sticky-note"></i> Notes (Optional)</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Any additional notes for this invoice..."></textarea>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-submit"><i class="fas fa-check-circle"></i> Create Invoice</button>
                    <a href="create_invoice.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Appointment Selection
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php include("../includes/footer.php"); // Ensure path is correct ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date picker for Due Date
        flatpickr('.datepicker', {
            dateFormat: 'Y-m-d',
            minDate: 'today' // Or remove/adjust if past due dates are allowed
        });

        // Calculate and display total amount dynamically
        function calculateDisplayTotal() {
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const tax = parseFloat(document.getElementById('tax_amount').value) || 0;
            const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
            const total = amount + tax - discount;
            document.getElementById('total_amount_display').value = total.toFixed(2);
            // If you had a hidden field for total_amount:
            // document.getElementById('total_amount_hidden').value = total.toFixed(2);
        }

        // Add event listeners to amount fields
        document.getElementById('amount').addEventListener('input', calculateDisplayTotal);
        document.getElementById('tax_amount').addEventListener('input', calculateDisplayTotal);
        document.getElementById('discount_amount').addEventListener('input', calculateDisplayTotal);

        // Initialize calculation on page load
        calculateDisplayTotal(); // Call once on load
    </script>
</body>
</html>
<?php
} // End of the GET request block (when appoid is provided)
$database->close();
?>
