<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

// Handle Generate Timetable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_timetable'])) {
    $class_id = $_POST['class_id'];
    
    // Delete existing timetable for this class
    $stmt = $pdo->prepare("DELETE FROM timetable WHERE class_id = ?");
    $stmt->execute([$class_id]);
    
    // Get subjects for this class
    $stmt = $pdo->prepare("
        SELECT cs.*, s.subject_name 
        FROM class_subjects cs
        JOIN subjects s ON cs.subject_id = s.id
        WHERE cs.class_id = ? AND cs.is_optional = 0
    ");
    $stmt->execute([$class_id]);
    $subjects = $stmt->fetchAll();
    
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $time_slots = [
        ['09:00:00', '10:00:00'],
        ['10:00:00', '11:00:00'],
        ['11:00:00', '12:00:00'],
        ['12:00:00', '13:00:00'],
        ['13:00:00', '14:00:00']
    ];
    
    $teacher = $pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1")->fetch();
    $teacher_id = $teacher ? $teacher['id'] : 1;
    
    $count = 0;
    foreach($subjects as $subject) {
        $day = $days[$count % count($days)];
        $slot = $time_slots[$count % count($time_slots)];
        
        $stmt = $pdo->prepare("INSERT INTO timetable (class_id, subject_id, teacher_id, day_of_week, start_time, end_time, room_no) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$class_id, $subject['subject_id'], $teacher_id, $day, $slot[0], $slot[1], 'Room ' . (101 + $count)]);
        $count++;
    }
    
    $message = "Timetable generated for " . count($subjects) . " subjects!";
}

$classes = $pdo->query("SELECT * FROM classes ORDER BY FIELD(class_name, 'Nursery','Prep','1','2','3','4','5','6','7','8','9','10'), section")->fetchAll();
$timetable = $pdo->query("
    SELECT t.*, c.class_name, c.section, s.subject_name, u.full_name as teacher_name
    FROM timetable t
    JOIN classes c ON t.class_id = c.id
    JOIN subjects s ON t.subject_id = s.id
    JOIN users u ON t.teacher_id = u.id
    ORDER BY t.day_of_week, t.start_time
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auto Timetable - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .btn { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-success { background: #28a745; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn"> Back to Dashboard</a>
        <h1>Automatic Timetable Generator</h1>
        
        <?php if($message): ?>
        <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Generate Timetable</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Select Class</label>
                    <select name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo $c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="generate_timetable" class="btn btn-success">Generate Timetable</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Current Timetable</h2>
            <div style="overflow-x: auto;">
                </table>
                    <thead>
                        <tr><th>Class</th><th>Subject</th><th>Teacher</th><th>Day</th><th>Time</th><th>Room</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($timetable as $t): ?>
                        <tr>
                            <td><?php echo $t['class_name'] . ($t['section'] ? ' - ' . $t['section'] : ''); ?></td>
                            <td><?php echo $t['subject_name']; ?></td>
                            <td><?php echo $t['teacher_name']; ?></td>
                            <td><?php echo $t['day_of_week']; ?></td>
                            <td><?php echo date('g:i A', strtotime($t['start_time'])); ?> - <?php echo date('g:i A', strtotime($t['end_time'])); ?></td>
                            <td><?php echo $t['room_no']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>