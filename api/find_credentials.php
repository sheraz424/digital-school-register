<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db_connection.php';

header('Content-Type: application/json');

$roll_no = isset($_GET['roll_no']) ? $_GET['roll_no'] : '';
$email = isset($_GET['email']) ? $_GET['email'] : '';

if (empty($roll_no) && empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Please enter Roll Number or Email']);
    exit;
}

try {
    $query = "SELECT name, roll_no, email FROM students WHERE ";
    $params = [];
    
    if ($roll_no && $email) {
        $query .= "roll_no = ? OR email = ?";
        $params = [$roll_no, $email];
    } elseif ($roll_no) {
        $query .= "roll_no = ?";
        $params = [$roll_no];
    } else {
        $query .= "email = ?";
        $params = [$email];
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $student = $stmt->fetch();
    
    if ($student) {
        echo json_encode([
            'success' => true,
            'name' => $student['name'],
            'roll_no' => $student['roll_no'],
            'email' => $student['email'],
            'message' => 'Student found! Use the email below to login.'
        ]);
    } else {
        // Check in users table for teachers/parents
        $stmt = $pdo->prepare("SELECT full_name, email, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo json_encode([
                'success' => true,
                'name' => $user['full_name'],
                'roll_no' => 'N/A',
                'email' => $user['email'],
                'message' => ucfirst($user['role']) . ' found! Use the email below to login. Password: admin123 (if not changed)'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No student found with the provided information. Please contact your class teacher or school administrator for assistance.'
            ]);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error. Please contact administrator.']);
}
?>