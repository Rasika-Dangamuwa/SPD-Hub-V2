<?php
// SPD Hub V2 - Manager Dashboard
// dashboards/manager_dashboard.php

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

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'manager') {
    header("Location: ../index.php?error=access_denied");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$designation = $_SESSION['designation'];

// Handle brand selection (placeholder for now)
$selected_brand_id = $_SESSION['selected_brand_id'] ?? 1;
$selected_brand_name = $_SESSION['selected_brand_name'] ?? 'Maggi';

// Placeholder data - will be replaced with real database queries later
$accessible_brands = [
    ['id' => 1, 'name' => 'Maggi', 'code' => 'MAG'],
    ['id' => 2, 'name' => 'Nescafe', 'code' => 'NES'],
    ['id' => 3, 'name' => 'KitKat', 'code' => 'KIT'],
    ['id' => 4, 'name' => 'Milo', 'code' => 'MIL']
];

// Placeholder statistics
$stats = [
    'active_events' => 8,
    'vehicles_assigned' => 4,
    'temp_locations' => 6,
    'pending_requests' => 3
];

// Handle brand switching
if (isset($_POST['switch_brand'])) {
    $new_brand_id = (int)$_POST['brand_id'];
    $new_brand = array_filter($accessible_brands, function($b) use ($new_brand_id) {
        return $b['id'] === $new_brand_id;
    });
    
    if (!empty($new_brand)) {
        $new_brand = array_values($new_brand)[0];
        $_SESSION['selected_brand_id'] = $new_brand['id'];
        $_SESSION['selected_brand_name'] = $new_brand['name'];
        header("Location: manager_dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - SPD Hub</title>
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
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --gray: #6c757d;
            --dark-gray: #495057;
            --black: #212529;
            --shadow: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
            --border-radius: 12px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-gray);
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-dark) 100%);
            color: var(--white);
            padding: 1rem 0;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo i {
            font-size: 2rem;
        }

        .logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .brand-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .brand-selector select {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            color: var(--dark-gray);
            cursor: pointer;
        }

        .brand-indicator {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .welcome-section {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-blue);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            border-left: 4px solid var(--primary-blue);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray);
            font-weight: 500;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .dashboard-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            background: linear-gradient(45deg, var(--primary-blue), var(--primary-blue-dark));
            color: var(--white);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .card-icon {
            font-size: 2rem;
            opacity: 0.9;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        .card-description {
            color: var(--gray);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .card-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-blue-dark);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-blue);
            border: 2px solid var(--primary-blue);
        }

        .btn-outline:hover {
            background: var(--primary-blue);
            color: var(--white);
        }

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-warning {
            background: var(--warning);
            color: var(--white);
        }

        .quick-stats {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .quick-stats h3 {
            color: var(--dark-gray);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .mini-stat {
            text-align: center;
            padding: 1rem;
            background: var(--light-gray);
            border-radius: 8px;
        }

        .mini-stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-blue);
        }

        .mini-stat-label {
            font-size: 0.85rem;
            color: var(--gray);
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .brand-selector {
                order: -1;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .feature-badge {
            background: var(--warning);
            color: var(--white);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .timeline-preview {
            background: var(--light-gray);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .timeline-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .timeline-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-cube"></i>
                <h1>SPD Hub - Manager</h1>
            </div>
            
            <!-- Brand Selector -->
            <div class="brand-selector">
                <i class="fas fa-tags"></i>
                <span>Active Brand:</span>
                <form method="POST" style="display: inline;">
                    <select name="brand_id" onchange="this.form.submit();">
                        <?php foreach ($accessible_brands as $brand): ?>
                            <option value="<?php echo $brand['id']; ?>" 
                                    <?php echo $brand['id'] == $selected_brand_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brand['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="switch_brand" value="1">
                </form>
                <div class="brand-indicator">
                    <i class="fas fa-circle" style="color: #28a745;"></i>
                    <?php echo htmlspecialchars($selected_brand_name); ?>
                </div>
            </div>

            <div class="user-info">
                <div class="user-profile">
                    <div class="user-avatar">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($user_name); ?></div>
                        <div style="font-size: 0.875rem; opacity: 0.8;"><?php echo htmlspecialchars($designation); ?></div>
                    </div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Section -->
        <section class="welcome-section">
            <h2>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h2>
            <p>Manage your <strong><?php echo htmlspecialchars($selected_brand_name); ?></strong> brand operations - Create events, manage vehicles, handle stock requests, and oversee promotional activities.</p>
        </section>

        <!-- Statistics -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_events']; ?></div>
                <div class="stat-label">Active Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['vehicles_assigned']; ?></div>
                <div class="stat-label">Vehicles Assigned</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['temp_locations']; ?></div>
                <div class="stat-label">Temporary Locations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
        </section>

        <!-- Main Dashboard Grid -->
        <section class="dashboard-grid">
            <!-- Event Management -->
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-calendar-plus card-icon"></i>
                    <div class="card-title">Event Management</div>
                </div>
                <div class="card-body">
                    <div class="card-description">
                        Create and manage promotional events for <?php echo htmlspecialchars($selected_brand_name); ?>. Upload HOD approved documents and assign vehicles or temporary locations.
                    </div>
                    <div class="card-actions">
                        <a href="#" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Create Event
                        </a>
                        <a href="#" class="btn btn-outline">
                            <i class="fas fa-list"></i>
                            View Events
                        </a>
                        <a href="#" class="btn btn-outline">
                            <i class="fas fa-upload"></i>
                            Upload Document
                        </a>
                    </div>
                </div>
            </div>

            <!-- Vehicle Management -->
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-truck card-icon"></i>
                    <div class="card-title">Vehicle Management</div>
                </div>
                <div class="card-body">
                    <div class="card-description">
                        Manage brand vehicles, view vehicle timelines, check stock levels, and request vehicle-to-vehicle transfers.
                    </div>
                    <div class="card-actions">
                        <a href="#" class="btn btn-primary">
                            <i class="fas fa-eye"></i>
                            View Vehicles
                        </a>
                        <a href="#" class="btn btn-outline">
                            <i class="fas fa-calendar"></i>
                            Vehicle Timeline
                        </a>
                        <a href="#" class="btn btn-outline">
                            <i class="fas fa-exchange-alt"></i>
                            Assign to Brand
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stock Management -->
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-boxes card-icon"></i>
                    <div class="card-title">Stock Management</div>
                </div>
                <div class="card-body">
                    <div class="card-description">
                        Monitor stock levels across all vehicles and temporary locations. Request stock from warehouse for specific locations.
                    </div>
                    <div class="card-actions">
                        <a href="#" class="btn btn-primary">
                            <i class="fas fa-eye"></i>
                            View All Stocks
                        </a>
                        <a href="#" class="btn btn-outline">
                            <i class="fas fa-plus"></i>
                            Request Stock
                        </a>
                        <a href="#" class="btn btn-success">
                            <i class="fas fa-history"></i>
                            Request History
                        </a>
                    </div>
                </div>
            </div>

            <!-- Temporary Locations -->
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-map-marker-alt card-icon"></i>
                    <div class="card-title">Temporary Locations</div>
                </div>
                <div class="card-body">
                    <div class="card-description">
                        Manage temporary event locations like carnival huts, mall booths, and outdoor promotional setups.
                    </div>
                    <div class="card-actions">
                        <a href="#" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Location
                        </a>
                        <a href="#" class="btn btn-outline">
                            <i class="fas fa-list"></i>
                            Manage Locations
                        </a>
                        <a href="#" class="btn btn-outline">
                            <i class="fas fa-eye"></i>
                            Location Stocks
                        </a>
                    </div>
                </div>
            </div>

            <!-- Brand Customization -->
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-palette card-icon"></i>
                    <div class="card-title">Brand Customization</div>
                </div>
                <div class="card-body">
                    <div class="card-description">
                        Customize <?php echo htmlspecialchars($selected_brand_name); ?> specific settings: sampling types, event categories, and promotional configurations.
                    </div>
                    <div class="card-actions">
                        <a href="#" class="btn btn-primary">
                            <i class="fas fa-cog"></i>
                            Sampling Types
                        </a>
                        <a href="#" class="btn btn-outline">
                            <i class="fas fa-tags"></i>
                            Event Categories
                        </a>
                        <a href="#" class="btn btn-outline">
                            <i class="fas fa-edit"></i>
                            Brand Settings
                        </a>
                    </div>
                </div>
            </div>

            <!-- Timeline & Workload -->
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-chart-gantt card-icon"></i>
                    <div class="card-title">Timeline & Workload</div>
                </div>
                <div class="card-body">
                    <div class="card-description">
                        View vehicle and propagandist timelines to manage workload and schedule events efficiently.
                    </div>
                    <div class="card-actions">
                        <a href="#" class="btn btn-primary">
                            <i class="fas fa-calendar-alt"></i>
                            Vehicle Timeline
                        </a>
                        <a href="#" class="btn btn-outline">
                            <i class="fas fa-users"></i>
                            Propagandist Timeline
                        </a>
                        <a href="#" class="btn btn-warning">
                            <i class="fas fa-clock"></i>
                            Workload Analysis
                        </a>
                    </div>
                    <div class="timeline-preview">
                        <strong>Today's Schedule:</strong>
                        <div class="timeline-item">
                            <span>Vehicle MAG-01</span>
                            <span style="color: var(--success);">Available</span>
                        </div>
                        <div class="timeline-item">
                            <span>Vehicle MAG-02</span>
                            <span style="color: var(--warning);">Event 10AM-4PM</span>
                        </div>
                        <div class="timeline-item">
                            <span>Prop. John Silva</span>
                            <span style="color: var(--danger);">Busy - Galle Face</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Brand Statistics -->
        <section class="quick-stats">
            <h3>
                <i class="fas fa-chart-bar"></i>
                <?php echo htmlspecialchars($selected_brand_name); ?> Brand Statistics
            </h3>
            <div class="stats-row">
                <div class="mini-stat">
                    <div class="mini-stat-number">15</div>
                    <div class="mini-stat-label">Sampling Types</div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-number">8</div>
                    <div class="mini-stat-label">Event Categories</div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-number">4</div>
                    <div class="mini-stat-label">Assigned Vehicles</div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-number">12</div>
                    <div class="mini-stat-label">Active Locations</div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-number">89%</div>
                    <div class="mini-stat-label">Stock Level</div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-number">6</div>
                    <div class="mini-stat-label">This Week Events</div>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Brand switching functionality
        document.querySelector('select[name="brand_id"]').addEventListener('change', function() {
            // Add loading state
            this.style.opacity = '0.6';
            this.disabled = true;
            
            // Submit form
            this.form.submit();
        });

        // Add click handlers for feature buttons (placeholder)
        document.querySelectorAll('.btn[href="#"]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const feature = this.textContent.trim();
                alert(`${feature} feature will be implemented when we build this module.\n\nCurrent brand: <?php echo htmlspecialchars($selected_brand_name); ?>`);
            });
        });

        // Auto-refresh brand statistics every 5 minutes
        setInterval(function() {
            // Will implement real-time updates later
            console.log('Refreshing brand statistics for: <?php echo htmlspecialchars($selected_brand_name); ?>');
        }, 300000);

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>