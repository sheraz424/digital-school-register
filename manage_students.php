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
    
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = (SELECT user_id FROM students WHERE id = ?)");
    $stmt->execute([$new_password, $id]);
    
    $stmt = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
    $stmt->execute([$new_password, $id]);
    
    $success = "Student password reset to: student123";
}

// Handle Add Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $roll_no = $_POST['roll_no'];
    $name = $_POST['name'];
    $class_id = $_POST['class_id'];
    $parent_email = $_POST['parent_email'];
    $contact = $_POST['contact'];
    
    $student_email = strtolower($roll_no) . '@dsr.com';
    $default_password = 'student123';
    
    $stmt = $pdo->prepare("INSERT INTO students (roll_no, name, class_id, email, parent_email, contact, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$roll_no, $name, $class_id, $student_email, $parent_email, $contact, $default_password]);
    $student_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'student')");
    $stmt->execute([$roll_no, $student_email, $default_password, $name]);
    $user_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("UPDATE students SET user_id = ? WHERE id = ?");
    $stmt->execute([$user_id, $student_id]);
    
    if ($parent_email) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$parent_email]);
        if (!$stmt->fetch()) {
            $parent_name = "Parent of $name";
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'parent')");
            $stmt->execute([$parent_email, $parent_email, $default_password, $parent_name]);
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
    
    $student_email = strtolower($roll_no) . '@dsr.com';
    $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, full_name=? WHERE id = (SELECT user_id FROM students WHERE id=?)");
    $stmt->execute([$roll_no, $student_email, $name, $id]);
    
    $success = "Student updated successfully!";
}

// Get filter values
$class_filter = $_GET['class_filter'] ?? '';
$section_filter = $_GET['section_filter'] ?? '';
$search_filter = $_GET['search'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'roll_no';
$sort_order = $_GET['sort_order'] ?? 'ASC';

// Build query with filters
$query = "
    SELECT s.*, c.class_name, c.section 
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE 1=1
";

if ($class_filter) {
    $query .= " AND c.class_name = '$class_filter'";
}
if ($section_filter) {
    $query .= " AND c.section = '$section_filter'";
}
if ($search_filter) {
    $query .= " AND (s.name LIKE '%$search_filter%' OR s.roll_no LIKE '%$search_filter%' OR s.email LIKE '%$search_filter%' OR s.parent_email LIKE '%$search_filter%')";
}

// Apply sorting
$valid_sort_columns = ['roll_no', 'name', 'class_name', 'created_at'];
if (in_array($sort_by, $valid_sort_columns)) {
    if ($sort_by == 'class_name') {
        $query .= " ORDER BY c.class_name $sort_order, s.roll_no ASC";
    } else {
        $query .= " ORDER BY s.$sort_by $sort_order";
    }
} else {
    $query .= " ORDER BY s.roll_no ASC";
}

$students = $pdo->query($query)->fetchAll();

// Get unique classes for filters
$uniqueClasses = $pdo->query("SELECT DISTINCT class_name FROM classes ORDER BY FIELD(class_name, 'Nursery','Prep','1','2','3','4','5','6','7','8','9','10')")->fetchAll();
$uniqueSections = $pdo->query("SELECT DISTINCT section FROM classes WHERE section != '' ORDER BY section")->fetchAll();

$classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY FIELD(class_name, 'Nursery','Prep','1','2','3','4','5','6','7','8','9','10'), section")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 1400px; margin: 0 auto; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid green; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: var(--bg); }
        .delete-btn, .reset-btn, .edit-btn { padding: 5px 10px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 5px; font-size: 12px; }
        .edit-btn { background: var(--accent); color: white; }
        .reset-btn { background: var(--gold); color: white; }
        .delete-btn { background: var(--red); color: white; }
        .add-btn { background: var(--teal); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; margin: 20px 0; }
        .export-btn { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; margin: 20px 10px 20px 0; }
        .back-btn { background: var(--blue); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block; margin-right: 10px; }
        .filter-section { background: var(--bg); padding: 15px; border-radius: 12px; margin-bottom: 20px; }
        .filter-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 12px; }
        .filter-group input, .filter-group select { width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px; background: white; }
        body.dark-theme .filter-group input, body.dark-theme .filter-group select { background: var(--card); color: var(--text); }
        .btn-filter { background: var(--accent); color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; }
        .btn-reset { background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; }
        .sort-link { color: var(--accent); text-decoration: none; }
        .sort-link:hover { text-decoration: underline; }
        .stats-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .student-count { font-size: 14px; color: var(--muted); }
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
        body.dark-theme .modal-content { background: var(--card); }
        .modal-content input, .modal-content select { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 8px; }
        .modal-buttons { display: flex; gap: 10px; margin-top: 20px; }
        .btn-submit { background: var(--accent); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-cancel { background: #999; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .info-text { font-size: 12px; color: #666; margin-top: -5px; margin-bottom: 10px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: var(--accent); color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        @media (max-width: 768px) { .container { padding: 10px; } table { font-size: 12px; } th, td { padding: 8px; } .filter-row { flex-direction: column; } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <button class="add-btn" onclick="openAddModal()">+ Add New Student</button>
        <button class="export-btn" onclick="exportToExcel()">Export to Excel</button>
        
        <h1>Manage Students</h1>
        
        <?php if(isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Filters Section -->
        <div class="filter-section">
            <h3>Filters</h3>
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Name, Roll No, Email, Parent Email..." value="<?php echo htmlspecialchars($search_filter); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Class</label>
                        <select name="class_filter">
                            <option value="">All Classes</option>
                            <?php foreach($uniqueClasses as $c): ?>
                                <option value="<?php echo $c['class_name']; ?>" <?php echo ($class_filter == $c['class_name']) ? 'selected' : ''; ?>>
                                    <?php echo $c['class_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Section</label>
                        <select name="section_filter">
                            <option value="">All Sections</option>
                            <?php foreach($uniqueSections as $s): ?>
                                <option value="<?php echo $s['section']; ?>" <?php echo ($section_filter == $s['section']) ? 'selected' : ''; ?>>
                                    <?php echo $s['section']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-filter">Apply Filters</button>
                        <a href="manage_students.php" class="btn-reset" style="display: inline-block; padding: 8px 16px; text-decoration: none;">Reset</a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="student-count">Total Students: <strong><?php echo count($students); ?></strong></div>
            <div class="sort-options">
                Sort by: 
                <a href="?sort_by=roll_no&sort_order=ASC<?php echo $search_filter ? '&search='.$search_filter : ''; ?><?php echo $class_filter ? '&class_filter='.$class_filter : ''; ?><?php echo $section_filter ? '&section_filter='.$section_filter : ''; ?>" class="sort-link">Roll No ↑</a> |
                <a href="?sort_by=name&sort_order=ASC<?php echo $search_filter ? '&search='.$search_filter : ''; ?><?php echo $class_filter ? '&class_filter='.$class_filter : ''; ?><?php echo $section_filter ? '&section_filter='.$section_filter : ''; ?>" class="sort-link">Name ↑</a> |
                <a href="?sort_by=class_name&sort_order=ASC<?php echo $search_filter ? '&search='.$search_filter : ''; ?><?php echo $class_filter ? '&class_filter='.$class_filter : ''; ?><?php echo $section_filter ? '&section_filter='.$section_filter : ''; ?>" class="sort-link">Class ↑</a> |
                <a href="?sort_by=created_at&sort_order=DESC<?php echo $search_filter ? '&search='.$search_filter : ''; ?><?php echo $class_filter ? '&class_filter='.$class_filter : ''; ?><?php echo $section_filter ? '&section_filter='.$section_filter : ''; ?>" class="sort-link">Newest First</a>
            </div>
        </div>
        
        <!-- Students Table -->
        <div style="overflow-x: auto;">
            <table id="studentsTable">
                <thead>
                    <tr>
                        <th>Roll No</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Email</th>
                        <th>Parent Email</th>
                        <th>Contact</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($students as $s): ?>
                    <tr class="student-row">
                        <td class="roll-no"><?php echo htmlspecialchars($s['roll_no']); ?></td>
                        <td class="student-name"><?php echo htmlspecialchars($s['name']); ?></td>
                        <td class="class-name"><?php echo $s['class_name']; ?></td>
                        <td class="section"><?php echo $s['section'] ?: '-'; ?></td>
                        <td class="email"><?php echo htmlspecialchars($s['email'] ?: '-'); ?></td>
                        <td class="parent-email"><?php echo htmlspecialchars($s['parent_email'] ?: '-'); ?></td>
                        <td class="contact"><?php echo htmlspecialchars($s['contact'] ?: '-'); ?></td>
                        <td class="date"><?php echo date('d M Y', strtotime($s['created_at'])); ?></td>
                        <td class="actions">
                            <button class="edit-btn" onclick="openEditModal(<?php echo $s['id']; ?>)">Edit</button>
                            <a href="?reset_password=<?php echo $s['id']; ?>" class="reset-btn" onclick="return confirm('Reset password to default (student123)?')">Reset Pwd</a>
                            <a href="?delete=<?php echo $s['id']; ?>" class="delete-btn" onclick="return confirm('Delete this student?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($students)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">No students found matching the filters</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
            <form method="POST">
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
        function openAddModal() { document.getElementById('addModal').style.display = 'flex'; }
        
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
                    } else { alert('Error loading student data'); }
                }).catch(error => { alert('Error: ' + error); });
        }
        
        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
        
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
        
        function exportToExcel() {
            let table = document.getElementById('studentsTable');
            let html = table.outerHTML;
            let url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            let link = document.createElement('a');
            link.href = url;
            link.download = 'students_list.xls';
            link.click();
        }
    </script>
</body>
</html>