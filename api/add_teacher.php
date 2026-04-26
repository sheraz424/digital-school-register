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

$full_name = $data['full_name'] ?? '';
$email = $data['email'] ?? '';
$username = $data['username'] ?? '';
$contact = $data['contact'] ?? '';
$password = $data['password'] ?? 'admin123';

if (!$full_name || !$email || !$username) {
    sendResponse(false, 'Name, email and username are required');
}

try {
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, contact) VALUES (?, ?, ?, ?, 'teacher', ?)");
    $stmt->execute([$username, $email, $password, $full_name, $contact]);
    sendResponse(true, 'Teacher added successfully');
} catch(PDOException $e) {
    if ($e->getCode() == 23000) {
        sendResponse(false, 'User with this email or username already exists');
    } else {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}
?>