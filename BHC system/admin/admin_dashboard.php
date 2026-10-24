<?php
session_start();
require_once '../configDB/db.php';
require_once '../configDB/logger.php'; // Idinagdag para sa Activity Audit Logging

if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'doctor', 'nurse'])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";

// Handle Patient Registration Approval/Rejection
if (isset($_POST['update_patient_status'])) {
    $user_id = intval($_POST['user_id']);
    $status = trim($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $user_id);
    if ($stmt->execute()) {
        // I-record sa Audit Trail Logs
        logActivity($conn, "UPDATE PATIENT STATUS", "Updated patient ID $user_id status to $status");
        $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                        <i class='fa-solid fa-circle-check me-2'></i> Patient registration status updated to <strong>$status</strong>!
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                    </div>";
    }
    $stmt->close();
}

// Register Patient Modal Handler
if (isset($_POST['register_patient'])) {
    $full_name = trim($_POST['full_name']);
    $age = intval($_POST['age']);
    $gender = trim($_POST['gender']);
    $contact_no = trim($_POST['contact_no']);
    $birthdate = trim($_POST['birthdate']);
    $civil_status = trim($_POST['civil_status'] ?? '');
    $philhealth_no = trim($_POST['philhealth_no'] ?? '');
    $address = trim($_POST['address']);
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = 'patient';
    $status = 'Approved';

    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                        <i class='fa-solid fa-triangle-exclamation me-2'></i> Username <strong>$username</strong> is already taken!
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                    </div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (full_name, age, gender, contact_no, birthdate, civil_status, philhealth_no, address, emergency_contact, username, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisssssssssss", $full_name, $age, $gender, $contact_no, $birthdate, $civil_status, $philhealth_no, $address, $emergency_contact, $username, $password, $role, $status);
        
        if ($stmt->execute()) {
            // I-record sa Audit Trail Logs
            logActivity($conn, "REGISTER PATIENT", "Successfully registered new patient: $full_name");
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                            <i class='fa-solid fa-circle-check me-2'></i> Patient <strong>$full_name</strong> successfully registered!
                            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                        </div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                            Error: " . $conn->error . "
                            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                        </div>";
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// Update Appointment Status & Move to Medical History / Remove from Requests List
if (isset($_POST['update_appointment'])) {
    $app_id = intval($_POST['appointment_id']);
    $status = trim($_POST['status']);
    
    $app_query = $conn->prepare("SELECT * FROM appointments WHERE id = ?");
    $app_query->bind_param("i", $app_id);
    $app_query->execute();
    $app_res = $app_query->get_result()->fetch_assoc();
    $app_query->close();

    if ($app_res) {
        $p_name = $app_res['patient_name'];
        $diagnosis = "Clinic Appointment - Purpose: " . ($app_res['purpose'] ?? 'General Checkup') . " | Status: $status";
        $prescription = "Schedule: " . ($app_res['appointment_date'] ?? '') . " " . ($app_res['appointment_time'] ?? '');

        $conn->query("ALTER TABLE medical_records ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");

        $ins_med = $conn->prepare("INSERT INTO medical_records (patient_name, diagnosis, prescription, created_at) VALUES (?, ?, ?, NOW())");
        $ins_med->bind_param("sss", $p_name, $diagnosis, $prescription);
        $ins_med->execute();
        $ins_med->close();
        $quantity_to_deduct = 1; 

        if (!empty($prescription)) {
            $update_stock = $conn->prepare("UPDATE inventory SET stock_level = GREATEST(0, stock_level - ?) WHERE medicine_name LIKE ?");
            $search_term = "%" . $prescription . "%";
            $update_stock->bind_param("is", $quantity_to_deduct, $search_term);
            $update_stock->execute();
            $update_stock->close();
        } 

        $conn->query("SET FOREIGN_KEY_CHECKS = 1");

        $del_app = $conn->prepare("DELETE FROM appointments WHERE id = ?");
        $del_app->bind_param("i", $app_id);
        $del_app->execute();
        $del_app->close();

        // I-record sa Audit Trail Logs
        logActivity($conn, "PROCESS APPOINTMENT", "Processed and saved appointment for $p_name (Status: $status)");

        $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fa-solid fa-circle-check me-2'></i> Appointment successfully saved, moved to Medical History, and removed from active requests!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// Delete Appointment Handler
if (isset($_POST['delete_appointment'])) {
    $app_id = intval($_POST['appointment_id']);
    
    $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $app_id);
    if ($stmt->execute()) {
        // I-record sa Audit Trail Logs
        logActivity($conn, "DELETE APPOINTMENT", "Deleted appointment request ID $app_id");
        $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fa-solid fa-circle-check me-2'></i> Appointment request deleted!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    $stmt->close();
}

// Dashboard Counts & Pending Home Visitations / Expired Medicines Count
$total_patients = $conn->query("SELECT COUNT(*) AS total FROM users WHERE LOWER(role) = 'patient' AND status = 'Approved'")->fetch_assoc()['total'] ?? 0;
$pending_approvals = $conn->query("SELECT COUNT(*) AS total FROM users WHERE LOWER(role) = 'patient' AND status = 'Pending'")->fetch_assoc()['total'] ?? 0;
$pending_appointments = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE status = 'Pending'")->fetch_assoc()['total'] ?? 0;
$critical_stock = $conn->query("SELECT COUNT(*) AS total FROM inventory WHERE stock_level <= 10")->fetch_assoc()['total'] ?? 0;
$expired_medicines = $conn->query("SELECT COUNT(*) AS total FROM inventory WHERE expiration_date IS NOT NULL AND expiration_date < CURDATE()")->fetch_assoc()['total'] ?? 0;
$pending_visitations = $conn->query("SELECT COUNT(*) AS total FROM home_visitations WHERE status = 'Pending'")->fetch_assoc()['total'] ?? 0;

$pending_patients = $conn->query("SELECT * FROM users WHERE LOWER(role) = 'patient' AND status = 'Pending' ORDER BY id DESC");
$appointments = $conn->query("SELECT a.*, u.contact_no, u.address FROM appointments a LEFT JOIN users u ON a.patient_name = u.full_name ORDER BY a.id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HCMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css/style.css">
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand"><i class="fa-solid fa-hospital-user me-2"></i> Health Center</div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" class="nav-link active"><i class="fa-solid fa-chart-line me-3"></i> Dashboard</a></li>
            <li><a href="home_visitations.php" class="nav-link d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-house-medical me-3"></i> Home Visitations</span>
                <?php if($pending_visitations > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?= $pending_visitations; ?></span>
                <?php endif; ?>
            </a></li>
            <li><a href="inventory.php" class="nav-link"><i class="fa-solid fa-boxes-stacked me-3"></i> Medicine Inventory</a></li>
            <li><a href="medical_history.php" class="nav-link"><i class="fa-solid fa-clock-rotate-left me-3"></i> Medical History</a></li>
            <li><a href="export_report.php" class="nav-link"><i class="fa-solid fa-file-invoice me-3"></i> FHSIS / Reports</a></li>
            <li><a href="index.php" class="nav-link"><i class="fa-solid fa-users me-3"></i> Registered Patients</a></li>
            <li><a href="audit_logs.php" class="nav-link"><i class="fa-solid fa-shield-halved me-3"></i> Audit Logs</a></li>
        </ul>
        <div class="p-3 border-top mt-auto">
            <a href="../auth/logout.php" class="btn btn-outline-danger w-100 btn-sm py-2 fw-semibold">
                <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Log Out
            </a>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="top-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">Overview Dashboard</h4>
                <p class="text-muted small mb-0">Welcome back, Administrator <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></p>
            </div>
            <button class="btn btn-primary btn-sm px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#registerModal">
                <i class="fa-solid fa-user-plus me-1"></i> Register Patient
            </button>
        </div>

        <?= $message; ?>

        <!-- Dashboard Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md">
                <a href="index.php" class="stat-card text-decoration-none">
                    <div class="stat-icon stat-blue"><i class="fa-solid fa-users"></i></div>
                    <div><span class="text-muted small d-block">Approved Patients</span><h3 class="fw-bold mb-0 text-dark"><?= $total_patients; ?></h3></div>
                </a>
            </div>
            <div class="col-md">
                <div class="stat-card">
                    <div class="stat-icon stat-amber"><i class="fa-solid fa-user-clock"></i></div>
                    <div><span class="text-muted small d-block">Pending Approvals</span><h3 class="fw-bold mb-0"><?= $pending_approvals; ?></h3></div>
                </div>
            </div>
            <div class="col-md">
                <div class="stat-card">
                    <div class="stat-icon stat-emerald"><i class="fa-solid fa-calendar-check"></i></div>
                    <div><span class="text-muted small d-block">Appointments</span><h3 class="fw-bold mb-0"><?= $pending_appointments; ?></h3></div>
                </div>
            </div>
            <div class="col-md">
                <div class="stat-card">
                    <div class="stat-icon stat-rose"><i class="fa-solid fa-capsules"></i></div>
                    <div><span class="text-muted small d-block">Critical Stock</span><h3 class="fw-bold mb-0"><?= $critical_stock; ?></h3></div>
                </div>
            </div>
            <div class="col-md">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(220, 38, 38, 0.1); color: #dc2626;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div><span class="text-muted small d-block">Expired Meds</span><h3 class="fw-bold mb-0"><?= $expired_medicines; ?></h3></div>
                </div>
            </div>
        </div>

        <div class="card card-custom">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fa-solid fa-calendar-day text-primary me-2"></i> Appointment Requests</span>
                <span class="badge bg-light text-dark border"><?= $appointments ? $appointments->num_rows : 0; ?> Total</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Patient Details</th>
                                <th>Schedule</th>
                                <th>Purpose</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($appointments && $appointments->num_rows > 0): ?>
                                <?php while($app = $appointments->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong class="d-block text-dark"><?= htmlspecialchars($app['patient_name']); ?></strong>
                                            <small class="text-muted"><?= htmlspecialchars($app['contact_no'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td><small><?= htmlspecialchars($app['appointment_date']); ?> <?= htmlspecialchars($app['appointment_time']); ?></small></td>
                                        <td><small><?= htmlspecialchars($app['purpose'] ?? 'General Checkup'); ?></small></td>
                                        <td class="text-end">
                                            <form method="POST" class="d-flex gap-1 justify-content-end">
                                                <input type="hidden" name="appointment_id" value="<?= $app['id']; ?>">
                                                <select name="status" class="form-select form-select-sm status-select-dashboard">
                                                    <option value="Pending" <?= $app['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="Approved" <?= $app['status'] == 'Approved' ? 'selected' : ''; ?>>Approve</option>
                                                    <option value="Rejected" <?= $app['status'] == 'Rejected' ? 'selected' : ''; ?>>Reject</option>
                                                </select>
                                                <button type="submit" name="update_appointment" class="btn btn-sm btn-primary">Save</button>
                                                <button type="submit" name="delete_appointment" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this request?');"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No appointments requested.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Register Patient Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus text-primary me-2"></i> Register New Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label small fw-semibold">Full Name</label><input type="text" class="form-control" name="full_name" required></div>
                            <div class="col-md-3"><label class="form-label small fw-semibold">Age</label><input type="number" class="form-control" name="age" required></div>
                            <div class="col-md-3"><label class="form-label small fw-semibold">Gender</label><select class="form-select" name="gender"><option>Male</option><option>Female</option></select></div>
                            <div class="col-md-4"><label class="form-label small fw-semibold">Contact No.</label><input type="text" class="form-control" name="contact_no" required></div>
                            <div class="col-md-4"><label class="form-label small fw-semibold">Birthdate</label><input type="date" class="form-control" name="birthdate" required></div>
                            <div class="col-md-4"><label class="form-label small fw-semibold">Address</label><input type="text" class="form-control" name="address" required></div>
                            <div class="col-md-6"><label class="form-label small fw-semibold">Username</label><input type="text" class="form-control" name="username" required></div>
                            <div class="col-md-6"><label class="form-label small fw-semibold">Password</label><input type="password" class="form-control" name="password" required></div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="register_patient" class="btn btn-primary btn-sm">Save Patient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Ang Chart Container at Canvas -->
    <div class="row mt-4 mb-4" style="margin-left: 260px; padding-right: 20px;">
        <div class="col-12">
            <div class="card card-custom p-4 bg-white border rounded-3 shadow-sm">
                <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-chart-pie me-2"></i> Health Center Overview Analytics</h5>
                <div class="chart-container">
                    <canvas id="healthChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Ang JavaScript para sa Chart.js -->
    <script>
        const ctx = document.getElementById('healthChart').getContext('2d');
        const healthChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Patients', 'Appointments', 'Home Visitations', 'Critical Stock', 'Expired Meds'],
                datasets: [{
                    label: 'System Metrics Count',
                    data: [
                        <?= $total_patients; ?>, 
                        <?= $pending_appointments; ?>, 
                        <?= $pending_visitations; ?>, 
                        <?= $critical_stock; ?>,
                        <?= $expired_medicines; ?>
                    ],
                    backgroundColor: [
                        'rgba(2, 132, 199, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(220, 38, 38, 0.9)'
                    ],
                    borderColor: [
                        'rgb(2, 132, 199)',
                        'rgb(16, 185, 129)',
                        'rgb(245, 158, 11)',
                        'rgb(239, 68, 68)',
                        'rgb(185, 28, 28)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>