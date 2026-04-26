<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'student') {
    header('Location: dashboard.php');
    exit;
}

$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Get student info by matching name or email
$stmt = $pdo->prepare("
    SELECT s.*, c.class_name, c.section 
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.name LIKE ? OR s.email = ? OR s.roll_no = ?
    LIMIT 1
");
$searchName = '%' . $user_name . '%';
$stmt->execute([$searchName, $user_email, $user_name]);
$student = $stmt->fetch();

if (!$student) {
    // Try to find by any match
    $stmt = $pdo->prepare("SELECT s.*, c.class_name, c.section FROM students s JOIN classes c ON s.class_id = c.id LIMIT 1");
    $stmt->execute();
    $student = $stmt->fetch();
}

$attendance = [];
if ($student && isset($student['id'])) {
    $stmt = $pdo->prepare("
        SELECT attendance_date, status, remarks 
        FROM attendance 
        WHERE student_id = ?
        ORDER BY attendance_date DESC
    ");
    $stmt->execute([$student['id']]);
    $attendance = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Attendance - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; max-width: 800px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: var(--bg); }
        .status-P { color: green; font-weight: bold; }
        .status-A { color: red; font-weight: bold; }
        .status-L { color: orange; font-weight: bold; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: var(--accent); color: white; text-decoration: none; border-radius: 8px; }
        .student-info { background: var(--bg); padding: 15px; border-radius: 12px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>My Attendance Record</h1>
        
        <?php if($student): ?>
        <div class="student-info">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($student['name'] ?? 'N/A'); ?></p>
            <p><strong>Class:</strong> <?php echo ($student['class_name'] ?? '') . (($student['section'] ?? '') ? '-' . $student['section'] : ''); ?></p>
            <p><strong>Roll No:</strong> <?php echo htmlspecialchars($student['roll_no'] ?? 'N/A'); ?></p>
        </div>
        <?php endif; ?>
        
        <table>
            <thead><tr><th>Date</th><th>Status</th><th>Remarks</th></thead>
            <tbody>
                <?php foreach($attendance as $a): ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($a['attendance_date'])); ?></td>
                    <td class="status-<?php echo $a['status']; ?>">
                        <?php echo $a['status'] === 'P' ? 'Present' : ($a['status'] === 'A' ? 'Absent' : 'Late'); ?>
                    </td>
                    <td><?php echo $a['remarks'] ?: '-'; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($attendance)): ?>
                <tr><td colspan="3" style="text-align:center;">No attendance records found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>