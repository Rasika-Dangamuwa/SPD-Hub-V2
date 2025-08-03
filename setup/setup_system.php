<?php
// SPD Hub V2 - System Setup & Demo Data
// setup/setup_system.php

require_once '../config/db_connect.php';

echo "<h2>SPD Hub V2 - System Setup</h2>";

try {
    // Hash password for demo accounts
    $demo_password = password_hash('password', PASSWORD_DEFAULT);
    
    echo "<h3>1. Creating Demo Users...</h3>";
    
    // Clear existing users (for fresh setup)
    $conn->query("DELETE FROM users");
    
    // Insert demo users
    $users = [
        ['manager', 'manager@spdhub.com', $demo_password, 'brand_manager', 'Brand Manager', '0711234567', NULL],
        ['prop1', 'prop1@spdhub.com', $demo_password, 'propagandist', 'Propagandist One', '0712345678', 1],
        ['prop2', 'prop2@spdhub.com', $demo_password, 'propagandist', 'Propagandist Two', '0713456789', 2],
        ['prop3', 'prop3@spdhub.com', $demo_password, 'propagandist', 'Propagandist Three', '0714567890', NULL],
        ['warehouse', 'warehouse@spdhub.com', $demo_password, 'warehouse', 'Warehouse Manager', '0715678901', NULL],
    ];
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role, name, phone, vehicle_assigned) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($users as $user) {
        $stmt->bind_param("ssssssi", $user[0], $user[1], $user[2], $user[3], $user[4], $user[5], $user[6]);
        $stmt->execute();
        echo "✅ Created user: {$user[4]} ({$user[1]})<br>";
    }
    
    echo "<h3>2. Setting up Vehicles...</h3>";
    
    // Clear existing vehicles
    $conn->query("DELETE FROM vehicles");
    
    // Insert demo vehicles
    $vehicles = [
        ['Maggi Van 01', 'WP CAR 1234', 'Maggi', 2, 'active'],
        ['Nescafe Van 01', 'WP CAR 5678', 'Nescafe', 3, 'active'],
        ['KitKat Van 01', 'WP CAR 9012', 'KitKat', NULL, 'active'],
    ];
    
    $stmt = $conn->prepare("INSERT INTO vehicles (vehicle_name, vehicle_number, brand_assigned, in_charge_id, status) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($vehicles as $vehicle) {
        $stmt->bind_param("sssii", $vehicle[0], $vehicle[1], $vehicle[2], $vehicle[3], $vehicle[4]);
        $stmt->execute();
        echo "✅ Created vehicle: {$vehicle[0]}<br>";
    }
    
    echo "<h3>3. Setting up Brands & Categories...</h3>";
    
    // Clear existing brands
    $conn->query("DELETE FROM brands");
    
    // Insert demo brands
    $brands = [
        ['Maggi', 'MAG', 'Instant noodles and seasoning products', 'active'],
        ['Nescafe', 'NES', 'Coffee and beverage products', 'active'],
        ['KitKat', 'KIT', 'Chocolate bars and confectionery', 'active'],
        ['Milo', 'MIL', 'Chocolate malt drink', 'active'],
    ];
    
    $stmt = $conn->prepare("INSERT INTO brands (brand_name, brand_code, description, status) VALUES (?, ?, ?, ?)");
    
    foreach ($brands as $brand) {
        $stmt->bind_param("ssss", $brand[0], $brand[1], $brand[2], $brand[3]);
        $stmt->execute();
        echo "✅ Created brand: {$brand[0]}<br>";
    }
    
    // Clear existing sampling categories
    $conn->query("DELETE FROM sampling_categories");
    
    // Insert demo sampling categories
    $categories = [
        [1, 'Maggi Papare Kottu', 'paid', 25.00, 'active'],
        [1, 'Maggi Spicy Blast', 'paid', 20.00, 'active'],
        [1, 'Maggi Chicken Curry', 'paid', 22.00, 'active'],
        [1, 'Maggi Free Sample', 'free', 0.00, 'active'],
        [2, 'Nescafe Latte', 'paid', 35.00, 'active'],
        [2, 'Nescafe Cappuccino', 'paid', 30.00, 'active'],
        [2, 'Nescafe Espresso', 'paid', 25.00, 'active'],
        [2, 'Nescafe Free Sample', 'free', 0.00, 'active'],
        [3, 'KitKat Original', 'paid', 40.00, 'active'],
        [3, 'KitKat Chunky', 'paid', 45.00, 'active'],
        [4, 'Milo Hot', 'paid', 30.00, 'active'],
        [4, 'Milo Cold', 'paid', 35.00, 'active'],
    ];
    
    $stmt = $conn->prepare("INSERT INTO sampling_categories (brand_id, category_name, category_type, cup_price, status) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($categories as $category) {
        $stmt->bind_param("issds", $category[0], $category[1], $category[2], $category[3], $category[4]);
        $stmt->execute();
        echo "✅ Created sampling category: {$category[1]}<br>";
    }
    
    echo "<h3>4. Setting up Products...</h3>";
    
    // Clear existing products
    $conn->query("DELETE FROM products");
    
    // Insert demo products
    $products = [
        ['Maggi 2 Minute Noodles', 'MAG001', 1, 'pcs', 'sampling_material', 'active'],
        ['Maggi Seasoning Packets', 'MAG002', 1, 'pcs', 'sampling_material', 'active'],
        ['Maggi Cooking Sauce', 'MAG003', 1, 'bottles', 'sampling_material', 'active'],
        ['Nescafe 3in1 Sachets', 'NES001', 2, 'pcs', 'sampling_material', 'active'],
        ['Nescafe Classic Jar', 'NES002', 2, 'jars', 'sampling_material', 'active'],
        ['KitKat 4 Finger', 'KIT001', 3, 'pcs', 'sampling_material', 'active'],
        ['KitKat Chunky', 'KIT002', 3, 'pcs', 'sampling_material', 'active'],
        ['Milo Powder Sachets', 'MIL001', 4, 'pcs', 'sampling_material', 'active'],
        ['Paper Cups (Small)', 'GEN001', NULL, 'pcs', 'sampling_material', 'active'],
        ['Paper Cups (Large)', 'GEN002', NULL, 'pcs', 'sampling_material', 'active'],
        ['Plastic Spoons', 'GEN003', NULL, 'pcs', 'sampling_material', 'active'],
        ['Napkins', 'GEN004', NULL, 'packs', 'sampling_material', 'active'],
        ['Maggi TOD Flaps', 'MAG_TOD', 1, 'pcs', 'tod_flap', 'active'],
        ['Nescafe TOD Flaps', 'NES_TOD', 2, 'pcs', 'tod_flap', 'active'],
        ['KitKat TOD Flaps', 'KIT_TOD', 3, 'pcs', 'tod_flap', 'active'],
        ['Nescafe Branded Mugs', 'NES_MUG', 2, 'pcs', 'premium_gift', 'active'],
        ['Maggi Recipe Books', 'MAG_BOOK', 1, 'pcs', 'premium_gift', 'active'],
        ['KitKat Keychains', 'KIT_KEY', 3, 'pcs', 'premium_gift', 'active'],
    ];
    
    $stmt = $conn->prepare("INSERT INTO products (product_name, product_code, brand_id, unit, product_type, status) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($products as $product) {
        $stmt->bind_param("ssssss", $product[0], $product[1], $product[2], $product[3], $product[4], $product[5]);
        $stmt->execute();
        echo "✅ Created product: {$product[0]}<br>";
    }
    
    echo "<h3>5. Setting up Stock Locations...</h3>";
    
    // Clear existing stock locations
    $conn->query("DELETE FROM stock_locations");
    
    // Insert demo stock locations
    $locations = [
        ['Main Warehouse', 'warehouse', NULL, NULL, 'Colombo Main Distribution Center'],
        ['Maggi Van 01 Stock', 'vehicle', 1, NULL, NULL],
        ['Nescafe Van 01 Stock', 'vehicle', 2, NULL, NULL],
        ['KitKat Van 01 Stock', 'vehicle', 3, NULL, NULL],
    ];
    
    $stmt = $conn->prepare("INSERT INTO stock_locations (location_name, location_type, vehicle_id, temp_location_id, address) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($locations as $location) {
        $stmt->bind_param("ssiss", $location[0], $location[1], $location[2], $location[3], $location[4]);
        $stmt->execute();
        echo "✅ Created stock location: {$location[0]}<br>";
    }
    
    echo "<h3>6. Adding Initial Stock...</h3>";
    
    // Clear existing stocks
    $conn->query("DELETE FROM stocks");
    
    // Insert demo stocks (warehouse only)
    $stocks = [
        [1, 1, 1000], // Maggi noodles
        [2, 1, 500],  // Maggi seasoning
        [3, 1, 300],  // Maggi sauce
        [4, 1, 800],  // Nescafe sachets
        [5, 1, 200],  // Nescafe jars
        [6, 1, 600],  // KitKat 4 finger
        [7, 1, 400],  // KitKat chunky
        [8, 1, 350],  // Milo sachets
        [9, 1, 2000], // Small cups
        [10, 1, 1500], // Large cups
        [11, 1, 3000], // Spoons
        [12, 1, 500],  // Napkins
        [13, 1, 100],  // Maggi TOD flaps
        [14, 1, 80],   // Nescafe TOD flaps
        [15, 1, 60],   // KitKat TOD flaps
        [16, 1, 50],   // Nescafe mugs
        [17, 1, 75],   // Maggi books
        [18, 1, 100],  // KitKat keychains
    ];
    
    $stmt = $conn->prepare("INSERT INTO stocks (product_id, location_id, quantity) VALUES (?, ?, ?)");
    
    foreach ($stocks as $stock) {
        $stmt->bind_param("iii", $stock[0], $stock[1], $stock[2]);
        $stmt->execute();
    }
    
    echo "✅ Added initial stock quantities<br>";
    
    echo "<h3>7. Setting up Temporary Event Locations...</h3>";
    
    // Clear existing temporary locations
    $conn->query("DELETE FROM temporary_event_locations");
    
    // Insert demo temporary locations
    $temp_locations = [
        ['Galle Face Carnival Hut', 'carnival_hut', 'Galle Face Green, Colombo 03', 'John Silva', '0771234567', 'active'],
        ['Majestic City Mall Booth', 'mall_booth', 'Majestic City, Bambalapitiya', 'Anne Perera', '0772345678', 'active'],
        ['Independence Square Event Area', 'outdoor_booth', 'Independence Square, Colombo 07', 'David Fernando', '0773456789', 'active'],
        ['One Galle Face Mall Kiosk', 'mall_kiosk', 'One Galle Face, Colombo 02', 'Priya Jayasinghe', '0774567890', 'active'],
    ];
    
    $stmt = $conn->prepare("INSERT INTO temporary_event_locations (location_name, location_type, address, contact_person, contact_phone, status) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($temp_locations as $temp_location) {
        $stmt->bind_param("ssssss", $temp_location[0], $temp_location[1], $temp_location[2], $temp_location[3], $temp_location[4], $temp_location[5]);
        $stmt->execute();
        echo "✅ Created temporary location: {$temp_location[0]}<br>";
    }
    
    echo "<h3>8. Creating Sample Events...</h3>";
    
    // Insert sample events
    $events = [
        ['Maggi Taste Festival - Galle Face', date('Y-m-d', strtotime('+2 days')), '09:00:00', '17:00:00', 'Galle Face Green, Colombo 03', NULL, 1, 2, 'uploads/sample_approval.pdf', 200, 50, 10, TRUE, 1],
        ['Nescafe Coffee Experience', date('Y-m-d', strtotime('+5 days')), '10:00:00', '16:00:00', 'Majestic City Mall, Bambalapitiya', NULL, 2, 3, 'uploads/sample_approval2.pdf', 150, 30, 5, FALSE, 1],
        ['KitKat Chunky Launch', date('Y-m-d', strtotime('+7 days')), '11:00:00', '18:00:00', 'Mobile sampling around Colombo', 3, NULL, 4, 'uploads/sample_approval3.pdf', 100, 20, 8, TRUE, 1],
    ];
    
    $stmt = $conn->prepare("INSERT INTO events (event_name, event_date, event_time, event_end_time, location_address, vehicle_id, temp_location_id, propagandist_id, approved_document, expected_sampling_count, tod_flaps_planned, premium_gifts_planned, sampling_acknowledgement_required, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($events as $event) {
        $stmt->bind_param("sssssisissiibi", $event[0], $event[1], $event[2], $event[3], $event[4], $event[5], $event[6], $event[7], $event[8], $event[9], $event[10], $event[11], $event[12], $event[13]);
        $stmt->execute();
        echo "✅ Created event: {$event[0]}<br>";
    }
    
    echo "<h3>✅ System Setup Complete!</h3>";
    echo "<hr>";
    echo "<h4>Demo Login Credentials:</h4>";
    echo "<ul>";
    echo "<li><strong>Brand Manager:</strong> manager@spdhub.com / password</li>";
    echo "<li><strong>Propagandist 1:</strong> prop1@spdhub.com / password (Assigned to Maggi Van 01)</li>";
    echo "<li><strong>Propagandist 2:</strong> prop2@spdhub.com / password (Assigned to Nescafe Van 01)</li>";
    echo "<li><strong>Propagandist 3:</strong> prop3@spdhub.com / password (No vehicle assigned)</li>";
    echo "<li><strong>Warehouse Manager:</strong> warehouse@spdhub.com / password</li>";
    echo "</ul>";
    
    echo "<p><a href='../index.php'>🚀 Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Setup Error:</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}

// Close connection
$conn->close();
?>