<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'teacher') {
    header('Location: dashboard.php');
    exit;
}

// Get all students with their classes
$students = $pdo->query("
    SELECT s.*, c.class_name, c.section 
    FROM students s
    JOIN classes c ON s.class_id = c.id
    ORDER BY c.class_name, s.roll_no
")->fetchAll();

// Group students by class
$studentsByClass = [];
foreach ($students as $student) {
    $className = $student['class_name'] . ($student['section'] ? '-' . $student['section'] : '');
    if (!isset($studentsByClass[$className])) {
        $studentsByClass[$className] = [];
    }
    $studentsByClass[$className][] = $student;
}

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $student_id = $_POST['student_id'];
    $subject = $_POST['subject'];
    $grade = $_POST['grade'];
    $marks = $_POST['marks'];
    
    // Store in session for demo (in production, save to database)
    if (!isset($_SESSION['grades'])) {
        $_SESSION['grades'] = [];
    }
    $_SESSION['grades'][$student_id][$subject] = ['grade' => $grade, 'marks' => $marks];
    
    $success = "Grades saved successfully!";
}

// Get saved grades from session
$savedGrades = $_SESSION['grades'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter Grades - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .class-section { margin-bottom: 30px; background: white; border-radius: 16px; padding: 20px; border: 1px solid #ddd; }
        .class-title { font-size: 20px; font-weight: bold; margin-bottom: 15px; color: var(--accent); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: var(--bg); }
        .grade-select, .marks-input { padding: 6px; border-radius: 6px; border: 1px solid #ddd; }
        .save-btn { background: var(--teal); color: white; border: none; padding: 5px 15px; border-radius: 6px; cursor: pointer; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: var(--accent); color: white; text-decoration: none; border-radius: 8px; }
        .success-msg { background: #d4edda; color: #155724; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
        .subjects { display: flex; gap: 10px; margin: 10px 0; }
        .subject-tab { padding: 5px 15px; background: var(--bg); border-radius: 20px; cursor: pointer; }
        .subject-tab.active { background: var(--accent); color: white; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>Enter Student Grades</h1>
        
        <?php if(isset($success)): ?>
        <div class="success-msg"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <p>Select a subject and enter grades for each student.</p>
        
        <div class="subjects">
            <?php 
            $subjects = ['Mathematics', 'Physics', 'Chemistry', 'Computer Science', 'English', 'Urdu'];
            foreach($subjects as $subj): 
            ?>
            <div class="subject-tab" onclick="selectSubject('<?php echo $subj; ?>')"><?php echo $subj; ?></div>
            <?php endforeach; ?>
        </div>
        
        <input type="hidden" id="selectedSubject" value="Mathematics">
        
        <?php foreach($studentsByClass as $className => $classStudents): ?>
        <div class="class-section">
            <div class="class-title">Class <?php echo $className; ?></div>
            <form method="POST">
                <table>
                    <thead>
                        <tr><th>Roll No</th><th>Student Name</th><th>Grade</th><th>Marks (out of 100)</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($classStudents as $student): 
                            $existingGrade = $savedGrades[$student['id']][$_POST['subject'] ?? 'Mathematics'] ?? null;
                        ?>
                        <tr>
                            <td><?php echo $student['roll_no']; ?></td>
                            <td><?php echo $student['name']; ?></td>
                            <td>
                                <select name="grade" class="grade-select" id="grade-<?php echo $student['id']; ?>">
                                    <option value="">Select Grade</option>
                                    <option value="A+" <?php echo ($existingGrade['grade'] ?? '') == 'A+' ? 'selected' : ''; ?>>A+</option>
                                    <option value="A" <?php echo ($existingGrade['grade'] ?? '') == 'A' ? 'selected' : ''; ?>>A</option>
                                    <option value="B+" <?php echo ($existingGrade['grade'] ?? '') == 'B+' ? 'selected' : ''; ?>>B+</option>
                                    <option value="B" <?php echo ($existingGrade['grade'] ?? '') == 'B' ? 'selected' : ''; ?>>B</option>
                                    <option value="C" <?php echo ($existingGrade['grade'] ?? '') == 'C' ? 'selected' : ''; ?>>C</option>
                                    <option value="D" <?php echo ($existingGrade['grade'] ?? '') == 'D' ? 'selected' : ''; ?>>D</option>
                                    <option value="F" <?php echo ($existingGrade['grade'] ?? '') == 'F' ? 'selected' : ''; ?>>F</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="marks" class="marks-input" id="marks-<?php echo $student['id']; ?>" 
                                       value="<?php echo $existingGrade['marks'] ?? ''; ?>" min="0" max="100" style="width:80px;">
                            </td>
                            <td>
                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                <input type="hidden" name="subject" id="subjectInput-<?php echo $student['id']; ?>" value="Mathematics">
                                <input type="hidden" name="save_grades" value="1">
                                <button type="submit" class="save-btn">Save</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    
    <script>
        function selectSubject(subject) {
            document.getElementById('selectedSubject').value = subject;
            document.querySelectorAll('.subject-tab').forEach(tab => {
                tab.classList.remove('active');
                if(tab.innerText === subject) tab.classList.add('active');
            });
            
            // Update all hidden subject inputs
            document.querySelectorAll('input[name="subject"]').forEach(input => {
                input.value = subject;
            });
            
            alert(`Now entering grades for ${subject}. Fill in the grades below.`);
        }
        
        // Set default active
        document.querySelector('.subject-tab').classList.add('active');
    </script>
</body>
</html>