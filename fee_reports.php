<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Get fee summary
$feeSummary = $pdo->query("
    SELECT 
        COUNT(DISTINCT student_id) as total_students,
        COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END), 0) as collected,
        COALESCE(SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN status = 'Overdue' THEN amount ELSE 0 END), 0) as overdue
    FROM fees
")->fetch();

// Get all students with their fee status
$students = $pdo->query("
    SELECT s.id, s.roll_no, s.name, c.class_name, c.section,
           COALESCE(SUM(CASE WHEN f.status = 'Paid' THEN f.amount ELSE 0 END), 0) as paid,
           COALESCE(SUM(CASE WHEN f.status IN ('Pending', 'Overdue') THEN f.amount ELSE 0 END), 0) as due,
           COALESCE(SUM(f.amount), 0) as total_fees
    FROM students s
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN fees f ON s.id = f.student_id
    GROUP BY s.id
    ORDER BY c.class_name, s.roll_no
")->fetchAll();

// Handle mark as paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $student_id = $_POST['student_id'];
    $stmt = $pdo->prepare("UPDATE fees SET status = 'Paid', payment_date = CURDATE() WHERE student_id = ?");
    $stmt->execute([$student_id]);
    header('Location: fee_reports.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Reports - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 16px; border: 1px solid #ddd; text-align: center; }
        .stat-value { font-size: 28px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: var(--bg); }
        .back-btn { background: var(--blue); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block; margin-bottom: 20px; }
        .status-paid { color: green; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .status-overdue { color: red; font-weight: bold; }
        .search-box { padding: 10px; width: 300px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .pay-btn { background: var(--teal); color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>Fee Reports</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $feeSummary['total_students'] ?? 0; ?></div>
                <div>Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: green;">Rs <?php echo number_format($feeSummary['collected'] ?? 0); ?></div>
                <div>Collected</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: orange;">Rs <?php echo number_format($feeSummary['pending'] ?? 0); ?></div>
                <div>Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: red;">Rs <?php echo number_format($feeSummary['overdue'] ?? 0); ?></div>
                <div>Overdue</div>
            </div>
        </div>
        
        <input type="text" id="search" class="search-box" placeholder="Search by student name or roll no..." onkeyup="filterTable()">
        
        <form method="POST" id="markPaidForm">
            <input type="hidden" name="student_id" id="paid_student_id">
            <input type="hidden" name="mark_paid" value="1">
        </form>
        
        <table>
            <thead>
                <tr><th>Roll No</th><th>Student Name</th><th>Class</th><th>Total Fees</th><th>Paid</th><th>Due</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody id="feeTable">
                <?php foreach($students as $s): 
                    $status = $s['due'] == 0 ? 'Paid' : ($s['due'] > 0 ? 'Pending' : 'Pending');
                    $statusClass = strtolower($status);
                ?>
                <tr>
                    <td><?php echo $s['roll_no']; ?></td>
                    <td><?php echo $s['name']; ?></td>
                    <td><?php echo $s['class_name'] . ($s['section'] ? '-' . $s['section'] : ''); ?></td>
                    <td>Rs <?php echo number_format($s['total_fees']); ?></td>
                    <td>Rs <?php echo number_format($s['paid']); ?></td>
                    <td>Rs <?php echo number_format($s['due']); ?></td>
                    <td class="status-<?php echo $statusClass; ?>"><?php echo $status; ?></td>
                    <td>
                        <?php if($status != 'Paid' && $s['due'] > 0): ?>
                        <button class="pay-btn" onclick="markAsPaid(<?php echo $s['id']; ?>)">Mark Paid</button>
                        <?php else: ?>
                        ✓
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <script>
        function filterTable() {
            const input = document.getElementById('search');
            const filter = input.value.toLowerCase();
            const rows = document.getElementById('feeTable').getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const name = rows[i].getElementsByTagName('td')[1]?.innerText.toLowerCase() || '';
                const roll = rows[i].getElementsByTagName('td')[0]?.innerText.toLowerCase() || '';
                rows[i].style.display = (name.includes(filter) || roll.includes(filter)) ? '' : 'none';
            }
        }
        
        function markAsPaid(studentId) {
            if (confirm('Mark all fees as paid for this student?')) {
                document.getElementById('paid_student_id').value = studentId;
                document.getElementById('markPaidForm').submit();
            }
        }
    </script>
</body>
</html>