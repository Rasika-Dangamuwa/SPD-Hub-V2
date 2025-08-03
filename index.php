<?php
// SPD Hub V2 - Login Page
// index.php

session_start();

// Database connection
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

// Set charset
$conn->set_charset("utf8mb4");

// Helper functions
function logActivity($conn, $user_id, $activity_type, $description) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, activity_description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issss", $user_id, $activity_type, $description, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

function authenticateUser($conn, $login, $password) {
    // Check if login is email or username
    $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL);
    
    if ($isEmail) {
        $stmt = $conn->prepare("SELECT user_id, username, name, password_hash, account_type, designation, status FROM users WHERE email = ? AND status = 'active'");
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, name, password_hash, account_type, designation, status FROM users WHERE username = ? AND status = 'active'");
    }
    
    if ($stmt) {
        $stmt->bind_param("s", $login);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                $stmt->close();
                return $user;
            }
        }
        $stmt->close();
    }
    
    return false;
}

function getDashboardUrl($account_type) {
    $dashboards = [
        'admin' => 'dashboards/admin_dashboard.php',
        'manager' => 'dashboards/manager_dashboard.php',
        'propagandist' => 'dashboards/propagandist_dashboard.php',
        'observer' => 'dashboards/observer_dashboard.php',
        'auditor' => 'dashboards/auditor_dashboard.php'
    ];
    
    return $dashboards[$account_type] ?? 'index.php';
}

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['account_type'])) {
    $dashboard_url = getDashboardUrl($_SESSION['account_type']);
    header("Location: $dashboard_url");
    exit();
}

$error = '';
$success = '';

// Handle logout message
if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $success = 'You have been successfully logged out.';
}

// Handle access denied message
if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    $error = 'Access denied. You do not have permission to access that resource.';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($login) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        try {
            $user = authenticateUser($conn, $login, $password);
            
            if ($user) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['account_type'] = $user['account_type'];
                $_SESSION['designation'] = $user['designation'];
                $_SESSION['login_time'] = time();
                
                // Log successful login
                logActivity($conn, $user['user_id'], 'login', 'User logged in successfully');
                
                // Redirect to appropriate dashboard
                $dashboard_url = getDashboardUrl($user['account_type']);
                header("Location: $dashboard_url");
                exit();
            } else {
                $error = 'Invalid username/email or password.';
                // Log failed login attempt
                logActivity($conn, null, 'login_failed', "Failed login attempt for: $login");
            }
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
    <title>SPD Hub - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-blue: #007bff;
            --primary-blue-dark: #0056b3;
            --success: #28a745;
            --danger: #dc3545;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --gray: #6c757d;
            --dark-gray: #495057;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: var(--white);
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 400px;
            backdrop-filter: blur(10px);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            font-size: 3rem;
            color: var(--primary-blue);
            margin-bottom: 1rem;
            background: linear-gradient(45deg, var(--primary-blue), var(--primary-blue-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .logo-subtitle {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .input-container {
            position: relative;
        }

        .input-container i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(45deg, var(--primary-blue), var(--primary-blue-dark));
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .demo-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
        }

        .demo-title {
            text-align: center;
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .demo-accounts {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }

        .demo-account {
            background: var(--light-gray);
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            border: 1px solid #e9ecef;
        }

        .demo-role {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .demo-credentials {
            color: var(--gray);
            font-family: 'Courier New', monospace;
        }

        .footer-note {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: var(--gray);
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
            
            .logo-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-cube"></i>
            </div>
            <h1 class="logo-title">SPD Hub</h1>
            <p class="logo-subtitle">Sales Promotion Department System</p>
        </div>

        <!-- Alerts -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="index.php">
            <div class="form-group">
                <label for="login">Username or Email</label>
                <div class="input-container">
                    <i class="fas fa-user"></i>
                    <input 
                        type="text" 
                        id="login" 
                        name="login" 
                        class="form-input"
                        placeholder="Enter username or email"
                        value="<?php echo isset($_POST['login']) ? htmlspecialchars($_POST['login']) : ''; ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-container">
                    <i class="fas fa-lock"></i>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input"
                        placeholder="Enter password"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i>
                Sign In
            </button>
        </form>

        <!-- Demo Accounts -->
        <div class="demo-section">
            <h4 class="demo-title">Demo Account Access</h4>
            <div class="demo-accounts">
                <div class="demo-account">
                    <div class="demo-role">Administrator</div>
                    <div class="demo-credentials">admin@spdhub.com | password123</div>
                </div>
                <div class="demo-account">
                    <div class="demo-role">Brand Promotion Manager</div>
                    <div class="demo-credentials">manager@spdhub.com | password123</div>
                </div>
                <div class="demo-account">
                    <div class="demo-role">Propagandist</div>
                    <div class="demo-credentials">propagandist@spdhub.com | password123</div>
                </div>
                <div class="demo-account">
                    <div class="demo-role">Observer</div>
                    <div class="demo-credentials">observer@spdhub.com | password123</div>
                </div>
                <div class="demo-account">
                    <div class="demo-role">Auditor</div>
                    <div class="demo-credentials">auditor@spdhub.com | password123</div>
                </div>
            </div>
        </div>

        <p class="footer-note">
            SPD Hub V2 &copy; <?php echo date('Y'); ?> - Secure Access Portal
        </p>
    </div>
</body>
</html>