<?php
require_once '../db_connection.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_GET['action'] ?? '';

// Get teachers list
if ($action === 'get_teachers') {
    $teachers = $pdo->query("SELECT id, full_name, role, teacher_type FROM users WHERE role = 'teacher' ORDER BY full_name")->fetchAll();
    echo json_encode(['success' => true, 'data' => $teachers]);
    exit;
}

// Get class teachers mapping for all classes
if ($action === 'get_all_class_teachers') {
    $stmt = $pdo->query("
        SELECT ct.*, c.class_name, c.section, u.full_name as teacher_name 
        FROM class_teachers ct
        JOIN classes c ON ct.class_id = c.id
        JOIN users u ON ct.teacher_id = u.id
        ORDER BY FIELD(c.class_name, 'Nursery','Prep','1','2','3','4','5','6','7','8','9','10'), c.section
    ");
    $data = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// Get class teachers mapping for a specific class
if ($action === 'get_class_teachers') {
    $class_id = $_GET['class_id'] ?? 0;
    $stmt = $pdo->prepare("
        SELECT ct.*, u.full_name as teacher_name 
        FROM class_teachers ct
        JOIN users u ON ct.teacher_id = u.id
        WHERE ct.class_id = ?
    ");
    $stmt->execute([$class_id]);
    $data = $stmt->fetch();
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// Assign class teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'assign_class_teacher') {
    $data = json_decode(file_get_contents('php://input'), true);
    $class_id = $data['class_id'];
    $teacher_id = $data['teacher_id'];
    
    // Check if already assigned
    $check = $pdo->prepare("SELECT id FROM class_teachers WHERE class_id = ?");
    $check->execute([$class_id]);
    if ($check->fetch()) {
        $stmt = $pdo->prepare("UPDATE class_teachers SET teacher_id = ? WHERE class_id = ?");
        $stmt->execute([$teacher_id, $class_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO class_teachers (class_id, teacher_id) VALUES (?, ?)");
        $stmt->execute([$class_id, $teacher_id]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Class teacher assigned successfully']);
    exit;
}

// Delete class teacher assignment
if ($action === 'delete_class_teacher') {
    $class_id = $_GET['class_id'] ?? 0;
    $stmt = $pdo->prepare("DELETE FROM class_teachers WHERE class_id = ?");
    $stmt->execute([$class_id]);
    echo json_encode(['success' => true, 'message' => 'Class teacher removed successfully']);
    exit;
}

// Assign subject teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'assign_subject_teacher') {
    $data = json_decode(file_get_contents('php://input'), true);
    $class_id = $data['class_id'];
    $subject_id = $data['subject_id'];
    $teacher_id = $data['teacher_id'];
    
    // Check if already assigned
    $check = $pdo->prepare("SELECT id FROM subject_teachers WHERE class_id = ? AND subject_id = ?");
    $check->execute([$class_id, $subject_id]);
    if ($check->fetch()) {
        $stmt = $pdo->prepare("UPDATE subject_teachers SET teacher_id = ? WHERE class_id = ? AND subject_id = ?");
        $stmt->execute([$teacher_id, $class_id, $subject_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO subject_teachers (class_id, subject_id, teacher_id) VALUES (?, ?, ?)");
        $stmt->execute([$class_id, $subject_id, $teacher_id]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Subject teacher assigned successfully']);
    exit;
}

// Delete subject teacher assignment
if ($action === 'delete_subject_teacher') {
    $id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("DELETE FROM subject_teachers WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Subject teacher removed successfully']);
    exit;
}

// Get subject teachers for a class
if ($action === 'get_subject_teachers') {
    $class_id = $_GET['class_id'] ?? 0;
    $stmt = $pdo->prepare("
        SELECT st.*, s.subject_name, u.full_name as teacher_name 
        FROM subject_teachers st
        JOIN subjects s ON st.subject_id = s.id
        JOIN users u ON st.teacher_id = u.id
        WHERE st.class_id = ?
        ORDER BY s.subject_name
    ");
    $stmt->execute([$class_id]);
    $data = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}
?>