<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$user_role = $_SESSION['user_role'];
$message = '';
$error = '';

// Handle Allocate Fees to Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate_fees'])) {
    $student_id = $_POST['student_id'];
    $fee_type = $_POST['fee_type'];
    $amount = $_POST['amount'];
    $due_date = $_POST['due_date'];
    $discount = $_POST['discount'] ?? 0;
    
    $final_amount = $amount - $discount;
    
    $stmt = $pdo->prepare("INSERT INTO fee_allocations (student_id, fee_type, amount, discount, due_date, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->execute([$student_id, $fee_type, $final_amount, $discount, $due_date]);
    $message = "Fees allocated successfully! Discount applied: Rs $discount";
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
    
    $stmt = $pdo->prepare("INSERT INTO fee_payments (allocation_id, student_id, amount_paid, payment_date, payment_method, receipt_no, remarks, received_by) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?)");
    $stmt->execute([$allocation_id, $allocation['student_id'], $amount_paid, $payment_method, $receipt_no, $remarks, $_SESSION['user_id']]);
    
    if ($new_total >= $allocation['amount']) {
        $status = 'Paid';
    } else {
        $status = 'Partial';
    }
    
    $stmt = $pdo->prepare("UPDATE fee_allocations SET status = ? WHERE id = ?");
    $stmt->execute([$status, $allocation_id]);
    
    $message = "Payment recorded successfully! Receipt No: $receipt_no";
}

// Handle Apply Discount
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_discount'])) {
    $allocation_id = $_POST['allocation_id'];
    $discount_amount = $_POST['discount_amount'];
    $discount_reason = $_POST['discount_reason'];
    
    $stmt = $pdo->prepare("UPDATE fee_allocations SET discount = ?, discount_reason = ?, amount = amount - ? WHERE id = ?");
    $stmt->execute([$discount_amount, $discount_reason, $discount_amount, $allocation_id]);
    $message = "Discount of Rs $discount_amount applied! Reason: $discount_reason";
}

// Handle Bulk Allocate to Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_allocate'])) {
    $class_id = $_POST['class_id'];
    $fee_type = $_POST['fee_type'];
    $amount = $_POST['amount'];
    $due_date = $_POST['due_date'];
    
    $students = $pdo->prepare("SELECT id FROM students WHERE class_id = ?");
    $students->execute([$class_id]);
    
    foreach($students->fetchAll() as $student) {
        $stmt = $pdo->prepare("INSERT INTO fee_allocations (student_id, fee_type, amount, due_date, status) VALUES (?, ?, ?, ?, 'Pending')");
        $stmt->execute([$student['id'], $fee_type, $amount, $due_date]);
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
    $stmt = $pdo->prepare("INSERT INTO fee_payments (allocation_id, student_id, amount_paid, payment_date, payment_method, receipt_no, received_by) VALUES (?, ?, ?, CURDATE(), 'Cash', ?, ?)");
    $stmt->execute([$allocation_id, $allocation['student_id'], $allocation['amount'], $receipt_no, $_SESSION['user_id']]);
    
    $message = "Fee marked as paid! Receipt No: $receipt_no";
}

$students = $pdo->query("SELECT s.id, s.name, s.roll_no, c.class_name FROM students s JOIN classes c ON s.class_id = c.id ORDER BY s.roll_no")->fetchAll();
$classes = $pdo->query("SELECT id, class_name, section FROM classes")->fetchAll();

$allocations = $pdo->query("
    SELECT fa.*, s.name, s.roll_no, c.class_name,
           COALESCE(SUM(fp.amount_paid), 0) as paid_amount
    FROM fee_allocations fa
    JOIN students s ON fa.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN fee_payments fp ON fa.id = fp.allocation_id
    GROUP BY fa.id
    ORDER BY c.class_name, s.roll_no, fa.due_date ASC
")->fetchAll();

$summary = $pdo->query("
    SELECT 
        COUNT(DISTINCT student_id) as total_students,
        COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END), 0) as collected,
        COALESCE(SUM(CASE WHEN status IN ('Pending', 'Partial') THEN amount ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN status = 'Partial' THEN 1 ELSE 0 END), 0) as partial_count
    FROM fee_allocations
")->fetch();
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
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #ddd; }
        .stat-value { font-size: 28px; font-weight: bold; color: #2E86AB; }
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
        .status-overdue { color: #dc3545; font-weight: bold; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 500px; max-width: 90%; }
        body.dark-theme .modal-content { background: var(--card); }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        .table-container { overflow-x: auto; }
        .filter-row { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .form-row { grid-template-columns: 1fr; } th, td { font-size: 12px; padding: 6px; } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="<?php echo ($user_role === 'accountant') ? 'accountant_dashboard.php' : 'dashboard.php'; ?>" class="back-btn"> Back to Dashboard</a>
        <h1>Fee Management System</h1>
        
        <?php if($message): ?>
        <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo $summary['total_students']; ?></div><div>Total Students</div></div>
            <div class="stat-card"><div class="stat-value" style="color: #28a745;">Rs <?php echo number_format($summary['collected']); ?></div><div>Collected</div></div>
            <div class="stat-card"><div class="stat-value" style="color: #ffc107;">Rs <?php echo number_format($summary['pending']); ?></div><div>Pending</div></div>
            <div class="stat-card"><div class="stat-value" style="color: #17a2b8;"><?php echo $summary['partial_count']; ?></div><div>Partial Payments</div></div>
        </div>
        
        <?php if($user_role === 'admin' || $user_role === 'accountant'): ?>
        <!-- Allocate Fee to Student -->
        <div class="card">
            <h2>Allocate Fee to Student</h2>
            <form method="POST">
                <input type="hidden" name="allocate_fees" value="1">
                <div class="form-row">
                    <div class="form-group"><label>Student</label><select name="student_id" required><option value="">Select Student</option><?php foreach($students as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo $s['name']; ?> (<?php echo $s['roll_no']; ?> - <?php echo $s['class_name']; ?>)</option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Fee Type</label><input type="text" name="fee_type" placeholder="e.g., Monthly Tuition, Annual Fee" required></div>
                    <div class="form-group"><label>Amount (Rs)</label><input type="number" name="amount" step="0.01" placeholder="Amount" required></div>
                    <div class="form-group"><label>Discount (Rs)</label><input type="number" name="discount" step="0.01" placeholder="Discount amount" value="0"></div>
                    <div class="form-group"><label>Due Date</label><input type="date" name="due_date" required></div>
                </div>
                <button type="submit" class="btn">Allocate Fee</button>
            </form>
        </div>
        
        <!-- Bulk Allocate to Class -->
        <div class="card">
            <h2>Bulk Allocate to Class</h2>
            <form method="POST">
                <input type="hidden" name="bulk_allocate" value="1">
                <div class="form-row">
                    <div class="form-group"><label>Class</label><select name="class_id" required><option value="">Select Class</option><?php foreach($classes as $c): ?><option value="<?php echo $c['id']; ?>">Class <?php echo $c['class_name'] . ($c['section'] ? '-' . $c['section'] : ''); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Fee Type</label><input type="text" name="fee_type" placeholder="Fee Type" required></div>
                    <div class="form-group"><label>Amount (Rs)</label><input type="number" name="amount" step="0.01" required></div>
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
                <input type="text" id="searchFee" placeholder="Search by student name or roll number..." style="width:100%; padding:8px; margin-bottom:15px; border:1px solid #ddd; border-radius:6px;">
                <table>
                    <thead>
                        <tr><th>Student</th><th>Roll No</th><th>Class</th><th>Fee Type</th><th>Amount</th><th>Discount</th><th>Paid</th><th>Due</th><th>Due Date</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody id="feeTable">
                        <?php foreach($allocations as $a): 
                            $due_amount = $a['amount'] - $a['paid_amount'];
                            $status_class = '';
                            if($a['status'] == 'Paid') $status_class = 'status-paid';
                            elseif($a['status'] == 'Pending') $status_class = 'status-pending';
                            elseif($a['status'] == 'Partial') $status_class = 'status-partial';
                            else $status_class = 'status-overdue';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['name']); ?></td>
                            <td><?php echo $a['roll_no']; ?></td>
                            <td><?php echo $a['class_name']; ?></td>
                            <td><?php echo $a['fee_type']; ?></td>
                            <td>Rs <?php echo number_format($a['amount']); ?></td>
                            <td>Rs <?php echo number_format($a['discount'] ?? 0); ?></td>
                            <td>Rs <?php echo number_format($a['paid_amount']); ?></td>
                            <td>Rs <?php echo number_format($due_amount); ?></td>
                            <td><?php echo date('d M Y', strtotime($a['due_date'])); ?></td>                            <td class="<?php echo $status_class; ?>"><?php echo $a['status']; ?></td>
                            <td>
                                <?php if($a['status'] != 'Paid'): ?>
                                <button class="btn btn-sm btn-success" onclick="openPaymentModal(<?php echo $a['id']; ?>, '<?php echo addslashes($a['name']); ?>', <?php echo $due_amount; ?>)">Pay</button>
                                <button class="btn btn-sm btn-warning" onclick="openDiscountModal(<?php echo $a['id']; ?>, '<?php echo addslashes($a['name']); ?>', <?php echo $a['amount']; ?>, <?php echo $a['paid_amount']; ?>)">Discount</button>
                                <a href="?mark_paid=<?php echo $a['id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Mark as paid?')">Mark Paid</a>
                                <?php else: ?>
                                <span>✓</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Payment Modal -->
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
                <div style="display: flex; gap: 10px; margin-top: 20px;"><button type="submit" class="btn btn-success">Record Payment</button><button type="button" class="btn btn-danger" onclick="closeModal('paymentModal')">Cancel</button></div>
            </form>
        </div>
    </div>
    
    <!-- Discount Modal -->
    <div id="discountModal" class="modal">
        <div class="modal-content">
            <h3>Apply Discount</h3>
            <form method="POST">
                <input type="hidden" name="apply_discount" value="1">
                <input type="hidden" name="allocation_id" id="discount_allocation_id">
                <div class="form-group"><label>Student</label><input type="text" id="discount_student_name" readonly></div>
                <div class="form-group"><label>Total Amount</label><input type="text" id="discount_total_amount" readonly></div>
                <div class="form-group"><label>Already Paid</label><input type="text" id="discount_paid_amount" readonly></div>
                <div class="form-group"><label>Discount Amount (Rs)</label><input type="number" name="discount_amount" step="0.01" required></div>
                <div class="form-group"><label>Discount Reason</label><textarea name="discount_reason" rows="2" placeholder="Scholarship, Sibling discount, etc." required></textarea></div>
                <div style="display: flex; gap: 10px; margin-top: 20px;"><button type="submit" class="btn btn-warning">Apply Discount</button><button type="button" class="btn btn-danger" onclick="closeModal('discountModal')">Cancel</button></div>
            </form>
        </div>
    </div>
    
    <script>
        // Search functionality
        document.getElementById('searchFee')?.addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#feeTable tr');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
        
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
        
        function closeModal(modalId) { 
            document.getElementById(modalId).style.display = 'none'; 
        }
        
        function toggleTheme() { 
            document.body.classList.toggle('dark-theme'); 
            localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); 
        }
        
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
        
        window.onclick = function(event) { 
            const paymentModal = document.getElementById('paymentModal'); 
            const discountModal = document.getElementById('discountModal');
            if (event.target == paymentModal) paymentModal.style.display = 'none';
            if (event.target == discountModal) discountModal.style.display = 'none';
        }
    </script>
</body>
</html>