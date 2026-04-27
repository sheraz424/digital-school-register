<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Initialize variables
$totalStudents = 0;
$totalTeachers = 0;
$totalClasses = 0;
$myStudents = 0;
$myAttendance = 0;
$myChildren = 0;
$todayAttendance = 0;
$recentActivities = [];

// Get statistics based on role
if ($user_role === 'admin') {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $result = $stmt->fetch();
    $totalStudents = $result ? $result['total'] : 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'teacher'");
    $result = $stmt->fetch();
    $totalTeachers = $result ? $result['total'] : 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM classes");
    $result = $stmt->fetch();
    $totalClasses = $result ? $result['total'] : 0;
    
    // Admin recent activities - latest attendance and student additions
    $recentActivities = $pdo->query("
        SELECT 'attendance' as type, a.attendance_date as date, c.class_name, c.section, 
               COUNT(a.id) as total_students,
               SUM(CASE WHEN a.status = 'P' THEN 1 ELSE 0 END) as present
        FROM attendance a
        JOIN classes c ON a.class_id = c.id
        GROUP BY a.attendance_date, c.class_name, c.section
        ORDER BY a.attendance_date DESC
        LIMIT 3
    ")->fetchAll();
}

if ($user_role === 'teacher') {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $result = $stmt->fetch();
    $myStudents = $result ? $result['total'] : 0;
    
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT student_id) as total, 
               SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present
        FROM attendance WHERE attendance_date = ?
    ");
    $stmt->execute([$today]);
    $att = $stmt->fetch();
    $todayAttendance = ($att && $att['total'] > 0) ? round(($att['present'] / $att['total']) * 100) : 0;
    
    // Teacher recent activities - their classes attendance
    $recentActivities = $pdo->query("
        SELECT 'attendance' as type, a.attendance_date as date, c.class_name, c.section,
               COUNT(a.id) as total_students,
               SUM(CASE WHEN a.status = 'P' THEN 1 ELSE 0 END) as present
        FROM attendance a
        JOIN classes c ON a.class_id = c.id
        GROUP BY a.attendance_date, c.class_name, c.section
        ORDER BY a.attendance_date DESC
        LIMIT 3
    ")->fetchAll();
}

if ($user_role === 'student') {
    // Get student info
    $stmt = $pdo->prepare("
        SELECT s.*, c.class_name, c.section 
        FROM students s
        JOIN classes c ON s.class_id = c.id
        WHERE s.name = ? OR s.email = ? OR s.roll_no = ?
        LIMIT 1
    ");
    $searchName = '%' . $user_name . '%';
    $stmt->execute([$searchName, $user_email, $user_name]);
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
        
        // Student recent activities - their own attendance
        $recentActivities = $pdo->prepare("
            SELECT 'own_attendance' as type, attendance_date as date, status, remarks
            FROM attendance 
            WHERE student_id = ?
            ORDER BY attendance_date DESC
            LIMIT 3
        ");
        $recentActivities->execute([$student['id']]);
        $recentActivities = $recentActivities->fetchAll();
    }
}

if ($user_role === 'parent') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM students 
        WHERE parent_email = ?
    ");
    $stmt->execute([$user_email]);
    $result = $stmt->fetch();
    $myChildren = $result ? $result['total'] : 0;
    
    // Parent recent activities - their children's attendance
    $recentActivities = $pdo->prepare("
        SELECT 'child_attendance' as type, a.attendance_date as date, a.status, s.name as student_name,
               a.remarks
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE s.parent_email = ?
        ORDER BY a.attendance_date DESC
        LIMIT 3
    ");
    $recentActivities->execute([$user_email]);
    $recentActivities = $recentActivities->fetchAll();
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
        body.role-admin .admin-only-block { display: block !important; }
        body.role-teacher .teacher-only { display: flex !important; }
        body.role-teacher .teacher-only-block { display: block !important; }
        body.role-student .student-only { display: flex !important; }
        body.role-student .student-only-block { display: block !important; }
        body.role-parent .parent-only { display: flex !important; }
        body.role-parent .parent-only-block { display: block !important; }
        
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
        .quick-btn svg {
            width: 18px;
            height: 18px;
            color: var(--accent);
        }
        .quick-btn:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        .quick-btn:hover svg {
            color: white;
        }
        .status-P { color: green; font-weight: bold; }
        .status-A { color: red; font-weight: bold; }
        .status-L { color: orange; font-weight: bold; }
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
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
            
            <a href="change_password.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
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
                        if($user_role === 'admin') echo "Manage your school from here.";
                        elseif($user_role === 'teacher') echo "Manage your classes and track student attendance.";
                        elseif($user_role === 'student') echo "View your attendance and academic progress.";
                        else echo "Monitor your child's educational journey.";
                        ?>
                    </p>
                </div>
            </div>

            <div class="stats-grid">
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
                        <span class="stat-label">Total Classes</span>
                        <span class="stat-value"><?php echo $totalClasses; ?></span>
                    </div>
                </div>
                
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
                
                <div class="stat-card teal student-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                    <div class="stat-info">
                        <span class="stat-label">My Attendance</span>
                        <span class="stat-value"><?php echo $myAttendance; ?>%</span>
                    </div>
                </div>
                
                <div class="stat-card gold parent-only">
                    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                    <div class="stat-info">
                        <span class="stat-label">My Children</span>
                        <span class="stat-value"><?php echo $myChildren; ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="quick-actions-grid">
                    <button class="quick-btn teacher-only" onclick="location.href='attendance.php'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Mark Attendance
                    </button>
                    
                    <button class="quick-btn teacher-only" onclick="location.href='attendance_history.php'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14"/><path d="M22 3h-6a4 4 0 0 0-4 4v14"/></svg>
                        View History
                    </button>
                    
                    <button class="quick-btn teacher-only" onclick="location.href='teacher_grades.php'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        Enter Grades
                    </button>
                    
                    <button class="quick-btn admin-only" onclick="location.href='manage_students.php'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                        Manage Students
                    </button>
                    
                    <button class="quick-btn admin-only" onclick="location.href='manage_teachers.php'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                        Manage Teachers
                    </button>
                    
                    <button class="quick-btn admin-only" onclick="location.href='fee_reports.php'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Fee Reports
                    </button>
                    
                    <button class="quick-btn student-only" onclick="location.href='student_attendance.php'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        View My Attendance
                    </button>
                    
                    <button class="quick-btn student-only" onclick="location.href='student_grades.php'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        View My Grades
                    </button>
                    
                    <button class="quick-btn parent-only" onclick="location.href='parent_attendance.php'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Child's Attendance
                    </button>
                    
                    <button class="quick-btn parent-only" onclick="location.href='parent_fees.php'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Pay Fees
                    </button>
                    
                    <button class="quick-btn" onclick="location.href='change_password.php'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        Change Password
                    </button>
                </div>
            </div>

            <div class="card teacher-only admin-only">
                <div class="card-header">
                    <h3>Class Attendance Today</h3>
                </div>
                <div class="attendance-bars">
                    <?php
                    $classes = $pdo->query("SELECT id, class_name, section FROM classes LIMIT 5");
                    while($class = $classes->fetch()):
                        $classDisplay = $class['class_name'] . ($class['section'] ? '-' . $class['section'] : '');
                        $today = date('Y-m-d');
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as total, SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present
                            FROM attendance WHERE class_id = ? AND attendance_date = ?
                        ");
                        $stmt->execute([$class['id'], $today]);
                        $att = $stmt->fetch();
                        $percent = ($att && $att['total'] > 0) ? round(($att['present'] / $att['total']) * 100) : 0;
                        $warnClass = $percent < 75 ? 'warn' : '';
                        $warnText = $percent < 75 ? 'warn-text' : '';
                    ?>
                    <div class="att-row">
                        <span>Class <?php echo $classDisplay; ?></span>
                        <div class="bar-track"><div class="bar-fill <?php echo $warnClass; ?>" style="width:<?php echo $percent; ?>%"></div></div>
                        <span class="att-pct <?php echo $warnText; ?>"><?php echo $percent; ?>%</span>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Recent Activity - REAL DATA based on role -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <?php 
                        if($user_role === 'admin') echo 'Recent School Activity';
                        elseif($user_role === 'teacher') echo 'Recent Class Activity';
                        elseif($user_role === 'student') echo 'My Recent Attendance';
                        else echo "Children's Recent Activity";
                        ?>
                    </h3>
                    <a href="#" class="view-all">View All</a>
                </div>
                <div class="activity-list">
                    <?php if(empty($recentActivities)): ?>
                        <div class="activity-item">
                            <div class="activity-dot blue-dot"></div>
                            <div class="activity-body">
                                <span class="activity-text">No recent activity found.</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach($recentActivities as $activity): ?>
                            <?php if($user_role === 'admin' || $user_role === 'teacher'): ?>
                                <?php 
                                    $presentCount = $activity['present'] ?? 0;
                                    $totalCount = $activity['total_students'] ?? 0;
                                    $classDisplay = ($activity['class_name'] ?? '') . (($activity['section'] ?? '') ? '-' . $activity['section'] : '');
                                ?>
                                <div class="activity-item">
                                    <div class="activity-dot blue-dot"></div>
                                    <div class="activity-body">
                                        <span class="activity-text">
                                            Attendance marked for Class <?php echo $classDisplay; ?> - 
                                            <?php echo $presentCount; ?>/<?php echo $totalCount; ?> students present
                                        </span>
                                        <span class="activity-time"><?php echo date('d M Y', strtotime($activity['date'])); ?></span>
                                    </div>
                                </div>
                            <?php elseif($user_role === 'student'): ?>
                                <div class="activity-item">
                                    <div class="activity-dot <?php echo $activity['status'] === 'P' ? 'green-dot' : ($activity['status'] === 'A' ? 'red-dot' : 'gold-dot'); ?>"></div>
                                    <div class="activity-body">
                                        <span class="activity-text">
                                            On <?php echo date('d M Y', strtotime($activity['date'])); ?>: 
                                            <strong>
                                                <?php 
                                                if($activity['status'] === 'P') echo 'Present';
                                                elseif($activity['status'] === 'A') echo 'Absent';
                                                else echo 'Late';
                                                ?>
                                            </strong>
                                            <?php echo $activity['remarks'] ? ' - ' . $activity['remarks'] : ''; ?>
                                        </span>
                                        <span class="activity-time"><?php echo date('d M Y', strtotime($activity['date'])); ?></span>
                                    </div>
                                </div>
                            <?php elseif($user_role === 'parent'): ?>
                                <div class="activity-item">
                                    <div class="activity-dot <?php echo $activity['status'] === 'P' ? 'green-dot' : ($activity['status'] === 'A' ? 'red-dot' : 'gold-dot'); ?>"></div>
                                    <div class="activity-body">
                                        <span class="activity-text">
                                            <strong><?php echo htmlspecialchars($activity['student_name']); ?></strong> was 
                                            <?php 
                                            if($activity['status'] === 'P') echo 'present';
                                            elseif($activity['status'] === 'A') echo 'absent';
                                            else echo 'late';
                                            ?>
                                            on <?php echo date('d M Y', strtotime($activity['date'])); ?>
                                            <?php echo $activity['remarks'] ? ' - ' . $activity['remarks'] : ''; ?>
                                        </span>
                                        <span class="activity-time"><?php echo date('d M Y', strtotime($activity['date'])); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <style>
        .green-dot { background: var(--green); width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
        .red-dot { background: var(--red); width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
        .gold-dot { background: var(--gold); width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
        .blue-dot { background: var(--accent); width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
        .teal-dot { background: var(--teal); width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
    </style>

    <script>
        document.getElementById('date-display').textContent = new Date().toLocaleDateString('en-PK', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
        
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        }
    </script>
</body>
</html>