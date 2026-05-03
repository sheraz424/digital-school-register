<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_requirement'])) {
    $class_id = $_POST['class_id'];
    $subject_id = $_POST['subject_id'];
    $periods_per_week = $_POST['periods_per_week'];
    
    $stmt = $pdo->prepare("INSERT INTO subject_requirements (class_id, subject_id, periods_per_week) VALUES (?, ?, ?)");
    $stmt->execute([$class_id, $subject_id, $periods_per_week]);
}

header('Location: auto_timetable.php');
exit;
?>