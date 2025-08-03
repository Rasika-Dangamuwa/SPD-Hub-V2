<?php
// SPD Hub V2 - Propagandist Dashboard
// dashboards/propagandist_dashboard.php

session_start();
require_once '../config/db_connect.php';

// Check authentication and role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'propagandist') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$vehicle_assigned = $_SESSION['vehicle_assigned'];

// Get dashboard statistics
try {
    // My events this month
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE propagandist_id = ? AND MONTH(event_date) = MONTH(NOW()) AND YEAR(event_date) = YEAR(NOW())");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $events_this_month = $stmt->get_result()->fetch_assoc()['total'];

    // Pending event reports (drafts)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM event_reports er JOIN events e ON er.event_id = e.event_id WHERE e.propagandist_id = ? AND er.status = 'draft'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $draft_reports = $stmt->get_result()->fetch_assoc()['total'];

    // Upcoming events (next 7 days)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE propagandist_id = ? AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'scheduled'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $upcoming_events = $stmt->get_result()->fetch_assoc()['total'];

    // Completed events this month
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE propagandist_id = ? AND status = 'closed' AND MONTH(event_date) = MONTH(NOW())");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $completed_events = $stmt->get_result()->fetch_assoc()['total'];

    // My assigned vehicle info
    $vehicle_info = null;
    if ($vehicle_assigned) {
        $stmt = $conn->prepare("SELECT vehicle_name, vehicle_number, brand_assigned FROM vehicles WHERE vehicle_id = ?");
        $stmt->bind_param("i", $vehicle_assigned);
        $stmt->execute();
        $vehicle_info = $stmt->get_result()->fetch_assoc();
    }

    // Today's events
    $stmt = $conn->prepare("
        SELECT e.event_id, e.event_name, e.event_time, e.event_end_time, e.location_address, e.status,
               CASE 
                   WHEN e.vehicle_id IS NOT NULL THEN v.vehicle_name
                   WHEN e.temp_location_id IS NOT NULL THEN tl.location_name
               END as location_name
        FROM events e
        LEFT JOIN vehicles v ON e.vehicle_id = v.vehicle_id
        LEFT JOIN temporary_event_locations tl ON e.temp_location_id = tl.location_id
        WHERE e.propagandist_id = ? AND e.event_date = CURDATE()
        ORDER BY e.event_time ASC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $todays_events = $stmt->get_result();

    // Upcoming events (next 7 days)
    $stmt = $conn->prepare("
        SELECT e.event_id, e.event_name, e.event_date, e.event_time, e.location_address, e.status,
               CASE 
                   WHEN e.vehicle_id IS NOT NULL THEN v.vehicle_name
                   WHEN e.temp_location_id IS NOT NULL THEN tl.location_name
               END as location_name
        FROM events e
        LEFT JOIN vehicles v ON e.vehicle_id = v.vehicle_id
        LEFT JOIN temporary_event_locations tl ON e.temp_location_id = tl.location_id
        WHERE e.propagandist_id = ? 
        AND e.event_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY e.event_date ASC, e.event_time ASC
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $upcoming_events_list = $stmt->get_result();

    // Pending stock confirmations
    $stmt = $conn->prepare("
        SELECT sr.request_id, sr.request_number, sr.obd_number, sr.assigned_at
        FROM stock_requests sr
        WHERE sr.status = 'assigned' 
        AND (sr.target_vehicle_id = ? OR sr.target_vehicle_id IN (SELECT vehicle_id FROM vehicles WHERE in_charge_id = ?))
        ORDER BY sr.assigned_at ASC
    ");
    $stmt->bind_param("ii", $vehicle_assigned, $user_id);
    $stmt->execute();
    $pending_confirmations = $stmt->get_result();

} catch (Exception $e) {
    error_log("Propagandist Dashboard error: " . $e->getMessage());
    $events_this_month = $draft_reports = $upcoming_events = $completed_events = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propagandist Dashboard - SPD Hub</title>
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
                        <div style="font-size: 0.875rem; opacity: 0.8;">
                            Propagandist
                            <?php if ($vehicle_info): ?>
                                - <?php echo htmlspecialchars($vehicle_info['vehicle_name']); ?>
                            <?php endif; ?>
                        </div>
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
            <p>
                Manage your events, submit reports, and handle vehicle stock operations.
                <?php if ($vehicle_info): ?>
                    You are assigned to <strong><?php echo htmlspecialchars($vehicle_info['vehicle_name']); ?></strong> 
                    (<?php echo htmlspecialchars($vehicle_info['vehicle_number']); ?>)
                    <?php if ($vehicle_info['brand_assigned']): ?>
                        for <strong><?php echo htmlspecialchars($vehicle_info['brand_assigned']); ?></strong> brand.
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </section>

        <!-- Statistics -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $events_this_month; ?></div>
                <div class="stat-label">Events This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $draft_reports; ?></div>
                <div class="stat-label">Draft Reports</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $upcoming_events; ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $completed_events; ?></div>
                <div class="stat-label">Completed This Month</div>
            </div>
        </section>

        <!-- Today's Events Section -->
        <?php if ($todays_events->num_rows > 0): ?>
        <section class="welcome-section" style="border-left-color: #28a745;">
            <h3><i class="fas fa-calendar-day"></i> Today's Events</h3>
            <div class="table-container" style="margin-top: 1rem; box-shadow: none;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Time</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($event = $todays_events->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                <td>
                                    <?php echo date('g:i A', strtotime($event['event_time'])); ?> - 
                                    <?php echo date('g:i A', strtotime($event['event_end_time'])); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($event['location_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($event['location_address']); ?></small>
                                </td>
                                <td>
                                    <span class="status status-<?php echo $event['status']; ?>">
                                        <?php echo ucfirst($event['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($event['status'] === 'scheduled'): ?>
                                        <a href="../modules/events/start_event.php?id=<?php echo $event['event_id']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-play"></i>
                                            Start Event
                                        </a>
                                    <?php elseif ($event['status'] === 'in_progress'): ?>
                                        <a href="../modules/reports/submit_report.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                            Submit Report
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <!-- Main Dashboard Grid -->
        <section class="dashboard-grid">
            <!-- Event Management -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <h4 class="card-title">My Events</h4>
                    </div>
                </div>
                <p class="card-description">
                    View assigned events, check schedules, and manage event execution from start to finish.
                </p>
                <div class="card-actions">
                    <a href="../modules/events/my_events.php" class="btn btn-primary">
                        <i class="fas fa-list"></i>
                        View My Events
                    </a>
                    <a href="../modules/events/event_calendar.php" class="btn btn-outline">
                        <i class="fas fa-calendar"></i>
                        Calendar View
                    </a>
                </div>
            </div>

            <!-- Event Reports -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Event Reports</h4>
                    </div>
                </div>
                <p class="card-description">
                    Submit event reports with sampling details, stock usage, and upload required documents.
                </p>
                <div class="card-actions">
                    <a href="../modules/reports/my_reports.php" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i>
                        My Reports
                        <?php if ($draft_reports > 0): ?>
                            <span class="badge"><?php echo $draft_reports; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="../modules/reports/create_report.php" class="btn btn-outline">
                        <i class="fas fa-plus"></i>
                        New Report
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
                        <h4 class="card-title">Vehicle Stock</h4>
                    </div>
                </div>
                <p class="card-description">
                    Monitor your vehicle's stock levels, confirm stock receipts, and manage stock transfers.
                </p>
                <div class="card-actions">
                    <a href="../modules/stock/my_vehicle_stock.php" class="btn btn-primary">
                        <i class="fas fa-eye"></i>
                        View Stock
                    </a>
                    <a href="../modules/stock/confirm_receipts.php" class="btn btn-outline">
                        <i class="fas fa-check"></i>
                        Confirm Receipts
                    </a>
                </div>
            </div>

            <!-- Stock Transfers -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Stock Transfers</h4>
                    </div>
                </div>
                <p class="card-description">
                    Request vehicle-to-vehicle stock transfers and track transfer history with approvals.
                </p>
                <div class="card-actions">
                    <a href="../modules/transfers/request_transfer.php" class="btn btn-primary">
                        <i class="fas fa-share"></i>
                        Request Transfer
                    </a>
                    <a href="../modules/transfers/transfer_history.php" class="btn btn-outline">
                        <i class="fas fa-history"></i>
                        Transfer History
                    </a>
                </div>
            </div>

            <!-- Vehicle Access -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Vehicle Access</h4>
                    </div>
                </div>
                <p class="card-description">
                    Access all vehicle information (read-only) but manage only your assigned vehicle's operations.
                </p>
                <div class="card-actions">
                    <a href="../modules/vehicles/all_vehicles.php" class="btn btn-primary">
                        <i class="fas fa-eye"></i>
                        View All Vehicles
                    </a>
                    <?php if ($vehicle_assigned): ?>
                        <a href="../modules/vehicles/my_vehicle.php" class="btn btn-outline">
                            <i class="fas fa-edit"></i>
                            My Vehicle
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- OBD Acknowledgements -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div>
                        <h4 class="card-title">OBD Acknowledgements</h4>
                    </div>
                </div>
                <p class="card-description">
                    Submit acknowledgements for stock usage with OBD numbers and track all OBD history.
                </p>
                <div class="card-actions">
                    <a href="../modules/obd/submit_acknowledgement.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Submit OBD Ack
                    </a>
                    <a href="../modules/obd/my_acknowledgements.php" class="btn btn-outline">
                        <i class="fas fa-list"></i>
                        My OBDs
                    </a>
                </div>
            </div>
        </section>

        <!-- Activity Sections -->
        <section class="dashboard-grid" style="margin-top: 2rem;">
            <!-- Upcoming Events -->
            <div class="table-container">
                <div class="table-header">
                    <h4>Upcoming Events (Next 7 Days)</h4>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Date & Time</th>
                            <th>Location</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($upcoming_events_list->num_rows > 0): ?>
                            <?php while ($event = $upcoming_events_list->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($event['event_date'])); ?><br>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($event['event_time'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($event['location_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($event['location_address'], 0, 30)) . '...'; ?></small>
                                    </td>
                                    <td>
                                        <span class="status status-<?php echo $event['status']; ?>">
                                            <?php echo ucfirst($event['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No upcoming events in the next 7 days.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pending Stock Confirmations -->
            <div class="table-container">
                <div class="table-header">
                    <h4>Pending Stock Confirmations</h4>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>OBD Number</th>
                            <th>Assigned Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pending_confirmations->num_rows > 0): ?>
                            <?php while ($confirmation = $pending_confirmations->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($confirmation['request_number']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($confirmation['obd_number']); ?></strong></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($confirmation['assigned_at'])); ?></td>
                                    <td>
                                        <a href="../modules/stock/confirm_receipt.php?id=<?php echo $confirmation['request_id']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i>
                                            Confirm Receipt
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No pending stock confirmations.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        // Check for today's events every 30 minutes
        setInterval(function() {
            fetch('../api/check_todays_events.php')
                .then(response => response.json())
                .then(data => {
                    if (data.has_events && data.show_notification) {
                        // Show notification for upcoming events
                        if ('Notification' in window && Notification.permission === 'granted') {
                            new Notification('SPD Hub', {
                                body: 'You have events scheduled for today. Check your dashboard.',
                                icon: '../assets/img/logo.png'
                            });
                        }
                    }
                })
                .catch(error => console.log('Event check failed:', error));
        }, 1800000); // 30 minutes

        // Request notification permission on load
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    </script>
</body>
</html>