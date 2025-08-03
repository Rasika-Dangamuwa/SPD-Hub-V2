<?php
// SPD Hub V2 - Logout Script
// logout.php

session_start();
require_once 'config/db_connect.php';

// Log logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    try {
        logActivity($conn, $_SESSION['user_id'], 'logout', 'User logged out successfully');
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with success message
header("Location: index.php?msg=logged_out");
exit();
?>