<?php
session_start();
require_once '../configDB/db.php';

if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'doctor', 'nurse'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch all audit logs
$logs = $conn->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 100");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - HCMS</title>
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
            <li><a href="home_visitations.php" class="nav-link"><i class="fa-solid fa-house-medical me-3"></i> Home Visitations</a></li>
            <li><a href="inventory.php" class="nav-link"><i class="fa-solid fa-boxes-stacked me-3"></i> Medicine Inventory</a></li>
            <li><a href="medical_history.php" class="nav-link"><i class="fa-solid fa-clock-rotate-left me-3"></i> Medical History</a></li>
            <li><a href="export_report.php" class="nav-link"><i class="fa-solid fa-file-invoice me-3"></i> FHSIS / Reports</a></li>
            <li><a href="index.php" class="nav-link"><i class="fa-solid fa-users me-3"></i> Registered Patients</a></li>
            <li><a href="audit_logs.php" class="nav-link active"><i class="fa-solid fa-shield-halved me-3"></i> Audit Logs</a></li>
        </ul>
        <div class="p-3 border-top mt-auto">
            <a href="../auth/logout.php" class="btn btn-outline-danger w-100 btn-sm py-2 fw-semibold">
                <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Log Out
            </a>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="top-header">
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-shield-halved text-primary me-2"></i> System Audit Logs & Activity Trail</h4>
            <p class="text-muted small mb-0">Real-time security logs tracking user actions and system changes:</p>
        </div>

        <div class="card card-custom mt-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <span class="fw-bold"><i class="fa-solid fa-list-check me-2 text-primary"></i> Recent User Activities</span>
                <span class="badge bg-light text-dark border">Top 100 Logs</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Action Performed</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logs && $logs->num_rows > 0): ?>
                                <?php while($row = $logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><small class="text-muted"><i class="fa-regular fa-clock me-1"></i><?= htmlspecialchars($row['created_at']); ?></small></td>
                                        <td><strong class="text-dark"><?= htmlspecialchars($row['username']); ?></strong></td>
                                        <td><span class="badge bg-primary bg-opacity-10 text-primary"><?= htmlspecialchars(strtoupper($row['role'])); ?></span></td>
                                        <td><span class="fw-semibold text-dark"><?= htmlspecialchars($row['action']); ?></span></td>
                                        <td><small class="text-muted"><?= htmlspecialchars($row['details']); ?></small></td>
                                        <td><small class="badge bg-light text-secondary border"><?= htmlspecialchars($row['ip_address']); ?></small></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No activity logs recorded yet.</td></tr>
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