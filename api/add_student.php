<?php
require_once '../db_connection.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    sendResponse(false, 'Access denied');
}

$data = json_decode(file_get_contents('php://input'), true);

$roll_no = $data['roll_no'] ?? '';
$name = $data['name'] ?? '';
$class_id = $data['class_id'] ?? '';
$email = $data['email'] ?? '';
$parent_email = $data['parent_email'] ?? '';
$contact = $data['contact'] ?? '';

if (!$roll_no || !$name || !$class_id) {
    sendResponse(false, 'Roll number, name and class are required');
}

try {
    $stmt = $pdo->prepare("INSERT INTO students (roll_no, name, class_id, email, parent_email, contact) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$roll_no, $name, $class_id, $email, $parent_email, $contact]);
    sendResponse(true, 'Student added successfully');
} catch(PDOException $e) {
    if ($e->getCode() == 23000) {
        sendResponse(false, 'Student with this roll number already exists');
    } else {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}
?>