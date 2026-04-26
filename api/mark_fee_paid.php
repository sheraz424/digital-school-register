<?php
require_once '../db_connection.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$student_id = $data['student_id'] ?? 0;

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student ID required']);
    exit;
}

$stmt = $pdo->prepare("UPDATE fees SET status = 'Paid', payment_date = CURDATE() WHERE student_id = ?");
$stmt->execute([$student_id]);

echo json_encode(['success' => true, 'message' => 'Fees marked as paid']);
?>