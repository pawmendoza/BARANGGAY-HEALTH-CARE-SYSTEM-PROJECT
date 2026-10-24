<?php
session_start();
require_once '../configDB/db.php';

if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'doctor', 'nurse'])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";

// Handle Delete Medical Record
if (isset($_POST['delete_record'])) {
    $record_id = intval($_POST['record_id']);
    $del_stmt = $conn->prepare("DELETE FROM medical_records WHERE id = ?");
    $del_stmt->bind_param("i", $record_id);
    if ($del_stmt->execute()) {
        $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fa-solid fa-circle-check me-2'></i> Medical record deleted successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    $del_stmt->close();
}

$history = $conn->query("SELECT * FROM medical_records ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History - HCMS</title>
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
            <li><a href="medical_history.php" class="nav-link active"><i class="fa-solid fa-clock-rotate-left me-3"></i> Medical History</a></li>
            <li><a href="export_report.php" class="nav-link"><i class="fa-solid fa-file-invoice me-3"></i> FHSIS / Reports</a></li>
            <li><a href="index.php" class="nav-link"><i class="fa-solid fa-users me-3"></i> Registered Patients</a></li>
        </ul>
        <div class="p-3 border-top mt-auto">
            <a href="../auth/logout.php" class="btn btn-outline-danger w-100 btn-sm py-2 fw-semibold">
                <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Log Out
            </a>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="top-header">
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Patient Medical Records</h4>
            <p class="text-muted small mb-0">View past consultations, diagnoses, and prescriptions given</p>
        </div>

        <?= $message; ?>

        <div class="card card-custom">
            <div class="card-header bg-white py-3 fw-bold border-bottom">Consultation History Log</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Patient Name</th>
                                <th>Status / Findings</th>
                                <th>Prescription / Notes</th>
                                <th>Date Recorded</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history && $history->num_rows > 0): ?>
                                <?php while($row = $history->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['patient_name']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-success bg-opacity-10 text-success fw-semibold me-1">Completed</span>
                                            <span class="text-dark"><?= htmlspecialchars($row['diagnosis']); ?></span>
                                        </td>
                                        <td><small class="text-muted"><?= htmlspecialchars($row['prescription']); ?></small></td>
                                        <td>
                                            <small class="fw-medium text-dark">
                                                <?= !empty($row['created_at']) ? date('M d, Y h:i A', strtotime($row['created_at'])) : (!empty($row['record_date']) ? date('M d, Y h:i A', strtotime($row['record_date'])) : date('M d, Y h:i A')); ?>
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this medical record?');">
                                                <input type="hidden" name="record_id" value="<?= $row['id']; ?>">
                                                <button type="submit" name="delete_record" class="btn btn-sm btn-outline-danger" title="Delete Record">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No medical records found.</td></tr>
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