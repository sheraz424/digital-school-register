<?php
require_once 'db_connection.php';
header('Content-Type: application/json');

// Get attendance data for charts
$type = $_GET['type'] ?? 'weekly';

if ($type == 'weekly') {
    // Get last 7 days attendance
    $data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present
            FROM attendance 
            WHERE attendance_date = ?
        ");
        $stmt->execute([$date]);
        $result = $stmt->fetch();
        
        $total = $result['total'] ?? 0;
        $present = $result['present'] ?? 0;
        $percentage = $total > 0 ? round(($present / $total) * 100) : 0;
        
        $data[] = [
            'date' => date('D', strtotime($date)),
            'percentage' => $percentage
        ];
    }
    echo json_encode($data);
    exit;
}

if ($type == 'monthly') {
    // Get monthly attendance for last 6 months
    $data = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present
            FROM attendance 
            WHERE DATE_FORMAT(attendance_date, '%Y-%m') = ?
        ");
        $stmt->execute([$month]);
        $result = $stmt->fetch();
        
        $total = $result['total'] ?? 0;
        $present = $result['present'] ?? 0;
        $percentage = $total > 0 ? round(($present / $total) * 100) : 0;
        
        $data[] = [
            'month' => date('M', strtotime($month . '-01')),
            'percentage' => $percentage
        ];
    }
    echo json_encode($data);
    exit;
}

if ($type == 'class') {
    // Get attendance by class
    $stmt = $pdo->query("
        SELECT c.class_name, 
               ROUND(AVG(CASE WHEN a.status = 'P' THEN 100 ELSE 0 END), 1) as percentage
        FROM attendance a
        JOIN classes c ON a.class_id = c.id
        GROUP BY c.id
        LIMIT 5
    ");
    $data = $stmt->fetchAll();
    echo json_encode($data);
    exit;
}
?>