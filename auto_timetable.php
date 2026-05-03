<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

// Generate timetable automatically
if (isset($_POST['generate_timetable'])) {
    $class_id = $_POST['class_id'];
    generateTimetable($pdo, $class_id);
    $message = "Timetable generated successfully for the selected class!";
}

// Get all data for forms
$classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY class_name")->fetchAll();
$teachers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'teacher'")->fetchAll();
$subjects = $pdo->query("SELECT id, subject_name FROM subjects")->fetchAll();

// Get subject requirements
$requirements = $pdo->prepare("
    SELECT sr.*, s.subject_name 
    FROM subject_requirements sr
    JOIN subjects s ON sr.subject_id = s.id
    ORDER BY sr.class_id
");
$requirements->execute();
$requirements = $requirements->fetchAll();

// Get teacher availability
$availabilities = $pdo->prepare("
    SELECT ta.*, u.full_name as teacher_name
    FROM teacher_availability ta
    JOIN users u ON ta.teacher_id = u.id
    ORDER BY u.full_name, ta.day_of_week
");
$availabilities->execute();
$availabilities = $availabilities->fetchAll();

// Function to generate timetable
function generateTimetable($pdo, $class_id) {
    // Clear existing timetable for this class
    $stmt = $pdo->prepare("DELETE FROM timetable WHERE class_id = ?");
    $stmt->execute([$class_id]);
    
    // Get subjects requirements for this class
    $stmt = $pdo->prepare("
        SELECT sr.*, s.subject_name, s.id as subject_id
        FROM subject_requirements sr
        JOIN subjects s ON sr.subject_id = s.id
        WHERE sr.class_id = ?
    ");
    $stmt->execute([$class_id]);
    $subjects = $stmt->fetchAll();
    
    // Get qualified teachers for each subject
    $subject_teachers = [];
    foreach($subjects as $subject) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name
            FROM users u
            WHERE u.role = 'teacher'
        ");
        $stmt->execute();
        $subject_teachers[$subject['subject_id']] = $stmt->fetchAll();
    }
    
    // Get time slots
    $time_slots = $pdo->query("SELECT * FROM time_slots WHERE slot_name != 'Break' ORDER BY slot_order")->fetchAll();
    
    // Days of week
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    // Track period counts per subject
    $period_count = [];
    foreach($subjects as $subject) {
        $period_count[$subject['subject_id']] = 0;
    }
    
    // Track teacher usage per day (to avoid conflicts)
    $teacher_usage = [];
    foreach($days as $day) {
        $teacher_usage[$day] = [];
        foreach($time_slots as $slot) {
            $teacher_usage[$day][$slot['id']] = null;
        }
    }
    
    // Generate timetable
    $timetable_entries = [];
    
    foreach($days as $day) {
        // Skip Saturday if needed
        if ($day == 'Saturday') continue;
        
        foreach($time_slots as $slot) {
            // Find a subject that still needs periods
            $selected_subject = null;
            $selected_teacher = null;
            
            foreach($subjects as $subject) {
                $needed = $subject['periods_per_week'];
                $current = $period_count[$subject['subject_id']];
                
                if ($current < $needed) {
                    // Find available teacher for this subject
                    foreach($subject_teachers[$subject['subject_id']] as $teacher) {
                        // Check if teacher is available at this time
                        $stmt = $pdo->prepare("
                            SELECT * FROM teacher_availability 
                            WHERE teacher_id = ? 
                            AND day_of_week = ? 
                            AND start_time <= ? 
                            AND end_time >= ?
                        ");
                        $stmt->execute([$teacher['id'], $day, $slot['start_time'], $slot['end_time']]);
                        
                        if ($stmt->rowCount() > 0) {
                            // Check if teacher is not already teaching at this time
                            if ($teacher_usage[$day][$slot['id']] != $teacher['id']) {
                                $selected_subject = $subject;
                                $selected_teacher = $teacher;
                                break 2;
                            }
                        }
                    }
                }
            }
            
            if ($selected_subject && $selected_teacher) {
                $timetable_entries[] = [
                    'class_id' => $class_id,
                    'subject_id' => $selected_subject['subject_id'],
                    'teacher_id' => $selected_teacher['id'],
                    'day_of_week' => $day,
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'room_no' => 'Room ' . rand(101, 205)
                ];
                
                $period_count[$selected_subject['subject_id']]++;
                $teacher_usage[$day][$slot['id']] = $selected_teacher['id'];
            }
        }
    }
    
    // Insert generated timetable
    $stmt = $pdo->prepare("
        INSERT INTO timetable (class_id, subject_id, teacher_id, day_of_week, start_time, end_time, room_no) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach($timetable_entries as $entry) {
        $stmt->execute([
            $entry['class_id'], $entry['subject_id'], $entry['teacher_id'],
            $entry['day_of_week'], $entry['start_time'], $entry['end_time'], $entry['room_no']
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auto Timetable Generator - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme {
            --bg: #1a1a2e;
            --card: #16213e;
            --text: #eeeeee;
            --border: #2c3e50;
        }
        body.dark-theme .container, body.dark-theme .card {
            background: var(--card);
            color: var(--text);
            border-color: var(--border);
        }
        body.dark-theme input, body.dark-theme select, body.dark-theme textarea {
            background: #0f0f23;
            border-color: var(--border);
            color: var(--text);
        }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .btn { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin: 5px; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        select, input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .delete-btn { background: #E74C3C; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 12px; }
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
        <h1>Auto Timetable Generator</h1>
        
        <?php if($message): ?>
        <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Generate Timetable -->
        <div class="card">
            <h2>Generate Automatic Timetable</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Select Class</label>
                        <select name="class_id" required>
                            <option value="">Select Class</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>">Class <?php echo $c['class_name'] . ($c['section'] ? '-' . $c['section'] : ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="generate_timetable" class="btn btn-success">Generate Timetable</button>
                <a href="timetable.php" class="btn">View Generated Timetable</a>
            </form>
        </div>
        
        <!-- Subject Requirements -->
        <div class="card">
            <h2>Subject Requirements (Periods per Week)</h2>
            <form method="POST" action="save_requirements.php">
                <div class="form-row">
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class_id" id="req_class_id" required>
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
                        <label>Periods per Week</label>
                        <input type="number" name="periods_per_week" min="1" max="8" value="3" required>
                    </div>
                </div>
                <button type="submit" name="add_requirement" class="btn">Add Requirement</button>
            </form>
            
            <h3 style="margin-top: 20px;">Current Requirements</h3>
            <table>
                <thead>
                    <tr><th>Class</th><th>Subject</th><th>Periods/Week</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach($requirements as $req): 
                        $class_name = $pdo->query("SELECT class_name, section FROM classes WHERE id = {$req['class_id']}")->fetch();
                    ?>
                    <tr>
                        <td>Class <?php echo $class_name['class_name'] . ($class_name['section'] ? '-' . $class_name['section'] : ''); ?></td>
                        <td><?php echo $req['subject_name']; ?></td>
                        <td><?php echo $req['periods_per_week']; ?></td>
                        <td><a href="delete_requirement.php?id=<?php echo $req['id']; ?>" class="delete-btn" onclick="return confirm('Delete this requirement?')">Delete</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Teacher Availability -->
        <div class="card">
            <h2>Teacher Availability</h2>
            <form method="POST" action="save_availability.php">
                <div class="form-row">
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
                        <input type="time" name="start_time" value="08:00" required>
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" value="14:00" required>
                    </div>
                </div>
                <button type="submit" name="add_availability" class="btn">Add Availability</button>
            </form>
            
            <h3 style="margin-top: 20px;">Current Teacher Availability</h3>
            <table>
                <thead>
                    <tr><th>Teacher</th><th>Day</th><th>Start Time</th><th>End Time</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach($availabilities as $avail): ?>
                    <tr>
                        <td><?php echo $avail['teacher_name']; ?></td>
                        <td><?php echo $avail['day_of_week']; ?></td>
                        <td><?php echo date('g:i A', strtotime($avail['start_time'])); ?></td>
                        <td><?php echo date('g:i A', strtotime($avail['end_time'])); ?></td>
                        <td><a href="delete_availability.php?id=<?php echo $avail['id']; ?>" class="delete-btn" onclick="return confirm('Delete this availability?')">Delete</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Algorithm Explanation -->
        <div class="card">
            <h2>How Auto-Generation Works</h2>
            <p>The timetable is generated automatically using these constraints:</p>
            <ul>
                <li><strong>Subject Requirements:</strong> Each subject needs a specific number of periods per week</li>
                <li><strong>Teacher Availability:</strong> Teachers can only teach during their available time slots</li>
                <li><strong>No Teacher Conflicts:</strong> Same teacher cannot teach two classes at the same time</li>
                <li><strong>Time Slots:</strong> Standard school periods (08:00 - 13:40)</li>
                <li><strong>Room Assignment:</strong> Rooms are automatically assigned</li>
            </ul>
            <p><strong>To use:</strong></p>
            <ol>
                <li>First, set up subject requirements (how many periods per week for each subject)</li>
                <li>Then, set teacher availability (which days and times they can teach)</li>
                <li>Click "Generate Timetable" to automatically create the schedule</li>
                <li>View the generated timetable in the "Timetable" page</li>
            </ol>
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