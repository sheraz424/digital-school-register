<?php
// install.php - One-click setup helper
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DSR - Installation Wizard</title>
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #17a2b8; }
        .step { background: #e9ecef; padding: 15px; border-radius: 8px; margin: 15px 0; }
        button { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0EB1A8; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        h1 { color: #0F2447; }
    </style>
</head>
<body>
    <h1>📚 DSR - Installation Wizard</h1>
    
    <?php
    $step = $_GET["step"] ?? 1;
    $error = "";
    $success = "";
    
    if ($step == 2 && $_SERVER["REQUEST_METHOD"] === "POST") {
        try {
            $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $pdo->exec("CREATE DATABASE IF NOT EXISTS dsr_school");
            $success .= "✓ Database 'dsr_school' created/verified<br>";
            
            $pdo->exec("USE dsr_school");
            
            $sql = file_get_contents("dsr_school.sql");
            if ($sql) {
                $pdo->exec($sql);
                $success .= "✓ Tables and data imported successfully<br>";
            } else {
                $error = "dsr_school.sql file not found!";
            }
            
            $pdo = null;
        } catch(PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
    ?>
    
    <?php if($step == 1): ?>
    <div class="step">
        <h3>Step 1: System Requirements Check</h3>
        <ul>
            <li>PHP Version: <?php echo phpversion(); ?> <?php echo phpversion() >= 7.4 ? "✅" : "⚠️ (Need PHP 7.4+)"; ?></li>
            <li>PDO MySQL: <?php echo extension_loaded("pdo_mysql") ? "✅" : "❌"; ?></li>
            <li>MySQL Server: 
                <?php 
                try {
                    new PDO("mysql:host=localhost", "root", "");
                    echo "✅ Connected";
                } catch(Exception $e) {
                    echo "❌ (Start MySQL in XAMPP)";
                }
                ?>
            </li>
            <li>Database file: <?php echo file_exists("dsr_school.sql") ? "✅ dsr_school.sql found" : "❌ dsr_school.sql missing"; ?></li>
        </ul>
        
        <?php if(file_exists("dsr_school.sql")): ?>
        <form method="POST" action="?step=2">
            <button type="submit">Step 2: Install Database →</button>
        </form>
        <?php else: ?>
        <div class="error">❌ dsr_school.sql file not found! Please ensure the database backup file exists.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if($step == 2): ?>
        <?php if($success): ?>
        <div class="success">
            <h3>✅ Installation Complete!</h3>
            <p><?php echo $success; ?></p>
        </div>
        <div class="step">
            <a href="login.php"><button>🔐 Go to Login Page</button></a>
        </div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="error">
            <h3>❌ Installation Failed</h3>
            <p><?php echo $error; ?></p>
        </div>
        <div class="step">
            <a href="?step=1"><button>← Try Again</button></a>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="info">
        <h4>📖 Manual Setup Instructions:</h4>
        <ol>
            <li>Start XAMPP (Apache and MySQL)</li>
            <li>Go to <code>http://localhost/phpmyadmin</code></li>
            <li>Create database: <code>dsr_school</code></li>
            <li>Import <code>dsr_school.sql</code> file</li>
            <li>Go to <code>http://localhost/digital-school-register/login.php</code></li>
        </ol>
    </div>
    
    <div class="info">
        <h4>🔑 Login Credentials (Password: admin123)</h4>
        <p><strong>Admin:</strong> admin@dsr.com | <strong>Teacher:</strong> teacher@dsr.com<br>
        <strong>Student:</strong> student1@dsr.com | <strong>Parent:</strong> parent@dsr.com</p>
    </div>
</body>
</html>
