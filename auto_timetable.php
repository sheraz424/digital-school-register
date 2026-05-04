<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message = '';

// Matric subjects by class level
$subjectMapping = [
    'Nursery' => ['English', 'Urdu', 'Mathematics', 'General Science'],
    'Prep' => ['English', 'Urdu', 'Mathematics', 'General Science'],
    '1' => ['English', 'Urdu', 'Mathematics', 'Islamiat', 'General Science'],
    '2' => ['English', 'Urdu', 'Mathematics', 'Islamiat', 'General Science'],
    '3' => ['English', 'Urdu', 'Mathematics', 'Islamiat', 'General Science'],
    '4' => ['English', 'Urdu', 'Mathematics', 'Islamiat', 'General Science'],
    '5' => ['English', 'Urdu', 'Mathematics', 'Islamiat', 'General Science'],
    '6' => ['English', 'Urdu', 'Mathematics', 'Islamiat', 'General Science', 'Pakistan Studies', 'Computer Science'],
    '7' => ['English', 'Urdu', 'Mathematics', 'Islamiat', 'General Science', 'Pakistan Studies', 'Computer Science'],
    '8' => ['English', 'Urdu', 'Mathematics', 'Islamiat', 'General Science', 'Pakistan Studies', 'Computer Science'],
    '9_Science' => ['English', 'Urdu', 'Mathematics', 'Islamiat', 'Pakistan Studies', 'Physics', 'Chemistry', 'Biology'],
    '9_Arts' => ['English', 'Urdu', 'Mathematics', 'Islamiat', 'Pakistan Studies', 'History', 'Geography', 'Economics'],
    '10_Science' => ['English', 'Urdu', 'Mathematics', 'Islamiat', 'Pakistan Studies', 'Physics', 'Chemistry', 'Biology'],
    '10_Arts' => ['English', 'Urdu', 'Mathematics', 'Islamiat', 'Pakistan Studies', 'History', 'Geography', 'Economics'],
];

if (isset($_POST['generate'])) {
    // Clear existing timetable
    $pdo->exec("DELETE FROM timetable");
    
    // Get all teachers
    $teachers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'teacher' LIMIT 10")->fetchAll();
    $default_teacher_id = !empty($teachers) ? $teachers[0]['id'] : 1;
    
    // Days of week
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    
    // Time slots (45 minutes each)
    $time_slots = [
        '08:00:00' => '08:45:00',
        '08:45:00' => '09:30:00',
        '09:30:00' => '10:15:00',
        '10:15:00' => '10:30:00', // Break
        '10:30:00' => '11:15:00',
        '11:15:00' => '12:00:00',
        '12:00:00' => '12:45:00',
        '12:45:00' => '13:30:00',
    ];
    
    // Get all classes
    $classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY FIELD(class_name, 'Nursery','Prep','1','2','3','4','5','6','7','8','9','10'), section")->fetchAll();
    
    $insertCount = 0;
    
    foreach($classes as $class) {
        $classKey = $class['class_name'] . '_' . $class['section'];
        if ($class['section'] == '') {
            $classKey = $class['class_name'];
        }
        
        // Get subjects for this class
        $subjects = [];
        if (isset($subjectMapping[$classKey])) {
            $subjects = $subjectMapping[$classKey];
        } else {
            // Try to get from database
            $stmt = $pdo->prepare("
                SELECT s.subject_name FROM class_subjects cs
                JOIN subjects s ON cs.subject_id = s.id
                WHERE cs.class_id = ?
            ");
            $stmt->execute([$class['id']]);
            $dbSubjects = $stmt->fetchAll();
            foreach($dbSubjects as $s) {
                $subjects[] = $s['subject_name'];
            }
        }
        
        if (empty($subjects)) {
            continue;
        }
        
        // Get subject IDs
        $subjectIds = [];
        foreach($subjects as $subjName) {
            $stmt = $pdo->prepare("SELECT id FROM subjects WHERE subject_name = ?");
            $stmt->execute([$subjName]);
            $subject = $stmt->fetch();
            if ($subject) {
                $subjectIds[$subjName] = $subject['id'];
            }
        }
        
        // Assign subjects to time slots across week (each subject appears multiple times per week)
        $slotIndex = 0;
        $totalSubjects = count($subjects);
        
        foreach($days as $day) {
            $slotIndex = 0;
            foreach($time_slots as $start_time => $end_time) {
                // Skip break time
                if ($start_time == '10:15:00') {
                    continue;
                }
                
                // Get subject for this slot (rotate through subjects)
                $subjectIndex = ($slotIndex % $totalSubjects);
                $subjectName = $subjects[$subjectIndex];
                $subjectId = $subjectIds[$subjectName] ?? 1;
                
                // Assign teacher
                $teacherName = '';
                if (strpos($subjectName, 'English') !== false) $teacherName = 'Ms. English';
                elseif (strpos($subjectName, 'Urdu') !== false) $teacherName = 'Ms. Urdu';
                elseif (strpos($subjectName, 'Mathematics') !== false) $teacherName = 'Mr. Maths';
                elseif (strpos($subjectName, 'Islamiat') !== false) $teacherName = 'Muft. Islamiat';
                elseif (strpos($subjectName, 'Science') !== false) $teacherName = 'Ms. Science';
                elseif (strpos($subjectName, 'Physics') !== false) $teacherName = 'Dr. Physics';
                elseif (strpos($subjectName, 'Chemistry') !== false) $teacherName = 'Dr. Chemistry';
                elseif (strpos($subjectName, 'Biology') !== false) $teacherName = 'Ms. Biology';
                elseif (strpos($subjectName, 'Computer') !== false) $teacherName = 'Mr. CS';
                elseif (strpos($subjectName, 'Pakistan Studies') !== false) $teacherName = 'Prof. History';
                elseif (strpos($subjectName, 'History') !== false) $teacherName = 'Prof. History';
                elseif (strpos($subjectName, 'Geography') !== false) $teacherName = 'Mr. Geography';
                elseif (strpos($subjectName, 'Economics') !== false) $teacherName = 'Ms. Economics';
                else $teacherName = 'Teacher';
                
                // Find or create teacher
                $teacherStmt = $pdo->prepare("SELECT id FROM users WHERE full_name = ? AND role = 'teacher'");
                $teacherStmt->execute([$teacherName]);
                $teacher = $teacherStmt->fetch();
                if ($teacher) {
                    $teacherId = $teacher['id'];
                } else {
                    $teacherId = $default_teacher_id;
                }
                
                $room_no = 'Room ' . (101 + $slotIndex);
                
                $stmt = $pdo->prepare("INSERT INTO timetable (class_id, subject_id, teacher_id, day_of_week, start_time, end_time, room_no) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$class['id'], $subjectId, $teacherId, $day, $start_time, $end_time, $room_no]);
                $insertCount++;
                
                $slotIndex++;
            }
        }
    }
    
    $message = "Timetable generated successfully! Total $insertCount entries created for all classes.";
}

$timetableCount = $pdo->query("SELECT COUNT(*) FROM timetable")->fetchColumn();
$classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY FIELD(class_name, 'Nursery','Prep','1','2','3','4','5','6','7','8','9','10'), section")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auto Timetable - Admin</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 1000px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        body.dark-theme .card { background: var(--card); border-color: var(--border); }
        .btn { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-success { background: #28a745; }
        .btn-secondary { background: #6c757d; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .subjects-list { font-size: 12px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>Automatic Timetable Generator</h1>
        
        <?php if($message): ?>
        <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h3>Current Timetable Status</h3>
            <p>Total entries in timetable: <strong><?php echo $timetableCount; ?></strong></p>
            <p>This will generate a complete weekly timetable for all classes with proper subject rotation.</p>
            
            <h4>Subjects by Class Level:</h4>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>Class Level</th><th>Subjects</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Nursery - Prep</td><td>English, Urdu, Mathematics, General Science</td></tr>
                        <tr><td>Class 1 - 5</td><td>English, Urdu, Mathematics, Islamiat, General Science</td></tr>
                        <tr><td>Class 6 - 8</td><td>English, Urdu, Mathematics, Islamiat, General Science, Pakistan Studies, Computer Science</td></tr>
                        <tr><td>Class 9 - 10 Science</td><td>English, Urdu, Mathematics, Islamiat, Pakistan Studies, Physics, Chemistry, Biology</td></tr>
                        <tr><td>Class 9 - 10 Arts</td><td>English, Urdu, Mathematics, Islamiat, Pakistan Studies, History, Geography, Economics</td></tr>
                    </tbody>
                </table>
            </div>
            
            <form method="POST" style="margin-top: 20px;">
                <button type="submit" name="generate" class="btn btn-success">Generate Full Timetable</button>
                <a href="timetable.php" class="btn btn-secondary">View Timetable</a>
            </form>
        </div>
    </div>
    
    <script>
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>