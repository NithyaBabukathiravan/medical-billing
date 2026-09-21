<?php
// Database Configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', '');           // Change to your MySQL password
define('DB_NAME', 'medical_billing');

// Create connection
function getDBConnection() {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
   // $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3307);
    if ($conn->connect_error) {
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]));
    }
    
    $conn->set_charset('utf8mb4');
    return $conn;
}

// Start session if not already started
// Set timezone
date_default_timezone_set('Asia/Kolkata');
?>
