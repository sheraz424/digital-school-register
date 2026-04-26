<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'student') {
    header('Location: dashboard.php');
    exit;
}

$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Get student info
$stmt = $pdo->prepare("
    SELECT s.*, c.class_name, c.section 
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.name = ? OR s.email = ?
    LIMIT 1
");
$stmt->execute([$user_name, $user_email]);
$student = $stmt->fetch();

// Sample grades data
$subjects = [
    'Mathematics' => ['grade' => 'A', 'marks' => 85, 'status' => 'Excellent'],
    'Physics' => ['grade' => 'B+', 'marks' => 78, 'status' => 'Good'],
    'Chemistry' => ['grade' => 'A-', 'marks' => 82, 'status' => 'Very Good'],
    'Computer Science' => ['grade' => 'A+', 'marks' => 92, 'status' => 'Outstanding'],
    'English' => ['grade' => 'B', 'marks' => 74, 'status' => 'Satisfactory'],
    'Urdu' => ['grade' => 'A', 'marks' => 88, 'status' => 'Excellent']
];

$totalMarks = array_sum(array_column($subjects, 'marks'));
$percentage = round($totalMarks / count($subjects));
$overallGrade = $percentage >= 85 ? 'A' : ($percentage >= 75 ? 'B' : ($percentage >= 60 ? 'C' : 'D'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Grades - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; max-width: 1000px; margin: 0 auto; }
        .grade-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .summary-item { text-align: center; padding: 20px; background: var(--bg); border-radius: 12px; }
        .summary-value { font-size: 36px; font-weight: bold; color: var(--accent); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: var(--bg); }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: var(--accent); color: white; text-decoration: none; border-radius: 8px; }
        .grade-A, .grade-Aplus { color: green; font-weight: bold; }
        .grade-B, .grade-Bplus { color: #8B8000; font-weight: bold; }
        .grade-C { color: orange; font-weight: bold; }
        .grade-D, .grade-F { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>My Grades</h1>
        
        <?php if($student): ?>
        <div class="grade-card">
            <h3><?php echo htmlspecialchars($student['name']); ?></h3>
            <p>Class: <?php echo $student['class_name'] . ($student['section'] ? '-' . $student['section'] : ''); ?> | Roll No: <?php echo $student['roll_no']; ?></p>
        </div>
        <?php endif; ?>
        
        <div class="summary">
            <div class="summary-item">
                <div class="summary-value"><?php echo $percentage; ?>%</div>
                <div>Overall Percentage</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $overallGrade; ?></div>
                <div>Overall Grade</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $totalMarks; ?>/<?php echo count($subjects) * 100; ?></div>
                <div>Total Marks</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo count($subjects); ?></div>
                <div>Subjects</div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr><th>Subject</th><th>Marks</th><th>Grade</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach($subjects as $subject => $data): 
                    $gradeClass = '';
                    if ($data['grade'] == 'A' || $data['grade'] == 'A+') $gradeClass = 'grade-A';
                    elseif ($data['grade'] == 'B' || $data['grade'] == 'B+') $gradeClass = 'grade-B';
                    elseif ($data['grade'] == 'C') $gradeClass = 'grade-C';
                    else $gradeClass = 'grade-D';
                ?>
                <tr>
                    <td><?php echo $subject; ?></td>
                    <td><?php echo $data['marks']; ?>/100</td>
                    <td class="<?php echo $gradeClass; ?>"><?php echo $data['grade']; ?></td>
                    <td><?php echo $data['status']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="grade-card" style="margin-top: 20px; background: #e8f4fd;">
            <h4>Grade Scale</h4>
            <p>A+ (90-100) | A (80-89) | B+ (75-79) | B (70-74) | C (60-69) | D (50-59) | F (Below 50)</p>
            <p><strong>Remarks:</strong> Keep up the good work! Your performance is satisfactory.</p>
        </div>
    </div>
</body>
</html>