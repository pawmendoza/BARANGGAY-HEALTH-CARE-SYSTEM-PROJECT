<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function logActivity($conn, $action, $details = "") {
    // Awtomatikong siguraduhin na umiiral ang audit_logs table at ang mga kailangang columns nito
    $conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        role VARCHAR(50) NOT NULL,
        action VARCHAR(255) NOT NULL,
        details TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->query("ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER id");
    $conn->query("ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL AFTER details");

    $user_id = $_SESSION['user_id'] ?? NULL;
    $username = $_SESSION['username'] ?? 'System';
    $role = $_SESSION['role'] ?? 'Guest';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, username, role, action, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isssss", $user_id, $username, $role, $action, $details, $ip_address);
        $stmt->execute();
        $stmt->close();
    }
}
?>