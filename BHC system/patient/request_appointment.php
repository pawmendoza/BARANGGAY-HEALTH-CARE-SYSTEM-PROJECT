<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "hcms_db");
$message = "";

if (isset($_POST['submit_visitation'])) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $patient_name = $_SESSION['full_name'] ?? 'Patient';
    $contact_no = $conn->real_escape_string($_POST['contact_no']);
    $address = $conn->real_escape_string($_POST['complete_address']);
    $req_date = $conn->real_escape_string($_POST['requested_date']);
    $condition = $conn->real_escape_string($_POST['patient_condition']);
    $reason = $conn->real_escape_string($_POST['reason_for_visit']);

    $sql = "INSERT INTO home_visitations (user_id, patient_name, contact_no, complete_address, requested_date, patient_condition, reason_for_visit) 
            VALUES ($user_id, '$patient_name', '$contact_no', '$address', '$req_date', '$condition', '$reason')";

    if ($conn->query($sql)) {
        $message = "<div class='alert alert-success alert-dismissible fade show'>Matagumpay na naipasa ang Home Visit Request! Maghintay ng confirmation at assigned BHW mula sa Health Center.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}

// Fetch previous visitation requests of this patient
$user_id = $_SESSION['user_id'] ?? 0;
$my_visitations = $conn->query("SELECT * FROM home_visitations WHERE user_id = $user_id ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Home Visitation - HCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css/style.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-dark bg-success px-4 shadow-sm">
        <span class="navbar-brand fw-bold"><i class="fa-solid fa-house-chimney-medical me-2"></i> Patient Portal - Home Visit Request</span>
        <div>
            <a href="patient_portal.php" class="btn btn-light btn-sm me-2">Back to Portal</a>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </nav>

    <div class="container my-4">
        <?= $message; ?>

        <div class="row">
            <!-- Form Side -->
            <div class="col-md-5 mb-4">
                <div class="card shadow-sm border-success">
                    <div class="card-header bg-success text-white fw-bold">
                        <i class="fa-solid fa-truck-medical me-2"></i> Book a Home Visit
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Contact Number</label>
                                <input type="text" name="contact_no" class="form-control" placeholder="09123456789" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Complete House Address</label>
                                <textarea name="complete_address" class="form-control" rows="2" placeholder="House No., Street, Purok, Barangay" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Preferred Visit Date</label>
                                <input type="date" name="requested_date" class="form-control" min="<?= date('Y-m-d'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Patient Category / Condition</label>
                                <select name="patient_condition" class="form-select" required>
                                    <option value="" selected disabled>-- Select Category --</option>
                                    <option value="Senior Citizen / Bedridden">Senior Citizen / Bedridden</option>
                                    <option value="PWD (Person with Disability)">PWD (Person with Disability)</option>
                                    <option value="Maternal / Prenatal Care">Maternal / Prenatal Care</option>
                                    <option value="Post-natal / Infant Check">Post-natal / Infant Check</option>
                                    <option value="High-Risk Patient">High-Risk Patient</option>
                                    <option value="Other Medical Concern">Other Medical Concern</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Reason for Home Visit Request</label>
                                <textarea name="reason_for_visit" class="form-control" rows="3" placeholder="Ipaliwanag nang maikli kung bakit kailangan ng home visit..." required></textarea>
                            </div>
                            <button type="submit" name="submit_visitation" class="btn btn-success w-100 fw-bold">
                                <i class="fa-solid fa-paper-plane me-1"></i> Submit Request
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- History Table Side -->
            <div class="col-md-7">
                <div class="card shadow-sm border-secondary">
                    <div class="card-header bg-secondary text-white fw-bold">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i> My Home Visit Requests
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0 align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date & Category</th>
                                        <th>Address & Reason</th>
                                        <th>Assigned BHW</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($my_visitations && $my_visitations->num_rows > 0): ?>
                                        <?php while($row = $my_visitations->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= date('M d, Y', strtotime($row['requested_date'])); ?></strong><br>
                                                    <span class="badge bg-info text-dark"><?= htmlspecialchars($row['patient_condition']); ?></span>
                                                </td>
                                                <td>
                                                    <small><strong>Address:</strong> <?= htmlspecialchars($row['complete_address']); ?></small><br>
                                                    <small class="text-muted"><?= htmlspecialchars($row['reason_for_visit']); ?></small>
                                                </td>
                                                <td><strong><?= htmlspecialchars($row['assigned_bhw']); ?></strong></td>
                                                <td>
                                                    <?php if($row['status'] == 'Scheduled' || $row['status'] == 'Completed'): ?>
                                                        <span class="badge bg-success"><?= $row['status']; ?></span>
                                                    <?php elseif($row['status'] == 'Cancelled'): ?>
                                                        <span class="badge bg-danger">Cancelled</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">Wala ka pang naipagagawang home visit request.</td>
                                        </tr>
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