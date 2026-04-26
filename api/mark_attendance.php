<?php
require_once '../db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

$user = getCurrentUser($pdo);
if (!$user) {
    sendResponse(false, 'Please login first');
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    sendResponse(false, 'Invalid data received');
}

$class_id = isset($data['class_id']) ? intval($data['class_id']) : 0;
$date = isset($data['date']) ? $data['date'] : date('Y-m-d');
$attendance = isset($data['attendance']) ? $data['attendance'] : [];
$subject_id = isset($data['subject_id']) ? intval($data['subject_id']) : null;

if (!$class_id || empty($attendance)) {
    sendResponse(false, 'Class ID and attendance data are required');
}

try {
    $pdo->beginTransaction();
    
    $successCount = 0;
    
    foreach ($attendance as $record) {
        $student_id = intval($record['student_id']);
        $status = $record['status'];
        $remarks = isset($record['remarks']) ? $record['remarks'] : '';
        
        if (!in_array($status, ['P', 'A', 'L'])) {
            continue;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO attendance (student_id, class_id, subject_id, attendance_date, status, remarks, marked_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            status = VALUES(status), remarks = VALUES(remarks), marked_by = VALUES(marked_by)
        ");
        
        if ($stmt->execute([$student_id, $class_id, $subject_id, $date, $status, $remarks, $user['id']])) {
            $successCount++;
        }
    }
    
    $pdo->commit();
    sendResponse(true, "Attendance saved successfully. $successCount records updated.");
    
} catch (PDOException $e) {
    $pdo->rollBack();
    sendResponse(false, 'Database error: ' . $e->getMessage());
}

function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
?>