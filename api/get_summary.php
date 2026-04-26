<?php
require_once '../db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method');
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if (!$class_id) {
    sendResponse(false, 'Class ID is required');
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM students WHERE class_id = ?");
    $stmt->execute([$class_id]);
    $total = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'A' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'L' THEN 1 ELSE 0 END) as late
        FROM attendance 
        WHERE class_id = ? AND attendance_date = ?
    ");
    $stmt->execute([$class_id, $date]);
    $attendance = $stmt->fetch();
    
    $present = $attendance['present'] ?? 0;
    $absent = $attendance['absent'] ?? 0;
    $late = $attendance['late'] ?? 0;
    $percentage = $total > 0 ? round((($present + $late * 0.5) / $total) * 100) : 0;
    
    sendResponse(true, 'Summary fetched', [
        'total' => intval($total),
        'present' => intval($present),
        'absent' => intval($absent),
        'late' => intval($late),
        'percentage' => $percentage
    ]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
?>