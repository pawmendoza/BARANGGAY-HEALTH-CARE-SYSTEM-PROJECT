<?php
session_start();

// Linisin ang lahat ng session variables
$_SESSION = array();

// Sirain ang session cookie kung mayroon
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Sirain ang buong session
session_destroy();

// Dahil nasa auth/ folder na si logout.php, ire-redirect nito sa login.php
header("Location: login.php");
exit();
?>