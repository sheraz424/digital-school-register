<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Get counts based on role
$totalStudents = 0;
$totalTeachers = 0;
$totalAccountants = 0;
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
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'accountant'");
    $totalAccountants = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM classes");
    $totalClasses = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM fee_allocations WHERE status IN ('Pending', 'Partial')");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <title>DSR - Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css" />
    <style>
        :root {
            --bg: #F1F5F9;
            --card: #FFFFFF;
            --text: #1E2D3D;
            --muted: #6B7A8D;
            --border: #E0E8F0;
            --navy: #0F2447;
            --blue: #1A3A5C;
            --accent: #2E86AB;
            --teal: #0EB1A8;
            --gold: #F4A261;
            --green: #2ECC71;
            --red: #E74C3C;
        }

        body.dark-theme {
            --bg: #1a1a2e;
            --card: #16213e;
            --text: #eeeeee;
            --muted: #a0a0a0;
            --border: #2c3e50;
        }

        body.dark-theme .sidebar,
        body.dark-theme .topbar,
        body.dark-theme .card,
        body.dark-theme .welcome-banner,
        body.dark-theme .stat-card {
            background: var(--card);
            border-color: var(--border);
        }

        body.dark-theme .quick-btn {
            background: #0f0f23;
            border-color: var(--border);
            color: var(--text);
        }

        body.dark-theme input,
        body.dark-theme select {
            background: #0f0f23;
            border-color: var(--border);
            color: var(--text);
        }

        .admin-only, .teacher-only, .student-only, .parent-only, .academic_officer-only { display: none !important; }
        
        body.role-admin .admin-only { display: flex !important; }
        body.role-teacher .teacher-only { display: flex !important; }
        body.role-student .student-only { display: flex !important; }
        body.role-parent .parent-only { display: flex !important; }
        body.role-academic_officer .academic_officer-only { display: flex !important; }
        
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        
        .notification-icon { position: relative; cursor: pointer; }
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
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            border: 1px solid var(--border);
        }
        .notification-dropdown.show { display: block; }
        .notification-item {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            color: var(--text);
        }
        .theme-toggle {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        .theme-toggle:hover {
            background: var(--bg);
            transform: scale(1.1);
        }
        .badge.pending {
            background: #FFF3CD;
            color: #856404;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .stat-card.red {
            background: linear-gradient(135deg, #E74C3C, #C0392B);
        }
        .stat-card.red .stat-label,
        .stat-card.red .stat-value {
            color: white;
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .quick-actions-grid { grid-template-columns: 1fr; }
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
            
            <a href="manage_accountants.php" class="nav-item admin-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M3 8h4M6 5v6"/></svg>
                Manage Accountants
            </a>
            
            <a href="manage_classes.php" class="nav-item admin-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                Manage Classes
            </a>
            
            <a href="fee_management.php" class="nav-item admin-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Fee Management
            </a>
            
            <div class="nav-section-label">Advanced</div>
            
            <a href="accountant_dashboard.php" class="nav-item admin-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Accountant Portal
            </a>
            
            <a href="academic_officer_dashboard.php" class="nav-item admin-only academic_officer-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                    <path d="M2 17l10 5 10-5"/>
                    <path d="M2 12l10 5 10-5"/>
                </svg>
                Academic Officer
            </a>
            
            <a href="export_reports.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Export Reports
            </a>
            
            <a href="email_notifications.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 6L12 13L2 6"/><path d="M22 6v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6"/><path d="M2 6l10 7 10-7"/></svg>
                Email Notifications
            </a>
            
            <a href="auto_timetable.php" class="nav-item admin-only">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><polyline points="16 15 12 11 8 15"/></svg>
                Auto Timetable
            </a>
            
            <a href="timetable.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/></svg>
                View Timetable
            </a>
            
            <a href="leave_management.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 8v4l3 3M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
                    <path d="M12 6v2"/>
                </svg>
                Leave Management
            </a>
            
            <div class="nav-section-label">Account</div>
            
            <a href="change_password.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Change Password
            </a>
            
            <a href="logout.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </a>
        </nav>
        <div class="sidebar-profile">
            <div class="profile-avatar"><?php echo substr($user_name, 0, 1); ?></div>
            <div class="profile-info">
                <span class="profile-name"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="profile-role"><?php echo ucfirst($user_role); ?></span>
            </div>
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
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Dark/Light Mode">
                    <svg id="theme-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>
                
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
                        <div class="notification-item"> <?php echo $notif; ?></div>
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
                        if($user_role === 'admin') echo "Manage your school from here. Total students: $totalStudents, Teachers: $totalTeachers";
                        elseif($user_role === 'teacher') echo "Manage your classes and track student attendance.";
                        elseif($user_role === 'student') echo "View your attendance and academic progress.";
                        elseif($user_role === 'parent') echo "Monitor your child's educational journey.";
                        elseif($user_role === 'accountant') echo "Manage fee collections and financial records.";
                        elseif($user_role === 'academic_officer') echo "Register students and manage exam schedules.";
                        ?>
                    </p>
                </div>
            </div>

            <div class="stats-grid">
                <!-- Admin Stats -->
                <div class="stat-card blue admin-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                    <div class="stat-info">
                        <span class="stat-label">Total Students</span>
                        <span class="stat-value"><?php echo $totalStudents; ?></span>
                    </div>
                </div>
                <div class="stat-card teal admin-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                    <div class="stat-info">
                        <span class="stat-label">Total Teachers</span>
                        <span class="stat-value"><?php echo $totalTeachers; ?></span>
                    </div>
                </div>
                <div class="stat-card gold admin-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div>
                    <div class="stat-info">
                        <span class="stat-label">Total Accountants</span>
                        <span class="stat-value"><?php echo $totalAccountants; ?></span>
                    </div>
                </div>
                <div class="stat-card red admin-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                    <div class="stat-info">
                        <span class="stat-label" style="color: rgba(255,255,255,0.8);">Fees Pending</span>
                        <span class="stat-value" style="color: white;"><?php echo $pendingFees; ?></span>
                    </div>
                </div>
                
                <!-- Teacher Stats -->
                <div class="stat-card blue teacher-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                    <div class="stat-info">
                        <span class="stat-label">My Students</span>
                        <span class="stat-value"><?php echo $myStudents; ?></span>
                    </div>
                </div>
                <div class="stat-card gold teacher-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
                    <div class="stat-info">
                        <span class="stat-label">Today's Attendance</span>
                        <span class="stat-value"><?php echo $todayAttendance; ?>%</span>
                    </div>
                </div>
                
                <!-- Student Stats -->
                <div class="stat-card teal student-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                    <div class="stat-info">
                        <span class="stat-label">My Attendance</span>
                        <span class="stat-value"><?php echo $myAttendance; ?>%</span>
                    </div>
                </div>
                
                <!-- Parent Stats -->
                <div class="stat-card gold parent-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                    <div class="stat-info">
                        <span class="stat-label">My Children</span>
                        <span class="stat-value"><?php echo $myChildren; ?></span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header"><h3>Quick Actions</h3></div>
                <div class="quick-actions-grid">
                    <button class="quick-btn teacher-only" onclick="location.href='attendance.php'">Mark Attendance</button>
                    <button class="quick-btn teacher-only" onclick="location.href='attendance_history.php'">View History</button>
                    <button class="quick-btn teacher-only" onclick="location.href='teacher_grades.php'">Enter Grades</button>
                    
                    <button class="quick-btn admin-only" onclick="location.href='manage_students.php'">Manage Students</button>
                    <button class="quick-btn admin-only" onclick="location.href='manage_teachers.php'">Manage Teachers</button>
                    <button class="quick-btn admin-only" onclick="location.href='manage_accountants.php'">Manage Accountants</button>
                    <button class="quick-btn admin-only" onclick="location.href='fee_management.php'">Fee Management</button>
                    <button class="quick-btn admin-only" onclick="location.href='export_reports.php'">Export Reports</button>
                    <button class="quick-btn admin-only" onclick="location.href='email_notifications.php'">Email Alerts</button>
                    <button class="quick-btn admin-only" onclick="location.href='auto_timetable.php'">Auto Timetable</button>
                    
                    <button class="quick-btn academic_officer-only" onclick="location.href='academic_officer_dashboard.php'">Register Student</button>
                    <button class="quick-btn academic_officer-only" onclick="location.href='academic_officer_dashboard.php'">Generate Datesheet</button>
                    
                    <button class="quick-btn student-only" onclick="location.href='student_attendance.php'">My Attendance</button>
                    <button class="quick-btn student-only" onclick="location.href='student_grades.php'">My Grades</button>
                    
                    <button class="quick-btn parent-only" onclick="location.href='parent_attendance.php'">Child's Attendance</button>
                    <button class="quick-btn parent-only" onclick="location.href='parent_fees.php'">Pay Fees</button>
                    
                    <button class="quick-btn" onclick="location.href='leave_management.php'">Apply Leave</button>
                    <button class="quick-btn" onclick="location.href='change_password.php'">Change Password</button>
                </div>
            </div>

            <!-- Class Attendance - Teacher Only -->
            <div class="card teacher-only">
                <div class="card-header"><h3>Class Attendance Today</h3></div>
                <div class="attendance-bars">
                    <?php
                    $classes = $pdo->query("SELECT id, class_name, section FROM classes WHERE class_name IN ('1','2','3','4','5','6','7','8','9','10') LIMIT 5");
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
        
        document.addEventListener('click', function(event) {
            const notificationIcon = document.querySelector('.notification-icon');
            const dropdown = document.getElementById('notificationDropdown');
            if (notificationIcon && !notificationIcon.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        function toggleTheme() {
            document.body.classList.toggle('dark-theme');
            const theme = document.body.classList.contains('dark-theme') ? 'dark' : 'light';
            localStorage.setItem('theme', theme);
            
            const icon = document.getElementById('theme-icon');
            if (theme === 'dark') {
                icon.innerHTML = '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';
            } else {
                icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
            }
        }
        
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-theme');
        }
    </script>
</body>
</html>