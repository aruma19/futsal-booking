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

try {
    // Get booked slots for the specific date and field
    $query = "SELECT jam, lama_sewa, status 
              FROM booking 
              WHERE id_lapangan = ? 
              AND tanggal = ? 
              AND status IN ('pending', 'aktif')
              ORDER BY jam ASC";
    
    $stmt = mysqli_prepare($connection, $query);
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
            'status' => $row['status']
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'bookedSlots' => $bookedSlots,
        'date' => $date,
        'lapangan_id' => $lapangan_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>