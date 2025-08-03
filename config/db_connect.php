<?php
// SPD Hub V2 - Database Connection
// config/db_connect.php

$servername = "localhost";
$username = "root";
$password = "";
$database = "spd_hub_v2";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("❌ Database Connection Failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Error reporting for development
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Function to log activities
function logActivity($conn, $user_id, $activity_type, $description, $reference_table = null, $reference_id = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, activity_description, reference_table, reference_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $activity_type, $description, $reference_table, $reference_id, $ip_address);
    $stmt->execute();
    $stmt->close();
}

// Function to check if user has access to specific vehicle
function hasVehicleAccess($conn, $user_id, $vehicle_id) {
    $stmt = $conn->prepare("SELECT vehicle_assigned FROM users WHERE user_id = ? AND role = 'propagandist'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['vehicle_assigned'] == $vehicle_id;
    }
    
    return false;
}

// Function to get user role
function getUserRole($conn, $user_id) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['role'];
    }
    
    return false;
}

// Function to generate unique request number
function generateRequestNumber($conn) {
    do {
        $request_number = 'REQ' . date('Ymd') . sprintf('%04d', rand(1, 9999));
        $stmt = $conn->prepare("SELECT request_id FROM stock_requests WHERE request_number = ?");
        $stmt->bind_param("s", $request_number);
        $stmt->execute();
        $result = $stmt->get_result();
    } while ($result->num_rows > 0);
    
    return $request_number;
}

?>