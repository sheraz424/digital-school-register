<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Handle Add Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    $class_name = $_POST['class_name'];
    $section = $_POST['section'];
    $stmt = $pdo->prepare("INSERT INTO classes (class_name, section) VALUES (?, ?)");
    $stmt->execute([$class_name, $section]);
    header('Location: manage_classes.php');
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: manage_classes.php');
    exit;
}

$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name, section")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Classes - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; }
        .form-group { margin: 15px 0; }
        input, select { padding: 8px; margin: 5px; }
        button { padding: 8px 16px; background: var(--accent); color: white; border: none; border-radius: 5px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: var(--bg); }
        .delete-btn { background: var(--red); color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; }
        .add-btn { background: var(--teal); margin-bottom: 20px; }
        .back-btn { background: var(--blue); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block; margin-bottom: 20px; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <h1>Manage Classes</h1>
        
        <h3>Add New Class</h3>
        <form method="POST">
            <input type="text" name="class_name" placeholder="Class (e.g., 9, 10, 11)" required>
            <input type="text" name="section" placeholder="Section (e.g., A, B)" style="width:80px">
            <input type="hidden" name="add_class" value="1">
            <button type="submit" class="add-btn">+ Add Class</button>
        </form>
        
        <table>
            <thead><tr><th>ID</th><th>Class</th><th>Section</th><th>Actions</th></thead>
            <tbody>
                <?php foreach($classes as $c): ?>
                <tr>
                    <td><?php echo $c['id']; ?></td>
                    <td><?php echo $c['class_name']; ?></td>
                    <td><?php echo $c['section'] ?: '-'; ?></td>
                    <td><a href="?delete=<?php echo $c['id']; ?>" class="delete-btn" onclick="return confirm('Delete this class? This will also delete all students in this class!')">Delete</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>