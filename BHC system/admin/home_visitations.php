<?php
session_start();
require_once '../configDB/db.php';
require_once '../configDB/logger.php'; // Idinagdag para sa Activity Audit Logging

if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'doctor', 'nurse'])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";

// Handle Delete Visitation Request
if (isset($_POST['delete_visit'])) {
    $visit_id = intval($_POST['visit_id']);

    // Kunin ang pangalan ng pasyente bago burahin para sa log details
    $get_v = $conn->prepare("SELECT patient_name FROM home_visitations WHERE id = ?");
    $get_v->bind_param("i", $visit_id);
    $get_v->execute();
    $v_res_del = $get_v->get_result()->fetch_assoc();
    $v_patient = $v_res_del['patient_name'] ?? "ID #$visit_id";
    $get_v->close();

    $del_stmt = $conn->prepare("DELETE FROM home_visitations WHERE id = ?");
    $del_stmt->bind_param("i", $visit_id);
    if ($del_stmt->execute()) {
        // I-record sa Audit Trail Logs
        logActivity($conn, "DELETE VISITATION", "Deleted home visitation request for: $v_patient");
        $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fa-solid fa-circle-check me-2'></i> Home visitation request deleted successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    $del_stmt->close();
}

// Handle Status Update
if (isset($_POST['update_status'])) {
    $visit_id = intval($_POST['visit_id']);
    $status = trim($_POST['status']);
    
    $v_query = $conn->prepare("SELECT * FROM home_visitations WHERE id = ?");
    $v_query->bind_param("i", $visit_id);
    $v_query->execute();
    $v_res = $v_query->get_result()->fetch_assoc();
    $v_query->close();

    $stmt = $conn->prepare("UPDATE home_visitations SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $visit_id);
    
    if ($stmt->execute()) {
        $p_log_name = $v_res['patient_name'] ?? "ID #$visit_id";
        // I-record sa Audit Trail Logs
        logActivity($conn, "UPDATE VISITATION STATUS", "Updated home visitation for $p_log_name to status: $status");

        if ($status === 'Completed' && $v_res) {
            $p_name = $v_res['patient_name'];
            $diagnosis = "Home Visitation Checkup - Condition: " . ($v_res['patient_condition'] ?? 'General');
            $prescription = "Reason/Notes: " . ($v_res['reason_for_visit'] ?? 'N/A');
            
            $conn->query("ALTER TABLE medical_records ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            
            $ins_med = $conn->prepare("INSERT INTO medical_records (patient_name, diagnosis, prescription, created_at) VALUES (?, ?, ?, NOW())");
            $ins_med->bind_param("sss", $p_name, $diagnosis, $prescription);
            $ins_med->execute();
            $ins_med->close();
            
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        }

        $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fa-solid fa-circle-check me-2'></i> Visitation status updated to <strong>$status</strong> and logged to Medical History!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    $stmt->close();
}

// Count pending visitation requests for notification
$pending_count = $conn->query("SELECT COUNT(*) AS total FROM home_visitations WHERE status = 'Pending'")->fetch_assoc()['total'] ?? 0;

$visitations = $conn->query("SELECT * FROM home_visitations ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Visitations - HCMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css/style.css">
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand"><i class="fa-solid fa-hospital-user me-2"></i> Health Center</div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" class="nav-link"><i class="fa-solid fa-chart-line me-3"></i> Dashboard</a></li>
            <li><a href="home_visitations.php" class="nav-link active d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-house-medical me-3"></i> Home Visitations</span>
                <?php if($pending_count > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?= $pending_count; ?></span>
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
        <div class="top-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="fa-solid fa-house-medical text-primary me-2"></i> Home Visitation Requests</h4>
                <p class="text-muted small mb-0">Manage and monitor patient requests for home health visits</p>
            </div>
            <?php if($pending_count > 0): ?>
                <div class="alert alert-warning py-2 px-3 mb-0 small d-flex align-items-center border-warning">
                    <i class="fa-solid fa-bell me-2 text-warning fs-5"></i>
                    <span>You have <strong><?= $pending_count; ?></strong> pending home visitation request(s).</span>
                </div>
            <?php endif; ?>
        </div>

        <?= $message; ?>

        <div class="card card-custom">
            <div class="card-header bg-white py-3 fw-bold border-bottom d-flex justify-content-between align-items-center">
                <span>Visitation Requests List</span>
                <span class="badge bg-warning text-dark border"><?= $pending_count; ?> Pending Request(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Patient Name</th>
                                <th>Address</th>
                                <th>Condition</th>
                                <th>Reason / Date</th>
                                <th class="text-end">Status Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($visitations && $visitations->num_rows > 0): ?>
                                <?php while($row = $visitations->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['patient_name']); ?></strong></td>
                                        <td><small class="text-muted"><?= htmlspecialchars($row['complete_address'] ?? $row['address'] ?? 'N/A'); ?></small></td>
                                        <td><span class="badge bg-info bg-opacity-10 text-info"><?= htmlspecialchars($row['patient_condition'] ?? 'General'); ?></span></td>
                                        <td>
                                            <small class="d-block text-dark"><?= htmlspecialchars($row['reason_for_visit'] ?? $row['reason'] ?? ''); ?></small>
                                            <span class="text-muted requested-date">Requested: <?= htmlspecialchars($row['requested_date'] ?? $row['visit_date'] ?? ''); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" class="d-flex gap-1 align-items-center justify-content-end">
                                                <input type="hidden" name="visit_id" value="<?= $row['id']; ?>">
                                                <select name="status" class="form-select form-select-sm status-select-home">
                                                    <option value="Pending" <?= $row['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="Scheduled" <?= $row['status'] == 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                    <option value="Completed" <?= $row['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="Cancelled" <?= $row['status'] == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                                <button type="submit" name="update_status" class="btn btn-sm btn-primary">Save</button>
                                                <button type="submit" name="delete_visit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this visitation request?');" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No home visitation requests found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>