<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Handle Add Accountant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_accountant'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $contact = $_POST['contact'];
    $password = 'accountant123';
    
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, contact) VALUES (?, ?, ?, ?, 'accountant', ?)");
    $stmt->execute([$username, $email, $password, $full_name, $contact]);
    $success = "Accountant added! Email: $email | Password: $password";
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'accountant'");
    $stmt->execute([$id]);
    header('Location: manage_accountants.php');
    exit;
}

$accountants = $pdo->query("SELECT * FROM users WHERE role = 'accountant' ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Accountants - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: var(--bg); }
        .delete-btn { background: #E74C3C; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; }
        .add-btn { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin: 20px 0; }
        .back-btn { background: #1A3A5C; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block; margin-right: 10px; }
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
        .btn-submit { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-cancel { background: #999; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .info-text { font-size: 12px; color: #666; margin-top: -5px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn"> Back to Dashboard</a>
        <button class="add-btn" onclick="openAddModal()">+ Add New Accountant</button>
        
        <h1>Manage Accountants</h1>
        
        <?php if(isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr><th>ID</th><th>Full Name</th><th>Email</th><th>Username</th><th>Contact</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach($accountants as $a): ?>
                <tr>
                    <td><?php echo $a['id']; ?></td>
                    <td><?php echo htmlspecialchars($a['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($a['email']); ?></td>
                    <td><?php echo htmlspecialchars($a['username']); ?></td>
                    <td><?php echo htmlspecialchars($a['contact'] ?? '-'); ?></td>
                    <td><a href="?delete=<?php echo $a['id']; ?>" class="delete-btn" onclick="return confirm('Delete this accountant?')">Delete</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Accountant Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3>Add New Accountant</h3>
            <form method="POST">
                <input type="text" name="full_name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="username" placeholder="Username" required>
                <input type="text" name="contact" placeholder="Contact Number">
                <div class="info-text">Default password: accountant123</div>
                <input type="hidden" name="add_accountant" value="1">
                <div class="modal-buttons">
                    <button type="submit" class="btn-submit">Add Accountant</button>
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
    </script>
</body>
</html>