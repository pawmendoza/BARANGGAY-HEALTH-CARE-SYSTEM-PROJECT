<?php
session_start();
require_once '../configDB/db.php';

if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'doctor', 'nurse'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Invalid request. Medical record ID is missing.");
}

$record_id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM medical_records WHERE id = ?");
$stmt->bind_param("i", $record_id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) {
    die("Medical record not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Slip - <?= htmlspecialchars($record['patient_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Pinagsamang CSS gamit ang iyong main stylesheet -->
    <link rel="stylesheet" href="../style.css/style.css">
    <style>
        /* Print-specific overrides lamang para sa malinis na papel kapag nag-print */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: #ffffff !important;
            }
            .card-custom {
                border: 1px solid #000 !important;
                box-shadow: none !important;
                margin: 0 !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body class="bg-light">

    <div class="container no-print text-center mt-4">
        <button onclick="window.print()" class="btn btn-primary px-4 py-2 fw-semibold">
            <i class="fa-solid fa-print me-2"></i> Print Prescription
        </button>
        <a href="medical_history.php" class="btn btn-outline-secondary px-4 py-2 fw-semibold ms-2">
            <i class="fa-solid fa-arrow-left me-2"></i> Back to Medical History
        </a>
    </div>

    <div class="container" style="max-width: 750px; margin-top: 30px;">
        <div class="card card-custom p-4 bg-white border rounded-3 shadow-sm">
            <div class="border-bottom pb-3 mb-4 text-center">
                <h3 class="fw-bold text-primary mb-1"><i class="fa-solid fa-hospital-user me-2"></i> BARANGAY HEALTH CENTER</h3>
                <p class="text-muted small mb-1">Official Patient Medical Record & Prescription Slip</p>
                <small class="text-secondary">Date: <strong><?= date('F d, Y', strtotime($record['created_at'] ?? $record['record_date'] ?? date('Y-m-d'))); ?></strong></small>
            </div>

            <div class="mb-4">
                <div class="p-3 bg-light rounded border">
                    <p class="mb-1"><strong>Patient Name:</strong> <span class="fs-5 text-dark fw-bold"><?= htmlspecialchars($record['patient_name']); ?></span></p>
                    <p class="mb-0 text-muted small">Record Reference #: HCMS-MR-<?= str_pad($record['id'], 5, '0', STR_PAD_LEFT); ?></p>
                </div>
            </div>

            <div class="mb-4">
                <h6 class="fw-bold text-uppercase text-secondary small mb-2"><i class="fa-solid fa-stethoscope text-primary me-2"></i> Diagnosis / Clinical Findings</h6>
                <div class="p-3 border rounded bg-white">
                    <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                </div>
            </div>

            <div class="mb-5">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="fs-3 fw-bold text-primary font-serif" style="font-family: serif;">Rx</span>
                    <h6 class="fw-bold text-uppercase text-secondary small mb-0">Prescription & Medication Instructions</h6>
                </div>
                <div class="p-3 border rounded bg-light">
                    <p class="mb-0 fw-semibold text-primary fs-6"><?= nl2br(htmlspecialchars($record['prescription'])); ?></p>
                </div>
            </div>

            <div class="row mt-5 pt-4">
                <div class="col-6 offset-6 text-center">
                    <div class="border-top border-dark pt-2">
                        <p class="mb-0 fw-bold text-dark">Attending Physician / Health Worker</p>
                        <small class="text-muted d-block">Barangay Health Center Representative</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>