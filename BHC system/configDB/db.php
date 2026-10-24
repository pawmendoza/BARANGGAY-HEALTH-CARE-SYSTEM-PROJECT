<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "hcms_db";

// Gumawa ng koneksyon gamit ang MySQLi
$conn = new mysqli($host, $username, $password, $database);

// Suriin kung may error sa koneksyon
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>