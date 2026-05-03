<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$user_role = $_SESSION['user_role'];
$action = $_GET['action'] ?? '';
$format = $_GET['format'] ?? 'excel';
$class_id = $_GET['class_id'] ?? 0;
$date = $_GET['date'] ?? date('Y-m-d');

if ($action == 'attendance') {
    // Get attendance data
    if ($class_id) {
        $stmt = $pdo->prepare("
            SELECT s.roll_no, s.name, a.status, a.remarks, a.attendance_date
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            WHERE a.class_id = ? AND a.attendance_date = ?
            ORDER BY s.roll_no
        ");
        $stmt->execute([$class_id, $date]);
    } else {
        $stmt = $pdo->prepare("
            SELECT s.roll_no, s.name, c.class_name, a.status, a.remarks, a.attendance_date
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            JOIN classes c ON s.class_id = c.id
            WHERE a.attendance_date = ?
            ORDER BY c.class_name, s.roll_no
        ");
        $stmt->execute([$date]);
    }
    $data = $stmt->fetchAll();
    
    $filename = "attendance_report_" . $date;
    
    if ($format == 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        
        echo "<table border='1'>";
        echo "<tr><th>Roll No</th><th>Student Name</th><th>Class</th><th>Status</th><th>Remarks</th><th>Date</th></tr>";
        foreach($data as $row) {
            echo "<tr>";
            echo "<td>" . $row['roll_no'] . "</td>";
            echo "<td>" . $row['name'] . "</td>";
            echo "<td>" . (isset($row['class_name']) ? $row['class_name'] : '-') . "</td>";
            echo "<td>" . ($row['status'] == 'P' ? 'Present' : ($row['status'] == 'A' ? 'Absent' : 'Late')) . "</td>";
            echo "<td>" . $row['remarks'] . "</td>";
            echo "<td>" . $row['attendance_date'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        exit;
    }
}

if ($action == 'fees') {
    $stmt = $pdo->query("
        SELECT s.roll_no, s.name, c.class_name, 
               COALESCE(SUM(f.amount), 0) as total_fees,
               COALESCE(SUM(CASE WHEN f.status = 'Paid' THEN f.amount ELSE 0 END), 0) as paid,
               COALESCE(SUM(CASE WHEN f.status = 'Pending' THEN f.amount ELSE 0 END), 0) as pending
        FROM students s
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN fees f ON s.id = f.student_id
        GROUP BY s.id
        ORDER BY s.roll_no
    ");
    $data = $stmt->fetchAll();
    
    $filename = "fee_report_" . date('Y-m-d');
    
    if ($format == 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        
        echo "<table border='1'>";
        echo "<tr><th>Roll No</th><th>Student Name</th><th>Class</th><th>Total Fees</th><th>Paid</th><th>Pending</th></tr>";
        foreach($data as $row) {
            echo "<tr>";
            echo "<td>" . $row['roll_no'] . "</td>";
            echo "<td>" . $row['name'] . "</td>";
            echo "<td>" . $row['class_name'] . "</td>";
            echo "<td>Rs " . number_format($row['total_fees']) . "</td>";
            echo "<td>Rs " . number_format($row['paid']) . "</td>";
            echo "<td>Rs " . number_format($row['pending']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        exit;
    }
}

// FIXED: Student List Report
if ($action == 'students') {
    $class_id = $_GET['class_id'] ?? 0;
    
    if ($class_id) {
        $stmt = $pdo->prepare("
            SELECT s.roll_no, s.name, s.email, s.parent_email, s.contact, c.class_name, c.section
            FROM students s
            JOIN classes c ON s.class_id = c.id
            WHERE s.class_id = ?
            ORDER BY s.roll_no
        ");
        $stmt->execute([$class_id]);
    } else {
        $stmt = $pdo->query("
            SELECT s.roll_no, s.name, s.email, s.parent_email, s.contact, c.class_name, c.section
            FROM students s
            JOIN classes c ON s.class_id = c.id
            ORDER BY c.class_name, s.roll_no
        ");
    }
    $data = $stmt->fetchAll();
    
    $filename = "student_list_" . date('Y-m-d');
    
    if ($format == 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        
        echo "<table border='1'>";
        echo "<tr><th>Roll No</th><th>Student Name</th><th>Class</th><th>Email</th><th>Parent Email</th><th>Contact</th></tr>";
        foreach($data as $row) {
            echo "<tr>";
            echo "<td>" . $row['roll_no'] . "</td>";
            echo "<td>" . $row['name'] . "</td>";
            echo "<td>" . $row['class_name'] . ($row['section'] ? '-' . $row['section'] : '') . "</td>";
            echo "<td>" . ($row['email'] ?: '-') . "</td>";
            echo "<td>" . ($row['parent_email'] ?: '-') . "</td>";
            echo "<td>" . ($row['contact'] ?: '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Export Reports - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme {
            --bg: #1a1a2e;
            --card: #16213e;
            --text: #eeeeee;
            --border: #2c3e50;
        }
        body.dark-theme .container, body.dark-theme .report-card {
            background: var(--card);
            color: var(--text);
            border-color: var(--border);
        }
        body.dark-theme select, body.dark-theme input {
            background: #0f0f23;
            border-color: var(--border);
            color: var(--text);
        }
        .container { padding: 20px; max-width: 800px; margin: 0 auto; }
        .report-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .btn { color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin: 5px; }
        .btn-excel { background: #28a745; }
        .btn-pdf { background: #dc3545; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        select, input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .theme-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #2E86AB;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 25px;
            cursor: pointer;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn"> Back to Dashboard</a>
        <h1>Export Reports</h1>
        
        <!-- Student List Report - FIXED -->
        <div class="report-card">
            <h2>Student List Report</h2>
            <form method="GET" action="export_reports.php">
                <input type="hidden" name="action" value="students">
                <div class="form-group">
                    <label>Select Class (Optional - leave empty for all)</label>
                    <select name="class_id">
                        <option value="0">All Classes</option>
                        <?php
                        $classes = $pdo->query("SELECT id, class_name, section FROM classes");
                        while($class = $classes->fetch()) {
                            echo "<option value='{$class['id']}'>Class {$class['class_name']}{$class['section']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" name="format" value="excel" class="btn btn-excel">Download as Excel</button>
            </form>
        </div>
        
        <!-- Attendance Report -->
        <div class="report-card">
            <h2>Attendance Report</h2>
            <form method="GET" action="export_reports.php">
                <input type="hidden" name="action" value="attendance">
                <div class="form-group">
                    <label>Select Class (Optional - leave empty for all)</label>
                    <select name="class_id">
                        <option value="0">All Classes</option>
                        <?php
                        $classes = $pdo->query("SELECT id, class_name, section FROM classes");
                        while($class = $classes->fetch()) {
                            echo "<option value='{$class['id']}'>Class {$class['class_name']}{$class['section']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Date</label>
                    <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <button type="submit" name="format" value="excel" class="btn btn-excel">Download as Excel</button>
            </form>
        </div>
        
        <!-- Fee Report -->
        <div class="report-card">
            <h2>Fee Report</h2>
            <form method="GET" action="export_reports.php">
                <input type="hidden" name="action" value="fees">
                <button type="submit" name="format" value="excel" class="btn btn-excel">Download Fee Report (Excel)</button>
            </form>
        </div>
    </div>
    
    <script>
        function toggleTheme() {
            document.body.classList.toggle('dark-theme');
            const theme = document.body.classList.contains('dark-theme') ? 'dark' : 'light';
            localStorage.setItem('theme', theme);
        }
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-theme');
        }
    </script>
</body>
</html>