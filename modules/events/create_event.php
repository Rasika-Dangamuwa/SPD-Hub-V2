<?php
// SPD Hub V2 - Create Event (Database Integrated)
// modules/events/create_event.php

session_start();
require_once '../../config/db_connect.php';

// Check authentication and role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'brand_manager') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get user's accessible brands
try {
    $stmt = $conn->prepare("
        SELECT b.brand_id, b.brand_name, b.brand_code, b.brand_color, bma.access_level
        FROM brands b
        JOIN brand_manager_access bma ON b.brand_id = bma.brand_id
        WHERE bma.user_id = ? AND bma.status = 'active' AND b.status = 'active'
        ORDER BY b.brand_name
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $accessible_brands = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Set default selected brand (first accessible brand)
    $selected_brand_id = $accessible_brands[0]['brand_id'] ?? 1;
    $selected_brand_name = $accessible_brands[0]['brand_name'] ?? 'Default';
    
} catch (Exception $e) {
    error_log("Brand access error: " . $e->getMessage());
    $accessible_brands = [];
    $selected_brand_id = 1;
    $selected_brand_name = 'Default';
}

// Fetch vehicles from database
try {
    $stmt = $conn->prepare("
        SELECT v.vehicle_id, v.vehicle_name, v.vehicle_number, v.status,
               b.brand_name as brand_name
        FROM vehicles v
        LEFT JOIN brands b ON v.brand_assigned = b.brand_id
        WHERE v.status = 'active'
        ORDER BY b.brand_name, v.vehicle_name
    ");
    $stmt->execute();
    $vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Vehicles fetch error: " . $e->getMessage());
    $vehicles = [];
}

// Fetch propagandists from database
try {
    $stmt = $conn->prepare("
        SELECT user_id, name, phone, district, email
        FROM users 
        WHERE account_type = 'propagandist' AND status = 'active'
        ORDER BY name
    ");
    $stmt->execute();
    $propagandists = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Propagandists fetch error: " . $e->getMessage());
    $propagandists = [];
}

// Fetch temporary locations from database
try {
    $stmt = $conn->prepare("
        SELECT location_id, location_name, location_type, address, district
        FROM temporary_event_locations 
        WHERE status = 'active'
        ORDER BY district, location_name
    ");
    $stmt->execute();
    $temp_locations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Temporary locations fetch error: " . $e->getMessage());
    $temp_locations = [];
}

// Fetch existing events for timeline (next 30 days)
try {
    $stmt = $conn->prepare("
        SELECT e.event_id, e.event_name, e.event_date, e.start_time, e.end_time,
               e.vehicle_id, e.propagandist_id, e.status,
               v.vehicle_name, u.name as propagandist_name
        FROM events e
        LEFT JOIN vehicles v ON e.vehicle_id = v.vehicle_id
        LEFT JOIN users u ON e.propagandist_id = u.user_id
        WHERE e.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND e.status IN ('scheduled', 'confirmed', 'in_progress')
        ORDER BY e.event_date, e.start_time
    ");
    $stmt->execute();
    $existing_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Organize events by resource for timeline
    $vehicle_timeline = [];
    $propagandist_timeline = [];
    
    foreach ($existing_events as $event) {
        $event_data = [
            'event_id' => $event['event_id'],
            'event' => $event['event_name'],
            'start' => $event['event_date'] . ' ' . $event['start_time'],
            'end' => $event['event_date'] . ' ' . $event['end_time'],
            'status' => $event['status']
        ];
        
        if ($event['vehicle_id']) {
            if (!isset($vehicle_timeline[$event['vehicle_id']])) {
                $vehicle_timeline[$event['vehicle_id']] = [];
            }
            $vehicle_timeline[$event['vehicle_id']][] = $event_data;
        }
        
        if ($event['propagandist_id']) {
            if (!isset($propagandist_timeline[$event['propagandist_id']])) {
                $propagandist_timeline[$event['propagandist_id']] = [];
            }
            $propagandist_timeline[$event['propagandist_id']][] = $event_data;
        }
    }
    
} catch (Exception $e) {
    error_log("Timeline fetch error: " . $e->getMessage());
    $vehicle_timeline = [];
    $propagandist_timeline = [];
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $event_name = trim($_POST['event_name']);
        $start_datetime = $_POST['start_datetime'];
        $end_datetime = $_POST['end_datetime'];
        $event_location = trim($_POST['event_location']);
        $sampling_amount = (int)$_POST['sampling_amount'];
        $vehicle_id = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;
        $propagandist_id = !empty($_POST['propagandist_id']) ? (int)$_POST['propagandist_id'] : null;
        $temp_location_id = !empty($_POST['temp_location_id']) ? (int)$_POST['temp_location_id'] : null;
        
        // Optional fields
        $contact_person = trim($_POST['contact_person'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $special_notes = trim($_POST['special_notes'] ?? '');
        $ack_required = isset($_POST['ack_required']) ? 1 : 0;
        
        // Validate inputs
        if (empty($event_name) || empty($start_datetime) || empty($end_datetime) || 
            empty($event_location) || $sampling_amount < 1) {
            throw new Exception('Please fill in all required fields.');
        }
        
        // Validate dates
        $start_date = new DateTime($start_datetime);
        $end_date = new DateTime($end_datetime);
        $now = new DateTime();
        
        if ($start_date <= $now) {
            throw new Exception('Start time must be in the future.');
        }
        
        if ($end_date <= $start_date) {
            throw new Exception('End time must be after start time.');
        }
        
        // Determine location type
        $location_type = 'custom';
        if ($vehicle_id) {
            $location_type = 'vehicle';
        } elseif ($temp_location_id) {
            $location_type = 'temporary_location';
        }
        
        // Handle file upload
        $approved_document_path = null;
        if (isset($_FILES['approved_document']) && $_FILES['approved_document']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/events/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['approved_document']['name'], PATHINFO_EXTENSION);
            $file_name = 'event_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $file_extension;
            $approved_document_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['approved_document']['tmp_name'], $approved_document_path)) {
                throw new Exception('Failed to upload document.');
            }
            
            // Store relative path
            $approved_document_path = 'uploads/events/' . $file_name;
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        // Insert event
        $stmt = $conn->prepare("
            INSERT INTO events (
                event_name, brand_id, event_date, start_time, end_time,
                location_type, vehicle_id, temp_location_id, custom_location_address,
                propagandist_id, approved_sampling_amount, contact_person, contact_phone,
                special_instructions, sampling_acknowledgement_required, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
        ");
        
        $event_date = $start_date->format('Y-m-d');
        $start_time = $start_date->format('H:i:s');
        $end_time = $end_date->format('H:i:s');
        
        $stmt->bind_param("sisssssissssii", 
            $event_name, $selected_brand_id, $event_date, $start_time, $end_time,
            $location_type, $vehicle_id, $temp_location_id, $event_location,
            $propagandist_id, $sampling_amount, $contact_person, $contact_phone,
            $special_notes, $ack_required, $user_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create event: ' . $stmt->error);
        }
        
        $event_id = $conn->insert_id;
        
        // Insert approved document if uploaded
        if ($approved_document_path) {
            $stmt = $conn->prepare("
                INSERT INTO approved_documents (
                    document_name, original_filename, file_path, file_size, file_type, mime_type,
                    document_type, uploaded_by, event_id, brand_id
                ) VALUES (?, ?, ?, ?, ?, ?, 'hod_approval', ?, ?, ?)
            ");
            
            $original_filename = $_FILES['approved_document']['name'];
            $file_size = $_FILES['approved_document']['size'];
            $file_type = $file_extension;
            $mime_type = $_FILES['approved_document']['type'];
            $document_name = 'HOD Approval - ' . $event_name;
            
            $stmt->bind_param("ssssissii",
                $document_name, $original_filename, $approved_document_path, $file_size,
                $file_type, $mime_type, $user_id, $event_id, $selected_brand_id
            );
            
            $stmt->execute();
        }
        
        // Log activity
        logActivity($conn, $user_id, 'event_created', 
            "Created new event: {$event_name} (ID: {$event_id})", 'events', $event_id);
        
        // Commit transaction
        $conn->commit();
        
        $message = "Event '{$event_name}' created successfully! Event ID: {$event_id}";
        $message_type = 'success';
        
        // Redirect to event list after successful creation
        header("Location: list_events.php?success=1&event_id={$event_id}");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction
        if ($conn->inTransaction ?? false) {
            $conn->rollback();
        }
        
        // Delete uploaded file if exists
        if (isset($approved_document_path) && file_exists($approved_document_path)) {
            unlink($approved_document_path);
        }
        
        $message = $e->getMessage();
        $message_type = 'error';
        
        error_log("Event creation error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - SPD Hub</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Include all the CSS from the original file */
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
            padding: 2rem 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .header {
            background: var(--white);
            padding: 1.5rem 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header h1 {
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand-badge {
            background: var(--primary-blue);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .back-btn {
            background: var(--gray);
            color: var(--white);
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: var(--dark-gray);
            transform: translateY(-2px);
        }

        .form-container {
            display: grid;
            grid-template-columns: 1fr 500px;
            gap: 2rem;
        }

        .form-section {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .timeline-section {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .section-title {
            color: var(--dark-gray);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
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

        .required {
            color: var(--danger);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .textarea {
            min-height: 100px;
            resize: vertical;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .file-upload {
            border: 2px dashed #ddd;
            padding: 2rem;
            text-align: center;
            border-radius: 6px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .file-upload:hover {
            border-color: var(--primary-blue);
            background: var(--light-gray);
        }

        .file-upload input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            top: 0;
            left: 0;
        }

        .upload-content {
            pointer-events: none;
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--primary-blue);
            margin-bottom: 1rem;
        }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
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

        .btn-secondary {
            background: var(--gray);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: var(--dark-gray);
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .form-container {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                <i class="fas fa-calendar-plus"></i>
                Create New Event
            </h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="brand-badge">
                    <i class="fas fa-tags"></i>
                    <?php echo htmlspecialchars($selected_brand_name); ?>
                </div>
                <a href="../../dashboards/brand_manager_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Main Form -->
        <form id="eventForm" method="POST" enctype="multipart/form-data">
            <div class="form-container">
                <!-- Event Information Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Event Information
                    </h3>

                    <div class="form-group">
                        <label for="event_name">Event Name <span class="required">*</span></label>
                        <input type="text" id="event_name" name="event_name" class="form-control" 
                               placeholder="Enter event name" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_datetime">Start Date & Time <span class="required">*</span></label>
                            <input type="datetime-local" id="start_datetime" name="start_datetime" 
                                   class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="end_datetime">End Date & Time <span class="required">*</span></label>
                            <input type="datetime-local" id="end_datetime" name="end_datetime" 
                                   class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="event_location">Event Location <span class="required">*</span></label>
                        <input type="text" id="event_location" name="event_location" class="form-control" 
                               placeholder="Enter event location/address" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="sampling_amount">Approved Sampling Amount <span class="required">*</span></label>
                            <input type="number" id="sampling_amount" name="sampling_amount" class="form-control" 
                                   placeholder="Enter amount" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="contact_person">Contact Person</label>
                            <input type="text" id="contact_person" name="contact_person" class="form-control" 
                                   placeholder="Contact person name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="contact_phone">Contact Phone Number</label>
                        <input type="tel" id="contact_phone" name="contact_phone" class="form-control" 
                               placeholder="0771234567">
                    </div>

                    <div class="form-group">
                        <label for="special_notes">Special Notes for Propagandist</label>
                        <textarea id="special_notes" name="special_notes" class="form-control textarea" 
                                  placeholder="Enter any special instructions or notes..."></textarea>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-container">
                            <input type="checkbox" id="ack_required" name="ack_required" value="1">
                            <label for="ack_required">Sampling Acknowledgement Required</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="approved_document">Approved Document (HOD Approval) <span class="required">*</span></label>
                        <div class="file-upload" id="fileUploadArea">
                            <input type="file" id="approved_document" name="approved_document" 
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="upload-content">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div style="font-weight: 600; margin-bottom: 0.5rem;">Click to upload or drag and drop</div>
                                <div style="font-size: 0.9rem; color: var(--gray);">PDF, JPG, PNG files (Max 10MB)</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assignment & Timeline Section -->
                <div class="timeline-section">
                    <h3 class="section-title">
                        <i class="fas fa-users-cog"></i>
                        Assignment & Schedule
                    </h3>

                    <div class="form-group">
                        <label for="vehicle_id">Assign Vehicle</label>
                        <select id="vehicle_id" name="vehicle_id" class="form-control">
                            <option value="">Select Vehicle (Optional)</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?php echo $vehicle['vehicle_id']; ?>">
                                    <?php echo htmlspecialchars($vehicle['vehicle_name']); ?> 
                                    (<?php echo htmlspecialchars($vehicle['vehicle_number']); ?>)
                                    <?php if ($vehicle['brand_name']): ?>
                                        - <?php echo htmlspecialchars($vehicle['brand_name']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="temp_location_id">OR Select Temporary Location</label>
                        <select id="temp_location_id" name="temp_location_id" class="form-control">
                            <option value="">Select Temporary Location (Optional)</option>
                            <?php foreach ($temp_locations as $location): ?>
                                <option value="<?php echo $location['location_id']; ?>">
                                    <?php echo htmlspecialchars($location['location_name']); ?> 
                                    (<?php echo htmlspecialchars($location['location_type']); ?>)
                                    - <?php echo htmlspecialchars($location['district']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="propagandist_id">Assign Propagandist <span class="required">*</span></label>
                        <select id="propagandist_id" name="propagandist_id" class="form-control" required>
                            <option value="">Select Propagandist</option>
                            <?php foreach ($propagandists as $prop): ?>
                                <option value="<?php echo $prop['user_id']; ?>">
                                    <?php echo htmlspecialchars($prop['name']); ?> 
                                    (<?php echo htmlspecialchars($prop['phone']); ?>)
                                    - <?php echo htmlspecialchars($prop['district']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Brand Selection for Multi-Brand Managers -->
                    <?php if (count($accessible_brands) > 1): ?>
                    <div class="form-group">
                        <label for="brand_id">Brand <span class="required">*</span></label>
                        <select id="brand_id" name="brand_id" class="form-control" required>
                            <?php foreach ($accessible_brands as $brand): ?>
                                <option value="<?php echo $brand['brand_id']; ?>" 
                                        <?php echo $brand['brand_id'] == $selected_brand_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($brand['brand_name']); ?>
                                    (<?php echo htmlspecialchars($brand['access_level']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="brand_id" value="<?php echo $selected_brand_id; ?>">
                    <?php endif; ?>

                    <!-- Timeline Information -->
                    <div style="background: var(--light-gray); padding: 1rem; border-radius: 6px; margin-top: 1rem;">
                        <h4 style="color: var(--dark-gray); margin-bottom: 0.5rem;">
                            <i class="fas fa-info-circle"></i>
                            Resource Availability
                        </h4>
                        <p style="font-size: 0.9rem; color: var(--gray);">
                            After selecting resources and times, the system will check for scheduling conflicts 
                            and display timeline information to help you avoid double-booking.
                        </p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                        <i class="fas fa-undo"></i>
                        Reset
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Create Event
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Vehicle and propagandist timeline data from PHP
        const vehicleTimeline = <?php echo json_encode($vehicle_timeline); ?>;
        const propagandistTimeline = <?php echo json_encode($propagandist_timeline); ?>;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            setMinDateTime();
            setupEventListeners();
        });

        function setMinDateTime() {
            const today = new Date();
            const todayString = today.toISOString().slice(0, 16);
            document.getElementById('start_datetime').min = todayString;
            document.getElementById('end_datetime').min = todayString;
        }

        function setupEventListeners() {
            // Vehicle/Location mutual exclusion
            document.getElementById('vehicle_id').addEventListener('change', function() {
                if (this.value) {
                    document.getElementById('temp_location_id').value = '';
                }
            });

            document.getElementById('temp_location_id').addEventListener('change', function() {
                if (this.value) {
                    document.getElementById('vehicle_id').value = '';
                }
            });

            // File upload styling
            const fileInput = document.getElementById('approved_document');
            const fileUploadArea = document.getElementById('fileUploadArea');
            
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileUploadArea.style.borderColor = 'var(--success)';
                    fileUploadArea.style.background = '#f8fff9';
                    const uploadContent = fileUploadArea.querySelector('.upload-content');
                    uploadContent.innerHTML = `
                        <div class="upload-icon" style="color: var(--success);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div style="font-weight: 600; margin-bottom: 0.5rem; color: var(--success);">
                            File Selected: ${this.files[0].name}
                        </div>
                        <div style="font-size: 0.9rem; color: var(--gray);">
                            Click to change file
                        </div>
                    `;
                }
            });
        }

        function resetForm() {
            document.getElementById('eventForm').reset();
            setMinDateTime();
            
            // Reset file upload area
            const fileUploadArea = document.getElementById('fileUploadArea');
            fileUploadArea.style.borderColor = '#ddd';
            fileUploadArea.style.background = '';
            
            const uploadContent = fileUploadArea.querySelector('.upload-content');
            uploadContent.innerHTML = `
                <div class="upload-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <div style="font-weight: 600; margin-bottom: 0.5rem;">Click to upload or drag and drop</div>
                <div style="font-size: 0.9rem; color: var(--gray);">PDF, JPG, PNG files (Max 10MB)</div>
            `;
        }

        // Form validation
        document.getElementById('eventForm').addEventListener('submit', function(e) {
            const vehicleId = document.getElementById('vehicle_id').value;
            const tempLocationId = document.getElementById('temp_location_id').value;
            
            if (!vehicleId && !tempLocationId) {
                e.preventDefault();
                alert('Please select either a vehicle or a temporary location for the event.');
                return false;
            }
            
            const startDateTime = new Date(document.getElementById('start_datetime').value);
            const endDateTime = new Date(document.getElementById('end_datetime').value);
            
            if (endDateTime <= startDateTime) {
                e.preventDefault();
                alert('End time must be after start time.');
                return false;
            }
            
            if (startDateTime <= new Date()) {
                e.preventDefault();
                alert('Start time must be in the future.');
                return false;
            }
        });
    </script>
</body>
</html>