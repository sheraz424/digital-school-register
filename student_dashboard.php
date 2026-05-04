<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'student') {
    header('Location: dashboard.php');
    exit;
}

$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Get student info
$stmt = $pdo->prepare("
    SELECT s.*, c.class_name, c.section 
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.name = ? OR s.email = ?
    LIMIT 1
");
$stmt->execute([$user_name, $user_email]);
$student = $stmt->fetch();

// Get attendance summary
$attendance = [];
$attendancePercent = 0;
if ($student) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present
        FROM attendance WHERE student_id = ?
    ");
    $stmt->execute([$student['id']]);
    $stats = $stmt->fetch();
    $total = $stats['total'] ?? 0;
    $present = $stats['present'] ?? 0;
    $attendancePercent = $total > 0 ? round(($present / $total) * 100) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard - DSR</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 1000px; margin: 0 auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #ddd; }
        .stat-value { font-size: 28px; font-weight: bold; color: #2E86AB; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .btn { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        .sidebar { width: 250px; background: #0F2447; min-height: 100vh; position: fixed; left: 0; top: 0; }
        .main-content { margin-left: 250px; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    
    <aside class="sidebar">
        <div class="sidebar-brand" style="padding: 20px;"><div class="sb-logo" style="color: white;">DSR</div><span class="sb-sub" style="color: rgba(255,255,255,0.6);">Student Portal</span></div>
        <nav style="padding: 16px;">
            <a href="student_dashboard.php" style="display: block; padding: 10px; color: white; background: rgba(255,255,255,0.1); border-radius: 8px;">Dashboard</a>
            <a href="student_attendance.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">My Attendance</a>
            <a href="student_grades.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">My Grades</a>
            <a href="timetable.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">Timetable</a>
            <a href="datesheet.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">Datesheet</a>
            <a href="leave_management.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">Apply Leave</a>
            <a href="change_password.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 20px;">Change Password</a>
            <a href="logout.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="container">
            <h1>Student Dashboard</h1>
            
            <?php if($student): ?>
            <p>Welcome, <strong><?php echo htmlspecialchars($student['name']); ?></strong>!</p>
            <p>Class: <?php echo $student['class_name'] . ($student['section'] ? ' - ' . $student['section'] : ''); ?> | Roll No: <?php echo $student['roll_no']; ?></p>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value"><?php echo $attendancePercent; ?>%</div><div>My Attendance</div></div>
            </div>
            
            <div class="card">
                <h3>Quick Actions</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
                    <a href="student_attendance.php" class="btn">View Attendance</a>
                    <a href="student_grades.php" class="btn">View Grades</a>
                    <a href="timetable.php" class="btn">View Timetable</a>
                    <a href="leave_management.php" class="btn">Apply Leave</a>
                </div>
            </div>
            <?php else: ?>
            <p>Student information not found. Please contact administrator.</p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>