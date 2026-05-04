<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'principal') {
    header('Location: dashboard.php');
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');
$class_id = $_GET['class_id'] ?? 0;

$classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY class_name")->fetchAll();

// Get attendance data
if ($class_id) {
    $attendance = $pdo->prepare("
        SELECT s.roll_no, s.name, a.status, a.remarks, a.attendance_date
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.class_id = ? AND a.attendance_date = ?
        ORDER BY s.roll_no
    ");
    $attendance->execute([$class_id, $date]);
    $attendance = $attendance->fetchAll();
} else {
    $attendance = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Attendance - Principal</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .btn { background: #2E86AB; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .status-P { color: green; font-weight: bold; }
        .status-A { color: red; font-weight: bold; }
        .status-L { color: orange; font-weight: bold; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="principal_dashboard.php" class="back-btn"> Back to Dashboard</a>
        <h1>View Attendance Records</h1>
        
        <div class="card">
            <form method="GET" action="">
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <select name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($class_id == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo $c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date" value="<?php echo $date; ?>">
                    <button type="submit" class="btn">View Attendance</button>
                </div>
            </form>
        </div>
        
        <?php if($class_id && !empty($attendance)): ?>
        <div class="card">
            <h3>Attendance for <?php echo date('d M Y', strtotime($date)); ?></h3>
            <div style="overflow-x: auto;">
                <table>
                    <thead><tr><th>Roll No</th><th>Student Name</th><th>Status</th><th>Remarks</th></tr></thead>
                    <tbody>
                        <?php foreach($attendance as $a): ?>
                        <tr>
                            <td><?php echo $a['roll_no']; ?></td>
                            <td><?php echo $a['name']; ?></td>
                            <td class="status-<?php echo $a['status']; ?>">
                                <?php echo $a['status'] == 'P' ? 'Present' : ($a['status'] == 'A' ? 'Absent' : 'Late'); ?>
                            </td>
                            <td><?php echo $a['remarks'] ?: '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php elseif($class_id): ?>
        <div class="card"><p>No attendance records found for selected class and date.</p></div>
        <?php endif; ?>
    </div>
</body>
</html>