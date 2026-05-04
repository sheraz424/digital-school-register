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

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT t.*, s.subject_name, u.full_name as teacher_name
        FROM timetable t
        JOIN subjects s ON t.subject_id = s.id
        JOIN users u ON t.teacher_id = u.id
        WHERE t.class_id = ?
        ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), t.start_time
    ");
    $stmt->execute([$class_id]);
    $results = $stmt->fetchAll();
    
    $organized = [];
    foreach($results as $row) {
        $organized[$row['day_of_week']][$row['start_time']] = [
            'id' => $row['id'],
            'subject_id' => $row['subject_id'],
            'subject_name' => $row['subject_name'],
            'teacher_id' => $row['teacher_id'],
            'teacher_name' => $row['teacher_name'],
            'room_no' => $row['room_no'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $organized]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>