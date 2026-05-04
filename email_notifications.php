<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$message = '';
$error = '';

// Create mail output directory if not exists (for localhost testing)
$mail_dir = __DIR__ . '/mail_output/';
if (!file_exists($mail_dir)) {
    mkdir($mail_dir, 0777, true);
}

// Function to actually send email (saves to file for localhost, will send on live server)
function sendRealEmail($to, $subject, $body) {
    global $mail_dir;
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: DSR School Management <noreply@dsr-school.com>" . "\r\n";
    $headers .= "Reply-To: noreply@dsr-school.com" . "\r\n";
    
    // For localhost, save to file instead of sending
    if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
        $filename = $mail_dir . 'email_' . date('Y-m-d_H-i-s') . '_' . rand(1000, 9999) . '.html';
        $content = "<!DOCTYPE html><html><head><title>$subject</title></head><body>";
        $content .= "<h2>To: $to</h2>";
        $content .= "<h2>Subject: $subject</h2>";
        $content .= "<hr>";
        $content .= $body;
        $content .= "</body></html>";
        
        file_put_contents($filename, $content);
        return true;
    } else {
        // For online hosting, actually send email
        return mail($to, $subject, $body, $headers);
    }
}

// Create email_logs table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_by INT
)");

// Create email_queue table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Function to save email to queue
function saveToQueue($to, $subject, $body, $pdo) {
    $stmt = $pdo->prepare("INSERT INTO email_queue (recipient, subject, body, status) VALUES (?, ?, ?, 'pending')");
    return $stmt->execute([$to, $subject, $body]);
}

// Function to log email
function logEmail($to, $subject, $body, $status, $error_msg, $pdo, $sent_by) {
    $stmt = $pdo->prepare("INSERT INTO email_logs (recipient, subject, body, status, error_message, sent_by) VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$to, $subject, $body, $status, $error_msg, $sent_by]);
}

// Send test email
if (isset($_POST['send_test'])) {
    $test_email = $_POST['test_email'];
    $subject = 'Test Email from DSR School Management';
    $body = "
    <html>
    <head><title>DSR Test Email</title></head>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #2E86AB;'>DSR School Management System</h2>
            <p>Dear User,</p>
            <p>This is a test email from the DSR School Management System.</p>
            <p>If you received this email, your email configuration is working!</p>
            <hr>
            <p style='font-size: 12px; color: #666;'>Regards,<br>DSR School Management</p>
        </div>
    </body>
    </html>";
    
    if (saveToQueue($test_email, $subject, $body, $pdo)) {
        logEmail($test_email, $subject, $body, 'pending', NULL, $pdo, $_SESSION['user_id']);
        $message = "Test email saved to queue.";
    } else {
        $error = "Failed to save test email to queue.";
    }
}

// Process queue (Send actual emails)
if (isset($_POST['process_queue']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin')) {
    $stmt = $pdo->query("SELECT * FROM email_queue WHERE status = 'pending' LIMIT 20");
    $emails = $stmt->fetchAll();
    
    $sent = 0;
    $failed = 0;
    $email_files = 0;
    
    foreach($emails as $email) {
        $result = sendRealEmail($email['recipient'], $email['subject'], $email['body']);
        
        if ($result) {
            $update = $pdo->prepare("UPDATE email_queue SET status = 'sent' WHERE id = ?");
            $update->execute([$email['id']]);
            
            // Update log
            $log = $pdo->prepare("UPDATE email_logs SET status = 'sent' WHERE recipient = ? AND subject = ?");
            $log->execute([$email['recipient'], $email['subject']]);
            $sent++;
            $email_files++;
        } else {
            $update = $pdo->prepare("UPDATE email_queue SET status = 'failed' WHERE id = ?");
            $update->execute([$email['id']]);
            $failed++;
        }
    }
    
    if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
        $message = "Processed $sent emails. Emails saved to: " . $mail_dir;
    } else {
        $message = "Processed $sent emails sent successfully, $failed failed.";
    }
}

// Clear queue
if (isset($_GET['clear_queue']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin')) {
    $pdo->exec("DELETE FROM email_queue WHERE status = 'sent'");
    $message = "Cleared sent emails from queue.";
}

// Delete single email from queue
if (isset($_GET['delete_email'])) {
    $id = $_GET['delete_email'];
    $stmt = $pdo->prepare("DELETE FROM email_queue WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: email_notifications.php');
    exit;
}

// Send absence alerts
if (isset($_POST['send_absence_alerts'])) {
    $date = $_POST['date'];
    $class_id = $_POST['class_id'] ?? 0;
    
    $sql = "SELECT s.name as student_name, s.parent_email, a.attendance_date, c.class_name
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            JOIN classes c ON s.class_id = c.id
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
            <body style='font-family: Arial, sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                    <h2 style='color: #2E86AB;'>Attendance Alert</h2>
                    <p>Dear Parent,</p>
                    <p>Your child <strong>{$student['student_name']}</strong> of class <strong>{$student['class_name']}</strong> was marked <strong style='color:red'>ABSENT</strong> on <strong>{$student['attendance_date']}</strong>.</p>
                    <p>Please log in to the DSR portal for more details.</p>
                    <hr>
                    <p style='font-size: 12px; color: #666;'>Regards,<br>DSR School Management</p>
                </div>
            </body>
            </html>";
            
            if (saveToQueue($student['parent_email'], $subject, $body, $pdo)) {
                logEmail($student['parent_email'], $subject, $body, 'pending', NULL, $pdo, $_SESSION['user_id']);
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
        $stmt = $pdo->query("SELECT DISTINCT parent_email as email FROM students WHERE parent_email IS NOT NULL AND parent_email != ''");
    } elseif ($recipient_type == 'teachers') {
        $stmt = $pdo->query("SELECT email FROM users WHERE role = 'teacher'");
    } elseif ($recipient_type == 'accountants') {
        $stmt = $pdo->query("SELECT email FROM users WHERE role = 'accountant'");
    } elseif ($recipient_type == 'students') {
        $stmt = $pdo->query("SELECT email FROM users WHERE role = 'student'");
    } else {
        $stmt = $pdo->query("SELECT email FROM users WHERE role NOT IN ('admin', 'super_admin')");
    }
    
    $recipients = $stmt->fetchAll();
    $queued = 0;
    
    foreach($recipients as $recipient) {
        $email = $recipient['email'];
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $body = "
            <html>
            <head><title>$subject</title></head>
            <body style='font-family: Arial, sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                    <h2 style='color: #2E86AB;'>$subject</h2>
                    <p>" . nl2br(htmlspecialchars($message_body)) . "</p>
                    <hr>
                    <p style='font-size: 12px; color: #666;'>Regards,<br>DSR School Management</p>
                </div>
            </body>
            </html>";
            
            if (saveToQueue($email, $subject, $body, $pdo)) {
                logEmail($email, $subject, $body, 'pending', NULL, $pdo, $_SESSION['user_id']);
                $queued++;
            }
        }
    }
    
    $message = "$queued emails saved to queue.";
}

// Get statistics
$pendingCount = $pdo->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetchColumn();
$sentCount = $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'sent'")->fetchColumn();
$failedCount = $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'failed'")->fetchColumn();
$parentsCount = $pdo->query("SELECT COUNT(DISTINCT parent_email) FROM students WHERE parent_email IS NOT NULL AND parent_email != ''")->fetchColumn();
$teachersCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();

// Get queue emails
$queueEmails = $pdo->query("SELECT * FROM email_queue ORDER BY created_at DESC LIMIT 30")->fetchAll();

// Get email logs
$emailLogs = $pdo->query("
    SELECT el.*, u.full_name as sent_by_name 
    FROM email_logs el
    LEFT JOIN users u ON el.sent_by = u.id
    ORDER BY el.sent_at DESC 
    LIMIT 30
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Notifications - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        body.dark-theme .card { background: var(--card); border-color: var(--border); }
        body.dark-theme input, body.dark-theme select, body.dark-theme textarea { background: #0f0f23; border-color: var(--border); color: var(--text); }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #ddd; }
        .stat-value { font-size: 28px; font-weight: bold; color: #2E86AB; }
        .btn { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin: 5px; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-sent { color: #28a745; font-weight: bold; }
        .status-failed { color: #dc3545; font-weight: bold; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        .info-note { background: #e8f4fd; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn"> Back to Dashboard</a>
        <h1>Email Notifications System</h1>
        
        <div class="info-note">
            📧 <strong>Note for Localhost Testing:</strong> Emails are saved to <code><?php echo $mail_dir; ?></code> folder. 
            <a href="mail_output/" target="_blank">Click here to view saved emails</a> (if folder exists).
            On live server, emails will be sent to actual inboxes.
        </div>
        
        <?php if($message): ?>
        <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
        <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo $pendingCount; ?></div><div>Pending Queue</div></div>
            <div class="stat-card"><div class="stat-value" style="color: #28a745;"><?php echo $sentCount; ?></div><div>Sent Emails</div></div>
            <div class="stat-card"><div class="stat-value" style="color: #dc3545;"><?php echo $failedCount; ?></div><div>Failed Emails</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $parentsCount; ?></div><div>Parents with Email</div></div>
        </div>
        
        <!-- Queue Management (Admin/Super Admin only) -->
        <?php if($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin'): ?>
        <div class="card" style="background: #e8f4fd;">
            <h2>Queue Management</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <form method="POST" style="display: inline-block;">
                    <button type="submit" name="process_queue" class="btn btn-success">Process Pending Queue (Send Emails)</button>
                </form>
                <a href="?clear_queue=1" class="btn btn-danger" onclick="return confirm('Clear all sent emails from queue?')">Clear Sent Emails</a>
            </div>
            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                <strong>Note:</strong> Click "Process Pending Queue" to actually send/ save emails.
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Send Test Email -->
        <div class="card">
            <h2>Send Test Email</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Test Email Address</label>
                    <input type="email" name="test_email" placeholder="your@email.com" required>
                </div>
                <button type="submit" name="send_test" class="btn btn-success">Save Test Email to Queue</button>
            </form>
        </div>
        
        <!-- Absence Alerts -->
        <div class="card">
            <h2>Send Absence Alerts</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Select Class (Optional)</label>
                        <select name="class_id">
                            <option value="0">All Classes</option>
                            <?php
                            $classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY FIELD(class_name, 'Nursery','Prep','1','2','3','4','5','6','7','8','9','10'), section");
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
                </div>
                <button type="submit" name="send_absence_alerts" class="btn">Save Absence Alerts to Queue</button>
            </form>
        </div>
        
        <!-- Bulk Message -->
        <div class="card">
            <h2>Send Bulk Message</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Recipient Type</label>
                        <select name="recipient_type" required>
                            <option value="parents">Parents Only</option>
                            <option value="teachers">Teachers Only</option>
                            <option value="students">Students Only</option>
                            <option value="accountants">Accountants Only</option>
                            <option value="all">All Users</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" placeholder="Email Subject" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" rows="5" placeholder="Type your message here..." required></textarea>
                </div>
                <button type="submit" name="send_bulk_message" class="btn">Save Bulk Message to Queue</button>
            </form>
        </div>
        
        <!-- Email Queue List -->
        <div class="card">
            <h2>Email Queue</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>Recipient</th><th>Subject</th><th>Status</th><th>Created</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($queueEmails as $q): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($q['recipient']); ?></td>
                            <td><?php echo htmlspecialchars(substr($q['subject'], 0, 50)); ?><?php echo strlen($q['subject']) > 50 ? '...' : ''; ?></td>
                            <td class="status-<?php echo $q['status']; ?>"><?php echo ucfirst($q['status']); ?></td>
                            <td><?php echo date('d M Y H:i', strtotime($q['created_at'])); ?></td>
                            <td><a href="?delete_email=<?php echo $q['id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Delete this email?')" style="background:#dc3545; color:white; padding:4px 8px; border-radius:4px; text-decoration:none;">Delete</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($queueEmails)): ?>
                        <tr><td colspan="5" style="text-align: center;">No emails in queue</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Email Log History -->
        <div class="card">
            <h2>Email Log History</h2>
            <div style="overflow-x: auto;">
                <input type="text" id="searchLogs" placeholder="Search by email or subject..." style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:8px;">
                <table>
                    <thead>
                        <tr><th>Recipient</th><th>Subject</th><th>Status</th><th>Sent At</th><th>Sent By</th></tr>
                    </thead>
                    <tbody id="logsTable">
                        <?php foreach($emailLogs as $log): ?>
                        <tr class="log-row">
                            <td><?php echo htmlspecialchars($log['recipient']); ?></td>
                            <td><?php echo htmlspecialchars(substr($log['subject'], 0, 50)); ?><?php echo strlen($log['subject']) > 50 ? '...' : ''; ?></tr>
                            <td class="status-<?php echo $log['status']; ?>"><?php echo ucfirst($log['status']); ?><?php echo $log['error_message'] ? '<br><small>' . htmlspecialchars(substr($log['error_message'], 0, 50)) . '</small>' : ''; ?></td>
                            <td><?php echo date('d M Y H:i', strtotime($log['sent_at'])); ?></td>
                            <td><?php echo $log['sent_by_name'] ?? 'System'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($emailLogs)): ?>
                        <tr><td colspan="5" style="text-align: center;">No email logs found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('searchLogs').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('.log-row');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
        
        function toggleTheme() { 
            document.body.classList.toggle('dark-theme'); 
            localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); 
        }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>