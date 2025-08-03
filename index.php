<?php
// SPD Hub V2 - Main Login Page
// index.php

session_start();
require_once 'config/db_connect.php';

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    // Redirect based on role
    switch ($_SESSION['user_role']) {
        case 'brand_manager':
            header("Location: dashboards/brand_manager_dashboard.php");
            break;
        case 'propagandist':
            header("Location: dashboards/propagandist_dashboard.php");
            break;
        case 'warehouse':
            header("Location: dashboards/warehouse_dashboard.php");
            break;
        default:
            session_destroy();
            break;
    }
    exit();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            // Check user credentials
            $stmt = $conn->prepare("SELECT user_id, password_hash, role, name, status, vehicle_assigned FROM users WHERE email = ? AND status = 'active'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['vehicle_assigned'] = $user['vehicle_assigned'];
                    
                    // Log login activity
                    logActivity($conn, $user['user_id'], 'login', 'User logged in successfully');
                    
                    // Redirect based on role
                    switch ($user['role']) {
                        case 'brand_manager':
                            header("Location: dashboards/brand_manager_dashboard.php");
                            break;
                        case 'propagandist':
                            header("Location: dashboards/propagandist_dashboard.php");
                            break;
                        case 'warehouse':
                            header("Location: dashboards/warehouse_dashboard.php");
                            break;
                        default:
                            $error = 'Invalid user role.';
                            break;
                    }
                    exit();
                } else {
                    $error = 'Invalid password.';
                }
            } else {
                $error = 'User not found or account is inactive.';
            }
            $stmt->close();
        } catch (Exception $e) {
            $error = 'Login failed. Please try again.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPD Hub - Event & Stock Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-cube"></i>
                <h1>SPD Hub</h1>
            </div>
            <p class="subtitle">Event & Stock Management System</p>
        </div>
        
        <div class="login-form-container">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="index.php" class="login-form">
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        Email Address
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        placeholder="Enter your email address"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        placeholder="Enter your password"
                    >
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>
        </div>
        
        <div class="login-footer">
            <div class="demo-accounts">
                <h4>Demo Accounts:</h4>
                <div class="demo-grid">
                    <div class="demo-card">
                        <strong>Brand Manager</strong>
                        <small>manager@spdhub.com</small>
                    </div>
                    <div class="demo-card">
                        <strong>Propagandist</strong>
                        <small>prop1@spdhub.com</small>
                    </div>
                    <div class="demo-card">
                        <strong>Warehouse</strong>
                        <small>warehouse@spdhub.com</small>
                    </div>
                </div>
                <p class="demo-note">All demo accounts use password: <code>password</code></p>
            </div>
        </div>
    </div>

    <div class="background-pattern"></div>
</body>
</html>