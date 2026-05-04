<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$user_role = $_SESSION['user_role'];

// Redirect non-admin users
if ($user_role !== 'admin') {
    if ($user_role === 'principal') {
        header('Location: principal_dashboard.php');
    } elseif ($user_role === 'accountant') {
        header('Location: accountant_dashboard.php');
    } elseif ($user_role === 'teacher') {
        header('Location: teacher_dashboard.php');
    } elseif ($user_role === 'student') {
        header('Location: student_dashboard.php');
    } elseif ($user_role === 'parent') {
        header('Location: parent_dashboard.php');
    }
    exit;
}

$user_name = $_SESSION['user_name'];

// Get statistics
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$totalStaff = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
$totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$pendingFees = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM fee_allocations WHERE status IN ('Pending', 'Partial')")->fetchColumn();
$pendingLeaves = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn();

// Get recent students
$recentStudents = $pdo->query("SELECT id, name, roll_no, created_at FROM students ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get recent payments (fixed query)
$recentPayments = $pdo->query("
    SELECT fp.*, s.name, s.roll_no 
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    ORDER BY fp.created_at DESC 
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - DSR</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 1400px; margin: 0 auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #ddd; }
        .stat-value { font-size: 32px; font-weight: bold; color: #2E86AB; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; }
        .btn { background: #2E86AB; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        .sidebar { width: 260px; background: #0F2447; min-height: 100vh; position: fixed; left: 0; top: 0; overflow-y: auto; }
        .main-content { margin-left: 260px; }
        .sidebar-brand { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sb-logo { font-size: 24px; font-weight: bold; color: white; letter-spacing: 2px; }
        .sb-sub { font-size: 10px; color: rgba(255,255,255,0.5); }
        .nav-item { display: block; padding: 10px 20px; color: rgba(255,255,255,0.7); text-decoration: none; }
        .nav-item:hover { background: rgba(255,255,255,0.1); color: white; }
        .nav-section { padding: 10px 20px 5px; font-size: 11px; color: rgba(255,255,255,0.4); text-transform: uppercase; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="sb-logo">DSR</div>
            <div class="sb-sub">Admin Panel</div>
        </div>
        <div class="nav-section">Main</div>
        <a href="dashboard.php" class="nav-item" style="background: rgba(255,255,255,0.1); color: white;">Dashboard</a>
        
        <div class="nav-section">Management</div>
        <a href="manage_students.php" class="nav-item">Manage Students</a>
        <a href="manage_teachers.php" class="nav-item">Manage Teachers</a>
    
        <a href="manage_classes.php" class="nav-item">Manage Classes</a>
        
        <div class="nav-section">Academic</div>
        <a href="attendance.php" class="nav-item">Attendance</a>
        <a href="timetable.php" class="nav-item">Timetable</a>
        <a href="datesheet.php" class="nav-item">Datesheet</a>
        <a href="results.php" class="nav-item">Results</a>
        
        <div class="nav-section">Finance</div>
        <a href="fee_management.php" class="nav-item">Fee Management</a>
        <a href="fee_reports.php" class="nav-item">Fee Reports</a>
        <a href="salary_management.php" class="nav-item">Salary Management</a>
        
        <div class="nav-section">HR & Communication</div>
        <a href="leave_management.php" class="nav-item">Leave Management</a>
        <a href="email_notifications.php" class="nav-item">Notifications</a>
        
        <div class="nav-section">Account</div>
        <a href="change_password.php" class="nav-item">Change Password</a>
        <a href="logout.php" class="nav-item">Logout</a>
    </aside>

    <main class="main-content">
        <div class="container">
            <h1>Admin Dashboard</h1>
            <p>Welcome, <strong><?php echo htmlspecialchars($user_name); ?></strong>!</p>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value"><?php echo $totalStudents; ?></div><div>Total Students</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $totalTeachers; ?></div><div>Total Teachers</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $totalStaff; ?></div><div>Total Staff</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $totalClasses; ?></div><div>Total Classes</div></div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value" style="color: #dc3545;">Rs <?php echo number_format($pendingFees); ?></div><div>Pending Fees</div></div>
                <div class="stat-card"><div class="stat-value" style="color: #ffc107;"><?php echo $pendingLeaves; ?></div><div>Pending Leaves</div></div>
            </div>
            
            <div class="card">
                <div class="card-header"><h3>Quick Actions</h3></div>
                <div>
                    <a href="manage_students.php" class="btn">+ Add Student</a>
                    <a href="manage_teachers.php" class="btn">+ Add Teacher</a>
                    
                    <a href="fee_management.php" class="btn">Manage Fees</a>
                    <a href="leave_management.php" class="btn">Approve Leaves</a>
                    <a href="attendance.php" class="btn">Mark Attendance</a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><h3>Recently Registered Students</h3><a href="manage_students.php" class="btn" style="background: #6c757d;">View All</a></div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Roll No</th><th>Name</th><th>Registration Date</th></tr></thead>
                        <tbody>
                            <?php foreach($recentStudents as $s): ?>
                            <tr><td><?php echo $s['roll_no']; ?></td><td><?php echo $s['name']; ?></td><td><?php echo date('d M Y', strtotime($s['created_at'])); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><h3>Recent Fee Payments</h3><a href="fee_reports.php" class="btn" style="background: #6c757d;">View Reports</a></div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Student</th><th>Amount</th><th>Payment Date</th><th>Method</th></tr></thead>
                        <tbody>
                            <?php foreach($recentPayments as $p): ?>
                            <tr><td><?php echo $p['name']; ?></td><td>Rs <?php echo number_format($p['amount_paid']); ?></td><td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td><td><?php echo $p['payment_method']; ?></td></tr>
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