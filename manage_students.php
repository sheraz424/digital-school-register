<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: manage_students.php');
    exit;
}

// Handle Reset Student Password
if (isset($_GET['reset_password'])) {
    $id = $_GET['reset_password'];
    $new_password = 'student123';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password in users table
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = (SELECT user_id FROM students WHERE id = ?)");
    $stmt->execute([$hashed_password, $id]);
    
    // Update password in students table
    $stmt = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
    $stmt->execute([$hashed_password, $id]);
    
    $success = "Student password reset to: student123";
}

// Handle Add Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $roll_no = $_POST['roll_no'];
    $name = $_POST['name'];
    $class_id = $_POST['class_id'];
    $parent_email = $_POST['parent_email'];
    $contact = $_POST['contact'];
    
    // Auto-generate email: rollno@dsr.com
    $student_email = strtolower($roll_no) . '@dsr.com';
    $default_password = 'student123';
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
    
    // Insert student
    $stmt = $pdo->prepare("INSERT INTO students (roll_no, name, class_id, email, parent_email, contact, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$roll_no, $name, $class_id, $student_email, $parent_email, $contact, $hashed_password]);
    $student_id = $pdo->lastInsertId();
    
    // Create user account for student
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'student')");
    $stmt->execute([$roll_no, $student_email, $hashed_password, $name]);
    $user_id = $pdo->lastInsertId();
    
    // Link student to user
    $stmt = $pdo->prepare("UPDATE students SET user_id = ? WHERE id = ?");
    $stmt->execute([$user_id, $student_id]);
    
    // Check if parent user exists, if not create parent account
    if ($parent_email) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$parent_email]);
        if (!$stmt->fetch()) {
            // Create parent account
            $parent_name = "Parent of $name";
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'parent')");
            $stmt->execute([$parent_email, $parent_email, $hashed_password, $parent_name]);
        }
    }
    
    $success = "Student added! Email: $student_email | Password: $default_password";
}

// Handle Edit Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $id = $_POST['student_id'];
    $roll_no = $_POST['roll_no'];
    $name = $_POST['name'];
    $class_id = $_POST['class_id'];
    $parent_email = $_POST['parent_email'];
    $contact = $_POST['contact'];
    
    $stmt = $pdo->prepare("UPDATE students SET roll_no=?, name=?, class_id=?, parent_email=?, contact=? WHERE id=?");
    $stmt->execute([$roll_no, $name, $class_id, $parent_email, $contact, $id]);
    
    // Update user account
    $student_email = strtolower($roll_no) . '@dsr.com';
    $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, full_name=? WHERE id = (SELECT user_id FROM students WHERE id=?)");
    $stmt->execute([$roll_no, $student_email, $name, $id]);
    
    $success = "Student updated successfully!";
}

$students = $pdo->query("
    SELECT s.*, c.class_name, c.section 
    FROM students s
    JOIN classes c ON s.class_id = c.id
    ORDER BY s.roll_no
")->fetchAll();

$classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY class_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid green; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: var(--bg); }
        .delete-btn { background: var(--red); color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .reset-btn { background: var(--gold); color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 5px; }
        .edit-btn { background: var(--accent); color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; margin-right: 5px; text-decoration: none; display: inline-block; }
        .add-btn { background: var(--teal); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; margin: 20px 0; }
        .back-btn { background: var(--blue); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block; margin-right: 10px; }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            width: 500px;
            max-width: 90%;
        }
        .modal-content input, .modal-content select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-submit { background: var(--accent); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-cancel { background: #999; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .info-text { font-size: 12px; color: #666; margin-top: -5px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <button class="add-btn" onclick="openAddModal()">+ Add New Student</button>
        
        <h1>Manage Students</h1>
        
        <?php if(isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr><th>Roll No</th><th>Name</th><th>Class</th><th>Email</th><th>Parent Email</th><th>Contact</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach($students as $s): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['roll_no']); ?></td>
                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                    <td><?php echo $s['class_name'] . ($s['section'] ? '-' . $s['section'] : ''); ?></td>
                    <td><?php echo htmlspecialchars($s['email'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($s['parent_email'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($s['contact'] ?: '-'); ?></td>
                    <td>
                        <button class="edit-btn" onclick="openEditModal(<?php echo $s['id']; ?>)">Edit</button>
                        <a href="?reset_password=<?php echo $s['id']; ?>" class="reset-btn" onclick="return confirm('Reset password to default (student123)?')">Reset Pwd</a>
                        <a href="?delete=<?php echo $s['id']; ?>" class="delete-btn" onclick="return confirm('Delete this student?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Student Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3>Add New Student</h3>
            <form method="POST">
                <input type="text" name="roll_no" placeholder="Roll Number (e.g., 901)" required>
                <div class="info-text">Email will be auto-generated: rollno@dsr.com</div>
                <div class="info-text">Default password: student123</div>
                <input type="text" name="name" placeholder="Full Name" required>
                <select name="class_id" required>
                    <option value="">Select Class</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>">Class <?php echo $c['class_name'] . ($c['section'] ? '-' . $c['section'] : ''); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="email" name="parent_email" placeholder="Parent Email (e.g., parent@example.com)">
                <div class="info-text">Parent account will be auto-created with password: student123</div>
                <input type="text" name="contact" placeholder="Contact Number">
                <input type="hidden" name="add_student" value="1">
                <div class="modal-buttons">
                    <button type="submit" class="btn-submit">Add Student</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Student</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="student_id" id="edit_student_id">
                <input type="text" name="roll_no" id="edit_roll_no" placeholder="Roll Number" required>
                <input type="text" name="name" id="edit_name" placeholder="Full Name" required>
                <select name="class_id" id="edit_class_id" required>
                    <option value="">Select Class</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>">Class <?php echo $c['class_name'] . ($c['section'] ? '-' . $c['section'] : ''); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="email" name="parent_email" id="edit_parent_email" placeholder="Parent Email">
                <input type="text" name="contact" id="edit_contact" placeholder="Contact Number">
                <input type="hidden" name="edit_student" value="1">
                <div class="modal-buttons">
                    <button type="submit" class="btn-submit">Save Changes</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        
        function openEditModal(id) {
            fetch(`api/get_student.php?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_student_id').value = data.data.id;
                        document.getElementById('edit_roll_no').value = data.data.roll_no;
                        document.getElementById('edit_name').value = data.data.name;
                        document.getElementById('edit_class_id').value = data.data.class_id;
                        document.getElementById('edit_parent_email').value = data.data.parent_email || '';
                        document.getElementById('edit_contact').value = data.data.contact || '';
                        document.getElementById('editModal').style.display = 'flex';
                    }
                });
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
    </script>
</body>
</html>