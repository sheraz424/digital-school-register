<?php
require_once '../db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

$user = getCurrentUser($pdo);
if (!$user) {
    sendResponse(false, 'Please login first');
}

$data = json_decode(file_get_contents('php://input'), true);
$attendance_id = isset($data['attendance_id']) ? intval($data['attendance_id']) : 0;

if (!$attendance_id) {
    sendResponse(false, 'Attendance ID is required');
}

try {
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
    if ($stmt->execute([$attendance_id])) {
        sendResponse(true, 'Attendance record deleted successfully');
    } else {
        sendResponse(false, 'Failed to delete attendance');
    }
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}

function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
?>