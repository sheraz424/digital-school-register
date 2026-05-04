<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'accountant') {
    header('Location: dashboard.php');
    exit;
}

$user_name = $_SESSION['user_name'];

// Get statistics
$pendingFees = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM fee_allocations WHERE status IN ('Pending', 'Partial')")->fetchColumn();
$totalCollected = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments")->fetchColumn();
$todayCollection = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE DATE(payment_date) = CURDATE()")->fetchColumn();
$monthCollection = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())")->fetchColumn();

// Get pending salaries count for notification
$pendingSalaries = $pdo->query("SELECT COUNT(*) FROM salaries WHERE status = 'Pending'")->fetchColumn();
$pendingSalaryAmount = $pdo->query("SELECT COALESCE(SUM(net_amount), 0) FROM salaries WHERE status = 'Pending'")->fetchColumn();

// Get recent payments
$recentPayments = $pdo->query("
    SELECT fp.*, s.name, s.roll_no 
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    ORDER BY fp.created_at DESC 
    LIMIT 20
")->fetchAll();

// Get pending salaries list
$pendingSalariesList = $pdo->query("
    SELECT s.*, 
           CASE WHEN s.staff_type = 'teacher' THEN (SELECT full_name FROM users WHERE id = s.staff_id)
                WHEN s.staff_type = 'staff' THEN (SELECT name FROM staff WHERE id = s.staff_id)
           END as staff_name
    FROM salaries s
    WHERE s.status = 'Pending'
    ORDER BY s.created_at DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Accountant Dashboard - DSR</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        body.dark-theme .card, body.dark-theme .stat-card { background: var(--card); border-color: var(--border); }
        .container { padding: 20px; max-width: 1400px; margin: 0 auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #ddd; }
        .stat-value { font-size: 28px; font-weight: bold; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; }
        .btn { background: #2E86AB; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; font-size: 13px; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .btn-success { background: #28a745; }
        .pay-btn { background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 11px; display: inline-block; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        .sidebar { width: 250px; background: #0F2447; min-height: 100vh; position: fixed; left: 0; top: 0; overflow-y: auto; }
        .main-content { margin-left: 250px; }
        .sidebar-brand { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sb-logo { font-size: 24px; font-weight: bold; color: white; letter-spacing: 2px; }
        .sb-sub { font-size: 10px; color: rgba(255,255,255,0.5); }
        .nav-item { display: block; padding: 10px 20px; color: rgba(255,255,255,0.7); text-decoration: none; margin: 2px 0; }
        .nav-item:hover { background: rgba(255,255,255,0.1); color: white; }
        .nav-section { padding: 10px 20px 5px; font-size: 11px; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1px; }
        .search-box { width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="sb-logo">DSR</div>
            <div class="sb-sub">Accountant Portal</div>
        </div>
        
        <div class="nav-section">Main</div>
        <a href="accountant_dashboard.php" class="nav-item" style="background: rgba(255,255,255,0.1); color: white;">Dashboard</a>
        
        <div class="nav-section">Finance</div>
        <a href="fee_management.php" class="nav-item">Fee Management</a>
        <a href="fee_reports.php" class="nav-item">Fee Reports</a>
        <a href="salary_management.php" class="nav-item">Salary Management</a>
        
        <div class="nav-section">Reports</div>
        <a href="export_reports.php" class="nav-item">Export Reports</a>
        
        <div class="nav-section">Account</div>
        <a href="change_password.php" class="nav-item">Change Password</a>
        <a href="logout.php" class="nav-item">Logout</a>
    </aside>

    <main class="main-content">
        <div class="container">
            <h1>Accountant Dashboard</h1>
            <p>Welcome, <strong><?php echo htmlspecialchars($user_name); ?></strong>!</p>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value" style="color: #28a745;">Rs <?php echo number_format($totalCollected); ?></div><div>Total Collected</div></div>
                <div class="stat-card"><div class="stat-value" style="color: #ffc107;">Rs <?php echo number_format($pendingFees); ?></div><div>Pending Fees</div></div>
                <div class="stat-card"><div class="stat-value" style="color: #17a2b8;">Rs <?php echo number_format($todayCollection); ?></div><div>Today's Collection</div></div>
                <div class="stat-card"><div class="stat-value" style="color: #2E86AB;">Rs <?php echo number_format($monthCollection); ?></div><div>This Month</div></div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value" style="color: #ffc107;"><?php echo $pendingSalaries; ?></div><div>Pending Salaries</div></div>
                <div class="stat-card"><div class="stat-value" style="color: #dc3545;">Rs <?php echo number_format($pendingSalaryAmount); ?></div><div>Pending Salary Amount</div></div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header"><h3>Quick Actions</h3></div>
                <div>
                    <a href="fee_management.php" class="btn">💰 Record Payment</a>
                    <a href="fee_reports.php" class="btn">📊 View Fee Reports</a>
                    <a href="salary_management.php" class="btn">💰 Process Salaries</a>
                    <a href="export_reports.php" class="btn">📎 Export Reports</a>
                </div>
            </div>
            
            <!-- Pending Salaries -->
            <div class="card">
                <div class="card-header"><h3>Pending Salary Payments</h3><a href="salary_management.php" class="btn btn-sm" style="background: #6c757d;">View All</a></div>
                <div style="overflow-x: auto;">
                    <tr>
                        <thead>
                            <tr><th>Staff Name</th><th>Type</th><th>Amount</th><th>Month</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($pendingSalariesList as $salary): ?>
                            <tr>
                                <td><?php echo $salary['staff_name']; ?></td>
                                <td><?php echo ucfirst($salary['staff_type']); ?></td>
                                <td>Rs <?php echo number_format($salary['net_amount']); ?></td>
                                <td><?php echo date('M Y', strtotime($salary['month'])); ?></td>
                                <td><a href="salary_management.php?pay=<?php echo $salary['id']; ?>" class="pay-btn">Mark Paid</a></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($pendingSalariesList)): ?>
                            <tr><td colspan="5" style="text-align: center;">No pending salary payments</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Payments -->
            <div class="card">
                <div class="card-header"><h3>Recent Fee Payments</h3><a href="fee_reports.php" class="btn btn-sm" style="background: #6c757d;">View All</a></div>
                <div style="overflow-x: auto;">
                    <input type="text" id="searchPayments" class="search-box" placeholder="Search by student name...">
                    <table>
                        <thead>
                            <tr><th>Receipt No</th><th>Student</th><th>Roll No</th><th>Amount</th><th>Payment Method</th><th>Date</th></tr>
                        </thead>
                        <tbody id="paymentsTable">
                            <?php foreach($recentPayments as $p): ?>
                            <tr>
                                <td><?php echo $p['receipt_no']; ?></td>
                                <td><?php echo $p['name']; ?></td>
                                <td><?php echo $p['roll_no']; ?></td>
                                <td>Rs <?php echo number_format($p['amount_paid']); ?></td>
                                <td><?php echo $p['payment_method']; ?></td>
                                <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Search functionality for payments
        document.getElementById('searchPayments')?.addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#paymentsTable tr');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
        
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>