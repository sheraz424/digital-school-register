<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Handle Add Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $full_name = $_POST['full_name'];
    $contact = $_POST['contact'];
    
    $name_parts = explode(' ', strtolower($full_name));
    $first_name = $name_parts[0];
    $last_name = isset($name_parts[1]) ? $name_parts[1] : $first_name;
    $email = $first_name . '.' . $last_name . '@dsr.com';
    $username = $email;
    $password = 'teacher123';
    
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, contact) VALUES (?, ?, ?, ?, 'teacher', ?)");
    $stmt->execute([$username, $email, $password, $full_name, $contact]);
    
    $success = "Teacher added! Email: $email | Password: $password";
}

// Handle Edit Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_teacher'])) {
    $id = $_POST['teacher_id'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    
    $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, contact=? WHERE id=? AND role='teacher'");
    $stmt->execute([$full_name, $email, $contact, $id]);
    
    $success = "Teacher updated successfully!";
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->execute([$id]);
    header('Location: manage_teachers.php');
    exit;
}

// Handle Reset Password
if (isset($_GET['reset_password'])) {
    $id = $_GET['reset_password'];
    $new_password = 'teacher123';
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'teacher'");
    $stmt->execute([$new_password, $id]);
    $success = "Password reset to: $new_password";
}

// Export to Excel
if (isset($_GET['export_excel'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="teachers_list_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Full Name</th><th>Email</th><th>Contact</th></tr>";
    
    $teachers = $pdo->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY id DESC")->fetchAll();
    foreach($teachers as $t) {
        echo "<tr>";
        echo "<td>" . $t['id'] . "</td>";
        echo "<td>" . $t['full_name'] . "</td>";
        echo "<td>" . $t['email'] . "</td>";
        echo "<td>" . ($t['contact'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

$teachers = $pdo->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme {
            --bg: #1a1a2e;
            --card: #16213e;
            --text: #eeeeee;
            --border: #2c3e50;
        }
        body.dark-theme .container,
        body.dark-theme .modal-content,
        body.dark-theme table tr td,
        body.dark-theme table tr th {
            background: var(--card);
            color: var(--text);
        }
        body.dark-theme input { background: #0f0f23; border-color: var(--border); color: var(--text); }
        .container { padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid green; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: var(--bg); }
        .delete-btn, .reset-btn, .edit-btn { padding: 5px 10px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 5px; font-size: 12px; }
        .edit-btn { background: var(--accent); color: white; }
        .reset-btn { background: var(--gold); color: white; }
        .delete-btn { background: var(--red); color: white; }
        .add-btn, .export-btn { background: var(--teal); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; margin: 20px 10px 20px 0; }
        .back-btn { background: var(--blue); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block; margin-right: 10px; }
        .search-section { margin: 20px 0; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .search-section input { flex: 1; padding: 10px; border: 1px solid var(--border); border-radius: 8px; min-width: 200px; }
        .clear-btn { padding: 10px 20px; background: var(--muted); color: white; border: none; border-radius: 8px; cursor: pointer; }
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
            width: 400px;
        }
        .modal-content input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 8px; }
        .modal-buttons { display: flex; gap: 10px; margin-top: 20px; }
        .btn-submit { background: var(--accent); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-cancel { background: #999; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .info-text { font-size: 12px; color: #666; margin-top: -5px; margin-bottom: 10px; }
        .theme-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--accent);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 25px;
            cursor: pointer;
            z-index: 1000;
        }
        @media (max-width: 768px) {
            .container { padding: 10px; }
            table { font-size: 12px; }
            th, td { padding: 8px; }
            .edit-btn, .reset-btn, .delete-btn { padding: 3px 6px; font-size: 10px; }
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">🌓 Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <button class="add-btn" onclick="openAddModal()">+ Add New Teacher</button>
        <a href="?export_excel=1" class="export-btn">Export to Excel</a>
        
        <h1>Manage Teachers</h1>
        
        <?php if(isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Search Section -->
        <div class="search-section">
            <input type="text" id="searchInput" placeholder="🔍 Search by name, email, or contact...">
            <button class="clear-btn" onclick="clearSearch()">Clear</button>
        </div>
        
        <div style="overflow-x: auto;">
            <table id="teachersTable">
                <thead>
                    <tr><th>ID</th><th>Full Name</th><th>Email</th><th>Contact</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach($teachers as $t): ?>
                    <tr class="teacher-row">
                        <td><?php echo $t['id']; ?></td>
                        <td class="teacher-name"><?php echo htmlspecialchars($t['full_name']); ?></td>
                        <td class="teacher-email"><?php echo htmlspecialchars($t['email']); ?></td>
                        <td class="teacher-contact"><?php echo htmlspecialchars($t['contact'] ?? '-'); ?></td>
                        <td>
                            <button class="edit-btn" onclick="openEditModal(<?php echo $t['id']; ?>)">Edit</button>
                            <a href="?reset_password=<?php echo $t['id']; ?>" class="reset-btn" onclick="return confirm('Reset password to default (teacher123)?')">Reset Pwd</a>
                            <a href="?delete=<?php echo $t['id']; ?>" class="delete-btn" onclick="return confirm('Delete this teacher?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Teacher Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3>Add New Teacher</h3>
            <form method="POST">
                <input type="text" name="full_name" placeholder="Full Name" required>
                <div class="info-text">Email will be auto-generated: firstname.lastname@dsr.com</div>
                <div class="info-text">Default password: teacher123</div>
                <input type="text" name="contact" placeholder="Contact Number">
                <input type="hidden" name="add_teacher" value="1">
                <div class="modal-buttons">
                    <button type="submit" class="btn-submit">Add Teacher</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Teacher Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Teacher</h3>
            <form method="POST">
                <input type="hidden" name="teacher_id" id="edit_teacher_id">
                <input type="text" name="full_name" id="edit_full_name" placeholder="Full Name" required>
                <input type="email" name="email" id="edit_email" placeholder="Email" required>
                <input type="text" name="contact" id="edit_contact" placeholder="Contact Number">
                <input type="hidden" name="edit_teacher" value="1">
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
            fetch(`api/get_teacher.php?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_teacher_id').value = data.data.id;
                        document.getElementById('edit_full_name').value = data.data.full_name;
                        document.getElementById('edit_email').value = data.data.email;
                        document.getElementById('edit_contact').value = data.data.contact || '';
                                               document.getElementById('editModal').style.display = 'flex';
                    } else {
                        alert('Error loading teacher data');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#teachersTable tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            document.querySelectorAll('#teachersTable tbody tr').forEach(row => {
                row.style.display = '';
            });
        }
        
        // Dark/Light Theme
        function toggleTheme() {
            document.body.classList.toggle('dark-theme');
            const theme = document.body.classList.contains('dark-theme') ? 'dark' : 'light';
            localStorage.setItem('theme', theme);
        }
        
        // Load saved theme
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-theme');
        }
    </script>
</body>
</html>