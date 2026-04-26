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
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->execute([$id]);
    header('Location: manage_teachers.php');
    exit;
}

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $contact = $_POST['contact'];
    $password = 'admin123';
    
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, contact) VALUES (?, ?, ?, ?, 'teacher', ?)");
    $stmt->execute([$username, $email, $password, $full_name, $contact]);
    header('Location: manage_teachers.php');
    exit;
}

$teachers = $pdo->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Teachers - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; max-width: 1000px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: var(--bg); }
        .delete-btn { background: var(--red); color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
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
            width: 400px;
        }
        .modal-content input {
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
        <button class="add-btn" onclick="openAddModal()">+ Add New Teacher</button>
        
        <h1>Manage Teachers</h1>
        
        <table>
            <thead>
                <tr><th>ID</th><th>Full Name</th><th>Email</th><th>Username</th><th>Contact</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach($teachers as $t): ?>
                <tr>
                    <td><?php echo $t['id']; ?></td>
                    <td><?php echo htmlspecialchars($t['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($t['email']); ?></td>
                    <td><?php echo htmlspecialchars($t['username'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($t['contact'] ?? '-'); ?></td>
                    <td style="color: green;">Active</td>
                    <td><a href="?delete=<?php echo $t['id']; ?>" class="delete-btn" onclick="return confirm('Delete this teacher?')">Delete</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($teachers)): ?>
                <tr><td colspan="7" style="text-align:center;">No teachers found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Teacher Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3>Add New Teacher</h3>
            <form method="POST">
                <input type="text" name="full_name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="username" placeholder="Username" required>
                <input type="text" name="contact" placeholder="Contact Number">
                <div class="modal-buttons">
                    <button type="submit" name="add_teacher" class="btn-submit">Add Teacher</button>
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>