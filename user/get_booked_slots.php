<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include('../config/database.php');

// Get parameters
$lapangan_id = isset($_GET['lapangan']) ? (int)$_GET['lapangan'] : 0;
$date = isset($_GET['date']) ? sanitize_input($_GET['date']) : '';

// Validate parameters
if ($lapangan_id <= 0 || empty($date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit();
}

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format']);
    exit();
}

// Check if date is not in the past
$today = date('Y-m-d');
if ($date < $today) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot book for past dates']);
    exit();
}

try {
    // Get booked slots for the specific date and field
    $query = "SELECT b.jam, b.lama_sewa, b.status, b.status_pembayaran, b.total_dibayar,
                     u.full_name, l.harga, l.nama as lapangan_nama
              FROM booking b 
              JOIN users u ON b.id_user = u.id
              JOIN lapangan l ON b.id_lapangan = l.id
              WHERE b.id_lapangan = ? 
              AND b.tanggal = ? 
              AND b.status IN ('pending', 'aktif')
              ORDER BY b.jam ASC";
    
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        throw new Exception('Prepare statement failed: ' . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, "is", $lapangan_id, $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $bookedSlots = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $startTime = $row['jam'];
        $duration = $row['lama_sewa'];
        
        // Convert to minutes for easier calculation
        $startMinutes = (int)date('H', strtotime($startTime)) * 60 + (int)date('i', strtotime($startTime));
        $endMinutes = $startMinutes + ($duration * 60);
        
        $bookedSlots[] = [
            'start' => $startMinutes,
            'end' => $endMinutes,
            'start_time' => $startTime,
            'end_time' => date('H:i', strtotime($startTime . ' + ' . $duration . ' hours')),
            'duration' => $duration,
            'status' => $row['status'],
            'payment_status' => $row['status_pembayaran'],
            'payment_amount' => $row['total_dibayar'],
            'customer_name' => $row['full_name'],
            'field_price' => $row['harga'],
            'field_name' => $row['lapangan_nama']
        ];
    }
    
    // Get field information
    $fieldQuery = "SELECT nama, tipe, harga, status FROM lapangan WHERE id = ? AND status = 'tersedia'";
    $fieldStmt = mysqli_prepare($connection, $fieldQuery);
    if (!$fieldStmt) {
        throw new Exception('Prepare statement failed: ' . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($fieldStmt, "i", $lapangan_id);
    mysqli_stmt_execute($fieldStmt);
    $fieldResult = mysqli_stmt_get_result($fieldStmt);
    $fieldInfo = mysqli_fetch_assoc($fieldResult);
    
    if (!$fieldInfo) {
        http_response_code(404);
        echo json_encode(['error' => 'Field not found or not available']);
        exit();
    }
    
    // Generate time slots availability (6:00 - 23:00)
    $timeSlots = [];
    for ($hour = 6; $hour <= 22; $hour++) {
        $timeMinutes = $hour * 60;
        $timeStr = sprintf('%02d:00', $hour);
        
        // Check if this hour is booked
        $isBooked = false;
        $bookingInfo = null;
        
        foreach ($bookedSlots as $slot) {
            if ($timeMinutes >= $slot['start'] && $timeMinutes < $slot['end']) {
                $isBooked = true;
                $bookingInfo = [
                    'customer' => $slot['customer_name'],
                    'status' => $slot['status'],
                    'payment_status' => $slot['payment_status'],
                    'duration' => $slot['duration']
                ];
                break;
            }
        }
        
        $timeSlots[] = [
            'time' => $timeStr,
            'hour' => $hour,
            'minutes' => $timeMinutes,
            'available' => !$isBooked,
            'booking_info' => $bookingInfo
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'bookedSlots' => $bookedSlots,
        'timeSlots' => $timeSlots,
        'fieldInfo' => $fieldInfo,
        'date' => $date,
        'lapangan_id' => $lapangan_id,
        'operational_hours' => [
            'start' => 6,  // 06:00
            'end' => 23    // 23:00
        ],
        'booking_rules' => [
            'min_duration' => 1,
            'max_duration' => 8,
            'advance_booking_days' => 30
        ]
    ]);
    
} catch (Exception $e) {
    error_log('get_booked_slots.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
?>