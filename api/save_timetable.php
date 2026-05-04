<?php
// Disable error output to ensure clean JSON
error_reporting(0);
ini_set('display_errors', 0);

// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db_connection.php';

header('Content-Type: application/json');

// Debug: Log the request
$debug = [];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit;
}

// Get JSON input
$raw_input = file_get_contents('php://input');

if (empty($raw_input)) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

$data = json_decode($raw_input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

if (!isset($data['entries'])) {
    echo json_encode(['success' => false, 'message' => 'No entries in data']);
    exit;
}

$entries = $data['entries'];

if (empty($entries)) {
    echo json_encode(['success' => false, 'message' => 'Empty entries array']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get class_id from first entry
    $class_id = $entries[0]['class_id'];
    
    // Delete existing timetable for this class
    $stmt = $pdo->prepare("DELETE FROM timetable WHERE class_id = ?");
    $stmt->execute([$class_id]);
    
    $count = 0;
    
    foreach($entries as $entry) {
        // Validate required fields
        if (empty($entry['subject_id']) || empty($entry['teacher_id']) || 
            empty($entry['day_of_week']) || empty($entry['start_time'])) {
            continue;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO timetable (class_id, subject_id, teacher_id, day_of_week, start_time, end_time, room_no) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $entry['class_id'],
            $entry['subject_id'],
            $entry['teacher_id'],
            $entry['day_of_week'],
            $entry['start_time'],
            !empty($entry['end_time']) ? $entry['end_time'] : getEndTime($entry['start_time']),
            !empty($entry['room_no']) ? $entry['room_no'] : ''
        ]);
        $count++;
    }
    
    $pdo->commit();
    
    if ($count > 0) {
        echo json_encode(['success' => true, 'message' => "Timetable saved successfully! $count entries added."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No valid entries found. Please select subject and teacher for at least one time slot.']);
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function getEndTime($startTime) {
    $parts = explode(':', $startTime);
    $hours = intval($parts[0]);
    $minutes = intval($parts[1]);
    $minutes += 45;
    if ($minutes >= 60) {
        $hours += 1;
        $minutes -= 60;
    }
    return sprintf("%02d:%02d:00", $hours, $minutes);
}
?>