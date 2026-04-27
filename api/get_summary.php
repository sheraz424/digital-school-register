<?php
error_reporting(0);
header('Content-Type: application/json');

require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM students WHERE class_id = ?");
    $stmt->execute([$class_id]);
    $total = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END), 0) as present,
            COALESCE(SUM(CASE WHEN status = 'A' THEN 1 ELSE 0 END), 0) as absent,
            COALESCE(SUM(CASE WHEN status = 'L' THEN 1 ELSE 0 END), 0) as late
        FROM attendance 
        WHERE class_id = ? AND attendance_date = ?
    ");
    $stmt->execute([$class_id, $date]);
    $attendance = $stmt->fetch();
    
    $present = $attendance['present'] ?? 0;
    $absent = $attendance['absent'] ?? 0;
    $late = $attendance['late'] ?? 0;
    $percentage = $total > 0 ? round((($present + $late * 0.5) / $total) * 100) : 0;
    
    echo json_encode(['success' => true, 'message' => 'Summary fetched', 'data' => [
        'total' => intval($total),
        'present' => intval($present),
        'absent' => intval($absent),
        'late' => intval($late),
        'percentage' => $percentage
    ]]);
    exit;
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>