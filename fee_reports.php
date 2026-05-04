<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$user_role = $_SESSION['user_role'];

// Get fee summary
$feeSummary = $pdo->query("
    SELECT 
        COUNT(DISTINCT student_id) as total_students,
        COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END), 0) as collected,
        COALESCE(SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN status = 'Overdue' THEN amount ELSE 0 END), 0) as overdue
    FROM fee_allocations
")->fetch();

// Get class filter
$class_filter = $_GET['class_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';

$query = "
    SELECT s.id, s.roll_no, s.name, c.class_name, c.section,
           COALESCE(SUM(CASE WHEN f.status = 'Paid' THEN f.amount ELSE 0 END), 0) as paid,
           COALESCE(SUM(CASE WHEN f.status IN ('Pending', 'Overdue') THEN f.amount ELSE 0 END), 0) as due,
           COALESCE(SUM(f.amount), 0) as total_fees,
           MAX(CASE WHEN f.status = 'Pending' THEN f.due_date END) as due_date
    FROM students s
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN fee_allocations f ON s.id = f.student_id
    WHERE 1=1
";

if ($class_filter) {
    $query .= " AND c.id = '$class_filter'";
}
if ($search_filter) {
    $query .= " AND (s.name LIKE '%$search_filter%' OR s.roll_no LIKE '%$search_filter%')";
}

$query .= " GROUP BY s.id ORDER BY c.class_name, s.roll_no";

$students = $pdo->query($query)->fetchAll();

$classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY class_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Reports - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #ddd; }
        .stat-value { font-size: 28px; font-weight: bold; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .btn { background: #2E86AB; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; margin: 5px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .status-paid { color: green; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .filter-row { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 12px; }
        select, input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="<?php echo ($user_role === 'principal') ? 'principal_dashboard.php' : 'dashboard.php'; ?>" class="back-btn">← Back to Dashboard</a>
        <h1>Fee Reports</h1>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo $feeSummary['total_students'] ?? 0; ?></div><div>Total Students</div></div>
            <div class="stat-card"><div class="stat-value" style="color: green;">Rs <?php echo number_format($feeSummary['collected'] ?? 0); ?></div><div>Collected</div></div>
            <div class="stat-card"><div class="stat-value" style="color: orange;">Rs <?php echo number_format($feeSummary['pending'] ?? 0); ?></div><div>Pending</div></div>
                       <div class="stat-card"><div class="stat-value" style="color: red;">Rs <?php echo number_format($feeSummary['overdue'] ?? 0); ?></div><div>Overdue</div></div>
        </div>
        
        <!-- Filters -->
        <div class="card">
            <h3>Filters</h3>
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search Student</label>
                        <input type="text" name="search" placeholder="By name or roll number..." value="<?php echo htmlspecialchars($search_filter); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Filter by Class</label>
                        <select name="class_id">
                            <option value="">All Classes</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($class_filter == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo $c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Filter by Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="Paid" <?php echo ($status_filter == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                            <option value="Pending" <?php echo ($status_filter == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Overdue" <?php echo ($status_filter == 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="fee_reports.php" class="btn" style="background: #6c757d;">Reset</a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Fee Table -->
        <div class="card">
            <h3>Fee Details</h3>
            <div style="overflow-x: auto;">
                <input type="text" id="tableSearch" placeholder="Search in table..." style="width:100%; padding:8px; margin-bottom:15px; border:1px solid #ddd; border-radius:6px;">
                <table>
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Total Fees (Rs)</th>
                            <th>Paid (Rs)</th>
                            <th>Due (Rs)</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <?php if($user_role === 'admin' || $user_role === 'accountant'): ?>
                            <th>Action</th>
                            <?php endif; ?>
                        </thead>
                        <tbody id="feeTable">
                            <?php foreach($students as $s): 
                                $status = 'Pending';
                                if($s['due'] == 0) $status = 'Paid';
                                elseif($s['due_date'] && strtotime($s['due_date']) < time()) $status = 'Overdue';
                                $statusClass = strtolower($status);
                            ?>
                            <tr>
                                <td><?php echo $s['roll_no']; ?></td>
                                <td><?php echo $s['name']; ?></td>
                                <td><?php echo $s['class_name'] . ($s['section'] ? '-' . $s['section'] : ''); ?></td>
                                <td>Rs <?php echo number_format($s['total_fees']); ?></td>
                                <td>Rs <?php echo number_format($s['paid']); ?></td>
                                <td>Rs <?php echo number_format($s['due']); ?></td>
                                <td><?php echo $s['due_date'] ? date('d M Y', strtotime($s['due_date'])) : '-'; ?></td>
                                <td class="status-<?php echo $statusClass; ?>"><?php echo $status; ?></td>
                                <?php if($user_role === 'admin' || $user_role === 'accountant'): ?>
                                <td>
                                    <?php if($status != 'Paid'): ?>
                                    <button class="btn" style="padding:4px 10px; font-size:11px; background:#28a745;" onclick="markAsPaid(<?php echo $s['id']; ?>)">Mark Paid</button>
                                    <?php else: ?>
                                    ✓ Paid
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
            </div>
        </div>
    </div>
    
    <script>
        // Table search functionality
        document.getElementById('tableSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#feeTable tr');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
        
        // Mark as paid function
        function markAsPaid(studentId) {
            if (confirm('Mark all fees as paid for this student?')) {
                fetch('api/mark_fee_paid.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ student_id: studentId })
                }).then(r => r.json()).then(result => {
                    alert(result.message);
                    if (result.success) location.reload();
                });
            }
        }
        
        function toggleTheme() { 
            document.body.classList.toggle('dark-theme'); 
            localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); 
        }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>