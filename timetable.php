<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$class_id = $_GET['class_id'] ?? 0;

$classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY FIELD(class_name, 'Nursery','Prep','1','2','3','4','5','6','7','8','9','10'), section")->fetchAll();

// Get timetable data
if ($class_id) {
    $timetable = $pdo->prepare("
        SELECT t.*, s.subject_name, u.full_name as teacher_name
        FROM timetable t
        JOIN subjects s ON t.subject_id = s.id
        JOIN users u ON t.teacher_id = u.id
        WHERE t.class_id = ?
        ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), t.start_time
    ");
    $timetable->execute([$class_id]);
    $timetable = $timetable->fetchAll();
} else {
    $timetable = [];
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Timetable - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #2E86AB; color: white; text-align: center; }
        td { text-align: center; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .btn { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn"> Back to Dashboard</a>
        <h1>Class Timetable</h1>
        
        <div class="card">
            <form method="GET" action="">
                <div class="form-group">
                    <label>Select Class</label>
                    <select name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($class_id == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo $c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn">View Timetable</button>
            </form>
        </div>
        
        <?php if($class_id && !empty($timetable)): ?>
        <div class="card">
            <h2>Timetable</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Day / Time</th>
                            <th>09:00 - 10:00</th>
                            <th>10:00 - 11:00</th>
                            <th>11:00 - 12:00</th>
                            <th>12:00 - 13:00</th>
                            <th>13:00 - 14:00</th>
                        </thead>
                    <tbody>
                        <?php foreach($days as $day): ?>
                        <tr>
                            <td style="background: #2E86AB; color: white; font-weight: bold;"><?php echo $day; ?></td>
                            <?php for($hour = 9; $hour < 14; $hour++): 
                                $start = sprintf('%02d:00:00', $hour);
                                $end = sprintf('%02d:00:00', $hour + 1);
                                $found = false;
                                foreach($timetable as $t) {
                                    if($t['day_of_week'] == $day && $t['start_time'] <= $start && $t['end_time'] >= $end) {
                                        echo "<td><strong>{$t['subject_name']}</strong><br><small>{$t['teacher_name']}<br>{$t['room_no']}</small></td>";
                                        $found = true;
                                        break;
                                    }
                                }
                                if(!$found) echo "<td>-</td>";
                            endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php elseif($class_id): ?>
        <div class="card">
            <p>No timetable found for this class. Please generate timetable first.</p>
            <a href="auto_timetable.php" class="btn">Go to Auto Timetable</a>
        </div>
        <?php endif; ?>
    </div>
    <script>
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>