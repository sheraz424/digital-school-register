<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'principal') {
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'] ?? 0;
$status = $_GET['status'] ?? '';

if ($id && $status) {
    $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, approved_by = ? WHERE id = ?");
    $stmt->execute([ucfirst($status), $_SESSION['user_id'], $id]);
}

header('Location: ' . ($_SESSION['user_role'] === 'principal' ? 'principal_dashboard.php' : 'dashboard.php'));
exit;
?>