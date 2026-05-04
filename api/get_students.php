<?php
error_reporting(0);
header('Content-Type: application/json');

require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit;
}

$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : null;

try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.roll_no, s.name, s.class_id, 
               c.class_name, c.section
        FROM students s
        JOIN classes c ON s.class_id = c.id
        WHERE s.class_id = ?
        ORDER BY CAST(s.roll_no AS UNSIGNED)
    ");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll();
    
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    $stmt2 = $pdo->prepare("
        SELECT student_id, status, remarks 
        FROM attendance 
        WHERE class_id = ? AND attendance_date = ?
    ");
    $stmt2->execute([$class_id, $date]);
    $attendanceMap = [];
    while ($row = $stmt2->fetch()) {
        $attendanceMap[$row['student_id']] = $row;
    }
    
    foreach ($students as &$student) {
        if (isset($attendanceMap[$student['id']])) {
            $student['status'] = $attendanceMap[$student['id']]['status'];
            $student['remarks'] = $attendanceMap[$student['id']]['remarks'];
        } else {
            $student['status'] = '';
            $student['remarks'] = '';
        }
        $student['grade'] = '';
    }
    
    echo json_encode(['success' => true, 'message' => 'Students fetched successfully', 'data' => $students]);
    exit;
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>