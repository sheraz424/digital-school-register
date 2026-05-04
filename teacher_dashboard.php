<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'teacher') {
    header('Location: dashboard.php');
    exit;
}

$user_name = $_SESSION['user_name'];
$today = date('Y-m-d');

// Get teacher's classes
$classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY class_name")->fetchAll();

// Get today's attendance summary
$todayAttendance = $pdo->query("
    SELECT COUNT(DISTINCT student_id) as total, 
           SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present
    FROM attendance WHERE attendance_date = '$today'
")->fetch();
$attendancePercent = ($todayAttendance['total'] > 0) ? round(($todayAttendance['present'] / $todayAttendance['total']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Dashboard - DSR</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
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
        <div class="sidebar-brand" style="padding: 20px;"><div class="sb-logo" style="color: white;">DSR</div><span class="sb-sub" style="color: rgba(255,255,255,0.6);">Teacher Portal</span></div>
        <nav style="padding: 16px;">
            <a href="teacher_dashboard.php" style="display: block; padding: 10px; color: white; background: rgba(255,255,255,0.1); border-radius: 8px;">Dashboard</a>
            <a href="attendance.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">Mark Attendance</a>
            <a href="attendance_history.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">Attendance History</a>
            <a href="teacher_grades.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">Enter Grades</a>
            <a href="timetable.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">View Timetable</a>
            <a href="leave_management.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">Apply Leave</a>
            <a href="change_password.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 20px;">Change Password</a>
            <a href="logout.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="container">
            <h1>Teacher Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($user_name); ?>!</p>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value"><?php echo count($classes); ?></div><div>My Classes</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $attendancePercent; ?>%</div><div>Today's Attendance</div></div>
            </div>
            
            <div class="card">
                <h3>Quick Actions</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
                    <a href="attendance.php" class="btn">Mark Attendance</a>
                    <a href="teacher_grades.php" class="btn">Enter Grades</a>
                    <a href="attendance_history.php" class="btn">View History</a>
                    <a href="leave_management.php" class="btn">Apply Leave</a>
                </div>
            </div>
            
            <div class="card">
                <h3>My Classes</h3>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr><th>Class</th><th>Section</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($classes as $c): ?>
                            <tr>
                                <td><?php echo $c['class_name']; ?></td>
                                <td><?php echo $c['section'] ?: '-'; ?></td>
                                <td><a href="attendance.php?class_id=<?php echo $c['id']; ?>" class="btn" style="padding: 5px 10px; font-size: 12px;">Mark Attendance</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>