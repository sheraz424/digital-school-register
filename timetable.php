<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$class_id = $_GET['class_id'] ?? 0;
$user_role = $_SESSION['user_role'];

// Get all classes
$classes = $pdo->query("
    SELECT id, class_name, section 
    FROM classes 
    ORDER BY FIELD(class_name, 'Nursery','Prep','1','2','3','4','5','6','7','8','9','10'), section
")->fetchAll();

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

// Organize timetable by day and time
$organized = [];
foreach($timetable as $entry) {
    $organized[$entry['day_of_week']][$entry['start_time']] = $entry;
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$time_slots = [
    '08:00:00' => '08:00 - 08:45',
    '08:45:00' => '08:45 - 09:30',
    '09:30:00' => '09:30 - 10:15',
    '10:30:00' => '10:30 - 11:15',
    '11:15:00' => '11:15 - 12:00',
    '12:00:00' => '12:00 - 12:45',
    '12:45:00' => '12:45 - 13:30',
];

// Get class info
$classInfo = null;
if ($class_id) {
    $stmt = $pdo->prepare("SELECT class_name, section FROM classes WHERE id = ?");
    $stmt->execute([$class_id]);
    $classInfo = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Timetable - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        body.dark-theme .card, body.dark-theme .timetable-table td { background: var(--card); border-color: var(--border); }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .btn { background: #2E86AB; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; }
        .timetable-table { width: 100%; border-collapse: collapse; }
        .timetable-table th, .timetable-table td { border: 1px solid #ddd; padding: 10px; text-align: center; vertical-align: top; }
        .timetable-table th { background: #2E86AB; color: white; }
        .break-cell { background: #FFF3CD; color: #856404; }
        body.dark-theme .break-cell { background: #3a3a1a; color: #ffc107; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        .filter-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 200px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 12px; }
        select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; }
        .subject-cell { min-width: 100px; }
        @media (max-width: 768px) { .timetable-table { font-size: 10px; } .timetable-table th, .timetable-table td { padding: 4px; } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="<?php echo ($user_role === 'principal') ? 'principal_dashboard.php' : ($user_role === 'admin' ? 'dashboard.php' : 'dashboard.php'); ?>" class="back-btn">← Back to Dashboard</a>
        <h1>Weekly Class Timetable</h1>
        
        <div class="card">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Select Class</label>
                        <select name="class_id" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($class_id == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo $c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn">View Timetable</button>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if($class_id && !empty($organized)): ?>
        <div class="card">
            <h3>
                Weekly Timetable - 
                <?php echo $classInfo['class_name']; ?> 
                <?php echo $classInfo['section'] ? ' - ' . $classInfo['section'] : ''; ?>
            </h3>
            <?php if($classInfo && ($classInfo['class_name'] == '9' || $classInfo['class_name'] == '10') && $classInfo['section'] == 'Science'): ?>
            <p style="font-size: 12px; color: #666; margin-top: 5px;">
                <strong>Science Group Subjects:</strong> English, Urdu, Mathematics, Islamiat, Pakistan Studies, Physics, Chemistry, Biology
            </p>
            <?php endif; ?>
            <?php if($classInfo && ($classInfo['class_name'] == '9' || $classInfo['class_name'] == '10') && $classInfo['section'] == 'Arts'): ?>
            <p style="font-size: 12px; color: #666; margin-top: 5px;">
                <strong>Arts Group Subjects:</strong> English, Urdu, Mathematics, Islamiat, Pakistan Studies, History, Geography, Economics
            </p>
            <?php endif; ?>
            
            <div style="overflow-x: auto;">
                <table class="timetable-table">
                    <thead>
                        <tr>
                            <th style="width:100px;">Time / Day</th>
                            <th>Monday</th>
                            <th>Tuesday</th>
                            <th>Wednesday</th>
                            <th>Thursday</th>
                            <th>Friday</th>
                        </thead>
                        <tbody>
                            <?php foreach($time_slots as $slot_time => $slot_label): ?>
                            <tr>
                                <td style="font-weight: bold; background: #f5f5f5;"><?php echo $slot_label; ?></td>
                                <?php foreach($days as $day): 
                                    $subject_display = '-';
                                    if(isset($organized[$day][$slot_time])) {
                                        $entry = $organized[$day][$slot_time];
                                        $subject_display = "<strong>{$entry['subject_name']}</strong><br><small>{$entry['teacher_name']}<br>{$entry['room_no']}</small>";
                                    }
                                ?>
                                    <td class="subject-cell">
                                        <?php echo $subject_display; ?>
                                    </table>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php elseif($class_id): ?>
            <div class="card">
                <p>No timetable found for this class. Please ask administrator to generate timetable.</p>
                <?php if($user_role === 'admin'): ?>
                <a href="auto_timetable.php" class="btn">Generate Timetable</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>