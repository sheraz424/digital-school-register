<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$user_role = $_SESSION['user_role'];

// Handle Add/Edit/Delete for Admin
if ($user_role === 'admin') {
    // Add timetable entry
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_timetable'])) {
        $class_id = $_POST['class_id'];
        $subject_id = $_POST['subject_id'];
        $teacher_id = $_POST['teacher_id'];
        $day_of_week = $_POST['day_of_week'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $room_no = $_POST['room_no'];
        
        $stmt = $pdo->prepare("INSERT INTO timetable (class_id, subject_id, teacher_id, day_of_week, start_time, end_time, room_no) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$class_id, $subject_id, $teacher_id, $day_of_week, $start_time, $end_time, $room_no]);
        $success = "Timetable entry added successfully!";
    }
    
    // Delete entry
    if (isset($_GET['delete'])) {
        $id = $_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM timetable WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: timetable.php');
        exit;
    }
}

// Get timetable entries
if ($user_role === 'admin') {
    $timetable = $pdo->query("
        SELECT t.*, c.class_name, s.subject_name, u.full_name as teacher_name
        FROM timetable t
        JOIN classes c ON t.class_id = c.id
        JOIN subjects s ON t.subject_id = s.id
        JOIN users u ON t.teacher_id = u.id
        ORDER BY t.day_of_week, t.start_time
    ")->fetchAll();
} else {
    // For teachers, show only their timetable
    $teacher_id = $_SESSION['user_id'];
    $timetable = $pdo->prepare("
        SELECT t.*, c.class_name, s.subject_name
        FROM timetable t
        JOIN classes c ON t.class_id = c.id
        JOIN subjects s ON t.subject_id = s.id
        WHERE t.teacher_id = ?
        ORDER BY t.day_of_week, t.start_time
    ");
    $timetable->execute([$teacher_id]);
    $timetable = $timetable->fetchAll();
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$day_map = [
    'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3,
    'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6
];

// Organize timetable by day and time
$organized = [];
foreach($timetable as $entry) {
    $organized[$entry['day_of_week']][] = $entry;
}

// Get data for admin forms
$classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY class_name")->fetchAll();
$subjects = $pdo->query("SELECT id, subject_name FROM subjects")->fetchAll();
$teachers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'teacher'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Timetable - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme {
            --bg: #1a1a2e;
            --card: #16213e;
            --text: #eeeeee;
            --border: #2c3e50;
        }
        body.dark-theme .container, body.dark-theme .card, body.dark-theme .timetable-table {
            background: var(--card);
            color: var(--text);
            border-color: var(--border);
        }
        body.dark-theme select, body.dark-theme input {
            background: #0f0f23;
            border-color: var(--border);
            color: var(--text);
        }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .timetable-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .timetable-table th, .timetable-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .timetable-table th { background: #2E86AB; color: white; text-align: center; }
        .timetable-cell { min-height: 80px; vertical-align: top; }
        .subject-name { font-weight: bold; color: #2E86AB; }
        .teacher-name { font-size: 12px; color: #666; }
        .room-no { font-size: 11px; color: #999; margin-top: 5px; }
        .btn { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin: 5px; }
        .btn-danger { background: #E74C3C; }
        .btn-small { padding: 4px 8px; font-size: 12px; margin: 2px; text-decoration: none; display: inline-block; border-radius: 4px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        select, input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
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
        @media (max-width: 768px) {
            .timetable-table { font-size: 12px; }
            .timetable-table th, .timetable-table td { padding: 6px; }
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn"> Back to Dashboard</a>
        <h1>Class Timetable</h1>
        
        <?php if(isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Add Timetable Entry - Admin Only -->
        <?php if($user_role === 'admin'): ?>
        <div class="card">
            <h2>Add Timetable Entry</h2>
            <form method="POST">
                <input type="hidden" name="add_timetable" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class_id" required>
                            <option value="">Select Class</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>">Class <?php echo $c['class_name'] . ($c['section'] ? '-' . $c['section'] : ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <select name="subject_id" required>
                            <option value="">Select Subject</option>
                            <?php foreach($subjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo $s['subject_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Teacher</label>
                        <select name="teacher_id" required>
                            <option value="">Select Teacher</option>
                            <?php foreach($teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo $t['full_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Day</label>
                        <select name="day_of_week" required>
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" required>
                    </div>
                    <div class="form-group">
                        <label>Room No</label>
                        <input type="text" name="room_no" placeholder="e.g., Room 101">
                    </div>
                </div>
                <button type="submit" class="btn">Add Timetable Entry</button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Timetable Display -->
        <div class="card">
            <h2>
                <?php if($user_role === 'admin'): ?>
                    All Classes Timetable
                <?php else: ?>
                    My Teaching Timetable
                <?php endif; ?>
            </h2>
            
            <?php if(empty($organized)): ?>
                <p>No timetable entries found.</p>
            <?php else: ?>
                <?php foreach($days as $day): ?>
                    <?php if(isset($organized[$day])): ?>
                    <div style="margin-bottom: 30px;">
                        <h3 style="background: #2E86AB; color: white; padding: 10px; border-radius: 8px;"><?php echo $day; ?></h3>
                        <table class="timetable-table">
                            <thead>
                                <tr><th>Time</th><th>Class</th><th>Subject</th><th>Teacher</th><th>Room</th><?php if($user_role === 'admin'): ?><th>Action</th><?php endif; ?></thead>
                            <tbody>
                                <?php 
                                usort($organized[$day], function($a, $b) {
                                    return strcmp($a['start_time'], $b['start_time']);
                                });
                                foreach($organized[$day] as $entry): 
                                ?>
                                <tr>
                                    <td><?php echo date('g:i A', strtotime($entry['start_time'])); ?> - <?php echo date('g:i A', strtotime($entry['end_time'])); ?></td>
                                    <td>Class <?php echo $entry['class_name'] . (isset($entry['section']) && $entry['section'] ? '-' . $entry['section'] : ''); ?></td>
                                    <td><span class="subject-name"><?php echo $entry['subject_name']; ?></span></td>
                                    <td><span class="teacher-name"><?php echo $entry['teacher_name'] ?? 'Teacher'; ?></span></td>
                                    <td><span class="room-no"><?php echo $entry['room_no'] ?: 'N/A'; ?></span></td>
                                    <?php if($user_role === 'admin'): ?>
                                    <td><a href="?delete=<?php echo $entry['id']; ?>" class="btn-small btn-danger" onclick="return confirm('Delete this timetable entry?')">Delete</a></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleTheme() {
            document.body.classList.toggle('dark-theme');
            localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light');
        }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>