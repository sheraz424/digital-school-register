<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

// Get all classes
$classes = $pdo->query("
    SELECT id, class_name, section 
    FROM classes 
    ORDER BY FIELD(class_name, 'Nursery','Prep','1','2','3','4','5','6','7','8','9','10'), section
")->fetchAll();

// Get all teachers
$teachers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'teacher'")->fetchAll();

// Get all subjects
$subjects = $pdo->query("SELECT id, subject_name FROM subjects")->fetchAll();

$message = '';
$error = '';

// Handle Save Timetable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_timetable'])) {
    $pdo->exec("DELETE FROM timetable");
    
    foreach($_POST['timetable'] as $entry) {
        if (!empty($entry['class_id']) && !empty($entry['subject_id']) && !empty($entry['teacher_id']) && !empty($entry['day_of_week'])) {
            $stmt = $pdo->prepare("INSERT INTO timetable (class_id, subject_id, teacher_id, day_of_week, start_time, end_time, room_no) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $entry['class_id'], 
                $entry['subject_id'], 
                $entry['teacher_id'], 
                $entry['day_of_week'], 
                $entry['start_time'] ?? '09:00:00', 
                $entry['end_time'] ?? '10:00:00', 
                $entry['room_no'] ?? 'Room 101'
            ]);
        }
    }
    $message = "Timetable saved successfully!";
}

// Load existing timetable
$existingTimetable = [];
$stmt = $pdo->query("SELECT * FROM timetable");
while($row = $stmt->fetch()) {
    $existingTimetable[$row['class_id']][$row['day_of_week']][$row['start_time']] = $row;
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$time_slots = [
    '08:00:00' => '08:00 - 08:45',
    '08:45:00' => '08:45 - 09:30',
    '09:30:00' => '09:30 - 10:15',
    '10:15:00' => '10:15 - 10:30 (Break)',
    '10:30:00' => '10:30 - 11:15',
    '11:15:00' => '11:15 - 12:00',
    '12:00:00' => '12:00 - 12:45',
    '12:45:00' => '12:45 - 13:30',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Timetable - DSR</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 1400px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        body.dark-theme .card { background: var(--card); border-color: var(--border); }
        .btn { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin: 5px; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
        .table-container { overflow-x: auto; }
        .timetable-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .timetable-table th, .timetable-table td { border: 1px solid #ddd; padding: 8px; text-align: center; vertical-align: top; }
        .timetable-table th { background: #2E86AB; color: white; position: sticky; top: 0; }
        .break-cell { background: #FFF3CD; color: #856404; }
        body.dark-theme .break-cell { background: #3a3a1a; color: #ffc107; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .edit-mode select, .edit-mode input { width: 100%; min-width: 100px; padding: 6px; border-radius: 4px; border: 1px solid #ddd; }
        @media (max-width: 768px) { .timetable-table { font-size: 10px; } .timetable-table th, .timetable-table td { padding: 4px; } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn"> Back to Dashboard</a>
        <h1>Complete School Timetable</h1>
        
        <?php if($message): ?>
        <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- View Mode -->
        <div class="card">
            <div class="card-header">
                <h3>Weekly Timetable - All Classes</h3>
                <?php if($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin'): ?>
                <button onclick="toggleEditMode()" class="btn btn-warning" id="editBtn">Edit Timetable</button>
                <?php endif; ?>
            </div>
            
            <div id="viewMode">
                <div class="table-container">
                    <table class="timetable-table">
                        <thead>
                            <tr>
                                <th style="width:100px;">Time / Class</th>
                                <?php foreach($classes as $c): ?>
                                    <th><?php echo $c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : ''); ?></th>
                                <?php endforeach; ?>
                            </thead>
                            <tbody>
                                <?php foreach($time_slots as $slot_time => $slot_label): 
                                    $is_break = strpos($slot_label, 'Break') !== false;
                                ?>
                                <tr class="<?php echo $is_break ? 'break-cell' : ''; ?>">
                                    <td style="font-weight: bold;"><?php echo $slot_label; ?></td>
                                    <?php foreach($classes as $c): 
                                        $subject_display = '-';
                                        if(!$is_break && isset($existingTimetable[$c['id']])) {
                                            foreach($existingTimetable[$c['id']] as $day => $day_slots) {
                                                if(isset($day_slots[$slot_time])) {
                                                    $entry = $day_slots[$slot_time];
                                                    $subject_name = '';
                                                    foreach($subjects as $s) {
                                                        if($s['id'] == $entry['subject_id']) {
                                                            $subject_name = $s['subject_name'];
                                                            break;
                                                        }
                                                    }
                                                    $teacher_name = '';
                                                    foreach($teachers as $t) {
                                                        if($t['id'] == $entry['teacher_id']) {
                                                            $teacher_name = $t['full_name'];
                                                            break;
                                                        }
                                                    }
                                                    $subject_display = "<strong>$subject_name</strong><br><small>$teacher_name</small><br><small>{$entry['room_no']}</small>";
                                                    break;
                                                }
                                            }
                                        }
                                    ?>
                                        <td class="<?php echo $is_break ? 'break-cell' : ''; ?>">
                                            <?php echo $subject_display; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin'): ?>
            <div id="editMode" style="display:none;">
                <form method="POST">
                    <input type="hidden" name="save_timetable" value="1">
                    <div class="table-container">
                        <table class="timetable-table">
                            <thead>
                                <tr>
                                    <th style="width:100px;">Time / Class</th>
                                    <?php foreach($classes as $c): ?>
                                        <th><?php echo $c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : ''); ?></th>
                                    <?php endforeach; ?>
                                </thead>
                                <tbody>
                                    <?php foreach($days as $day): ?>
                                        <tr style="background: #e8f4fd;">
                                            <td colspan="<?php echo count($classes) + 1; ?>" style="background: #2E86AB; color: white; font-weight: bold;">
                                                <?php echo $day; ?>
                                            </td>
                                        </tr>
                                        <?php foreach($time_slots as $slot_time => $slot_label): 
                                            $is_break = strpos($slot_label, 'Break') !== false;
                                        ?>
                                        <tr class="<?php echo $is_break ? 'break-cell' : ''; ?>">
                                            <td style="font-weight: bold;"><?php echo $slot_label; ?></td>
                                            <?php foreach($classes as $c): 
                                                $entry = null;
                                                if(isset($existingTimetable[$c['id']][$day][$slot_time])) {
                                                    $entry = $existingTimetable[$c['id']][$day][$slot_time];
                                                }
                                            ?>
                                                <td class="edit-mode">
                                                    <?php if($is_break): ?>
                                                        Break Time
                                                    <?php else: ?>
                                                        <input type="hidden" name="timetable[<?php echo $c['id']; ?>][<?php echo $day; ?>][<?php echo $slot_time; ?>][class_id]" value="<?php echo $c['id']; ?>">
                                                        <input type="hidden" name="timetable[<?php echo $c['id']; ?>][<?php echo $day; ?>][<?php echo $slot_time; ?>][day_of_week]" value="<?php echo $day; ?>">
                                                        <input type="hidden" name="timetable[<?php echo $c['id']; ?>][<?php echo $day; ?>][<?php echo $slot_time; ?>][start_time]" value="<?php echo $slot_time; ?>">
                                                        <input type="hidden" name="timetable[<?php echo $c['id']; ?>][<?php echo $day; ?>][<?php echo $slot_time; ?>][end_time]" value="<?php echo date('H:i:s', strtotime($slot_time) + 2700); ?>">
                                                        
                                                        <select name="timetable[<?php echo $c['id']; ?>][<?php echo $day; ?>][<?php echo $slot_time; ?>][subject_id]" style="width:100%; margin-bottom:5px;">
                                                            <option value="">-- Subject --</option>
                                                            <?php foreach($subjects as $s): ?>
                                                                <option value="<?php echo $s['id']; ?>" <?php echo ($entry && $entry['subject_id'] == $s['id']) ? 'selected' : ''; ?>>
                                                                    <?php echo $s['subject_name']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        
                                                        <select name="timetable[<?php echo $c['id']; ?>][<?php echo $day; ?>][<?php echo $slot_time; ?>][teacher_id]" style="width:100%; margin-bottom:5px;">
                                                            <option value="">-- Teacher --</option>
                                                            <?php foreach($teachers as $t): ?>
                                                                <option value="<?php echo $t['id']; ?>" <?php echo ($entry && $entry['teacher_id'] == $t['id']) ? 'selected' : ''; ?>>
                                                                    <?php echo $t['full_name']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        
                                                        <input type="text" name="timetable[<?php echo $c['id']; ?>][<?php echo $day; ?>][<?php echo $slot_time; ?>][room_no]" placeholder="Room" value="<?php echo $entry ? $entry['room_no'] : ''; ?>" style="width:100%; margin-top:5px;">
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </div>
                            <div style="margin-top: 20px;">
                                <button type="submit" class="btn btn-success">Save Timetable</button>
                                <button type="button" class="btn" onclick="toggleEditMode()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleEditMode() {
            const viewMode = document.getElementById('viewMode');
            const editMode = document.getElementById('editMode');
            const editBtn = document.getElementById('editBtn');
            if (viewMode.style.display === 'none') {
                viewMode.style.display = 'block';
                editMode.style.display = 'none';
                if(editBtn) editBtn.textContent = 'Edit Timetable';
            } else {
                viewMode.style.display = 'none';
                editMode.style.display = 'block';
                if(editBtn) editBtn.textContent = 'View Timetable';
            }
        }
        
        function toggleTheme() { 
            document.body.classList.toggle('dark-theme'); 
            localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); 
        }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>