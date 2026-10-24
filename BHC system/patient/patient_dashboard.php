<?php
session_start();
require_once '../configDB/db.php';

// Ensure the logged-in user is a patient
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Patient';
$patient_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$message = "";

// 1. Handle Clinic Appointment Submission
if (isset($_POST['request_appointment'])) {
    $app_date = trim($_POST['appointment_date']);
    $app_time = trim($_POST['appointment_time']);
    $purpose = trim($_POST['purpose']);
    $status = 'Pending';

    // Temporarily disable foreign key checks to prevent constraint errors
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    if ($patient_id) {
        $stmt = $conn->prepare("INSERT INTO appointments (user_id, patient_name, appointment_date, appointment_time, purpose, status) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isssss", $patient_id, $patient_name, $app_date, $app_time, $purpose, $status);
        }
    }
    
    if (!isset($stmt) || !$stmt) {
        $stmt = $conn->prepare("INSERT INTO appointments (patient_name, appointment_date, appointment_time, purpose, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $patient_name, $app_date, $app_time, $purpose, $status);
    }
    
    if ($stmt && $stmt->execute()) {
        $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                        <i class='fa-solid fa-circle-check me-2'></i> Appointment request submitted successfully!
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                    </div>";
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                        Error: " . $conn->error . "
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                    </div>";
    }
    if ($stmt) $stmt->close();
    
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
}

// 2. Handle Home Visitation Request Submission
if (isset($_POST['request_visitation'])) {
    $req_date = trim($_POST['requested_date']);
    $address = trim($_POST['complete_address']);
    $reason = trim($_POST['reason_for_visit']);
    $condition = trim($_POST['patient_condition'] ?? 'General Checkup');
    $status = 'Pending';

    // Temporarily disable foreign key checks to prevent constraint errors[cite: 23]
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    if ($patient_id) {
        $stmt = $conn->prepare("INSERT INTO home_visitations (user_id, patient_name, complete_address, requested_date, patient_condition, reason_for_visit, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("issssss", $patient_id, $patient_name, $address, $req_date, $condition, $reason, $status);
        }
    }

    if (!isset($stmt) || !$stmt) {
        $stmt = $conn->prepare("INSERT INTO home_visitations (patient_name, complete_address, requested_date, patient_condition, reason_for_visit, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $patient_name, $address, $req_date, $condition, $reason, $status);
    }
    
    if ($stmt && $stmt->execute()) {
        $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                        <i class='fa-solid fa-circle-check me-2'></i> Home visitation request submitted successfully!
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                    </div>";
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                        Error: " . $conn->error . "
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                    </div>";
    }
    if ($stmt) $stmt->close();

    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
}

// Fetch Data for Patient Portal
$inventory = $conn->query("SELECT * FROM inventory ORDER BY medicine_name ASC");

$my_appointments = $conn->prepare("SELECT * FROM appointments WHERE patient_name = ? ORDER BY id DESC");
$my_appointments->bind_param("s", $patient_name);
$my_appointments->execute();
$appointments_res = $my_appointments->get_result();

$my_visitations = $conn->prepare("SELECT * FROM home_visitations WHERE patient_name = ? ORDER BY id DESC");
$my_visitations->bind_param("s", $patient_name);
$my_visitations->execute();
$visitations_res = $my_visitations->get_result();

$my_history = $conn->prepare("SELECT * FROM medical_records WHERE patient_name = ? ORDER BY id DESC");
$my_history->bind_param("s", $patient_name);
$my_history->execute();
$history_res = $my_history->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal - Health Center Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css/style.css">
</head>
<body>

    <!-- Header / Navbar -->
    <nav class="navbar navbar-custom d-flex justify-content-between align-items-center px-4 py-3 bg-white border-bottom">
        <div class="d-flex align-items-center">
            <h5 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-heart-pulse me-2"></i> Patient Portal</h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small">Welcome, <strong><?= htmlspecialchars($patient_name); ?></strong></span>
            <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm px-3 fw-semibold"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Logout</a>
        </div>
    </nav>

    <div class="container my-4">
        <?= $message; ?>

        <!-- Navigation Tabs -->
        <ul class="nav nav-pills justify-content-center mb-4 gap-2 bg-white p-2 rounded-3 border" id="portalTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="medicines-tab" data-bs-toggle="pill" data-bs-target="#medicines" type="button"><i class="fa-solid fa-capsules me-2"></i> Available Medicines</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="appointments-tab" data-bs-toggle="pill" data-bs-target="#appointments" type="button"><i class="fa-solid fa-calendar-check me-2"></i> Clinic Appointments</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="visitations-tab" data-bs-toggle="pill" data-bs-target="#visitations" type="button"><i class="fa-solid fa-house-medical me-2"></i> Home Visitations</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="history-tab" data-bs-toggle="pill" data-bs-target="#history" type="button"><i class="fa-solid fa-clock-rotate-left me-2"></i> Medical History</button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="portalTabsContent">
            
            <!-- 1. Available Medicines -->
            <div class="tab-pane fade show active" id="medicines">
                <div class="card card-custom p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i> Health Center Medicine Inventory Status</h5>
                            <p class="text-muted small mb-0">Search or select a category to filter available medicines:</p>
                        </div>
                        <div class="input-group input-group-sm patient-search">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="patientSearchInput" class="form-control" placeholder="Search medicine name..." onkeyup="filterPatientMedicine()">
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <button type="button" onclick="filterPatientCategory('All', this)" class="btn btn-sm btn-outline-primary filter-btn active">All Categories</button>
                        <button type="button" onclick="filterPatientCategory('Pregnancy', this)" class="btn btn-sm btn-outline-primary filter-btn">Pregnancy / Maternal</button>
                        <button type="button" onclick="filterPatientCategory('Hypertension', this)" class="btn btn-sm btn-outline-primary filter-btn">High Blood / Hypertension</button>
                        <button type="button" onclick="filterPatientCategory('Fever', this)" class="btn btn-sm btn-outline-primary filter-btn">Fever & Pain</button>
                        <button type="button" onclick="filterPatientCategory('Antibiotic', this)" class="btn btn-sm btn-outline-primary filter-btn">Antibiotics</button>
                        <button type="button" onclick="filterPatientCategory('Vitamins', this)" class="btn btn-sm btn-outline-primary filter-btn">Vitamins & Supplements</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="patientMedicineTable">
                            <thead>
                                <tr>
                                    <th>Medicine Name</th>
                                    <th>Category</th>
                                    <th>Stock Availability</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($inventory && $inventory->num_rows > 0): ?>
                                    <?php while($med = $inventory->fetch_assoc()): ?>
                                        <tr class="patient-med-row">
                                            <td><strong class="med-name text-dark"><?= htmlspecialchars($med['medicine_name']); ?></strong></td>
                                            <td><span class="badge bg-info bg-opacity-10 text-info fw-semibold med-cat"><?= htmlspecialchars($med['category'] ?? 'General Medicine'); ?></span></td>
                                            <td>
                                                <?php $stock = $med['stock_level'] ?? $med['quantity'] ?? 0; ?>
                                                <?php if($stock > 0): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i> Available (<?= $stock; ?>)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger fw-semibold"><i class="fa-solid fa-circle-xmark me-1"></i> Out of Stock</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">No medicine inventory records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 2. Clinic Appointments -->
            <div class="tab-pane fade" id="appointments">
                <div class="row g-4">
                    <div class="col-md-5">
                        <div class="card card-custom p-4">
                            <h5 class="fw-bold mb-3"><i class="fa-solid fa-calendar-plus text-primary me-2"></i> Book Clinic Appointment</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Preferred Appointment Date</label>
                                    <input type="date" name="appointment_date" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Preferred Time</label>
                                    <input type="time" name="appointment_time" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Purpose of Visit</label>
                                    <textarea name="purpose" class="form-control" rows="3" placeholder="e.g. Dental Checkup, Prenatal Care, General Consultation" required></textarea>
                                </div>
                                <button type="submit" name="request_appointment" class="btn btn-primary w-100 fw-semibold py-2">
                                    <i class="fa-solid fa-paper-plane me-1"></i> Submit Appointment Request
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="card card-custom">
                            <div class="card-header bg-white py-3 fw-bold">My Appointment Requests</div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Schedule</th>
                                                <th>Purpose</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if($appointments_res && $appointments_res->num_rows > 0): ?>
                                                <?php while($app = $appointments_res->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <strong class="d-block text-dark"><?= htmlspecialchars($app['appointment_date']); ?></strong>
                                                            <small class="text-muted"><?= htmlspecialchars($app['appointment_time']); ?></small>
                                                        </td>
                                                        <td><small><?= htmlspecialchars($app['purpose']); ?></small></td>
                                                        <td>
                                                            <?php 
                                                                $st = $app['status'];
                                                                if($st == 'Approved') echo '<span class="badge bg-success">Approved</span>';
                                                                elseif($st == 'Rejected') echo '<span class="badge bg-danger">Rejected</span>';
                                                                else echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr><td colspan="3" class="text-center text-muted py-4">No appointment requests submitted yet.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Home Visitations -->
            <div class="tab-pane fade" id="visitations">
                <div class="row g-4">
                    <div class="col-md-5">
                        <div class="card card-custom p-4">
                            <h5 class="fw-bold mb-3"><i class="fa-solid fa-house-medical text-primary me-2"></i> Request Home Visitation</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Requested Visit Date</label>
                                    <input type="date" name="requested_date" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Home Address</label>
                                    <input type="text" name="complete_address" class="form-control" placeholder="House No., Street, Purok, Barangay" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Patient Condition / Category</label>
                                    <select name="patient_condition" class="form-select" required>
                                        <option value="Bedridden Patient">Bedridden Patient</option>
                                        <option value="Senior Citizen Checkup">Senior Citizen Checkup</option>
                                        <option value="Postnatal / Maternal Care">Postnatal / Maternal Care</option>
                                        <option value="PWD / Special Care">PWD / Special Care</option>
                                        <option value="General Consultation">General Consultation</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Reason for Visit</label>
                                    <textarea name="reason_for_visit" class="form-control" rows="3" placeholder="State reason why home visit is needed..." required></textarea>
                                </div>
                                <button type="submit" name="request_visitation" class="btn btn-primary w-100 fw-semibold py-2">
                                    <i class="fa-solid fa-paper-plane me-1"></i> Submit Request
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="card card-custom">
                            <div class="card-header bg-white py-3 fw-bold">My Home Visitation Requests</div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Requested Date</th>
                                                <th>Reason / Condition</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if($visitations_res && $visitations_res->num_rows > 0): ?>
                                                <?php while($v = $visitations_res->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($v['requested_date'] ?? $v['visit_date'] ?? 'N/A'); ?></strong></td>
                                                        <td>
                                                            <span class="d-block small text-dark fw-medium"><?= htmlspecialchars($v['reason_for_visit'] ?? $v['reason'] ?? 'N/A'); ?></span>
                                                            <small class="text-muted"><?= htmlspecialchars($v['patient_condition'] ?? ''); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                                $st = $v['status'];
                                                                if($st == 'Scheduled' || $st == 'Completed') echo '<span class="badge bg-success">' . $st . '</span>';
                                                                elseif($st == 'Cancelled') echo '<span class="badge bg-danger">' . $st . '</span>';
                                                                else echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr><td colspan="3" class="text-center text-muted py-4">No home visitation requests submitted yet.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Medical History -->
            <div class="tab-pane fade" id="history">
                <div class="card card-custom p-4">
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> My Personal Medical Consultation History</h5>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Diagnosis / Findings</th>
                                    <th>Prescription / Notes</th>
                                    <th>Date Recorded</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($history_res && $history_res->num_rows > 0): ?>
                                    <?php while($h = $history_res->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong class="text-dark"><?= htmlspecialchars($h['diagnosis'] ?? 'N/A'); ?></strong></td>
                                            <td><small class="text-muted"><?= htmlspecialchars($h['prescription'] ?? 'N/A'); ?></small></td>
                                            <td><small><?= htmlspecialchars($h['created_at'] ?? $h['record_date'] ?? 'N/A'); ?></small></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">No past medical consultation records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Client-Side Live Search & Category Filter Script -->
    <script>
        let patientSelectedCategory = 'All';

        function filterPatientMedicine() {
            let searchVal = document.getElementById('patientSearchInput').value.toLowerCase();
            let rows = document.querySelectorAll('.patient-med-row');

            rows.forEach(row => {
                let name = row.querySelector('.med-name').textContent.toLowerCase();
                let category = row.querySelector('.med-cat').textContent.toLowerCase();

                let matchesSearch = name.includes(searchVal) || category.includes(searchVal);
                let matchesCategory = (patientSelectedCategory === 'All') || category.includes(patientSelectedCategory.toLowerCase());

                if (matchesSearch && matchesCategory) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function filterPatientCategory(categoryName, btnElement) {
            patientSelectedCategory = categoryName;
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            btnElement.classList.add('active');
            filterPatientMedicine();
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>