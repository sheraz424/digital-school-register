<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$user_role = $_SESSION['user_role'];
$message = '';
$error = '';

// Handle Allocate Fees to Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate_fees'])) {
    $student_id = $_POST['student_id'];
    $fee_structure_id = $_POST['fee_structure_id'];
    $amount = $_POST['amount'];
    $due_date = $_POST['due_date'];
    
    $stmt = $pdo->prepare("INSERT INTO fee_allocations (student_id, fee_structure_id, amount, due_date, status) VALUES (?, ?, ?, ?, 'Pending')");
    $stmt->execute([$student_id, $fee_structure_id, $amount, $due_date]);
    $message = "Fees allocated successfully!";
}

// Handle Record Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $allocation_id = $_POST['allocation_id'];
    $amount_paid = $_POST['amount_paid'];
    $payment_method = $_POST['payment_method'];
    $remarks = $_POST['remarks'];
    
    $stmt = $pdo->prepare("SELECT * FROM fee_allocations WHERE id = ?");
    $stmt->execute([$allocation_id]);
    $allocation = $stmt->fetch();
    
    $total_paid = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) as total FROM fee_payments WHERE allocation_id = ?");
    $total_paid->execute([$allocation_id]);
    $paid_so_far = $total_paid->fetch()['total'];
    
    $new_total = $paid_so_far + $amount_paid;
    $receipt_no = 'RCP' . date('Ymd') . rand(1000, 9999);
    
    $stmt = $pdo->prepare("INSERT INTO fee_payments (allocation_id, amount_paid, payment_date, payment_method, receipt_no, remarks, received_by) VALUES (?, ?, CURDATE(), ?, ?, ?, ?)");
    $stmt->execute([$allocation_id, $amount_paid, $payment_method, $receipt_no, $remarks, $_SESSION['user_id']]);
    
    $stmt = $pdo->prepare("INSERT INTO fee_transactions (student_id, fee_allocation_id, amount, payment_date, payment_method, receipt_no, remarks, recorded_by) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?)");
    $stmt->execute([$allocation['student_id'], $allocation_id, $amount_paid, $payment_method, $receipt_no, $remarks, $_SESSION['user_id']]);
    
    if ($new_total >= $allocation['amount']) {
        $status = 'Paid';
    } else {
        $status = 'Partial';
    }
    
    $stmt = $pdo->prepare("UPDATE fee_allocations SET status = ? WHERE id = ?");
    $stmt->execute([$status, $allocation_id]);
    
    $message = "Payment recorded successfully! Receipt No: $receipt_no";
}

// Handle Bulk Allocate to Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_allocate'])) {
    $class_id = $_POST['class_id'];
    $fee_structure_id = $_POST['fee_structure_id'];
    $due_date = $_POST['due_date'];
    
    $students = $pdo->prepare("SELECT id FROM students WHERE class_id = ?");
    $students->execute([$class_id]);
    
    foreach($students->fetchAll() as $student) {
        $stmt = $pdo->prepare("INSERT INTO fee_allocations (student_id, fee_structure_id, amount, due_date, status) 
                               SELECT ?, ?, amount, ?, 'Pending' FROM fee_structure WHERE id = ?");
        $stmt->execute([$student['id'], $fee_structure_id, $due_date, $fee_structure_id]);
    }
    $message = "Fees allocated to all students in the class!";
}

// Handle Mark as Paid
if (isset($_GET['mark_paid'])) {
    $allocation_id = $_GET['mark_paid'];
    
    $stmt = $pdo->prepare("SELECT * FROM fee_allocations WHERE id = ?");
    $stmt->execute([$allocation_id]);
    $allocation = $stmt->fetch();
    
    $stmt = $pdo->prepare("UPDATE fee_allocations SET status = 'Paid' WHERE id = ?");
    $stmt->execute([$allocation_id]);
    
    $receipt_no = 'RCP' . date('Ymd') . rand(1000, 9999);
    $stmt = $pdo->prepare("INSERT INTO fee_payments (allocation_id, amount_paid, payment_date, payment_method, receipt_no, received_by) VALUES (?, ?, CURDATE(), 'Cash', ?, ?)");
    $stmt->execute([$allocation_id, $allocation['amount'], $receipt_no, $_SESSION['user_id']]);
    
    $stmt = $pdo->prepare("INSERT INTO fee_transactions (student_id, fee_allocation_id, amount, payment_date, payment_method, receipt_no, recorded_by) VALUES (?, ?, ?, CURDATE(), 'Cash', ?, ?)");
    $stmt->execute([$allocation['student_id'], $allocation_id, $allocation['amount'], $receipt_no, $_SESSION['user_id']]);
    
    $message = "Fee marked as paid! Receipt No: $receipt_no";
}

$students = $pdo->query("SELECT s.id, s.name, s.roll_no, c.class_name FROM students s JOIN classes c ON s.class_id = c.id ORDER BY c.class_name, s.roll_no")->fetchAll();
$fee_structures = $pdo->query("SELECT * FROM fee_structure WHERE is_active = 1")->fetchAll();
$classes = $pdo->query("SELECT id, class_name, section FROM classes")->fetchAll();

// Apply filters
$status_filter = $_GET['status'] ?? '';
$class_filter = $_GET['class_filter'] ?? '';
$search_filter = $_GET['search'] ?? '';

$query = "
    SELECT fa.*, s.name, s.roll_no, c.class_name, c.section, fs.fee_name, 
           COALESCE(SUM(fp.amount_paid), 0) as paid_amount
    FROM fee_allocations fa
    JOIN students s ON fa.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    JOIN fee_structure fs ON fa.fee_structure_id = fs.id
    LEFT JOIN fee_payments fp ON fa.id = fp.allocation_id
    WHERE 1=1
";

if ($status_filter) {
    $query .= " AND fa.status = '$status_filter'";
}
if ($class_filter) {
    $query .= " AND c.id = '$class_filter'";
}
if ($search_filter) {
    $query .= " AND (s.name LIKE '%$search_filter%' OR s.roll_no LIKE '%$search_filter%')";
}

$query .= " GROUP BY fa.id ORDER BY c.class_name, s.roll_no, fa.due_date ASC";

$allocations = $pdo->query($query)->fetchAll();

// Get summary data with filters
$summary_query = "
    SELECT 
        COUNT(DISTINCT student_id) as total_students,
        COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END), 0) as collected,
        COALESCE(SUM(CASE WHEN status IN ('Pending', 'Partial') THEN amount ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN status = 'Overdue' THEN amount ELSE 0 END), 0) as overdue,
        COALESCE(SUM(CASE WHEN status = 'Partial' THEN 1 ELSE 0 END), 0) as partial_count
    FROM fee_allocations
";
$summary = $pdo->query($summary_query)->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Management - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sora', sans-serif; background: var(--bg); color: var(--text); }
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 1400px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        body.dark-theme .card { background: var(--card); border-color: var(--border); }
        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #ddd; }
        body.dark-theme .stat-card { background: var(--card); border-color: var(--border); }
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
        .filter-row { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .status-paid { color: #28a745; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-partial { color: #17a2b8; font-weight: bold; }
        .status-overdue { color: #dc3545; font-weight: bold; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 500px; max-width: 90%; }
        body.dark-theme .modal-content { background: var(--card); }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        .table-container { overflow-x: auto; }
        .search-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .filter-row { flex-direction: column; } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn"> Back to Dashboard</a>
        <h1>Fee Management System</h1>
        
        <?php if($message): ?>
        <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Statistics Summary -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo $summary['total_students']; ?></div><div>Total Students</div></div>
            <div class="stat-card"><div class="stat-value" style="color: #28a745;">Rs <?php echo number_format($summary['collected']); ?></div><div>Collected</div></div>
            <div class="stat-card"><div class="stat-value" style="color: #ffc107;">Rs <?php echo number_format($summary['pending']); ?></div><div>Pending</div></div>
            <div class="stat-card"><div class="stat-value" style="color: #dc3545;">Rs <?php echo number_format($summary['overdue']); ?></div><div>Overdue</div></div>
            <div class="stat-card"><div class="stat-value" style="color: #17a2b8;"><?php echo $summary['partial_count']; ?></div><div>Partial Payments</div></div>
        </div>
        
        <!-- Filters -->
        <div class="card">
            <h3>Filters</h3>
            <div class="filter-row">
                <div class="filter-group">
                    <label>Search Student</label>
                    <input type="text" id="searchInput" class="search-input" placeholder="Search by name or roll number...">
                </div>
                <div class="filter-group">
                    <label>Filter by Status</label>
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Paid">Paid</option>
                        <option value="Partial">Partial</option>
                        <option value="Overdue">Overdue</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Filter by Class</label>
                    <select id="classFilter">
                        <option value="">All Classes</option>
                        <?php foreach($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>">Class <?php echo $c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button class="btn" onclick="applyFilters()">Apply Filters</button>
                    <button class="btn btn-warning" onclick="resetFilters()">Reset</button>
                </div>
            </div>
        </div>
        
        <?php if($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'accountant'): ?>
        <div class="card">
            <h2>Allocate Fee to Student</h2>
            <form method="POST">
                <input type="hidden" name="allocate_fees" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>Search Student</label>
                        <input type="text" id="studentSearch" class="search-input" placeholder="Type to search student...">
                        <select name="student_id" id="studentSelect" size="5" style="height: auto; margin-top: 5px;" required>
                            <option value="">Select Student</option>
                            <?php foreach($students as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo $s['name']; ?> (<?php echo $s['roll_no']; ?> - <?php echo $s['class_name']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Fee Type</label><select name="fee_structure_id" required><option value="">Select Fee Type</option><?php foreach($fee_structures as $fs): ?><option value="<?php echo $fs['id']; ?>"><?php echo $fs['fee_name']; ?> - Rs <?php echo number_format($fs['amount']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Amount</label><input type="number" name="amount" step="0.01" placeholder="Amount" required></div>
                    <div class="form-group"><label>Due Date</label><input type="date" name="due_date" required></div>
                </div>
                <button type="submit" class="btn">Allocate Fee</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Bulk Allocate to Class</h2>
            <form method="POST">
                <input type="hidden" name="bulk_allocate" value="1">
                <div class="form-row">
                    <div class="form-group"><label>Class</label><select name="class_id" required><option value="">Select Class</option><?php foreach($classes as $c): ?><option value="<?php echo $c['id']; ?>">Class <?php echo $c['class_name'] . ($c['section'] ? '-' . $c['section'] : ''); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Fee Type</label><select name="fee_structure_id" required><option value="">Select Fee Type</option><?php foreach($fee_structures as $fs): ?><option value="<?php echo $fs['id']; ?>"><?php echo $fs['fee_name']; ?> - Rs <?php echo number_format($fs['amount']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Due Date</label><input type="date" name="due_date" required></div>
                </div>
                <button type="submit" class="btn btn-success">Allocate to All Students in Class</button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Fee Allocations List -->
        <div class="card">
            <h2>Fee Ledger - All Allocations</h2>
            <div class="table-container">
                <table id="feeTable">
                    <thead>
                        <tr><th>Student</th><th>Roll No</th><th>Class</th><th>Fee Type</th><th>Amount</th><th>Paid</th><th>Due</th><th>Due Date</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($allocations as $a): $due_amount = $a['amount'] - $a['paid_amount']; $status_class = ''; if($a['status'] == 'Paid') $status_class = 'status-paid'; elseif($a['status'] == 'Pending') $status_class = 'status-pending'; elseif($a['status'] == 'Partial') $status_class = 'status-partial'; elseif($a['status'] == 'Overdue') $status_class = 'status-overdue'; ?>
                        <tr class="fee-row">
                            <td><?php echo htmlspecialchars($a['name']); ?></td>
                            <td><?php echo $a['roll_no']; ?></td>
                            <td><?php echo $a['class_name'] . ($a['section'] ? '-' . $a['section'] : ''); ?></td>
                            <td><?php echo $a['fee_name']; ?></td>
                            <td>Rs <?php echo number_format($a['amount']); ?></td>
                            <td>Rs <?php echo number_format($a['paid_amount']); ?></td>
                            <td>Rs <?php echo number_format($due_amount); ?></td>
                            <td><?php echo date('d M Y', strtotime($a['due_date'])); ?></td>
                            <td class="<?php echo $status_class; ?>"><?php echo $a['status']; ?></td>
                            <td><?php if($a['status'] != 'Paid'): ?><button class="btn btn-sm btn-success" onclick="openPaymentModal(<?php echo $a['id']; ?>, '<?php echo addslashes($a['name']); ?>', <?php echo $due_amount; ?>)">Pay</button><a href="?mark_paid=<?php echo $a['id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Mark as paid?')">Mark Paid</a><?php else: ?><span>Paid</span><?php endif; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <h3>Record Payment</h3>
            <form method="POST">
                <input type="hidden" name="record_payment" value="1">
                <input type="hidden" name="allocation_id" id="payment_allocation_id">
                <div class="form-group"><label>Student</label><input type="text" id="payment_student_name" readonly></div>
                <div class="form-group"><label>Amount Due</label><input type="text" id="payment_due_amount" readonly></div>
                <div class="form-group"><label>Amount Paying</label><input type="number" name="amount_paid" step="0.01" id="payment_amount" required></div>
                <div class="form-group"><label>Payment Method</label><select name="payment_method" required><option value="Cash">Cash</option><option value="Bank Transfer">Bank Transfer</option><option value="Credit Card">Credit Card</option><option value="Cheque">Cheque</option></select></div>
                <div class="form-group"><label>Remarks</label><textarea name="remarks" rows="2"></textarea></div>
                <div style="display: flex; gap: 10px; margin-top: 20px;"><button type="submit" class="btn btn-success">Record Payment</button><button type="button" class="btn btn-danger" onclick="closeModal()">Cancel</button></div>
            </form>
        </div>
    </div>
    
    <script>
        // Student search in allocation dropdown
        document.getElementById('studentSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let options = document.getElementById('studentSelect').options;
            for (let i = 0; i < options.length; i++) {
                let text = options[i].textContent.toLowerCase();
                options[i].style.display = text.includes(filter) ? '' : 'none';
            }
        });
        
        // Apply filters
        function applyFilters() {
            let search = document.getElementById('searchInput').value;
            let status = document.getElementById('statusFilter').value;
            let classFilter = document.getElementById('classFilter').value;
            window.location.href = `fee_management.php?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&class_filter=${encodeURIComponent(classFilter)}`;
        }
        
        function resetFilters() {
            window.location.href = 'fee_management.php';
        }
        
        function openPaymentModal(allocationId, studentName, dueAmount) {
            document.getElementById('payment_allocation_id').value = allocationId;
            document.getElementById('payment_student_name').value = studentName;
            document.getElementById('payment_due_amount').value = 'Rs ' + dueAmount.toLocaleString();
            document.getElementById('payment_amount').value = dueAmount;
            document.getElementById('paymentModal').style.display = 'flex';
        }
        
        function closeModal() { document.getElementById('paymentModal').style.display = 'none'; }
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
        window.onclick = function(event) { const modal = document.getElementById('paymentModal'); if (event.target == modal) modal.style.display = 'none'; }
        
        // Get URL parameters and set filter values
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('search')) document.getElementById('searchInput').value = urlParams.get('search');
        if (urlParams.get('status')) document.getElementById('statusFilter').value = urlParams.get('status');
        if (urlParams.get('class_filter')) document.getElementById('classFilter').value = urlParams.get('class_filter');
    </script>
</body>
</html>