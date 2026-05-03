<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'accountant') {
    header('Location: dashboard.php');
    exit;
}

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM fee_allocations WHERE status != 'Paid'");
$pendingFees = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM fee_allocations WHERE status != 'Paid'");
$pendingAmount = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM fee_payments WHERE DATE(payment_date) = CURDATE()");
$todayCollection = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM fee_payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
$monthCollection = $stmt->fetch()['total'];

$transactions = $pdo->query("
    SELECT ft.*, s.name as student_name, s.roll_no, u.full_name as collected_by
    FROM fee_transactions ft
    JOIN students s ON ft.student_id = s.id
    JOIN users u ON ft.recorded_by = u.id
    ORDER BY ft.created_at DESC LIMIT 20
")->fetchAll();

$dueFees = $pdo->query("
    SELECT fa.*, s.name, s.roll_no, c.class_name, COALESCE(SUM(fp.amount_paid), 0) as paid_amount
    FROM fee_allocations fa
    JOIN students s ON fa.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN fee_payments fp ON fa.id = fp.allocation_id
    WHERE fa.status IN ('Pending', 'Partial')
    GROUP BY fa.id ORDER BY fa.due_date ASC
")->fetchAll();

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM fee_settings");
while($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $allocation_id = $_POST['allocation_id'];
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $remarks = $_POST['remarks'];
    
    $stmt = $pdo->prepare("SELECT * FROM fee_allocations WHERE id = ?");
    $stmt->execute([$allocation_id]);
    $allocation = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) as total FROM fee_payments WHERE allocation_id = ?");
    $stmt->execute([$allocation_id]);
    $paid = $stmt->fetch()['total'];
    
    $new_total = $paid + $amount;
    $receipt_no = 'RCP' . date('Ymd') . rand(1000, 9999);
    
    $stmt = $pdo->prepare("INSERT INTO fee_transactions (student_id, fee_allocation_id, amount, payment_date, payment_method, receipt_no, remarks, recorded_by) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?)");
    $stmt->execute([$allocation['student_id'], $allocation_id, $amount, $payment_method, $receipt_no, $remarks, $user_id]);
    
    $stmt = $pdo->prepare("INSERT INTO fee_payments (allocation_id, amount_paid, payment_date, payment_method, receipt_no, remarks, received_by) VALUES (?, ?, CURDATE(), ?, ?, ?, ?)");
    $stmt->execute([$allocation_id, $amount, $payment_method, $receipt_no, $remarks, $user_id]);
    
    if ($new_total >= $allocation['amount']) { $status = 'Paid'; } else { $status = 'Partial'; }
    
    $stmt = $pdo->prepare("UPDATE fee_allocations SET status = ? WHERE id = ?");
    $stmt->execute([$status, $allocation_id]);
    
    $message = "Payment recorded! Receipt No: $receipt_no";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_discount'])) {
    $allocation_id = $_POST['allocation_id'];
    $discount_amount = $_POST['discount_amount'];
    $discount_reason = $_POST['discount_reason'];
    
    $stmt = $pdo->prepare("UPDATE fee_allocations SET discount_amount = ?, discount_reason = ? WHERE id = ?");
    $stmt->execute([$discount_amount, $discount_reason, $allocation_id]);
    
    $message = "Discount applied successfully!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    foreach($_POST['settings'] as $key => $value) {
        $stmt = $pdo->prepare("UPDATE fee_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
    }
    $message = "Settings updated successfully!";
}
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
        body.dark-theme input, body.dark-theme select, body.dark-theme textarea { background: #0f0f23; border-color: var(--border); color: var(--text); }
        .container { padding: 20px; max-width: 1400px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #ddd; }
        .stat-value { font-size: 28px; font-weight: bold; }
        .btn { background: #2E86AB; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; margin: 5px; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        select, input, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .status-paid { color: #28a745; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-partial { color: #17a2b8; font-weight: bold; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 500px; max-width: 90%; }
        body.dark-theme .modal-content { background: var(--card); }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        .sidebar { width: 250px; background: #0F2447; min-height: 100vh; position: fixed; left: 0; top: 0; }
        .main-content { margin-left: 250px; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    
    <aside class="sidebar">
        <div class="sidebar-brand" style="padding: 20px;"><div class="sb-logo" style="color: white; font-size: 24px;">DSR</div><span class="sb-sub" style="color: rgba(255,255,255,0.6);">Accountant Portal</span></div>
        <nav style="padding: 16px;">
            <a href="accountant_dashboard.php" style="display: block; padding: 10px; color: white; text-decoration: none; background: rgba(255,255,255,0.1); border-radius: 8px;">Dashboard</a>
            <a href="fee_management.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">Fee Management</a>
            <a href="export_reports.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">Reports</a>
            <a href="change_password.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 5px;">Change Password</a>
            <a href="logout.php" style="display: block; padding: 10px; color: rgba(255,255,255,0.7); text-decoration: none; margin-top: 20px;">Logout</a>
        </nav>
        <div class="sidebar-profile" style="position: absolute; bottom: 0; padding: 20px;">
            <div class="profile-avatar" style="background: #2E86AB;"><?php echo substr($user_name, 0, 1); ?></div>
            <div class="profile-info"><span class="profile-name" style="color: white;"><?php echo htmlspecialchars($user_name); ?></span><span class="profile-role" style="color: rgba(255,255,255,0.6);">Accountant</span></div>
        </div>
    </aside>

    <main class="main-content">
        <div class="container">
            <h1>Accountant Dashboard</h1>
            <?php if($message): ?><div class="success"><?php echo $message; ?></div><?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value" style="color: #ffc107;"><?php echo $pendingFees; ?></div><div>Pending Allocations</div></div>
                <div class="stat-card"><div class="stat-value" style="color: #dc3545;">Rs <?php echo number_format($pendingAmount); ?></div><div>Pending Amount</div></div>
                <div class="stat-card"><div class="stat-value" style="color: #28a745;">Rs <?php echo number_format($todayCollection); ?></div><div>Today's Collection</div></div>
                <div class="stat-card"><div class="stat-value" style="color: #17a2b8;">Rs <?php echo number_format($monthCollection); ?></div><div>This Month</div></div>
            </div>
            
            <div class="card"><h2>Fee Settings</h2>
                <form method="POST"><input type="hidden" name="update_settings" value="1">
                    <div class="form-row">
                        <div class="form-group"><label>Late Fee Percentage (%)</label><input type="number" name="settings[late_fee_percentage]" value="<?php echo $settings['late_fee_percentage'] ?? '5'; ?>" step="0.01"></div>
                        <div class="form-group"><label>Default Discount (%)</label><input type="number" name="settings[discount_percentage]" value="<?php echo $settings['discount_percentage'] ?? '10'; ?>" step="0.01"></div>
                        <div class="form-group"><label>Tax Percentage (%)</label><input type="number" name="settings[tax_percentage]" value="<?php echo $settings['tax_percentage'] ?? '0'; ?>" step="0.01"></div>
                        <div class="form-group"><label>Default Due Days</label><input type="number" name="settings[default_due_days]" value="<?php echo $settings['default_due_days'] ?? '15'; ?>"></div>
                    </div>
                    <button type="submit" class="btn">Save Settings</button>
                </form>
            </div>
            
            <div class="card"><h2>Due Fees List</h2>
                <div style="overflow-x: auto;">
                    <table><thead><tr><th>Roll No</th><th>Student Name</th><th>Class</th><th>Amount</th><th>Paid</th><th>Due</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody><?php foreach($dueFees as $fee): $due = $fee['amount'] - $fee['paid_amount']; ?>
                    <tr><td><?php echo $fee['roll_no']; ?></td><td><?php echo $fee['name']; ?></td><td><?php echo $fee['class_name']; ?></td><td>Rs <?php echo number_format($fee['amount']); ?></td><td>Rs <?php echo number_format($fee['paid_amount']); ?></td><td style="color: #dc3545;">Rs <?php echo number_format($due); ?></td><td><?php echo date('d M Y', strtotime($fee['due_date'])); ?></td><td class="status-<?php echo strtolower($fee['status']); ?>"><?php echo $fee['status']; ?></td>
                    <td><button class="btn btn-sm btn-success" onclick="openPaymentModal(<?php echo $fee['id']; ?>, '<?php echo $fee['name']; ?>', <?php echo $due; ?>)">Receive Payment</button><button class="btn btn-sm btn-warning" onclick="openDiscountModal(<?php echo $fee['id']; ?>, '<?php echo $fee['name']; ?>', <?php echo $fee['amount']; ?>, <?php echo $fee['paid_amount']; ?>)">Apply Discount</button></td></tr>
                    <?php endforeach; ?></tbody></table>
                </div>
            </div>
            
            <div class="card"><h2>Recent Transactions</h2>
                <table><thead><tr><th>Receipt No</th><th>Student</th><th>Roll No</th><th>Amount</th><th>Payment Method</th><th>Date</th><th>Collected By</th></tr></thead>
                <tbody><?php foreach($transactions as $t): ?>
                <tr><td><?php echo $t['receipt_no']; ?></td><td><?php echo $t['student_name']; ?></td><td><?php echo $t['roll_no']; ?></td><td>Rs <?php echo number_format($t['amount']); ?></td><td><?php echo $t['payment_method']; ?></td><td><?php echo date('d M Y', strtotime($t['payment_date'])); ?></td><td><?php echo $t['collected_by']; ?></td></tr>
                <?php endforeach; ?></tbody></table>
            </div>
        </div>
    </main>
    
    <div id="paymentModal" class="modal"><div class="modal-content"><h3>Record Payment</h3>
        <form method="POST"><input type="hidden" name="record_payment" value="1"><input type="hidden" name="allocation_id" id="payment_allocation_id">
        <div class="form-group"><label>Student</label><input type="text" id="payment_student_name" readonly></div>
        <div class="form-group"><label>Due Amount</label><input type="text" id="payment_due_amount" readonly></div>
        <div class="form-group"><label>Amount Receiving</label><input type="number" name="amount" step="0.01" id="payment_amount" required></div>
        <div class="form-group"><label>Payment Method</label><select name="payment_method" required><option value="Cash">Cash</option><option value="Bank Transfer">Bank Transfer</option><option value="Credit Card">Credit Card</option><option value="Cheque">Cheque</option><option value="Online">Online Transfer</option></select></div>
        <div class="form-group"><label>Remarks</label><textarea name="remarks" rows="2"></textarea></div>
        <div style="display: flex; gap: 10px; margin-top: 20px;"><button type="submit" class="btn btn-success">Record Payment</button><button type="button" class="btn btn-danger" onclick="closeModal()">Cancel</button></div></form>
    </div></div>
    
    <div id="discountModal" class="modal"><div class="modal-content"><h3>Apply Discount</h3>
        <form method="POST"><input type="hidden" name="add_discount" value="1"><input type="hidden" name="allocation_id" id="discount_allocation_id">
        <div class="form-group"><label>Student</label><input type="text" id="discount_student_name" readonly></div>
        <div class="form-group"><label>Total Amount</label><input type="text" id="discount_total_amount" readonly></div>
        <div class="form-group"><label>Already Paid</label><input type="text" id="discount_paid_amount" readonly></div>
        <div class="form-group"><label>Discount Amount (Rs)</label><input type="number" name="discount_amount" step="0.01" required></div>
        <div class="form-group"><label>Discount Reason</label><textarea name="discount_reason" rows="2" placeholder="Scholarship, Sibling discount, etc." required></textarea></div>
        <div style="display: flex; gap: 10px; margin-top: 20px;"><button type="submit" class="btn btn-warning">Apply Discount</button><button type="button" class="btn btn-danger" onclick="closeDiscountModal()">Cancel</button></div></form>
    </div></div>
    
    <script>
        function openPaymentModal(allocationId, studentName, dueAmount) {
            document.getElementById('payment_allocation_id').value = allocationId;
            document.getElementById('payment_student_name').value = studentName;
            document.getElementById('payment_due_amount').value = 'Rs ' + dueAmount.toLocaleString();
            document.getElementById('payment_amount').value = dueAmount;
            document.getElementById('paymentModal').style.display = 'flex';
        }
        function openDiscountModal(allocationId, studentName, totalAmount, paidAmount) {
            document.getElementById('discount_allocation_id').value = allocationId;
            document.getElementById('discount_student_name').value = studentName;
            document.getElementById('discount_total_amount').value = 'Rs ' + totalAmount.toLocaleString();
            document.getElementById('discount_paid_amount').value = 'Rs ' + paidAmount.toLocaleString();
            document.getElementById('discountModal').style.display = 'flex';
        }
        function closeModal() { document.getElementById('paymentModal').style.display = 'none'; }
        function closeDiscountModal() { document.getElementById('discountModal').style.display = 'none'; }
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
        window.onclick = function(event) { if (event.target == document.getElementById('paymentModal')) closeModal(); if (event.target == document.getElementById('discountModal')) closeDiscountModal(); }
    </script>
</body>
</html>