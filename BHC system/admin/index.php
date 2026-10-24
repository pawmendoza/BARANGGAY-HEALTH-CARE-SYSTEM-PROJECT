<?php
session_start();
require_once '../configDB/db.php';
require_once '../configDB/logger.php'; // Idinagdag para sa Activity Audit Logging

// Check if user is logged in and has privileges
if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'doctor', 'nurse'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Handle User Deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // Kunin muna ang pangalan ng pasyente bago burahin para sa log details
    $get_p = $conn->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'patient'");
    $get_p->bind_param("i", $delete_id);
    $get_p->execute();
    $p_res = $get_p->get_result()->fetch_assoc();
    $patient_name = $p_res['full_name'] ?? "ID #$delete_id";
    $get_p->close();

    $stmt_del = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'patient'");
    $stmt_del->bind_param("i", $delete_id);
    if ($stmt_del->execute()) {
        // I-record sa Audit Trail Logs
        logActivity($conn, "DELETE PATIENT", "Deleted patient record: $patient_name");
    }
    $stmt_del->close();
    header("Location: index.php");
    exit();
}

// Handle Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    $searchTerm = "%$search%";
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = 'patient' AND (full_name LIKE ? OR username LIKE ? OR address LIKE ?) ORDER BY full_name ASC");
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $patients = $stmt->get_result();
} else {
    $patients = $conn->query("SELECT * FROM users WHERE role = 'patient' ORDER BY full_name ASC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Patients Directory - Health Center Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="../style.css/style.css">
</head>
<body>

    <!-- Fixed Left Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-hospital-user me-2"></i> Health Center
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="admin_dashboard.php" class="nav-link">
                    <i class="fa-solid fa-chart-line me-3"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="home_visitations.php" class="nav-link">
                    <i class="fa-solid fa-house-medical me-3"></i> Home Visitations
                </a>
            </li>
            <li>
                <a href="inventory.php" class="nav-link">
                    <i class="fa-solid fa-boxes-stacked me-3"></i> Medicine Inventory
                </a>
            </li>
            <li>
                <a href="medical_history.php" class="nav-link">
                    <i class="fa-solid fa-clock-rotate-left me-3"></i> Medical History
                </a>
            </li>
            <li>
                <a href="export_report.php" class="nav-link">
                    <i class="fa-solid fa-file-invoice me-3"></i> FHSIS / Reports
                </a>
            </li>
            <li>
                <a href="index.php" class="nav-link active">
                    <i class="fa-solid fa-users me-3"></i> Registered Patients
                </a>
            </li>
            <li>
                <a href="audit_logs.php" class="nav-link">
                    <i class="fa-solid fa-shield-halved me-3"></i> Audit Logs
                </a>
            </li>
        </ul>
        <div class="p-3 border-top mt-auto">
            <a href="../auth/logout.php" class="btn btn-outline-danger w-100 btn-sm py-2 fw-semibold">
                <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Log Out
            </a>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-wrapper">
        
        <!-- Top Header Bar with Search -->
        <div class="top-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="fa-solid fa-users text-primary me-2"></i> Registered Patients Directory</h4>
                <p class="text-muted small mb-0">View all registered barangay residents and patient accounts</p>
            </div>
            
            <form method="GET" class="d-flex gap-2 patient-search-form">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, username..." value="<?= htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fa-solid fa-search"></i></button>
            </form>
        </div>

        <!-- Patient Accounts List Card -->
        <div class="card card-custom">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">Patient Accounts List</span>
                <span class="badge bg-light text-dark fw-normal border"><?= $patients ? $patients->num_rows : 0; ?> Patients Total</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Full Name</th>
                                <th>Address</th>
                                <th>Contact No.</th>
                                <th>Username</th>
                                <th>Role / Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($patients && $patients->num_rows > 0): ?>
                                <?php while($row = $patients->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark"><?= htmlspecialchars($row['full_name']); ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars(($row['age'] ?? '') . ' yrs - ' . ($row['gender'] ?? '')); ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($row['address'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($row['contact_no'] ?? $row['contact_number'] ?? 'N/A'); ?></td>
                                        <td><code><?= htmlspecialchars($row['username']); ?></code></td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle"><?= htmlspecialchars($row['role']); ?></span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><?= htmlspecialchars($row['status'] ?? 'Approved'); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <a href="../patient/consultation.php?patient=<?= urlencode($row['full_name']); ?>" class="btn btn-sm btn-outline-primary fw-semibold">
                                                <i class="fa-solid fa-stethoscope me-1"></i> Check-up
                                            </a>
                                            <a href="index.php?delete_id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-danger fw-semibold ms-1" onclick="return confirm('Sigurado ka bang gusto mong burahin ang patient na ito? Silang lahat ay mawawala sa talaan.');">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No patient accounts found.</td></tr>
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