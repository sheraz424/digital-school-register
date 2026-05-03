<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM subject_requirements WHERE id = ?");
    $stmt->execute([$_GET['id']]);
}

header('Location: auto_timetable.php');
exit;
?>