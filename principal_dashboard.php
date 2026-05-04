<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'principal') {
    header('Location: dashboard.php');
    exit;
}

$user_name = $_SESSION['user_name'];

// Get statistics (view only)
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$pendingFees = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM fee_allocations WHERE status IN ('Pending', 'Partial')")->fetchColumn();
$pendingLeaves = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn();

$today = date('Y-m-d');
$todayAttendance = $pdo->query("
    SELECT COUNT(DISTINCT student_id) as total, 
           SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present
    FROM attendance WHERE attendance_date = '$today'
")->fetch();
$attendancePercent = ($todayAttendance['total'] > 0) ? round(($todayAttendance['present'] / $todayAttendance['total']) * 100) : 0;

// Get pending leaves for approval
$pendingLeavesList = $pdo->query("
    SELECT l.*, u.full_name, u.role 
    FROM leave_requests l
    JOIN users u ON l.user_id = u.id
    WHERE l.status = 'Pending'
    ORDER BY l.created_at DESC
    LIMIT 20
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Principal Dashboard - DSR</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 1400px; margin: 0 auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #ddd; }
        .stat-value { font-size: 28px; font-weight: bold; color: #2E86AB; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .btn { background: #2E86AB; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .approve-btn { background: #28a745; color: white; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; margin: 2px; display: inline-block; }
        .reject-btn { background: #dc3545; color: white; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; margin: 2px; display: inline-block; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        .sidebar { width: 250px; background: #0F2447; min-height: 100vh; position: fixed; left: 0; top: 0; overflow-y: auto; }
        .main-content { margin-left: 250px; }
        .sidebar-brand { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sb-logo { font-size: 24px; font-weight: bold; color: white; letter-spacing: 2px; }
        .sb-sub { font-size: 10px; color: rgba(255,255,255,0.5); }
        .nav-item { display: block; padding: 10px 20px; color: rgba(255,255,255,0.7); text-decoration: none; margin: 2px 0; }
        .nav-item:hover { background: rgba(255,255,255,0.1); color: white; }
        .nav-section { padding: 10px 20px 5px; font-size: 11px; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1px; }
        .search-box { width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="sb-logo">DSR</div>
            <div class="sb-sub">Principal Portal</div>
        </div>
        
        <div class="nav-section">Main</div>
        <a href="principal_dashboard.php" class="nav-item" style="background: rgba(255,255,255,0.1); color: white;">Dashboard</a>
        
        <div class="nav-section">Academic</div>
        <a href="view_attendance.php" class="nav-item">View Attendance</a>
        <a href="view_teachers.php" class="nav-item">View Teachers</a>
        <a href="view_students.php" class="nav-item">View Students</a>
        <a href="view_results.php" class="nav-item">View Results</a>
        <a href="view_reports.php" class="nav-item">Reports</a>
        <a href="datesheet.php" class="nav-item">Datesheet</a>
        <a href="fee_reports.php" class="nav-item">Fee Reports</a>
        <a href="timetable.php" class="nav-item">Timetable</a>
        
        <div class="nav-section">HR</div>
        <a href="leave_management.php" class="nav-item">Leave Approvals</a>
        
        <div class="nav-section">Account</div>
        <a href="change_password.php" class="nav-item">Change Password</a>
        <a href="logout.php" class="nav-item">Logout</a>
    </aside>

    <main class="main-content">
        <div class="container">
            <h1>Principal Dashboard</h1>
            <p>Welcome, <strong><?php echo htmlspecialchars($user_name); ?></strong>! (View Only + Leave Approval)</p>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value"><?php echo $totalStudents; ?></div><div>Total Students</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $totalTeachers; ?></div><div>Total Teachers</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $totalClasses; ?></div><div>Total Classes</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $attendancePercent; ?>%</div><div>Today's Attendance</div></div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value" style="color: #dc3545;">Rs <?php echo number_format($pendingFees); ?></div><div>Pending Fees</div></div>
                <div class="stat-card"><div class="stat-value" style="color: #ffc107;"><?php echo $pendingLeaves; ?></div><div>Pending Leaves</div></div>
            </div>
            
            <!-- Pending Leave Requests for Approval -->
            <div class="card">
                <h3>Pending Leave Requests (Need Your Approval)</h3>
                <input type="text" id="searchLeaves" class="search-box" placeholder="Search by employee name...">
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr><th>Employee</th><th>Role</th><th>Type</th><th>Dates</th><th>Reason</th><th>Action</th></tr>
                        </thead>
                        <tbody id="leaveTable">
                            <?php foreach($pendingLeavesList as $leave): ?>
                            <tr>
                                <td><?php echo $leave['full_name']; ?></td>
                                <td><?php echo ucfirst($leave['role']); ?></td>
                                <td><?php echo $leave['leave_type']; ?></td>
                                <td><?php echo date('d M', strtotime($leave['start_date'])); ?> - <?php echo date('d M', strtotime($leave['end_date'])); ?></td>
                                <td><?php echo substr($leave['reason'], 0, 50); ?>...</td>
                                <td>
                                    <a href="approve_leave.php?id=<?php echo $leave['id']; ?>&status=approved" class="approve-btn" onclick="return confirm('Approve this leave?')">✓ Approve</a>
                                    <a href="approve_leave.php?id=<?php echo $leave['id']; ?>&status=rejected" class="reject-btn" onclick="return confirm('Reject this leave?')">✗ Reject</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($pendingLeavesList)): ?>
                            <tr><td colspan="6" style="text-align: center;">No pending leave requests</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Search functionality for leaves table
        document.getElementById('searchLeaves').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#leaveTable tr');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
        
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>