<?php
// config/database.php
$hostname = "localhost";
$username = "root";
$password = "";
$database = "booking_futsal";

$connection = new mysqli($hostname, $username, $password, $database);

if($connection->connect_error) {
    die('Connection error: '. $connection->connect_error);
}

// Set charset untuk keamanan
$connection->set_charset("utf8mb4");

// Helper functions untuk keamanan
function sanitize_input($data) {
    global $connection;
    return mysqli_real_escape_string($connection, trim($data));
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Function untuk mengecek koneksi database
function check_connection() {
    global $connection;
    if (!$connection) {
        die("Database connection failed: " . mysqli_connect_error());
    }
    return true;
}
?>