<?php
require_once '../db_connection.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Student ID required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if ($student) {
    echo json_encode(['success' => true, 'data' => $student]);
} else {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
}
?>