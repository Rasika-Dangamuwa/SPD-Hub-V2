<?php
// SPD Hub V2 - Warehouse Dashboard
// dashboards/warehouse_dashboard.php

session_start();
require_once '../config/db_connect.php';

// Check authentication and role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'warehouse') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get dashboard statistics
try {
    // Pending stock requests
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM stock_requests WHERE status = 'pending'");
    $stmt->execute();
    $pending_requests = $stmt->get_result()->fetch_assoc()['total'];

    // Active stock requests (assigned but not confirmed)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM stock_requests WHERE status = 'assigned'");
    $stmt->execute();
    $active_requests = $stmt->get_result()->fetch_assoc()['total'];

    // Low stock items (less than 50 units)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM stocks s 
        JOIN stock_locations sl ON s.location_id = sl.location_id 
        WHERE sl.location_type = 'warehouse' AND s.quantity < 50
    ");
    $stmt->execute();
    $low_stock_items = $stmt->get_result()->fetch_assoc()['total'];

    // Total products in warehouse
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT s.product_id) as total 
        FROM stocks s 
        JOIN stock_locations sl ON s.location_id = sl.location_id 
        WHERE sl.location_type = 'warehouse' AND s.quantity > 0
    ");
    $stmt->execute();
    $total_products = $stmt->get_result()->fetch_assoc()['total'];

    // Recent stock requests
    $stmt = $conn->prepare("
        SELECT sr.request_id, sr.request_number, sr.request_description, sr.created_at, u.name as requested_by_name,
               CASE 
                   WHEN sr.target_vehicle_id IS NOT NULL THEN v.vehicle_name
                   WHEN sr.target_temp_location_id IS NOT NULL THEN tl.location_name
               END as target_location,
               sr.status
        FROM stock_requests sr
        JOIN users u ON sr.requested_by = u.user_id
        LEFT JOIN vehicles v ON sr.target_vehicle_id = v.vehicle_id
        LEFT JOIN temporary_event_locations tl ON sr.target_temp_location_id = tl.location_id
        WHERE sr.status IN ('pending', 'assigned')
        ORDER BY sr.created_at ASC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_requests = $stmt->get_result();

    // Low stock alerts
    $stmt = $conn->prepare("
        SELECT p.product_name, p.product_code, s.quantity, p.unit
        FROM stocks s
        JOIN products p ON s.product_id = p.product_id
        JOIN stock_locations sl ON s.location_id = sl.location_id
        WHERE sl.location_type = 'warehouse' AND s.quantity < 50
        ORDER BY s.quantity ASC
        LIMIT 10
    ");
    $stmt->execute();
    $low_stock_list = $stmt->get_result();

    // Recent OBD acknowledgements
    $stmt = $conn->prepare("
        SELECT oa.obd_number, oa.usage_description, oa.acknowledged_at, u.name as acknowledged_by_name
        FROM obd_acknowledgements oa
        JOIN users u ON oa.acknowledged_by = u.user_id
        ORDER BY oa.acknowledged_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_obds = $stmt->get_result();

} catch (Exception $e) {
    error_log("Warehouse Dashboard error: " . $e->getMessage());
    $pending_requests = $active_requests = $low_stock_items = $total_products = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Dashboard - SPD Hub</title>
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
                        <div style="font-size: 0.875rem; opacity: 0.8;">Warehouse Manager</div>
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
            <p>Manage warehouse inventory, process stock requests, and oversee distribution operations.</p>
        </section>

        <!-- Statistics -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_requests; ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_requests; ?></div>
                <div class="stat-label">Active Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $low_stock_items; ?></div>
                <div class="stat-label">Low Stock Alerts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_products; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
        </section>

        <!-- Alert Section for Low Stock -->
        <?php if ($low_stock_items > 0): ?>
        <section class="welcome-section" style="border-left-color: #ffc107; background: #fff3cd;">
            <h3 style="color: #856404;"><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h3>
            <p style="color: #856404;">You have <?php echo $low_stock_items; ?> items with low stock levels. Please review and restock as needed.</p>
        </section>
        <?php endif; ?>

        <!-- Main Dashboard Grid -->
        <section class="dashboard-grid">
            <!-- Stock Request Management -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Stock Requests</h4>
                    </div>
                </div>
                <p class="card-description">
                    Review incoming stock requests, assign items, and generate OBD numbers for distribution.
                </p>
                <div class="card-actions">
                    <a href="../modules/warehouse/pending_requests.php" class="btn btn-primary">
                        <i class="fas fa-eye"></i>
                        Review Requests
                        <?php if ($pending_requests > 0): ?>
                            <span class="badge"><?php echo $pending_requests; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="../modules/warehouse/all_requests.php" class="btn btn-outline">
                        <i class="fas fa-list"></i>
                        All Requests
                    </a>
                </div>
            </div>

            <!-- Inventory Management -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Inventory Management</h4>
                    </div>
                </div>
                <p class="card-description">
                    Monitor warehouse stock levels, manage product inventory, and track stock movements.
                </p>
                <div class="card-actions">
                    <a href="../modules/warehouse/inventory.php" class="btn btn-primary">
                        <i class="fas fa-boxes"></i>
                        View Inventory
                    </a>
                    <a href="../modules/warehouse/add_stock.php" class="btn btn-outline">
                        <i class="fas fa-plus"></i>
                        Add Stock
                    </a>
                </div>
            </div>

            <!-- OBD Management -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-barcode"></i>
                    </div>
                    <div>
                        <h4 class="card-title">OBD Management</h4>
                    </div>
                </div>
                <p class="card-description">
                    Generate OBD numbers, track acknowledgements, and manage distribution records.
                </p>
                <div class="card-actions">
                    <a href="../modules/warehouse/obd_tracker.php" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Track OBDs
                    </a>
                    <a href="../modules/warehouse/generate_obd.php" class="btn btn-outline">
                        <i class="fas fa-plus"></i>
                        Generate OBD
                    </a>
                </div>
            </div>

            <!-- Stock Movements -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Stock Movements</h4>
                    </div>
                </div>
                <p class="card-description">
                    Track all stock movements, transfers, and distribution activities across the system.
                </p>
                <div class="card-actions">
                    <a href="../modules/warehouse/stock_movements.php" class="btn btn-primary">
                        <i class="fas fa-history"></i>
                        View Movements
                    </a>
                    <a href="../modules/warehouse/manual_adjustment.php" class="btn btn-outline">
                        <i class="fas fa-edit"></i>
                        Manual Adjustment
                    </a>
                </div>
            </div>

            <!-- Product Management -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Product Management</h4>
                    </div>
                </div>
                <p class="card-description">
                    Manage product catalog, add new products, and configure product categories and types.
                </p>
                <div class="card-actions">
                    <a href="../modules/warehouse/products.php" class="btn btn-primary">
                        <i class="fas fa-list"></i>
                        Manage Products
                    </a>
                    <a href="../modules/warehouse/add_product.php" class="btn btn-outline">
                        <i class="fas fa-plus"></i>
                        Add Product
                    </a>
                </div>
            </div>

            <!-- Reports & Analytics -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div>
                        <h4 class="card-title">Reports & Analytics</h4>
                    </div>
                </div>
                <p class="card-description">
                    Generate warehouse reports, analyze stock trends, and export inventory data.
                </p>
                <div class="card-actions">
                    <a href="../modules/warehouse/reports.php" class="btn btn-primary">
                        <i class="fas fa-chart-line"></i>
                        View Reports
                    </a>
                    <a href="../modules/warehouse/export_data.php" class="btn btn-outline">
                        <i class="fas fa-download"></i>
                        Export Data
                    </a>
                </div>
            </div>
        </section>

        <!-- Activity Sections -->
        <section class="dashboard-grid" style="margin-top: 2rem;">
            <!-- Pending Stock Requests -->
            <div class="table-container">
                <div class="table-header">
                    <h4>Pending Stock Requests</h4>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Requested By</th>
                            <th>Target Location</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_requests->num_rows > 0): ?>
                            <?php while ($request = $recent_requests->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($request['request_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['target_location']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <span class="status status-<?php echo $request['status']; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <a href="../modules/warehouse/process_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-cog"></i>
                                                Process
                                            </a>
                                        <?php elseif ($request['status'] === 'assigned'): ?>
                                            <a href="../modules/warehouse/view_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye"></i>
                                                View
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No pending stock requests.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Low Stock Alerts -->
            <div class="table-container">
                <div class="table-header">
                    <h4>Low Stock Alerts</h4>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Code</th>
                            <th>Current Stock</th>
                            <th>Unit</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($low_stock_list->num_rows > 0): ?>
                            <?php while ($stock = $low_stock_list->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stock['product_name']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($stock['product_code']); ?></strong></td>
                                    <td>
                                        <span style="color: <?php echo $stock['quantity'] < 20 ? '#dc3545' : '#ffc107'; ?>; font-weight: 600;">
                                            <?php echo $stock['quantity']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($stock['unit']); ?></td>
                                    <td>
                                        <a href="../modules/warehouse/restock.php?product=<?php echo $stock['product_code']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-plus"></i>
                                            Restock
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">All products are well stocked.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Recent OBD Acknowledgements -->
        <?php if ($recent_obds->num_rows > 0): ?>
        <section class="table-container" style="margin-top: 2rem;">
            <div class="table-header">
                <h4>Recent OBD Acknowledgements</h4>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>OBD Number</th>
                        <th>Usage Description</th>
                        <th>Acknowledged By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($obd = $recent_obds->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($obd['obd_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($obd['usage_description'], 0, 50)) . '...'; ?></td>
                            <td><?php echo htmlspecialchars($obd['acknowledged_by_name']); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($obd['acknowledged_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
        <?php endif; ?>
    </main>

    <script>
        // Auto-refresh pending requests count every 2 minutes
        setInterval(function() {
            fetch('../api/get_warehouse_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.pending_requests !== undefined) {
                        document.querySelector('.stat-card:nth-child(1) .stat-number').textContent = data.pending_requests;
                    }
                    if (data.active_requests !== undefined) {
                        document.querySelector('.stat-card:nth-child(2) .stat-number').textContent = data.active_requests;
                    }
                })
                .catch(error => console.log('Stats refresh failed:', error));
        }, 120000); // 2 minutes

        // Highlight low stock alerts
        document.addEventListener('DOMContentLoaded', function() {
            const lowStockRows = document.querySelectorAll('#low-stock-table tbody tr');
            lowStockRows.forEach(row => {
                const quantityCell = row.querySelector('td:nth-child(3)');
                if (quantityCell) {
                    const quantity = parseInt(quantityCell.textContent);
                    if (quantity < 20) {
                        row.style.backgroundColor = '#f8d7da';
                    } else if (quantity < 50) {
                        row.style.backgroundColor = '#fff3cd';
                    }
                }
            });
        });
    </script>
</body>
</html>