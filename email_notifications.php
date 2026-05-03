<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

// Suppress mail() warnings (since localhost doesn't have mail server)
error_reporting(E_ALL & ~E_WARNING);

$message = '';
$error = '';

// Create email queue table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Function to save email to database (works without mail server)
function saveEmailToDatabase($to, $subject, $body, $pdo) {
    $stmt = $pdo->prepare("INSERT INTO email_queue (recipient, subject, body, status) VALUES (?, ?, ?, 'pending')");
    return $stmt->execute([$to, $subject, $body]);
}

// Send test email (saves to database instead)
if (isset($_POST['send_test'])) {
    $test_email = $_POST['test_email'];
    $subject = 'Test Email from DSR School Management';
    $body = '
    <html>
    <head><title>DSR Test Email</title></head>
    <body>
        <h2 style="color: #2E86AB;">DSR School Management System</h2>
        <p>This is a test email from the DSR system.</p>
        <p>Email sent to: ' . htmlspecialchars($test_email) . '</p>
        <hr>
        <p style="font-size: 12px; color: #666;">Regards,<br>DSR School Management</p>
    </body>
    </html>';
    
    if (saveEmailToDatabase($test_email, $subject, $body, $pdo)) {
        $message = "Test email saved to queue. To send real emails, configure SMTP settings.";
    } else {
        $error = "Failed to save test email.";
    }
}

// Send absence alerts
if (isset($_POST['send_absence_alerts'])) {
    $date = $_POST['date'];
    $class_id = $_POST['class_id'] ?? 0;
    
    $sql = "SELECT s.name as student_name, s.parent_email, a.attendance_date 
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            WHERE a.status = 'A' AND a.attendance_date = ?";
    $params = [$date];
    
    if ($class_id && $class_id != 0) {
        $sql .= " AND a.class_id = ?";
        $params[] = $class_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $absentStudents = $stmt->fetchAll();
    
    $queued = 0;
    
    foreach($absentStudents as $student) {
        if ($student['parent_email'] && filter_var($student['parent_email'], FILTER_VALIDATE_EMAIL)) {
            $subject = "Absence Notification - DSR";
            $body = "
            <html>
            <head><title>Absence Notification</title></head>
            <body>
                <h2 style='color: #2E86AB;'>Attendance Alert</h2>
                <p>Dear Parent,</p>
                <p>This is to inform you that your child <strong>{$student['student_name']}</strong> was marked <strong style='color:red'>ABSENT</strong> on <strong>{$student['attendance_date']}</strong>.</p>
                <p>Please log in to the DSR portal for more details.</p>
                <hr>
                <p style='font-size: 12px; color: #666;'>Regards,<br>DSR School Management</p>
            </body>
            </html>";
            
            if (saveEmailToDatabase($student['parent_email'], $subject, $body, $pdo)) {
                $queued++;
            }
        }
    }
    
    $message = "$queued absence alerts saved to queue.";
}

// Send bulk message
if (isset($_POST['send_bulk_message'])) {
    $subject = $_POST['subject'];
    $message_body = $_POST['message'];
    $recipient_type = $_POST['recipient_type'];
    
    if ($recipient_type == 'parents') {
        $stmt = $pdo->query("SELECT DISTINCT parent_email FROM students WHERE parent_email IS NOT NULL AND parent_email != ''");
    } elseif ($recipient_type == 'teachers') {
        $stmt = $pdo->query("SELECT email FROM users WHERE role = 'teacher'");
    } elseif ($recipient_type == 'accountants') {
        $stmt = $pdo->query("SELECT email FROM users WHERE role = 'accountant'");
    } else {
        $stmt = $pdo->query("SELECT email FROM users WHERE role != 'admin'");
    }
    
    $recipients = $stmt->fetchAll();
    $queued = 0;
    
    foreach($recipients as $recipient) {
        $email = $recipient['parent_email'] ?? $recipient['email'];
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $body = "
            <html>
            <head><title>$subject</title></head>
            <body>
                <h2 style='color: #2E86AB;'>$subject</h2>
                <p>$message_body</p>
                <hr>
                <p style='font-size: 12px; color: #666;'>Regards,<br>DSR School Management</p>
            </body>
            </html>";
            
            if (saveEmailToDatabase($email, $subject, $body, $pdo)) {
                $queued++;
            }
        }
    }
    
    $message = "$queued emails saved to queue.";
}

// Process email queue (manual)
if (isset($_GET['process_queue']) && $_SESSION['user_role'] === 'admin') {
    $stmt = $pdo->query("SELECT * FROM email_queue WHERE status = 'pending' LIMIT 10");
    $emails = $stmt->fetchAll();
    
    $stmt_update = $pdo->prepare("UPDATE email_queue SET status = 'sent' WHERE id = ?");
    foreach($emails as $email) {
        $stmt_update->execute([$email['id']]);
    }
    
    $message = "Marked " . count($emails) . " emails as sent from queue.";
}

// Delete processed emails
if (isset($_GET['clear_queue']) && $_SESSION['user_role'] === 'admin') {
    $pdo->exec("DELETE FROM email_queue WHERE status = 'sent'");
    $message = "Cleared sent emails from queue.";
}

// Get queue counts
$pendingCount = $pdo->query("SELECT COUNT(*) as count FROM email_queue WHERE status = 'pending'")->fetch()['count'];
$sentCount = $pdo->query("SELECT COUNT(*) as count FROM email_queue WHERE status = 'sent'")->fetch()['count'];
$totalCount = $pdo->query("SELECT COUNT(*) as count FROM email_queue")->fetch()['count'];

// Get recent queue entries
$queueEmails = $pdo->query("SELECT * FROM email_queue ORDER BY created_at DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Notifications - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme {
            --bg: #1a1a2e;
            --card: #16213e;
            --text: #eeeeee;
            --border: #2c3e50;
        }
        body.dark-theme .container, body.dark-theme .card {
            background: var(--card);
            color: var(--text);
            border-color: var(--border);
        }
        body.dark-theme input, body.dark-theme select, body.dark-theme textarea {
            background: #0f0f23;
            border-color: var(--border);
            color: var(--text);
        }
        .container { padding: 20px; max-width: 900px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .btn { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin: 5px; }
        .btn-primary { background: #0EB1A8; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        textarea { min-height: 100px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .queue-stats { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-box { background: var(--bg); padding: 15px; border-radius: 12px; text-align: center; flex: 1; }
        .stat-number { font-size: 28px; font-weight: bold; color: #2E86AB; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-sent { color: #28a745; font-weight: bold; }
        .theme-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #2E86AB;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 25px;
            cursor: pointer;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn"> Back to Dashboard</a>
        <h1>Email Notifications</h1>
        
        <?php if($message): ?>
        <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Queue Statistics -->
        <div class="queue-stats">
            <div class="stat-box">
                <div class="stat-number" style="color: #ffc107;"><?php echo $pendingCount; ?></div>
                <div>Pending</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" style="color: #28a745;"><?php echo $sentCount; ?></div>
                <div>Sent</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $totalCount; ?></div>
                <div>Total</div>
            </div>
        </div>
        
        <!-- Admin Queue Actions -->
        <?php if($_SESSION['user_role'] === 'admin'): ?>
        <div class="card" style="background: #e8f4fd;">
            <h2>Queue Management</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="?process_queue=1" class="btn btn-primary">Process Pending Queue</a>
                <a href="?clear_queue=1" class="btn btn-danger" onclick="return confirm('Clear all sent emails from queue?')">Clear Sent Emails</a>
            </div>
            <p style="font-size: 12px; color: #666; margin-top: 10px;">Note: Emails are saved to database. Configure SMTP to send actual emails.</p>
        </div>
        <?php endif; ?>
        
        <!-- Test Email -->
        <div class="card">
            <h2>Test Email</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Test Email Address</label>
                    <input type="email" name="test_email" placeholder="your@email.com" required>
                </div>
                <button type="submit" name="send_test" class="btn">Send Test Email</button>
            </form>
        </div>
        
        <!-- Absence Alerts -->
        <div class="card">
            <h2>Send Absence Alerts</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Select Class (Optional)</label>
                    <select name="class_id">
                        <option value="0">All Classes</option>
                        <?php
                        $classes = $pdo->query("SELECT id, class_name, section FROM classes");
                        while($class = $classes->fetch()) {
                            echo "<option value='{$class['id']}'>Class {$class['class_name']}{$class['section']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <button type="submit" name="send_absence_alerts" class="btn btn-primary">Send Absence Alerts to Parents</button>
            </form>
        </div>
        
        <!-- Bulk Message -->
        <div class="card">
            <h2>Send Bulk Message</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Recipient Type</label>
                    <select name="recipient_type">
                        <option value="parents">Parents Only</option>
                        <option value="teachers">Teachers Only</option>
                        <option value="accountants">Accountants Only</option>
                        <option value="all">All Users</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" placeholder="Email Subject" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" placeholder="Type your message here..." required></textarea>
                </div>
                <button type="submit" name="send_bulk_message" class="btn">Send Bulk Message</button>
            </form>
        </div>
        
        <!-- Email Queue List -->
        <div class="card">
            <h2>Recent Email Queue</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Recipient</th><th>Subject</th><th>Status</th><th>Created</th></td>
                    </thead>
                    <tbody>
                        <?php foreach($queueEmails as $q): ?>
                        <tr>
                            <td><?php echo $q['id']; ?></td>
                            <td><?php echo htmlspecialchars($q['recipient']); ?></td>
                            <td><?php echo htmlspecialchars(substr($q['subject'], 0, 50)); ?>...</td>
                            <td class="status-<?php echo $q['status']; ?>"><?php echo $q['status']; ?></td>
                            <td><?php echo date('d M Y H:i', strtotime($q['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($queueEmails)): ?>
                        <tr><td colspan="5" style="text-align: center;">No emails in queue</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="card">
            <h2>Email Statistics</h2>
            <?php
            $parents = $pdo->query("SELECT COUNT(DISTINCT parent_email) as count FROM students WHERE parent_email IS NOT NULL AND parent_email != ''")->fetch();
            $teachers = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")->fetch();
            $accountants = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'accountant'")->fetch();
            ?>
            <p>Parents with email: <?php echo $parents['count']; ?></p>
            <p>Teachers: <?php echo $teachers['count']; ?></p>
            <p>Accountants: <?php echo $accountants['count']; ?></p>
        </div>
    </div>
    
    <script>
        function toggleTheme() {
            document.body.classList.toggle('dark-theme');
            localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light');
        }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>