<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'teacher') {
    header('Location: dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get current teacher data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    
    $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, contact=? WHERE id=?");
    $stmt->execute([$full_name, $email, $contact, $user_id]);
    
    $_SESSION['user_name'] = $full_name;
    $_SESSION['user_email'] = $email;
    
    $success = "Profile updated successfully!";
    
    // Refresh data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile - Teacher</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { max-width: 500px; margin: 50px auto; padding: 30px; background: white; border-radius: 16px; border: 1px solid var(--border); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; }
        .btn-submit { width: 100%; padding: 12px; background: var(--accent); color: white; border: none; border-radius: 8px; cursor: pointer; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 8px 16px; background: var(--blue); color: white; text-decoration: none; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>Edit My Profile</h1>
        
        <?php if($success): ?>
        <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($teacher['full_name']); ?>" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
            </div>
            <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contact" value="<?php echo htmlspecialchars($teacher['contact'] ?? ''); ?>">
            </div>
            <button type="submit" class="btn-submit">Update Profile</button>
        </form>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="change_password.php" style="color: var(--accent);">Change Password</a>
        </div>
    </div>
</body>
</html>