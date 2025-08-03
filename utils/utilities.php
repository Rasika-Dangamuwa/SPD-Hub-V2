<?php
// SPD Hub V2 - Utility Scripts & Helpers
// utils/utilities.php

/**
 * Password Generation Utility
 * Generate secure password hashes for user accounts
 */
function generatePasswordHash($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Test password generation
 */
if (isset($_GET['action']) && $_GET['action'] === 'generate_password') {
    $password = $_GET['password'] ?? 'password';
    $hash = generatePasswordHash($password);
    
    echo "<h3>Password Hash Generator</h3>";
    echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
    echo "<p><strong>Hash:</strong> <code>" . $hash . "</code></p>";
    echo "<p><a href='?'>Back</a></p>";
    exit();
}

/**
 * Directory Structure Creator
 * Creates the required folder structure for SPD Hub
 */
function createDirectoryStructure($base_path = '.') {
    $directories = [
        'assets',
        'assets/css',
        'assets/js',
        'assets/img',
        'config',
        'dashboards',
        'modules',
        'modules/events',
        'modules/reports',
        'modules/stock',
        'modules/warehouse',
        'modules/brands',
        'modules/categories',
        'modules/vehicles',
        'modules/locations',
        'modules/transfers',
        'modules/obd',
        'modules/analytics',
        'api',
        'uploads',
        'uploads/events',
        'uploads/reports',
        'uploads/documents',
        'setup',
        'utils',
        'tests'
    ];
    
    $created = [];
    $failed = [];
    
    foreach ($directories as $dir) {
        $full_path = $base_path . '/' . $dir;
        if (!is_dir($full_path)) {
            if (mkdir($full_path, 0755, true)) {
                $created[] = $dir;
            } else {
                $failed[] = $dir;
            }
        }
    }
    
    return ['created' => $created, 'failed' => $failed];
}

/**
 * Test directory creation
 */
if (isset($_GET['action']) && $_GET['action'] === 'create_directories') {
    $base_path = $_GET['path'] ?? '..';
    $result = createDirectoryStructure($base_path);
    
    echo "<h3>Directory Structure Creation</h3>";
    
    if (!empty($result['created'])) {
        echo "<h4>✅ Created Directories:</h4>";
        echo "<ul>";
        foreach ($result['created'] as $dir) {
            echo "<li>" . htmlspecialchars($dir) . "</li>";
        }
        echo "</ul>";
    }
    
    if (!empty($result['failed'])) {
        echo "<h4>❌ Failed to Create:</h4>";
        echo "<ul>";
        foreach ($result['failed'] as $dir) {
            echo "<li>" . htmlspecialchars($dir) . "</li>";
        }
        echo "</ul>";
    }
    
    if (empty($result['created']) && empty($result['failed'])) {
        echo "<p>All directories already exist.</p>";
    }
    
    echo "<p><a href='?'>Back</a></p>";
    exit();
}

/**
 * Database Test Connection
 */
function testDatabaseConnection() {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $database = "spd_hub_v2";
    
    try {
        $conn = new mysqli($servername, $username, $password, $database);
        
        if ($conn->connect_error) {
            return [
                'status' => 'error',
                'message' => 'Connection failed: ' . $conn->connect_error
            ];
        }
        
        // Test a simple query
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        if ($result) {
            $count = $result->fetch_assoc()['count'];
            $conn->close();
            return [
                'status' => 'success',
                'message' => "Database connected successfully. Found {$count} users."
            ];
        } else {
            $conn->close();
            return [
                'status' => 'warning',
                'message' => 'Connected but tables may not exist. Run setup script.'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

/**
 * Test database connection
 */
if (isset($_GET['action']) && $_GET['action'] === 'test_database') {
    $result = testDatabaseConnection();
    
    echo "<h3>Database Connection Test</h3>";
    
    $color = $result['status'] === 'success' ? 'green' : ($result['status'] === 'warning' ? 'orange' : 'red');
    echo "<p style='color: {$color}; font-weight: bold;'>";
    echo ucfirst($result['status']) . ": " . $result['message'];
    echo "</p>";
    
    echo "<p><a href='?'>Back</a></p>";
    exit();
}

/**
 * System Health Check
 */
function systemHealthCheck() {
    $checks = [];
    
    // PHP Version
    $php_version = phpversion();
    $checks['php_version'] = [
        'name' => 'PHP Version',
        'status' => version_compare($php_version, '7.4.0', '>=') ? 'success' : 'error',
        'message' => "PHP {$php_version} " . (version_compare($php_version, '7.4.0', '>=') ? '(OK)' : '(Requires 7.4+)')
    ];
    
    // MySQL Extension
    $checks['mysql'] = [
        'name' => 'MySQL Extension',
        'status' => extension_loaded('mysqli') ? 'success' : 'error',
        'message' => extension_loaded('mysqli') ? 'MySQLi extension loaded' : 'MySQLi extension not found'
    ];
    
    // Session Support
    $checks['sessions'] = [
        'name' => 'Session Support',
        'status' => function_exists('session_start') ? 'success' : 'error',
        'message' => function_exists('session_start') ? 'Session support available' : 'Session support not available'
    ];
    
    // File Upload
    $upload_max = ini_get('upload_max_filesize');
    $checks['file_upload'] = [
        'name' => 'File Upload',
        'status' => ini_get('file_uploads') ? 'success' : 'warning',
        'message' => ini_get('file_uploads') ? "File uploads enabled (Max: {$upload_max})" : 'File uploads disabled'
    ];
    
    // Uploads Directory
    $upload_dir = '../uploads';
    $checks['upload_dir'] = [
        'name' => 'Uploads Directory',
        'status' => (is_dir($upload_dir) && is_writable($upload_dir)) ? 'success' : 'warning',
        'message' => (is_dir($upload_dir) && is_writable($upload_dir)) ? 'Uploads directory writable' : 'Uploads directory not writable'
    ];
    
    return $checks;
}

/**
 * System health check
 */
if (isset($_GET['action']) && $_GET['action'] === 'health_check') {
    $checks = systemHealthCheck();
    
    echo "<h3>System Health Check</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Component</th><th>Status</th><th>Details</th></tr>";
    
    foreach ($checks as $check) {
        $color = $check['status'] === 'success' ? 'green' : ($check['status'] === 'warning' ? 'orange' : 'red');
        echo "<tr>";
        echo "<td>{$check['name']}</td>";
        echo "<td style='color: {$color}; font-weight: bold;'>" . ucfirst($check['status']) . "</td>";
        echo "<td>{$check['message']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "<p><a href='?'>Back</a></p>";
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPD Hub V2 - Utility Tools</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .tool { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin: 15px 0; }
        .tool h3 { margin-top: 0; color: #007bff; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .form-group { margin: 10px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 300px; }
    </style>
</head>
<body>
    <h1>SPD Hub V2 - Utility Tools</h1>
    <p>Collection of utility tools for system setup and maintenance.</p>

    <div class="tool">
        <h3>🔐 Password Hash Generator</h3>
        <p>Generate secure password hashes for user accounts.</p>
        <form method="GET">
            <input type="hidden" name="action" value="generate_password">
            <div class="form-group">
                <label>Password to Hash:</label>
                <input type="text" name="password" value="password" required>
            </div>
            <button type="submit" class="btn">Generate Hash</button>
        </form>
    </div>

    <div class="tool">
        <h3>📁 Directory Structure Creator</h3>
        <p>Create the required folder structure for SPD Hub system.</p>
        <form method="GET">
            <input type="hidden" name="action" value="create_directories">
            <div class="form-group">
                <label>Base Path (relative to this file):</label>
                <input type="text" name="path" value=".." placeholder="../">
            </div>
            <button type="submit" class="btn">Create Directories</button>
        </form>
    </div>

    <div class="tool">
        <h3>🗄️ Database Connection Test</h3>
        <p>Test the database connection and verify table structure.</p>
        <a href="?action=test_database" class="btn">Test Database</a>
    </div>

    <div class="tool">
        <h3>⚕️ System Health Check</h3>
        <p>Verify system requirements and configuration.</p>
        <a href="?action=health_check" class="btn">Run Health Check</a>
    </div>

    <div class="tool">
        <h3>🚀 Quick Setup Links</h3>
        <p>Essential setup and configuration links.</p>
        <a href="../setup/setup_system.php" class="btn">Run System Setup</a>
        <a href="../index.php" class="btn">Go to Login Page</a>
        <a href="https://github.com/your-repo/spd-hub-v2" class="btn" target="_blank">View Documentation</a>
    </div>

    <div class="tool">
        <h3>📋 System Information</h3>
        <table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%;">
            <tr><th>Component</th><th>Version/Status</th></tr>
            <tr><td>PHP Version</td><td><?php echo phpversion(); ?></td></tr>
            <tr><td>MySQL Extension</td><td><?php echo extension_loaded('mysqli') ? 'Loaded' : 'Not Found'; ?></td></tr>
            <tr><td>Max Upload Size</td><td><?php echo ini_get('upload_max_filesize'); ?></td></tr>
            <tr><td>Session Support</td><td><?php echo function_exists('session_start') ? 'Available' : 'Not Available'; ?></td></tr>
            <tr><td>Server Software</td><td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td></tr>
        </table>
    </div>

    <div style="background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 8px; padding: 15px; margin: 20px 0;">
        <h4>📖 Setup Instructions:</h4>
        <ol>
            <li><strong>Create Directories:</strong> Use the directory creator above</li>
            <li><strong>Database Setup:</strong> Create database 'spd_hub_v2' and run the SQL script</li>
            <li><strong>Test Connection:</strong> Verify database connectivity</li>
            <li><strong>Run Setup:</strong> Execute the system setup to populate demo data</li>
            <li><strong>Health Check:</strong> Verify all components are working</li>
        </ol>
    </div>
</body>
</html>