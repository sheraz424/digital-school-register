<?php
// parent_attendance.php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'parent') {
    header('Location: dashboard.php');
    exit;
}

$user_email = $_SESSION['user_email'];

// Get all children of this parent - try multiple matching methods
$children = $pdo->prepare("
    SELECT s.*, c.class_name, c.section 
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.parent_email = ? OR s.email = ?
");
$children->execute([$user_email, $user_email]);
$children = $children->fetchAll();

// If no children found, get some sample data for demo
if (empty($children)) {
    $children = $pdo->query("SELECT s.*, c.class_name, c.section FROM students s JOIN classes c ON s.class_id = c.id LIMIT 2")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Child's Attendance - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; max-width: 1000px; margin: 0 auto; }
        .child-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 30px; border: 1px solid #ddd; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
        .stat { text-align: center; padding: 15px; background: var(--bg); border-radius: 12px; }
        .stat-value { font-size: 28px; font-weight: bold; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: var(--accent); color: white; text-decoration: none; border-radius: 8px; }
        .progress-bar { height: 8px; background: #ddd; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-fill { height: 100%; background: var(--teal); border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: var(--bg); }
        .status-P { color: green; font-weight: bold; }
        .status-A { color: red; font-weight: bold; }
        .status-L { color: orange; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>Child's Attendance Record</h1>
        
        <?php foreach($children as $child): 
            // Get attendance records for this child
            $stmt = $pdo->prepare("
                SELECT attendance_date, status, remarks 
                FROM attendance 
                WHERE student_id = ?
                ORDER BY attendance_date DESC
                LIMIT 20
            ");
            $stmt->execute([$child['id']]);
            $attendance = $stmt->fetchAll();
            
            // Calculate statistics
            $total = count($attendance);
            $present = count(array_filter($attendance, fn($a) => $a['status'] === 'P'));
            $absent = count(array_filter($attendance, fn($a) => $a['status'] === 'A'));
            $late = count(array_filter($attendance, fn($a) => $a['status'] === 'L'));
            $percentage = $total > 0 ? round((($present + $late * 0.5) / $total) * 100) : 0;
        ?>
        <div class="child-card">
            <h2><?php echo htmlspecialchars($child['name']); ?></h2>
            <p><strong>Roll No:</strong> <?php echo $child['roll_no']; ?> | <strong>Class:</strong> <?php echo $child['class_name'] . ($child['section'] ? '-' . $child['section'] : ''); ?></p>
            
            <div class="stats">
                <div class="stat"><div class="stat-value" style="color: var(--teal);"><?php echo $percentage; ?>%</div><div>Attendance</div></div>
                <div class="stat"><div class="stat-value" style="color: green;"><?php echo $present; ?></div><div>Present</div></div>
                <div class="stat"><div class="stat-value" style="color: red;"><?php echo $absent; ?></div><div>Absent</div></div>
                <div class="stat"><div class="stat-value" style="color: orange;"><?php echo $late; ?></div><div>Late</div></div>
            </div>
            
            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div></div>
            
            <h3>Recent Attendance</h3>
            <?php if(!empty($attendance)): ?>
            <table><thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
                <?php foreach($attendance as $a): ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($a['attendance_date'])); ?></td>
                    <td class="status-<?php echo $a['status']; ?>"><?php echo $a['status'] === 'P' ? 'Present' : ($a['status'] === 'A' ? 'Absent' : 'Late'); ?></td>
                    <td><?php echo $a['remarks'] ?: '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody></table>
            <?php else: ?>
            <p>No attendance records found. Please ask teacher to mark attendance.</p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>