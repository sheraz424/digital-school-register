<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$user_role = $_SESSION['user_role'];

// Allow both admin and principal
if ($user_role !== 'admin' && $user_role !== 'principal') {
    header('Location: dashboard.php');
    exit;
}

$class_id = $_GET['class_id'] ?? 0;
$exam_type = $_GET['exam_type'] ?? 'Mid Term';

// Get all classes
$classes = $pdo->query("
    SELECT id, class_name, section 
    FROM classes 
    ORDER BY FIELD(class_name, 'Nursery','Prep','1','2','3','4','5','6','7','8','9','10'), section
")->fetchAll();

// Get exam types from database
$examTypes = $pdo->query("SELECT DISTINCT exam_type FROM results WHERE exam_type IS NOT NULL")->fetchAll();
if (empty($examTypes)) {
    $examTypes = [['exam_type' => 'Mid Term'], ['exam_type' => 'Final Term'], ['exam_type' => 'Test']];
}

// Get results
$studentsResults = [];
$classInfo = null;

if ($class_id) {
    // Get class info
    $stmt = $pdo->prepare("SELECT class_name, section FROM classes WHERE id = ?");
    $stmt->execute([$class_id]);
    $classInfo = $stmt->fetch();
    
    // Get results
    $stmt = $pdo->prepare("
        SELECT r.*, s.name as student_name, s.roll_no, sub.subject_name
        FROM results r
        JOIN students s ON r.student_id = s.id
        JOIN subjects sub ON r.subject_id = sub.id
        WHERE r.class_id = ? AND r.exam_type = ?
        ORDER BY s.roll_no, sub.subject_name
    ");
    $stmt->execute([$class_id, $exam_type]);
    $results = $stmt->fetchAll();
    
    // Group by student
    foreach($results as $result) {
        if (!isset($studentsResults[$result['student_id']])) {
            $studentsResults[$result['student_id']] = [
                'name' => $result['student_name'],
                'roll_no' => $result['roll_no'],
                'subjects' => []
            ];
        }
        $studentsResults[$result['student_id']]['subjects'][$result['subject_name']] = [
            'marks' => $result['marks_obtained'],
            'total' => $result['total_marks'],
            'percentage' => $result['percentage'],
            'grade' => $result['grade']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Results - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        body.dark-theme .card { background: var(--card); border-color: var(--border); }
        .btn { background: #2E86AB; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        .filter-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 20px; }
        .filter-group { flex: 1; min-width: 150px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 12px; }
        select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; }
        .result-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .result-table th, .result-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .result-table th { background: #2E86AB; color: white; }
        .student-card { background: var(--bg); border-radius: 12px; padding: 15px; margin-bottom: 20px; }
        .student-header { font-weight: bold; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 2px solid var(--accent); }
        .grade-A { color: #28a745; font-weight: bold; }
        .grade-B { color: #17a2b8; font-weight: bold; }
        .grade-C { color: #ffc107; font-weight: bold; }
        .grade-D, .grade-F { color: #dc3545; font-weight: bold; }
        .no-data { text-align: center; padding: 40px; color: var(--muted); }
        .search-box { width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="<?php echo ($user_role === 'principal') ? 'principal_dashboard.php' : 'dashboard.php'; ?>" class="back-btn">← Back to Dashboard</a>
        <h1>Student Results</h1>
        
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
                        <label>Exam Type</label>
                        <select name="exam_type">
                            <?php foreach($examTypes as $et): ?>
                                <option value="<?php echo $et['exam_type']; ?>" <?php echo ($exam_type == $et['exam_type']) ? 'selected' : ''; ?>>
                                    <?php echo $et['exam_type']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn">View Results</button>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if($class_id && !empty($studentsResults)): ?>
        <div class="card">
            <h3>
                <?php echo $exam_type; ?> Results - 
                <?php echo $classInfo['class_name']; ?> 
                <?php echo $classInfo['section'] ? ' - ' . $classInfo['section'] : ''; ?>
            </h3>
            <input type="text" id="searchStudent" class="search-box" placeholder="Search by student name or roll number...">
            
            <?php foreach($studentsResults as $studentId => $student): 
                $totalMarks = 0;
                $obtainedMarks = 0;
                $subjectCount = 0;
                foreach($student['subjects'] as $subject => $data) {
                    $totalMarks += $data['total'];
                    $obtainedMarks += $data['marks'];
                    $subjectCount++;
                }
                $overallPercent = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100) : 0;
                $overallGrade = $overallPercent >= 80 ? 'A' : ($overallPercent >= 70 ? 'B' : ($overallPercent >= 60 ? 'C' : ($overallPercent >= 50 ? 'D' : 'F')));
                $gradeClass = 'grade-' . $overallGrade;
            ?>
            <div class="student-card" data-name="<?php echo strtolower($student['name']); ?>" data-roll="<?php echo $student['roll_no']; ?>">
                <div class="student-header">
                    <?php echo $student['name']; ?> (Roll No: <?php echo $student['roll_no']; ?>)
                    <span style="float: right;">Overall: <?php echo $overallPercent; ?>% | Grade: <span class="<?php echo $gradeClass; ?>"><?php echo $overallGrade; ?></span></span>
                </div>
                <table class="result-table">
                    <thead>
                        <tr><th>Subject</th><th>Marks Obtained</th><th>Total Marks</th><th>Percentage</th><th>Grade</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($student['subjects'] as $subject => $data): 
                            $gradeClass = 'grade-' . $data['grade'];
                        ?>
                        <tr>
                            <td><?php echo $subject; ?></td>
                            <td><?php echo $data['marks']; ?></td>
                            <td><?php echo $data['total']; ?></td>
                            <td><?php echo $data['percentage']; ?>%</td>
                            <td class="<?php echo $gradeClass; ?>"><?php echo $data['grade']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th><?php echo $obtainedMarks; ?></th>
                            <th><?php echo $totalMarks; ?></th>
                            <th><?php echo $overallPercent; ?>%</th>
                            <th class="<?php echo $gradeClass; ?>"><?php echo $overallGrade; ?></th>
                        </table>
                    </tfoot>
                </table>
            </div>
            <?php endforeach; ?>
        </div>
        <?php elseif($class_id): ?>
        <div class="card">
            <div class="no-data">
                <p>No results found for this class and exam type.</p>
                <p>Please ensure results have been entered by teachers.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Search functionality
        document.getElementById('searchStudent')?.addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let students = document.querySelectorAll('.student-card');
            students.forEach(student => {
                let name = student.getAttribute('data-name') || '';
                let roll = student.getAttribute('data-roll') || '';
                if (name.includes(filter) || roll.includes(filter)) {
                    student.style.display = 'block';
                } else {
                    student.style.display = 'none';
                }
            });
        });
        
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>