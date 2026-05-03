<?php
error_reporting(0);
header('Content-Type: application/json');

require_once '../db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Teacher ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, full_name, email, contact FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->execute([$id]);
    $teacher = $stmt->fetch();
    
    if ($teacher) {
        echo json_encode(['success' => true, 'data' => $teacher]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>