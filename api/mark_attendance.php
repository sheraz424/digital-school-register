<?php
error_reporting(0);
header('Content-Type: application/json');

require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received']);
    exit;
}

$class_id = isset($data['class_id']) ? intval($data['class_id']) : 0;
$date = isset($data['date']) ? $data['date'] : date('Y-m-d');
$attendance = isset($data['attendance']) ? $data['attendance'] : [];
$subject_id = isset($data['subject_id']) ? intval($data['subject_id']) : null;

if (!$class_id || empty($attendance)) {
    echo json_encode(['success' => false, 'message' => 'Class ID and attendance data are required']);
    exit;
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
        
        if ($stmt->execute([$student_id, $class_id, $subject_id, $date, $status, $remarks, $_SESSION['user_id']])) {
            $successCount++;
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Attendance saved successfully. $successCount records updated."]);
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>