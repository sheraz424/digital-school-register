<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'academic_officer') {
    header('Location: dashboard.php');
    exit;
}

$user_name = $_SESSION['user_name'];
$message = '';
$error = '';

// Handle Register Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_student'])) {
    $roll_no = $_POST['roll_no'];
    $name = $_POST['name'];
    $class_id = $_POST['class_id'];
    $parent_email = $_POST['parent_email'];
    $contact = $_POST['contact'];
    $optional_subject = $_POST['optional_subject'] ?? null;
    
    $student_email = strtolower($roll_no) . '@dsr.com';
    $default_password = 'student123';
    
    // Insert student
    $stmt = $pdo->prepare("INSERT INTO students (roll_no, name, class_id, email, parent_email, contact, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$roll_no, $name, $class_id, $student_email, $parent_email, $contact, $default_password]);
    $student_id = $pdo->lastInsertId();
    
    // Create user account
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'student')");
    $stmt->execute([$roll_no, $student_email, $default_password, $name]);
    $user_id = $pdo->lastInsertId();
    
    // Link student to user
    $stmt = $pdo->prepare("UPDATE students SET user_id = ? WHERE id = ?");
    $stmt->execute([$user_id, $student_id]);
    
    // Save optional subject for 9th/10th
    if ($optional_subject) {
        $stmt = $pdo->prepare("INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)");
        $stmt->execute([$student_id, $optional_subject]);
    }
    
    $message = "Student registered successfully! Email: $student_email | Password: $default_password";
}

// Handle Generate Datesheet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_datesheet'])) {
    $class_id = $_POST['class_id'];
    $exam_start_date = $_POST['exam_start_date'];
    
    // Delete existing datesheet for this class
    $stmt = $pdo->prepare("DELETE FROM datesheet WHERE class_id = ?");
    $stmt->execute([$class_id]);
    
    // Get subjects for this class
    $stmt = $pdo->prepare("
        SELECT cs.*, s.subject_name 
        FROM class_subjects cs
        JOIN subjects s ON cs.subject_id = s.id
        WHERE cs.class_id = ?
    ");
    $stmt->execute([$class_id]);
    $subjects = $stmt->fetchAll();
    
    $current_date = strtotime($exam_start_date);
    $count = 0;
    
    foreach($subjects as $subject) {
        // Skip weekends (Saturday and Sunday)
        while (date('N', $current_date) >= 6) {
            $current_date = strtotime('+1 day', $current_date);
        }
        
        $room_no = 'Room ' . (101 + $count);
        $stmt = $pdo->prepare("INSERT INTO datesheet (class_id, subject_id, exam_date, start_time, end_time, room_no, created_by) VALUES (?, ?, ?, '09:00:00', '12:00:00', ?, ?)");
        $stmt->execute([$class_id, $subject['subject_id'], date('Y-m-d', $current_date), $room_no, $_SESSION['user_id']]);
        
        $current_date = strtotime('+1 day', $current_date);
        $count++;
    }
    
    $message = "Datesheet generated for " . count($subjects) . " subjects!";
}

// Get statistics
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$upcomingExams = $pdo->query("SELECT COUNT(*) FROM datesheet WHERE exam_date >= CURDATE()")->fetchColumn();

$classes = $pdo->query("SELECT * FROM classes ORDER BY FIELD(class_name, 'Nursery','Prep','1','2','3','4','5','6','7','8','9','10'), section")->fetchAll();
$optionalSubjects = $pdo->query("SELECT * FROM subjects WHERE subject_name IN ('Computer Science', 'Biology')")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Officer - DSR</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        body.dark-theme .card { background: var(--card); border-color: var(--border); }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #ddd; }
        .stat-value { font-size: 28px; font-weight: bold; color: #2E86AB; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .btn { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin-top: 10px; }
        .btn-success { background: #28a745; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>Academic Officer Dashboard</h1>
        
        <?php if($message): ?>
        <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo $totalStudents; ?></div><div>Total Students</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $totalClasses; ?></div><div>Total Classes</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $upcomingExams; ?></div><div>Upcoming Exams</div></div>
        </div>
        
        <!-- Register Student -->
        <div class="card">
            <h2>Register New Student</h2>
            <form method="POST">
                <input type="hidden" name="register_student" value="1">
                <div class="form-row">
                    <div class="form-group"><label>Roll Number</label><input type="text" name="roll_no" required></div>
                    <div class="form-group"><label>Full Name</label><input type="text" name="name" required></div>
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class_id" id="class_select" required>
                            <option value="">Select Class</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" data-class="<?php echo $c['class_name']; ?>">
                                    <?php echo $c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="optional_div" style="display:none;">
                        <label>Optional Subject (9th/10th only)</label>
                        <select name="optional_subject">
                            <option value="">Select Optional Subject</option>
                            <?php foreach($optionalSubjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo $s['subject_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Parent Email</label><input type="email" name="parent_email" placeholder="parent@example.com"></div>
                    <div class="form-group"><label>Contact Number</label><input type="text" name="contact"></div>
                </div>
                <button type="submit" class="btn btn-success">Register Student</button>
            </form>
        </div>
        
        <!-- Generate Datesheet -->
        <div class="card">
            <h2>Generate Exam Datesheet</h2>
            <form method="POST">
                <input type="hidden" name="generate_datesheet" value="1">
                <div class="form-row">
                    <div class="form-group"><label>Select Class</label>
                        <select name="class_id" required>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo $c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Exam Start Date</label><input type="date" name="exam_start_date" required></div>
                </div>
                <button type="submit" class="btn">Generate Datesheet</button>
            </form>
        </div>
        
        <!-- Datesheet View -->
        <div class="card">
            <h2>Upcoming Exams</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>Class</th><th>Subject</th><th>Exam Date</th><th>Time</th><th>Room</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $datesheets = $pdo->query("
                            SELECT d.*, c.class_name, c.section, s.subject_name 
                            FROM datesheet d
                            JOIN classes c ON d.class_id = c.id
                            JOIN subjects s ON d.subject_id = s.id
                            WHERE d.exam_date >= CURDATE()
                            ORDER BY d.exam_date ASC
                            LIMIT 20
                        ")->fetchAll();
                        foreach($datesheets as $d):
                        ?>
                        <tr>
                            <td><?php echo $d['class_name'] . ($d['section'] ? ' - ' . $d['section'] : ''); ?></td>
                            <td><?php echo $d['subject_name']; ?></td>
                            <td><?php echo date('d M Y', strtotime($d['exam_date'])); ?></td>
                            <td><?php echo date('g:i A', strtotime($d['start_time'])); ?> - <?php echo date('g:i A', strtotime($d['end_time'])); ?></td>
                            <td><?php echo $d['room_no']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('class_select')?.addEventListener('change', function() {
            const className = this.options[this.selectedIndex]?.getAttribute('data-class');
            const optionalDiv = document.getElementById('optional_div');
            optionalDiv.style.display = (className === '9' || className === '10') ? 'block' : 'none';
        });
        function toggleTheme() { 
            document.body.classList.toggle('dark-theme'); 
            localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); 
        }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>