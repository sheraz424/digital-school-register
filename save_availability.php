<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_availability'])) {
    $teacher_id = $_POST['teacher_id'];
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    
    // Check if similar availability exists
    $stmt = $pdo->prepare("SELECT * FROM teacher_availability WHERE teacher_id = ? AND day_of_week = ?");
    $stmt->execute([$teacher_id, $day_of_week]);
    
    if ($stmt->rowCount() > 0) {
        // Update existing
        $stmt = $pdo->prepare("UPDATE teacher_availability SET start_time = ?, end_time = ? WHERE teacher_id = ? AND day_of_week = ?");
        $stmt->execute([$start_time, $end_time, $teacher_id, $day_of_week]);
    } else {
        // Insert new
        $stmt = $pdo->prepare("INSERT INTO teacher_availability (teacher_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
        $stmt->execute([$teacher_id, $day_of_week, $start_time, $end_time]);
    }
}

header('Location: auto_timetable.php');
exit;
?>