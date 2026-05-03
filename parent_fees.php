<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'parent') {
    header('Location: dashboard.php');
    exit;
}

$user_email = $_SESSION['user_email'];

$children = $pdo->prepare("SELECT s.*, c.class_name, c.section FROM students s JOIN classes c ON s.class_id = c.id WHERE s.parent_email = ?");
$children->execute([$user_email]);
$children = $children->fetchAll();

if (empty($children)) {
    $demo_student = $pdo->query("SELECT s.*, c.class_name, c.section FROM students s JOIN classes c ON s.class_id = c.id LIMIT 1")->fetch();
    if ($demo_student) { $children = [$demo_student]; $demo_mode = true; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Payment - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 800px; margin: 0 auto; }
        .child-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        body.dark-theme .child-card { background: var(--card); border-color: var(--border); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: var(--bg); }
        .pay-btn { background: var(--teal); color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: var(--accent); color: white; text-decoration: none; border-radius: 8px; }
        .status-paid { color: green; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .total { font-size: 18px; font-weight: bold; margin-top: 15px; padding-top: 10px; border-top: 2px solid var(--border); }
        .demo-note { background: #fff3cd; color: #856404; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="dashboard.php" class="back-btn"> Back to Dashboard</a>
        <h1>Fee Payment Portal</h1>
        
        <?php if(isset($demo_mode)): ?><div class="demo-note"> No children linked to your account. Showing demo student. Contact admin to link your email.</div><?php endif; ?>
        
        <?php foreach($children as $child): 
            $fees = $pdo->prepare("SELECT * FROM fee_allocations WHERE student_id = ? ORDER BY due_date");
            $fees->execute([$child['id']]); $fees = $fees->fetchAll();
            $total_due = 0; $total_paid = 0;
            foreach($fees as $fee) { if ($fee['status'] == 'Paid') { $total_paid += $fee['amount']; } else { $total_due += $fee['amount']; } }
        ?>
        <div class="child-card">
            <h3><?php echo htmlspecialchars($child['name']); ?> (Roll No: <?php echo $child['roll_no']; ?>)</h3>
            <p>Class: <?php echo $child['class_name'] . ($child['section'] ? '-' . $child['section'] : ''); ?></p>
            <?php if(empty($fees)): ?><p>No fee records found for this student.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>Fee Type</th><th>Amount (Rs)</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead>
                <tbody><?php foreach($fees as $fee): ?>
                <tr>
                    <td><?php echo $fee['fee_type']; ?></td>
                    <td>Rs <?php echo number_format($fee['amount']); ?></td>
                    <td><?php echo date('d M Y', strtotime($fee['due_date'])); ?></td>
                    <td class="status-<?php echo strtolower($fee['status']); ?>"><?php echo $fee['status']; ?></td>
                    <td><?php if($fee['status'] == 'Pending'): ?><button class="pay-btn" onclick="payFee(<?php echo $fee['id']; ?>, <?php echo $fee['amount']; ?>, '<?php echo $fee['fee_type']; ?>')">Pay Now</button><?php else: ?>Paid on <?php echo $fee['payment_date'] ? date('d M Y', strtotime($fee['payment_date'])) : '-'; ?><?php endif; ?></td>
                </tr><?php endforeach; ?></tbody>
            </table>
            <div class="total">Total Due: Rs <?php echo number_format($total_due); ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <script>
        function payFee(feeId, amount, feeType) { if (confirm(`Pay Rs ${amount} for ${feeType}?`)) { fetch('api/pay_fee.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ fee_id: feeId }) }).then(r => r.json()).then(result => { alert(result.message); if (result.success) location.reload(); }); } }
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>