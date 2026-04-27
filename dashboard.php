<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Get counts based on role
$totalStudents = 0;
$totalTeachers = 0;
$totalClasses = 0;
$myStudents = 0;
$myAttendance = 0;
$myChildren = 0;
$todayAttendance = 0;
$pendingFees = 0;
$notifications = [];

if ($user_role === 'admin') {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $totalStudents = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'teacher'");
    $totalTeachers = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM classes");
    $totalClasses = $stmt->fetch()['total'];
    
    // Get pending fees count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM fees WHERE status = 'Pending'");
    $pendingFees = $stmt->fetch()['total'];
}

if ($user_role === 'teacher') {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $myStudents = $stmt->fetch()['total'];
    
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT student_id) as total, 
               SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present
        FROM attendance WHERE attendance_date = ?
    ");
    $stmt->execute([$today]);
    $att = $stmt->fetch();
    $todayAttendance = ($att && $att['total'] > 0) ? round(($att['present'] / $att['total']) * 100) : 0;
    
    // Get recent absent students for notification
    $stmt = $pdo->prepare("
        SELECT s.name, a.attendance_date 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.status = 'A' AND a.attendance_date = ?
        LIMIT 5
    ");
    $stmt->execute([$today]);
    $absentStudents = $stmt->fetchAll();
    foreach($absentStudents as $absent) {
        $notifications[] = $absent['name'] . ' was absent today.';
    }
}

if ($user_role === 'student') {
    $stmt = $pdo->prepare("
        SELECT s.*, c.class_name, c.section 
        FROM students s
        JOIN classes c ON s.class_id = c.id
        WHERE s.name = ? OR s.email = ?
        LIMIT 1
    ");
    $stmt->execute([$user_name, $user_email]);
    $student = $stmt->fetch();
    
    if ($student) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'L' THEN 1 ELSE 0 END) as late
            FROM attendance WHERE student_id = ?
        ");
        $stmt->execute([$student['id']]);
        $stats = $stmt->fetch();
        $totalDays = $stats['total'] ?? 0;
        $presentDays = $stats['present'] ?? 0;
        $lateDays = $stats['late'] ?? 0;
        $myAttendance = $totalDays > 0 ? round((($presentDays + $lateDays * 0.5) / $totalDays) * 100) : 0;
    }
}

if ($user_role === 'parent') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM students 
        WHERE parent_email = ?
    ");
    $stmt->execute([$user_email]);
    $myChildren = $stmt->fetch()['total'];
    
    // Get children with low attendance
    $stmt = $pdo->prepare("
        SELECT s.name, 
               ROUND(AVG(CASE WHEN a.status = 'P' THEN 100 WHEN a.status = 'L' THEN 50 ELSE 0 END), 1) as percent
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id
        WHERE s.parent_email = ?
        GROUP BY s.id
        HAVING percent < 75
    ");
    $stmt->execute([$user_email]);
    $lowAttendance = $stmt->fetchAll();
    foreach($lowAttendance as $child) {
        $notifications[] = $child['name'] . ' has low attendance (' . $child['percent'] . '%)';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>DSR — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css" />
    <style>
        .admin-only, .teacher-only, .student-only, .parent-only { display: none !important; }
        
        body.role-admin .admin-only { display: flex !important; }
        body.role-admin .admin-only-inline { display: inline-block !important; }
        body.role-teacher .teacher-only { display: flex !important; }
        body.role-teacher .teacher-only-inline { display: inline-block !important; }
        body.role-student .student-only { display: flex !important; }
        body.role-parent .parent-only { display: flex !important; }
        
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }
        .quick-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Sora', sans-serif;
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
            transition: all 0.2s;
        }
        .quick-btn svg { width: 18px; height: 18px; color: var(--accent); }
        .quick-btn:hover { background: var(--accent); color: white; border-color: var(--accent); }
        .quick-btn:hover svg { color: white; }
        
        /* Notification Bell */
        .notification-icon {
            position: relative;
            cursor: pointer;
        }
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--red);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: bold;
        }
        .notification-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 40px;
            width: 300px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
        }
        .notification-dropdown.show { display: block; }
        .notification-item {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .notification-item:last-child { border-bottom: none; }
        
        /* Progress Ring */
        .progress-ring {
            width: 80px;
            height: 80px;
            position: relative;
        }
        .progress-ring-circle {
            transform: rotate(-90deg);
        }
        .progress-ring-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 18px;
            font-weight: bold;
        }
    </style>
</head>
<body class="role-<?php echo $user_role; ?>">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="sb-logo">DSR</div>
            <span class="sb-sub">Digital School Register</span>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section-label">Main</div>
            <a href="dashboard.php" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            
            <a href="attendance.php" class="nav-item teacher-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Mark Attendance
            </a>
            
            <a href="attendance_history.php" class="nav-item teacher-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14"/><path d="M22 3h-6a4 4 0 0 0-4 4v14"/><path d="M12 21h10"/></svg>
                History
            </a>
            
            <a href="student_attendance.php" class="nav-item student-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                My Attendance
            </a>
            
            <a href="student_grades.php" class="nav-item student-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                My Grades
            </a>
            
            <a href="parent_attendance.php" class="nav-item parent-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Child's Attendance
            </a>
            
            <a href="parent_fees.php" class="nav-item parent-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Fee Payment
            </a>
            
            <a href="manage_students.php" class="nav-item admin-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Manage Students
            </a>
            
            <a href="manage_teachers.php" class="nav-item admin-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Manage Teachers
            </a>
            
            <a href="manage_classes.php" class="nav-item admin-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                Manage Classes
            </a>
            
            <a href="fee_reports.php" class="nav-item admin-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Fee Reports
            </a>
            
            <!-- Edit Profile Links -->
            <a href="edit_teacher_profile.php" class="nav-item teacher-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M17 6l2 2 4-4"/></svg>
                Edit Profile
            </a>
            
            <a href="edit_student_profile.php" class="nav-item student-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M17 6l2 2 4-4"/></svg>
                Edit Profile
            </a>
            
            <a href="change_password.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Change Password
            </a>
        </nav>
        <div class="sidebar-profile">
            <div class="profile-avatar"><?php echo substr($user_name, 0, 1); ?></div>
            <div class="profile-info">
                <span class="profile-name"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="profile-role"><?php echo ucfirst($user_role); ?></span>
            </div>
            <a href="logout.php" class="logout-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-btn" onclick="toggleSidebar()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="page-title">
                    <h1>Dashboard</h1>
                    <span class="breadcrumb">Home / Overview</span>
                </div>
            </div>
            <div class="topbar-right">
                <!-- Notification Bell -->
                <div class="notification-icon" onclick="toggleNotifications()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <?php if(count($notifications) > 0): ?>
                    <span class="notification-badge"><?php echo count($notifications); ?></span>
                    <?php endif; ?>
                    <div id="notificationDropdown" class="notification-dropdown">
                        <?php if(empty($notifications)): ?>
                        <div class="notification-item">No new notifications</div>
                        <?php else: ?>
                        <?php foreach($notifications as $notif): ?>
                        <div class="notification-item">🔔 <?php echo $notif; ?></div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="date-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span id="date-display"></span>
                </div>
            </div>
        </header>

        <div class="dashboard-body">
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h2>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
                    <p>
                        <?php 
                        if($user_role === 'admin') echo "🎓 Manage your school from here. Total students: $totalStudents, Teachers: $totalTeachers";
                        elseif($user_role === 'teacher') echo "📚 Manage your classes and track student attendance.";
                        elseif($user_role === 'student') echo "📖 View your attendance and academic progress.";
                        else echo "👪 Monitor your child's educational journey.";
                        ?>
                    </p>
                </div>
            </div>

            <div class="stats-grid">
                <!-- Admin Stats -->
                <div class="stat-card blue admin-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                    <div class="stat-info"><span class="stat-label">Total Students</span><span class="stat-value"><?php echo $totalStudents; ?></span></div>
                </div>
                <div class="stat-card teal admin-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                    <div class="stat-info"><span class="stat-label">Total Teachers</span><span class="stat-value"><?php echo $totalTeachers; ?></span></div>
                </div>
                <div class="stat-card gold admin-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div>
                    <div class="stat-info"><span class="stat-label">Total Classes</span><span class="stat-value"><?php echo $totalClasses; ?></span></div>
                </div>
                <div class="stat-card green admin-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                    <div class="stat-info"><span class="stat-label">Pending Fees</span><span class="stat-value"><?php echo $pendingFees; ?></span></div>
                </div>
                
                <!-- Teacher Stats -->
                <div class="stat-card blue teacher-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                    <div class="stat-info"><span class="stat-label">My Students</span><span class="stat-value"><?php echo $myStudents; ?></span></div>
                </div>
                <div class="stat-card gold teacher-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
                    <div class="stat-info"><span class="stat-label">Today's Attendance</span><span class="stat-value"><?php echo $todayAttendance; ?>%</span></div>
                </div>
                
                <!-- Student Stats -->
                <div class="stat-card teal student-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                    <div class="stat-info"><span class="stat-label">My Attendance</span><span class="stat-value"><?php echo $myAttendance; ?>%</span></div>
                </div>
                
                <!-- Parent Stats -->
                <div class="stat-card gold parent-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                    <div class="stat-info"><span class="stat-label">My Children</span><span class="stat-value"><?php echo $myChildren; ?></span></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header"><h3>Quick Actions</h3></div>
                <div class="quick-actions-grid">
                    <button class="quick-btn teacher-only" onclick="location.href='attendance.php'">📋 Mark Attendance</button>
                    <button class="quick-btn teacher-only" onclick="location.href='attendance_history.php'">📊 View History</button>
                    <button class="quick-btn teacher-only" onclick="location.href='teacher_grades.php'">✏️ Enter Grades</button>
                    
                    <button class="quick-btn admin-only" onclick="location.href='manage_students.php'">👨‍🎓 Manage Students</button>
                    <button class="quick-btn admin-only" onclick="location.href='manage_teachers.php'">👨‍🏫 Manage Teachers</button>
                    <button class="quick-btn admin-only" onclick="location.href='fee_reports.php'">💰 Fee Reports</button>
                    
                    <button class="quick-btn student-only" onclick="location.href='student_attendance.php'">📅 My Attendance</button>
                    <button class="quick-btn student-only" onclick="location.href='student_grades.php'">🎓 My Grades</button>
                    <button class="quick-btn student-only" onclick="location.href='edit_student_profile.php'">✏️ Edit Profile</button>
                    
                    <button class="quick-btn parent-only" onclick="location.href='parent_attendance.php'">👶 Child's Attendance</button>
                    <button class="quick-btn parent-only" onclick="location.href='parent_fees.php'">💵 Pay Fees</button>
                    
                    <button class="quick-btn" onclick="location.href='change_password.php'">🔒 Change Password</button>
                </div>
            </div>

            <!-- Class Attendance - Teacher Only -->
            <div class="card teacher-only admin-only">
                <div class="card-header"><h3>Class Attendance Today</h3></div>
                <div class="attendance-bars">
                    <?php
                    $classes = $pdo->query("SELECT id, class_name, section FROM classes LIMIT 5");
                    while($class = $classes->fetch()):
                        $classDisplay = $class['class_name'] . ($class['section'] ? '-' . $class['section'] : '');
                        $today = date('Y-m-d');
                        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present FROM attendance WHERE class_id = ? AND attendance_date = ?");
                        $stmt->execute([$class['id'], $today]);
                        $att = $stmt->fetch();
                        $percent = ($att && $att['total'] > 0) ? round(($att['present'] / $att['total']) * 100) : 0;
                    ?>
                    <div class="att-row">
                        <span>Class <?php echo $classDisplay; ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width:<?php echo $percent; ?>%"></div></div>
                        <span class="att-pct"><?php echo $percent; ?>%</span>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('date-display').textContent = new Date().toLocaleDateString('en-PK', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
        
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        }
        
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const notificationIcon = document.querySelector('.notification-icon');
            const dropdown = document.getElementById('notificationDropdown');
            if (!notificationIcon.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>