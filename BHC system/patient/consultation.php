<?php
session_start();
require_once '../configDB/db.php';

// Check if user is logged in and has privileges
if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'doctor', 'nurse'])) {
    header("Location: ../auth/login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

// Handle Form Submission para sa Consultation / Check-up
if (isset($_POST['save_consultation'])) {
    $p_name = trim($_POST['patient_name']);
    $bp = trim($_POST['bp']);
    $temp = trim($_POST['temp']);
    $weight = trim($_POST['weight']);
    $diagnosis = trim($_POST['diagnosis']);
    $prescription = trim($_POST['prescription']);
    $immunization = trim($_POST['immunization']);
    
    // Format diagnosis and vital signs for medical records
    $full_diagnosis = "BP: $bp | Temp: {$temp}°C | Wt: {$weight}kg | Dx: $diagnosis" . (!empty($immunization) ? " | Immunization: $immunization" : "");
    $full_prescription = $prescription;

    $stmt = $conn->prepare("INSERT INTO medical_records (patient_name, diagnosis, prescription) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $p_name, $full_diagnosis, $full_prescription);
    
    if ($stmt->execute()) {
        $success_msg = "Consultation and vital signs successfully recorded!";
    } else {
        $error_msg = "Error: " . $conn->error;
    }
    $stmt->close();
}

// Kunin ang listahan ng mga approved na pasyente mula sa database
$patients_list = $conn->query("SELECT full_name FROM users WHERE role = 'patient' AND status = 'Approved' ORDER BY full_name ASC");

// Kunin ang mga nakaraang konsultasyon mula sa medical_records
$consultations_history = $conn->query("SELECT * FROM medical_records ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Module - Health Center Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Embedded Styling para sigurado at walang sablay na layout -->
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
                <a href="../admin/admin_dashboard.php" class="nav-link">
                    <i class="fa-solid fa-chart-line me-3"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="../admin/home_visitations.php" class="nav-link">
                    <i class="fa-solid fa-house-medical me-3"></i> Home Visitations
                </a>
            </li>
            <li>
                <a href="../admin/inventory.php" class="nav-link">
                    <i class="fa-solid fa-boxes-stacked me-3"></i> Medicine Inventory
                </a>
            </li>
            <li>
                <a href="../admin/medical_history.php" class="nav-link">
                    <i class="fa-solid fa-clock-rotate-left me-3"></i> Medical History
                </a>
            </li>
            <li>
                <a href="../admin/export_report.php" class="nav-link">
                    <i class="fa-solid fa-file-invoice me-3"></i> FHSIS / Reports
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
        
        <!-- Top Header Bar -->
        <div class="top-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="fa-solid fa-stethoscope text-primary me-2"></i> Patient Check-up & Consultation Module</h4>
                <p class="text-muted small mb-0">Record vital signs, medical findings, prescriptions, and immunizations</p>
            </div>
            <a href="../admin/admin_dashboard.php" class="btn btn-outline-secondary btn-sm py-2 px-3 fw-semibold">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <?php if($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?= $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i> <?= $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Consultation Form -->
            <div class="col-xl-4">
                <div class="card card-custom p-4">
                    <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-user-doctor text-primary me-2"></i> Check-up Form</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Patient Name</label>
                            <input type="text" name="patient_name" class="form-control" placeholder="Select or type patient name" list="patients_datalist" value="<?= htmlspecialchars($_GET['patient'] ?? ''); ?>" required>
                            <datalist id="patients_datalist">
                                <?php if($patients_list): while($p = $patients_list->fetch_assoc()): ?>
                                    <option value="<?= $p['full_name']; ?>">
                                <?php endwhile; endif; ?>
                            </datalist>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <label class="form-label small fw-semibold">Blood Pres.</label>
                                <input type="text" name="bp" class="form-control form-control-sm" placeholder="120/80" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-semibold">Temp (°C)</label>
                                <input type="text" name="temp" class="form-control form-control-sm" placeholder="36.5" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-semibold">Weight (kg)</label>
                                <input type="text" name="weight" class="form-control form-control-sm" placeholder="60" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Diagnosis / Findings</label>
                            <textarea name="diagnosis" class="form-control" rows="2" placeholder="Ilagay ang suri o findings ng doktor..." required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Prescription / Medicines Given</label>
                            <textarea name="prescription" class="form-control" rows="2" placeholder="Hal. Paracetamol 500mg 3x a day..." required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Immunization (Kung Mayroon)</label>
                            <input type="text" name="immunization" class="form-control form-control-sm" placeholder="Hal. Anti-Flu / BCG Vaccine">
                        </div>

                        <button type="submit" name="save_consultation" class="btn btn-primary w-100 fw-semibold py-2">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Consultation Record
                        </button>
                    </form>
                </div>
            </div>

            <!-- Consultation History Table -->
            <div class="col-xl-8">
                <div class="card card-custom">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark">Recent Consultation Logs</span>
                        <span class="badge bg-light text-dark fw-normal border"><?= $consultations_history ? $consultations_history->num_rows : 0; ?> Records Found</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Patient Name</th>
                                        <th>Findings & Prescriptions</th>
                                        <th>Date Recorded</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($consultations_history && $consultations_history->num_rows > 0): ?>
                                        <?php while($row = $consultations_history->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong class="text-dark"><?= htmlspecialchars($row['patient_name']); ?></strong></td>
                                                <td>
                                                    <span class="text-primary fw-medium">Details:</span> <?= htmlspecialchars($row['diagnosis']); ?><br>
                                                    <small class="text-muted">Rx: <?= htmlspecialchars($row['prescription']); ?></small>
                                                </td>
                                                <td><small class="text-muted"><?= htmlspecialchars($row['created_at'] ?? $row['record_date'] ?? 'N/A'); ?></small></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center py-4 text-muted">No consultations recorded yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>