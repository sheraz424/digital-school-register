<?php
require_once '../db_connection.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$fee_id = $data['fee_id'] ?? 0;

if (!$fee_id) {
    echo json_encode(['success' => false, 'message' => 'Fee ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE fees SET status = 'Paid', payment_date = CURDATE() WHERE id = ?");
    $stmt->execute([$fee_id]);
    echo json_encode(['success' => true, 'message' => 'Payment successful!']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>