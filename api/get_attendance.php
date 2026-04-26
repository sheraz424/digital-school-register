<?php
require_once '../db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method');
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

if (!$class_id) {
    sendResponse(false, 'Class ID is required');
}

try {
    $stmt = $pdo->prepare("SELECT id, roll_no, name FROM students WHERE class_id = ? ORDER BY CAST(roll_no AS UNSIGNED)");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT id, student_id, attendance_date, status, remarks 
        FROM attendance 
        WHERE class_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ?
        ORDER BY attendance_date DESC
    ");
    $stmt->execute([$class_id, $month]);
    $attendanceRecords = [];
    while ($row = $stmt->fetch()) {
        if (!isset($attendanceRecords[$row['student_id']])) {
            $attendanceRecords[$row['student_id']] = [];
        }
        $attendanceRecords[$row['student_id']][] = $row;
    }
    
    sendResponse(true, 'History fetched', ['students' => $students, 'attendance' => $attendanceRecords, 'month' => $month]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
?>