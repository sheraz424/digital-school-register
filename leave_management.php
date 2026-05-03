<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$message = '';
$error = '';

// Apply for leave
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    $stmt = $pdo->prepare("INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->execute([$user_id, $leave_type, $start_date, $end_date, $reason]);
    $message = "Leave request submitted successfully!";
}

// Approve/Reject leave (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_leave']) && $user_role === 'admin') {
    $leave_id = $_POST['leave_id'];
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];
    
    $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, approved_by = ?, remarks = ? WHERE id = ?");
    $stmt->execute([$status, $user_id, $remarks, $leave_id]);
    $message = "Leave request $status!";
}

// Get leave requests
if ($user_role === 'admin') {
    $leaveRequests = $pdo->query("
        SELECT l.*, u.full_name, u.role 
        FROM leave_requests l
        JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
    ")->fetchAll();
} else {
    $leaveRequests = $pdo->prepare("
        SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC
    ");
    $leaveRequests->execute([$user_id]);
    $leaveRequests = $leaveRequests->fetchAll();
}

// Get counts
$pendingCount = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn();
$approvedCount = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Approved'")->fetchColumn();
$rejectedCount = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Rejected'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leave Management - DSR</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        body.dark-theme .card { background: var(--card); border-color: var(--border); }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #ddd; }
        .stat-value { font-size: 28px; font-weight: bold; }
        .stat-value.pending { color: #ffc107; }
        .stat-value.approved { color: #28a745; }
        .stat-value.rejected { color: #dc3545; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .btn { background: #2E86AB; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; }
        .btn-sm { padding: 5px 10px; font-size: 12px; margin: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .status-Pending { color: #ffc107; font-weight: bold; }
        .status-Approved { color: #28a745; font-weight: bold; }
        .status-Rejected { color: #dc3545; font-weight: bold; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>Leave Management System</h1>
        
        <?php if($message): ?>
        <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value pending"><?php echo $pendingCount; ?></div><div>Pending</div></div>
            <div class="stat-card"><div class="stat-value approved"><?php echo $approvedCount; ?></div><div>Approved</div></div>
            <div class="stat-card"><div class="stat-value rejected"><?php echo $rejectedCount; ?></div><div>Rejected</div></div>
        </div>
        
        <!-- Apply for Leave -->
        <div class="card">
            <h2>Apply for Leave</h2>
            <form method="POST">
                <input type="hidden" name="apply_leave" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>Leave Type</label>
                        <select name="leave_type" required>
                            <option value="Sick">Sick Leave</option>
                            <option value="Casual">Casual Leave</option>
                            <option value="Emergency">Emergency Leave</option>
                            <option value="Annual">Annual Leave</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Start Date</label><input type="date" name="start_date" required></div>
                    <div class="form-group"><label>End Date</label><input type="date" name="end_date" required></div>
                </div>
                <div class="form-group"><label>Reason</label><textarea name="reason" rows="3" required placeholder="Please provide reason for leave..."></textarea></div>
                <button type="submit" class="btn btn-success">Submit Leave Request</button>
            </form>
        </div>
        
        <!-- Leave History -->
        <div class="card">
            <h2><?php echo $user_role === 'admin' ? 'All Leave Requests' : 'My Leave Requests'; ?></h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <?php if($user_role === 'admin'): ?><th>Employee</th><?php endif; ?>
                            <th>Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <?php if($user_role === 'admin'): ?><th>Action</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($leaveRequests as $l): ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($l['created_at'])); ?></td>
                            <?php if($user_role === 'admin'): ?>
                            <td><?php echo $l['full_name']; ?><br><small><?php echo ucfirst($l['role']); ?></small></td>
                            <?php endif; ?>
                            <td><?php echo $l['leave_type']; ?></td>
                            <td><?php echo date('d M Y', strtotime($l['start_date'])); ?></td>
                            <td><?php echo date('d M Y', strtotime($l['end_date'])); ?></td>
                            <td><?php echo substr($l['reason'], 0, 50); ?>...</td>
                            <td class="status-<?php echo $l['status']; ?>"><?php echo $l['status']; ?></td>
                            <td><?php echo $l['remarks'] ?: '-'; ?></td>
                            <?php if($user_role === 'admin' && $l['status'] === 'Pending'): ?>
                            <td>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="leave_id" value="<?php echo $l['id']; ?>">
                                    <input type="hidden" name="status" value="Approved">
                                    <input type="hidden" name="remarks" value="Approved by Admin">
                                    <button type="submit" name="approve_leave" class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="leave_id" value="<?php echo $l['id']; ?>">
                                    <input type="hidden" name="status" value="Rejected">
                                    <input type="text" name="remarks" placeholder="Reason" style="width:100px; padding:4px;">
                                    <button type="submit" name="approve_leave" class="btn btn-sm btn-danger">Reject</button>
                                </form>
                            </td>
                            <?php elseif($user_role === 'admin'): ?>
                            <td>-</td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($leaveRequests)): ?>
                        <tr><td colspan="<?php echo $user_role === 'admin' ? '9' : '7'; ?>" style="text-align:center;">No leave requests found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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