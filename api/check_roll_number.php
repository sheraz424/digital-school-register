<?php
require_once '../db_connection.php';

header('Content-Type: application/json');

$roll_no = isset($_GET['roll_no']) ? $_GET['roll_no'] : '';
$exclude_id = isset($_GET['exclude_id']) ? intval($_GET['exclude_id']) : 0;

if (empty($roll_no)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    if ($exclude_id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE roll_no = ? AND id != ?");
        $stmt->execute([$roll_no, $exclude_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE roll_no = ?");
        $stmt->execute([$roll_no]);
    }
    
    $exists = $stmt->fetch() ? true : false;
    echo json_encode(['exists' => $exists]);
} catch (PDOException $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
?>