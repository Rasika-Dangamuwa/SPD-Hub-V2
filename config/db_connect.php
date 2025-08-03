<?php
// SPD Hub V2 - Database Connection & Security Functions
// config/db_connect.php

// Database configuration
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

/**
 * Log user activities for security and audit purposes
 */
function logActivity($conn, $user_id, $activity_type, $description, $reference_table = null, $reference_id = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = $conn->prepare("
        INSERT INTO activity_logs 
        (user_id, activity_type, activity_description, ip_address, user_agent, reference_table, reference_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt) {
        $stmt->bind_param("issssis", $user_id, $activity_type, $description, $ip_address, $user_agent, $reference_table, $reference_id);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Get user information by user ID
 */
function getUserById($conn, $user_id) {
    $stmt = $conn->prepare("SELECT user_id, username, email, role, name, phone, status FROM users WHERE user_id = ? AND status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Check if user has specific role
 */
function hasRole($conn, $user_id, $required_role) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ? AND status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['role'] === $required_role;
    }
    
    return false;
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole($conn, $user_id, $roles_array) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ? AND status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return in_array($row['role'], $roles_array);
    }
    
    return false;
}

/**
 * Update user's last login timestamp
 */
function updateLastLogin($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get system setting value
 */
function getSystemSetting($conn, $setting_key, $default_value = null) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $setting_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    
    return $default_value;
}

/**
 * Set system setting value
 */
function setSystemSetting($conn, $setting_key, $setting_value, $description = null) {
    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value, setting_description) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        setting_value = VALUES(setting_value), 
        setting_description = COALESCE(VALUES(setting_description), setting_description)
    ");
    $stmt->bind_param("sss", $setting_key, $setting_value, $description);
    $stmt->execute();
    $stmt->close();
}

/**
 * Validate and sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate secure password hash
 */
function generatePasswordHash($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password against hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check if session is valid and user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && isset($_SESSION['user_name']);
}

/**
 * Redirect to login if not authenticated
 */
function requireAuth() {
    if (!isAuthenticated()) {
        header("Location: ../index.php");
        exit();
    }
}

/**
 * Redirect to login if user doesn't have required role
 */
function requireRole($conn, $required_role) {
    requireAuth();
    
    if (!hasRole($conn, $_SESSION['user_id'], $required_role)) {
        header("Location: ../index.php?error=access_denied");
        exit();
    }
}

/**
 * Redirect to login if user doesn't have any of the required roles
 */
function requireAnyRole($conn, $roles_array) {
    requireAuth();
    
    if (!hasAnyRole($conn, $_SESSION['user_id'], $roles_array)) {
        header("Location: ../index.php?error=access_denied");
        exit();
    }
}

/**
 * Get dashboard URL based on user role
 */
function getDashboardUrl($role) {
    switch ($role) {
        case 'admin':
            return 'dashboards/admin_dashboard.php';
        case 'manager':
            return 'dashboards/manager_dashboard.php';
        case 'propagandist':
            return 'dashboards/propagandist_dashboard.php';
        case 'observer':
            return 'dashboards/observer_dashboard.php';
        case 'auditor':
            return 'dashboards/auditor_dashboard.php';
        default:
            return 'index.php';
    }
}

/**
 * Format user role for display
 */
function formatRole($role) {
    switch ($role) {
        case 'admin':
            return 'System Administrator';
        case 'manager':
            return 'Brand Promotion Manager';
        case 'propagandist':
            return 'Propagandist';
        case 'observer':
            return 'Observer';
        case 'auditor':
            return 'Auditor';
        default:
            return ucfirst($role);
    }
}

/**
 * Get user statistics for dashboard
 */
function getUserStats($conn, $user_id, $role) {
    $stats = [];
    
    switch ($role) {
        case 'admin':
            // Total users by role
            $result = $conn->query("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role");
            while ($row = $result->fetch_assoc()) {
                $stats[$row['role'] . '_count'] = $row['count'];
            }
            
            // Total activity logs today
            $result = $conn->query("SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()");
            $stats['activities_today'] = $result->fetch_assoc()['count'];
            break;
            
        case 'manager':
            // Placeholder for manager stats
            $stats['managed_events'] = 0;
            $stats['pending_approvals'] = 0;
            $stats['active_campaigns'] = 0;
            break;
            
        case 'propagandist':
            // Placeholder for propagandist stats
            $stats['assigned_events'] = 0;
            $stats['completed_activities'] = 0;
            $stats['pending_reports'] = 0;
            break;
            
        case 'observer':
            // Placeholder for observer stats
            $stats['total_events'] = 0;
            $stats['total_reports'] = 0;
            $stats['system_status'] = 'active';
            break;
            
        case 'auditor':
            // Placeholder for auditor stats
            $stats['audit_findings'] = 0;
            $stats['compliance_score'] = 95;
            $stats['pending_reviews'] = 0;
            break;
    }
    
    return $stats;
}

/**
 * Clean up old activity logs (keep only last 90 days)
 */
function cleanupOldLogs($conn) {
    $stmt = $conn->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stmt->execute();
    $stmt->close();
}

// Auto-cleanup old logs (run occasionally)
if (rand(1, 100) == 1) {
    cleanupOldLogs($conn);
}

?>