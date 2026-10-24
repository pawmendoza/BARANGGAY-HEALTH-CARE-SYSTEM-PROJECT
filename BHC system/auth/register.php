<?php
session_start();
require_once '../configDB/db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $age = intval($_POST['age']);
    $gender = trim($_POST['gender']);
    $contact_no = trim($_POST['contact_no']);
    $birthdate = trim($_POST['birthdate']);
    $civil_status = trim($_POST['civil_status']);
    $address = trim($_POST['address']);
    $emergency_contact = trim($_POST['emergency_contact']);
    $philhealth_no = trim($_POST['philhealth_no']);
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $message = "<div class='alert alert-danger'>This username is already taken!</div>";
    } else {
        $role = 'patient';
        $status = 'Pending';
        $stmt = $conn->prepare("INSERT INTO users (full_name, age, gender, contact_no, birthdate, civil_status, address, emergency_contact, philhealth_no, username, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisssssssssss", $full_name, $age, $gender, $contact_no, $birthdate, $civil_status, $address, $emergency_contact, $philhealth_no, $username, $password, $role, $status);

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Registration successful! Please wait for approval from the Health Center Admin.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
        $stmt->close();
    }
    $check_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Registration - HCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css/style.css">
</head>
<body class="bg-light">
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">📋 Health Care Management System Registration</h4>
                    </div>
                    <div class="card-body">
                        <?= $message; ?>
                        <form method="POST">
                            <h5 class="text-secondary border-bottom pb-2">Personal Information</h5>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Age</label>
                                    <input type="number" class="form-control" name="age" min="0" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender" required>
                                        <option value="">Select...</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" name="contact_no" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Birthdate</label>
                                    <input type="date" class="form-control" name="birthdate" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Civil Status</label>
                                    <select class="form-select" name="civil_status" required>
                                        <option value="">Select...</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">PhilHealth No. (Optional)</label>
                                    <input type="text" class="form-control" name="philhealth_no">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Barangay Address</label>
                                    <input type="text" class="form-control" name="address" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Emergency Contact (Person & No.)</label>
                                    <input type="text" class="form-control" name="emergency_contact" required>
                                </div>
                            </div>

                            <h5 class="text-secondary border-bottom pb-2 mt-4">Account Details</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100 btn-lg">Submit Registration</button>
                            <div class="text-center mt-3">
                                <a href="login.php">Already have an approved account? Login here</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>