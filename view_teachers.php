<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'principal') {
    header('Location: dashboard.php');
    exit;
}

$teachers = $pdo->query("SELECT id, full_name, email, contact FROM users WHERE role = 'teacher' ORDER BY full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Teachers - Principal</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="principal_dashboard.php" class="back-btn"> Back to Dashboard</a>
        <h1>Teachers List</h1>
        
        <div class="card">
            <div style="overflow-x: auto;">
                <table>
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Contact</th></tr></thead>
                    <tbody>
                        <?php foreach($teachers as $t): ?>
                        <tr>
                            <td><?php echo $t['id']; ?></td>
                            <td><?php echo $t['full_name']; ?></td>
                            <td><?php echo $t['email']; ?></td>
                            <td><?php echo $t['contact'] ?: '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>