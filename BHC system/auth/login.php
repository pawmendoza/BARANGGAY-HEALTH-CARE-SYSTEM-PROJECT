<?php
session_start();
require_once '../configDB/db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        // Inalis ang 'OR email = ?' dahil walang email column sa users table
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Sinusuri ang password (suportado ang parehong hashed at plain text)
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = strtolower($user['role']);
                $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];

                $role = $_SESSION['role'];

                // Tamang path papunta sa mga folder labas ng auth/
                if ($role == 'admin' || $role == 'doctor' || $role == 'nurse') {
                    header("Location: ../admin/admin_dashboard.php");
                    exit();
                } elseif ($role == 'patient') {
                    header("Location: ../patient/patient_dashboard.php");
                    exit();
                } else {
                    header("Location: ../admin/admin_dashboard.php");
                    exit();
                }
            } else {
                $error = "Your password is incorrect. Try again..";
            }
        } else {
            $error = "No account found with that username.";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HCMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css/style.css">
</head>
<body class="login-page">

    <div class="login-card">
        <div class="text-center mb-4">
            <h4 class="fw-bold text-primary mb-1"><i class="fa-solid fa-hospital-user me-2"></i> Health Center</h4>
            <p class="text-muted small mb-0">Sign in to your account to continue</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small mb-3"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Username</label>
                <input type="text" name="username" class="form-control" required placeholder="Enter your username">
            </div>
            <div class="mb-4">
                <label class="form-label small fw-semibold">Password</label>
                <input type="password" name="password" class="form-control" required placeholder="Enter your password">
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Sign In</button>
        </form>

        <div class="text-center mt-3">
            <small class="text-muted">Don't have an account yet? <a href="register.php" class="text-decoration-none fw-semibold">Sign up</a></small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>