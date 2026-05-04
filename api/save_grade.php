<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$student_id = $data['student_id'] ?? 0;
$class_id = $data['class_id'] ?? 0;
$subject_id = $data['subject_id'] ?? 0;
$marks = $data['marks'] ?? 0;
$grade = $data['grade'] ?? '';

if (!$student_id || !$class_id || !$subject_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Verify teacher has access to this class and subject
$user_id = $_SESSION['user_id'];
$hasAccess = false;

// Check class teacher access
$stmt = $pdo->prepare("SELECT id FROM class_teachers WHERE class_id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $user_id]);
if ($stmt->fetch()) {
    $hasAccess = true;
}

// Check subject teacher access
if (!$hasAccess) {
    $stmt = $pdo->prepare("SELECT id FROM subject_teachers WHERE class_id = ? AND subject_id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $subject_id, $user_id]);
    if ($stmt->fetch()) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to enter grades for this class/subject']);
    exit;
}

$percentage = ($marks / 100) * 100;

try {
    // Check if result already exists
    $checkStmt = $pdo->prepare("
        SELECT id FROM results 
        WHERE student_id = ? AND class_id = ? AND subject_id = ? AND exam_type = 'Mid Term'
    ");
    $checkStmt->execute([$student_id, $class_id, $subject_id]);
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE results 
            SET marks_obtained = ?, percentage = ?, grade = ?, entered_by = ? 
            WHERE id = ?
        ");
        $stmt->execute([$marks, $percentage, $grade, $user_id, $existing['id']]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO results (student_id, class_id, subject_id, marks_obtained, total_marks, percentage, grade, exam_type, entered_by) 
            VALUES (?, ?, ?, ?, 100, ?, ?, 'Mid Term', ?)
        ");
        $stmt->execute([$student_id, $class_id, $subject_id, $marks, $percentage, $grade, $user_id]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Grade saved successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>