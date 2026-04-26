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

// Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $id = $_POST['student_id'];
    $roll_no = $_POST['roll_no'];
    $name = $_POST['name'];
    $class_id = $_POST['class_id'];
    $email = $_POST['email'];
    $parent_email = $_POST['parent_email'];
    $contact = $_POST['contact'];
    
    $stmt = $pdo->prepare("UPDATE students SET roll_no=?, name=?, class_id=?, email=?, parent_email=?, contact=? WHERE id=?");
    $stmt->execute([$roll_no, $name, $class_id, $email, $parent_email, $contact, $id]);
    header('Location: manage_students.php');
    exit;
}

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $roll_no = $_POST['roll_no'];
    $name = $_POST['name'];
    $class_id = $_POST['class_id'];
    $email = $_POST['email'];
    $parent_email = $_POST['parent_email'];
    $contact = $_POST['contact'];
    
    $stmt = $pdo->prepare("INSERT INTO students (roll_no, name, class_id, email, parent_email, contact) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$roll_no, $name, $class_id, $email, $parent_email, $contact]);
    header('Location: manage_students.php');
    exit;
}

$students = $pdo->query("
    SELECT s.*, c.class_name, c.section 
    FROM students s
    JOIN classes c ON s.class_id = c.id
    ORDER BY s.roll_no
")->fetchAll();

$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name, section")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: var(--bg); }
        .delete-btn { background: var(--red); color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 12px; }
        .edit-btn { background: var(--accent); color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 12px; margin-right: 5px; }
        .add-btn { background: var(--teal); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; margin: 20px 0; font-size: 14px; }
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
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <button class="add-btn" onclick="openAddModal()">+ Add New Student</button>
        
        <h1>Manage Students</h1>
        
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
                        <button class="edit-btn" onclick="openEditModal(<?php echo $s['id']; ?>, '<?php echo addslashes($s['roll_no']); ?>', '<?php echo addslashes($s['name']); ?>', <?php echo $s['class_id']; ?>, '<?php echo addslashes($s['email']); ?>', '<?php echo addslashes($s['parent_email']); ?>', '<?php echo addslashes($s['contact']); ?>')">Edit</button>
                        <a href="?delete=<?php echo $s['id']; ?>" class="delete-btn" onclick="return confirm('Delete this student?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($students)): ?>
                <tr><td colspan="7" style="text-align:center;">No students found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Student Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3>Add New Student</h3>
            <form method="POST">
                <input type="text" name="roll_no" placeholder="Roll Number" required>
                <input type="text" name="name" placeholder="Full Name" required>
                <select name="class_id" required>
                    <option value="">Select Class</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>">Class <?php echo $c['class_name'] . ($c['section'] ? '-' . $c['section'] : ''); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="email" name="email" placeholder="Email">
                <input type="email" name="parent_email" placeholder="Parent Email">
                <input type="text" name="contact" placeholder="Contact Number">
                <div class="modal-buttons">
                    <button type="submit" name="add_student" class="btn-submit">Add Student</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Student</h3>
            <form method="POST">
                <input type="hidden" name="student_id" id="edit_id">
                <input type="text" name="roll_no" id="edit_roll_no" placeholder="Roll Number" required>
                <input type="text" name="name" id="edit_name" placeholder="Full Name" required>
                <select name="class_id" id="edit_class_id" required>
                    <option value="">Select Class</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>">Class <?php echo $c['class_name'] . ($c['section'] ? '-' . $c['section'] : ''); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="email" name="email" id="edit_email" placeholder="Email">
                <input type="email" name="parent_email" id="edit_parent_email" placeholder="Parent Email">
                <input type="text" name="contact" id="edit_contact" placeholder="Contact Number">
                <div class="modal-buttons">
                    <button type="submit" name="edit_student" class="btn-submit">Update Student</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        
        function openEditModal(id, roll_no, name, class_id, email, parent_email, contact) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_roll_no').value = roll_no;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_class_id').value = class_id;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_parent_email').value = parent_email;
            document.getElementById('edit_contact').value = contact;
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>