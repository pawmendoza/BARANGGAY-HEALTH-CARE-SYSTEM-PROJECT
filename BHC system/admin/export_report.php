<?php
session_start();
require_once '../configDB/db.php';

if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'doctor', 'nurse'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Ensure expiration_date and category columns exist in inventory table
$conn->query("ALTER TABLE inventory ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT 'General'");
$conn->query("ALTER TABLE inventory ADD COLUMN IF NOT EXISTS expiration_date DATE DEFAULT NULL");

$total_p = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='patient'")->fetch_assoc()['total'] ?? 0;
$total_a = $conn->query("SELECT COUNT(*) AS total FROM appointments")->fetch_assoc()['total'] ?? 0;
$total_v = $conn->query("SELECT COUNT(*) AS total FROM home_visitations")->fetch_assoc()['total'] ?? 0;

// Fetch Inventory Statistics using stock_level
$total_medicines = $conn->query("SELECT SUM(stock_level) AS total FROM inventory")->fetch_assoc()['total'] ?? 0;
$low_stock_medicines = $conn->query("SELECT * FROM inventory WHERE stock_level <= 20 ORDER BY medicine_name ASC");
$expired_medicines = $conn->query("SELECT * FROM inventory WHERE expiration_date IS NOT NULL AND expiration_date < CURDATE() ORDER BY expiration_date ASC");

// Fetch Treated Patients / Medical Records History
$treated_patients = $conn->query("SELECT * FROM medical_records ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FHSIS / Reports - HCMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css/style.css">
</head>
<body>

    <div class="sidebar no-print">
        <div class="sidebar-brand"><i class="fa-solid fa-hospital-user me-2"></i> Health Center</div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" class="nav-link"><i class="fa-solid fa-chart-line me-3"></i> Dashboard</a></li>
            <li><a href="home_visitations.php" class="nav-link"><i class="fa-solid fa-house-medical me-3"></i> Home Visitations</a></li>
            <li><a href="inventory.php" class="nav-link"><i class="fa-solid fa-boxes-stacked me-3"></i> Medicine Inventory</a></li>
            <li><a href="medical_history.php" class="nav-link"><i class="fa-solid fa-clock-rotate-left me-3"></i> Medical History</a></li>
            <li><a href="export_report.php" class="nav-link active"><i class="fa-solid fa-file-invoice me-3"></i> FHSIS / Reports</a></li>
            <li><a href="index.php" class="nav-link"><i class="fa-solid fa-users me-3"></i> Registered Patients</a></li>
        </ul>
        <div class="p-3 border-top mt-auto">
            <a href="../auth/logout.php" class="btn btn-outline-danger w-100 btn-sm py-2 fw-semibold">
                <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Log Out
            </a>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="top-header d-flex justify-content-between align-items-center no-print">
            <div>
                <h4 class="fw-bold mb-1"><i class="fa-solid fa-file-invoice text-primary me-2"></i> FHSIS Reports & Analytics</h4>
                <p class="text-muted small mb-0">Comprehensive summary report for barangay health center services</p>
            </div>
            <button onclick="window.print()" class="btn btn-primary btn-sm px-3 py-2 fw-semibold">
                <i class="fa-solid fa-print me-1"></i> Print / Export Report
            </button>
        </div>

        <div class="card card-custom p-4 mb-4">
            <h3 class="fw-bold text-center mb-1">Barangay Health Center Summary Report</h3>
            <p class="text-center text-muted small mb-4">Generated on: <?= date('F d, Y'); ?></p>

            <!-- Overview Counters -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="p-3 border rounded-3 bg-light text-center">
                        <span class="text-muted small d-block">Total Registered Patients</span>
                        <h3 class="fw-bold text-primary mb-0"><?= $total_p; ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 border rounded-3 bg-light text-center">
                        <span class="text-muted small d-block">Total Clinic Appointments</span>
                        <h3 class="fw-bold text-success mb-0"><?= $total_a; ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 border rounded-3 bg-light text-center">
                        <span class="text-muted small d-block">Total Home Visitations</span>
                        <h3 class="fw-bold text-warning mb-0"><?= $total_v; ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 border rounded-3 bg-light text-center">
                        <span class="text-muted small d-block">Total Available Medicine Units</span>
                        <h3 class="fw-bold text-info mb-0"><?= $total_medicines; ?></h3>
                    </div>
                </div>
            </div>

            <!-- Low Stock & Expired Medicines Section -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card border-danger shadow-sm">
                        <div class="card-header bg-danger text-white fw-bold small">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Low Stock Medicines (Critical)
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Medicine Name</th>
                                        <th>Category</th>
                                        <th>Remaining</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($low_stock_medicines && $low_stock_medicines->num_rows > 0): ?>
                                        <?php while($med = $low_stock_medicines->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($med['medicine_name']); ?></td>
                                                <td><?= htmlspecialchars($med['category'] ?? 'General'); ?></td>
                                                <td><span class="badge bg-danger"><?= $med['stock_level']; ?> units</span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center text-muted py-3">No low stock medicines found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-warning shadow-sm">
                        <div class="card-header bg-warning text-dark fw-bold small">
                            <i class="fa-solid fa-calendar-xmark me-1"></i> Expired Medicines
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Medicine Name</th>
                                        <th>Category</th>
                                        <th>Expiration Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($expired_medicines && $expired_medicines->num_rows > 0): ?>
                                        <?php while($exp = $expired_medicines->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($exp['medicine_name']); ?></td>
                                                <td><?= htmlspecialchars($exp['category'] ?? 'General'); ?></td>
                                                <td><span class="badge bg-dark"><?= htmlspecialchars($exp['expiration_date']); ?></span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center text-muted py-3">No expired medicines in inventory.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List of Treated Patients / Medical History Summary -->
            <div class="card border-secondary shadow-sm">
                <div class="card-header bg-secondary text-white fw-bold small">
                    <i class="fa-solid fa-clipboard-user me-1"></i> List of All Treated Patients & Consultations
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Patient Name</th>
                                    <th>Diagnosis / Findings</th>
                                    <th>Prescription / Notes</th>
                                    <th>Date Recorded</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($treated_patients && $treated_patients->num_rows > 0): ?>
                                    <?php while($record = $treated_patients->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($record['patient_name']); ?></strong></td>
                                            <td><?= htmlspecialchars($record['diagnosis']); ?></td>
                                            <td><small><?= htmlspecialchars($record['prescription']); ?></small></td>
                                            <td><small><?= htmlspecialchars($record['created_at'] ?? $record['record_date'] ?? 'N/A'); ?></small></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No medical Records or treated patients found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>