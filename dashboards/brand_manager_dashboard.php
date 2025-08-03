<?php
// SPD Hub V2 - Brand Manager Dashboard
// dashboards/brand_manager_dashboard.php

session_start();
require_once '../config/db_connect.php';

// Check authentication and role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'brand_manager') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get dashboard statistics
try {
    // Total events this month
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
    $stmt->execute();
    $events_this_month = $stmt->get_result()->fetch_assoc()['total'];

    // Pending event reports
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM event_reports er JOIN events e ON er.event_id = e.event_id WHERE er.status = 'submitted'");
    $stmt->execute();
    $pending_reports = $stmt->get_result()->fetch_assoc()['total'];

    // Active stock requests
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM stock_requests WHERE status IN ('pending', 'assigned') AND requested_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $active_requests = $stmt->get_result()->fetch_assoc()['total'];

    // Upcoming events (next 7 days)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'scheduled'");
    $stmt->execute();
    $upcoming_events = $stmt->get_result()->fetch_assoc()['total'];

    // Recent events for quick review
    $stmt = $conn->prepare("
        SELECT e.event_id, e.event_name, e.event_date, e.status, u.name as propagandist_name,
               CASE 
                   WHEN e.vehicle_id IS NOT NULL THEN v.vehicle_name
                   WHEN e.temp_location_id IS NOT NULL THEN tl.location_name
               END as location_name
        FROM events e
        LEFT JOIN users u ON e.propagandist_id = u.user_id
        LEFT JOIN vehicles v ON e.vehicle_id = v.vehicle_id
        LEFT JOIN temporary_event_locations tl ON e.temp_location_id = tl.location_id
        WHERE e.created_by = ?
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $recent_events = $stmt->get_result();

    // Pending event reports for review
    $stmt = $conn->prepare("
        SELECT er.report_id, e.event_name, e.event_date, u.name as propagandist_name, er.submitted_at
        FROM event_reports er
        JOIN events e ON er.event_id = e.event_id
        JOIN users u ON e.propagandist_id = u.user_id
        WHERE er.status = 'submitted'
        ORDER BY er.submitted_at ASC
        LIMIT 5
    ");
    $stmt->execute();
    $pending_report_list = $stmt->get_result();

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $events_this_month = $pending_reports = $active_requests = $upcoming_events = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brand Manager Dashboard - SPD Hub</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="dashboard-page">
    <!-- Header -->
    <header class="dashboard-header">
        <nav class="dashboard-nav">
            <div class="dashboard-logo">
                <i class="fas fa-cube"></i>
                <h2>SPD Hub</h2>
            </div>
            <div class="user-info">
                <div class="user-profile">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($user_name); ?></div>
                        <div style="font-size: 0.875rem; opacity: 0.8;">Brand Manager</div>
                    </div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="dashboard-content">
        <!-- Welcome Section -->
        <section class="welcome-section">
            <h3>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h3>
            <p>Manage events, review reports, and handle stock requests from your centralized dashboard.</p>
        </section>

        <!-- Statistics -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $events_this_month; ?></div>
                <div class="stat-label">Events This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_reports; ?></div>
                <div class="stat-label">Pending Reports</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_requests; ?></div>
                <div class="stat-label">Active Stock Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $upcoming_events; ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
        </section>

        <!-- Main Dashboard Grid -->
        <section class="dashboard-grid">
            <!-- Event Management -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Event Management</h4>
                    </div>
                </div>
                <p class="card-description">
                    Create new events, assign vehicles/locations and propagandists, and manage the complete event lifecycle.
                </p>
                <div class="card-actions">
                    <a href="../modules/events/create_event.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Create Event
                    </a>
                    <a href="../modules/events/list_events.php" class="btn btn-outline">
                        <i class="fas fa-list"></i>
                        View All Events
                    </a>
                </div>
            </div>

            <!-- Report Review -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Report Review</h4>
                    </div>
                </div>
                <p class="card-description">
                    Review event reports submitted by propagandists, approve or request corrections for accurate tracking.
                </p>
                <div class="card-actions">
                    <a href="../modules/reports/pending_reports.php" class="btn btn-primary">
                        <i class="fas fa-eye"></i>
                        Review Reports
                        <?php if ($pending_reports > 0): ?>
                            <span class="badge"><?php echo $pending_reports; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="../modules/reports/all_reports.php" class="btn btn-outline">
                        <i class="fas fa-history"></i>
                        All Reports
                    </a>
                </div>
            </div>

            <!-- Stock Management -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Stock Management</h4>
                    </div>
                </div>
                <p class="card-description">
                    Monitor stock levels across vehicles, request stock from warehouse, and track stock movements.
                </p>
                <div class="card-actions">
                    <a href="../modules/stock/request_stock.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Request Stock
                    </a>
                    <a href="../modules/stock/view_stocks.php" class="btn btn-outline">
                        <i class="fas fa-eye"></i>
                        View Stock Levels
                    </a>
                </div>
            </div>

            <!-- Vehicle & Location Management -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Vehicle & Locations</h4>
                    </div>
                </div>
                <p class="card-description">
                    Manage vehicles, temporary event locations, and view availability timelines for better planning.
                </p>
                <div class="card-actions">
                    <a href="../modules/vehicles/vehicle_timeline.php" class="btn btn-primary">
                        <i class="fas fa-calendar"></i>
                        View Timeline
                    </a>
                    <a href="../modules/locations/manage_locations.php" class="btn btn-outline">
                        <i class="fas fa-map-marker-alt"></i>
                        Manage Locations
                    </a>
                </div>
            </div>

            <!-- Brand & Categories -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Brand & Categories</h4>
                    </div>
                </div>
                <p class="card-description">
                    Manage brands, sampling categories, and configure pricing for different product types.
                </p>
                <div class="card-actions">
                    <a href="../modules/brands/manage_brands.php" class="btn btn-primary">
                        <i class="fas fa-edit"></i>
                        Manage Brands
                    </a>
                    <a href="../modules/categories/sampling_categories.php" class="btn btn-outline">
                        <i class="fas fa-list"></i>
                        Categories
                    </a>
                </div>
            </div>

            <!-- Analytics & Reports -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Analytics & Reports</h4>
                    </div>
                </div>
                <p class="card-description">
                    Generate comprehensive reports, analyze event performance, and track key metrics.
                </p>
                <div class="card-actions">
                    <a href="../modules/analytics/dashboard.php" class="btn btn-primary">
                        <i class="fas fa-chart-line"></i>
                        View Analytics
                    </a>
                    <a href="../modules/reports/generate_reports.php" class="btn btn-outline">
                        <i class="fas fa-file-export"></i>
                        Export Reports
                    </a>
                </div>
            </div>
        </section>

        <!-- Recent Activity Section -->
        <section class="dashboard-grid" style="margin-top: 2rem;">
            <!-- Recent Events -->
            <div class="table-container">
                <div class="table-header">
                    <h4>Recent Events</h4>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Propagandist</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_events->num_rows > 0): ?>
                            <?php while ($event = $recent_events->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['location_name'] ?? 'Not assigned'); ?></td>
                                    <td><?php echo htmlspecialchars($event['propagandist_name']); ?></td>
                                    <td>
                                        <span class="status status-<?php echo $event['status']; ?>">
                                            <?php echo ucfirst($event['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No recent events found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pending Reports -->
            <div class="table-container">
                <div class="table-header">
                    <h4>Pending Report Reviews</h4>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Date</th>
                            <th>Propagandist</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pending_report_list->num_rows > 0): ?>
                            <?php while ($report = $pending_report_list->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($report['event_name']); ?></td>
                                    <td><?php echo date('M j', strtotime($report['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($report['propagandist_name']); ?></td>
                                    <td><?php echo date('M j, g:i A', strtotime($report['submitted_at'])); ?></td>
                                    <td>
                                        <a href="../modules/reports/review_report.php?id=<?php echo $report['report_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                            Review
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No pending reports for review.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        // Auto-refresh pending counts every 5 minutes
        setInterval(function() {
            fetch('../api/get_dashboard_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.pending_reports !== undefined) {
                        document.querySelector('.stat-card:nth-child(2) .stat-number').textContent = data.pending_reports;
                    }
                })
                .catch(error => console.log('Stats refresh failed:', error));
        }, 300000); // 5 minutes
    </script>
</body>
</html>