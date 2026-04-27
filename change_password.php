<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$user_email = $_SESSION['user_email'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required!';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirm password do not match!';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        // Plain text password comparison
        if ($old_password === $user['password']) {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_password, $user_id]);
            $success = 'Password changed successfully!';
        } else {
            $error = 'Current password is incorrect!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - DSR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { max-width: 500px; margin: 50px auto; padding: 30px; background: white; border-radius: 16px; border: 1px solid var(--border); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text); }
        input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-family: 'Sora', sans-serif; }
        input:focus { outline: none; border-color: var(--accent); }
        .btn-submit { width: 100%; padding: 12px; background: var(--accent); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-submit:hover { background: var(--teal); }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 8px 16px; background: var(--blue); color: white; text-decoration: none; border-radius: 8px; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        h1 { color: var(--text); margin-bottom: 10px; }
        .role-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; margin-top: 10px; }
        .role-admin { background: #F4A261; color: white; }
        .role-teacher { background: #2E86AB; color: white; }
        .role-student { background: #2ECC71; color: white; }
        .role-parent { background: #9B59B6; color: white; }
        .info-text { font-size: 12px; color: var(--muted); margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <h1>Change Password</h1>
        <p>User: <strong><?php echo htmlspecialchars($user_name); ?></strong></p>
        <span class="role-badge role-<?php echo $user_role; ?>"><?php echo ucfirst($user_role); ?></span>
        
        <?php if($error): ?>
        <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
        <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="old_password" required placeholder="Enter your current password">
            </div>
            
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required placeholder="Enter new password (min 6 characters)">
                <div class="info-text">Password must be at least 6 characters long</div>
            </div>
            
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required placeholder="Re-enter new password">
            </div>
            
            <button type="submit" class="btn-submit">Update Password</button>
        </form>
        
        <div class="info-text" style="margin-top: 20px; text-align: center;">
            <strong>Note:</strong> If you forget your password, please contact the school administrator.
        </div>
    </div>
</body>
</html>