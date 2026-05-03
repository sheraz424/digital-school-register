<?php
require_once '../db_connection.php';
header('Content-Type: application/json');

$prefix = $_GET['prefix'] ?? '';

if (empty($prefix)) {
    echo json_encode(['success' => false, 'last_num' => 0]);
    exit;
}

$stmt = $pdo->prepare("SELECT roll_no FROM students WHERE roll_no LIKE CONCAT(?, '-%') ORDER BY id DESC LIMIT 1");
$stmt->execute([$prefix]);
$last = $stmt->fetch();

if ($last) {
    $parts = explode('-', $last['roll_no']);
    $last_num = intval(end($parts));
    echo json_encode(['success' => true, 'last_num' => $last_num]);
} else {
    echo json_encode(['success' => true, 'last_num' => 0]);
}
?>