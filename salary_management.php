<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'accountant') {
    header('Location: dashboard.php');
    exit;
}

$user_role = $_SESSION['user_role'];
$message = '';
$error = '';

// Process salary payment
if (isset($_GET['pay'])) {
    $id = $_GET['pay'];
    $stmt = $pdo->prepare("UPDATE salaries SET status = 'Paid', payment_date = CURDATE() WHERE id = ?");
    $stmt->execute([$id]);
    $message = "Salary marked as paid!";
}

// Process bulk salary generation
if (isset($_POST['generate_salaries'])) {
    $month = $_POST['month'];
    
    // Generate for teachers, admin, principal
    $stmt = $pdo->prepare("
        INSERT INTO salaries (staff_type, staff_id, month, amount, bonus, deductions, net_amount, status)
        SELECT 
            'teacher',
            u.id,
            ?,
            COALESCE(u.base_salary, 50000),
            0,
            0,
            COALESCE(u.base_salary, 50000),
            'Pending'
        FROM users u
        WHERE u.role IN ('teacher', 'admin', 'principal')
        AND NOT EXISTS (
            SELECT 1 FROM salaries s 
            WHERE s.staff_type = 'teacher' 
            AND s.staff_id = u.id 
            AND DATE_FORMAT(s.month, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
        )
    ");
    $stmt->execute([$month, $month]);
    
    // Generate for staff
    $stmt = $pdo->prepare("
        INSERT INTO salaries (staff_type, staff_id, month, amount, bonus, deductions, net_amount, status)
        SELECT 
            'staff',
            s.id,
            ?,
            COALESCE(s.salary, 35000),
            0,
            0,
            COALESCE(s.salary, 35000),
            'Pending'
        FROM staff s
        WHERE NOT EXISTS (
            SELECT 1 FROM salaries sal 
            WHERE sal.staff_type = 'staff' 
            AND sal.staff_id = s.id 
            AND DATE_FORMAT(sal.month, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
        )
    ");
    $stmt->execute([$month, $month]);
    
    $message = "Salaries generated for month: " . date('F Y', strtotime($month));
}

// Process salary update (bonus/deductions)
if (isset($_POST['update_salary'])) {
    $id = $_POST['salary_id'];
    $bonus = $_POST['bonus'];
    $deductions = $_POST['deductions'];
    
    $stmt = $pdo->prepare("SELECT amount FROM salaries WHERE id = ?");
    $stmt->execute([$id]);
    $salary = $stmt->fetch();
    
    $net_amount = $salary['amount'] + $bonus - $deductions;
    
    $stmt = $pdo->prepare("UPDATE salaries SET bonus = ?, deductions = ?, net_amount = ? WHERE id = ?");
    $stmt->execute([$bonus, $deductions, $net_amount, $id]);
    $message = "Salary updated successfully!";
}

// Get all employees (teachers, admin, principal, staff)
$employees = $pdo->query("
    SELECT id, full_name as name, role, base_salary, 'user' as source, email 
    FROM users 
    WHERE role IN ('teacher', 'admin', 'principal')
    UNION ALL
    SELECT id, name, 'staff' as role, salary as base_salary, 'staff' as source, email 
    FROM staff
    ORDER BY name
")->fetchAll();

// Get all salaries with employee details
$salaries = $pdo->query("
    SELECT s.*, 
           CASE 
               WHEN s.staff_type = 'teacher' THEN (SELECT full_name FROM users WHERE id = s.staff_id)
               WHEN s.staff_type = 'staff' THEN (SELECT name FROM staff WHERE id = s.staff_id)
           END as staff_name,
           CASE 
               WHEN s.staff_type = 'teacher' THEN (SELECT role FROM users WHERE id = s.staff_id)
               WHEN s.staff_type = 'staff' THEN 'staff'
           END as staff_role
    FROM salaries s
    ORDER BY s.created_at DESC
")->fetchAll();

$totalPending = $pdo->query("SELECT COALESCE(SUM(net_amount), 0) FROM salaries WHERE status = 'Pending'")->fetchColumn();
$totalPaid = $pdo->query("SELECT COALESCE(SUM(net_amount), 0) FROM salaries WHERE status = 'Paid'")->fetchColumn();

// Get available months for filtering
$availableMonths = $pdo->query("SELECT DISTINCT DATE_FORMAT(month, '%Y-%m') as month FROM salaries ORDER BY month DESC")->fetchAll();
$currentMonth = date('Y-m-01');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Management - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        body.dark-theme { --bg: #1a1a2e; --card: #16213e; --text: #eeeeee; --border: #2c3e50; }
        .container { padding: 20px; max-width: 1400px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        body.dark-theme .card { background: var(--card); border-color: var(--border); }
        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #ddd; }
        .stat-value { font-size: 28px; font-weight: bold; }
        .btn { background: #2E86AB; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
        .pay-btn { background: #28a745; color: white; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; display: inline-block; }
        .edit-btn { background: #ffc107; color: #333; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; display: inline-block; margin-right: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .status-paid { color: green; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; background: #2E86AB; color: white; border: none; padding: 10px 15px; border-radius: 25px; cursor: pointer; z-index: 1000; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .filter-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 20px; }
        .filter-group { flex: 1; min-width: 150px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 12px; }
        select, input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; }
        .search-box { width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            width: 400px;
        }
        body.dark-theme .modal-content { background: var(--card); }
        .modal-content input { width: 100%; padding: 8px; margin: 10px 0; border: 1px solid #ddd; border-radius: 6px; }
        .modal-buttons { display: flex; gap: 10px; margin-top: 20px; }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Dark/Light</button>
    <div class="container">
        <a href="<?php echo ($user_role === 'accountant') ? 'accountant_dashboard.php' : 'dashboard.php'; ?>" class="back-btn">← Back to Dashboard</a>
        <h1>Salary Management</h1>
        
        <?php if($message): ?>
        <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value" style="color: #ffc107;">Rs <?php echo number_format($totalPending); ?></div><div>Pending Salaries</div></div>
            <div class="stat-card"><div class="stat-value" style="color: #28a745;">Rs <?php echo number_format($totalPaid); ?></div><div>Total Paid</div></div>
        </div>
        
        <!-- Generate Salaries for Month -->
        <div class="card">
            <h3>Generate Monthly Salaries</h3>
            <form method="POST" class="filter-row">
                <div class="filter-group">
                    <label>Select Month</label>
                    <input type="month" name="month" value="<?php echo date('Y-m'); ?>" required>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" name="generate_salaries" class="btn btn-success">Generate Salaries</button>
                </div>
            </form>
        </div>
        
        <!-- Salary Records -->
        <div class="card">
            <h3>Salary Records</h3>
            <input type="text" id="searchSalary" class="search-box" placeholder="Search by name...">
            <div style="overflow-x: auto;">
                <select id="monthFilter" class="search-box" style="width: auto; display: inline-block; margin-right: 10px;">
                    <option value="">All Months</option>
                    <?php foreach($availableMonths as $m): ?>
                        <option value="<?php echo $m['month']; ?>"><?php echo date('F Y', strtotime($m['month'] . '-01')); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="statusFilter" class="search-box" style="width: auto; display: inline-block;">
                    <option value="">All Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Paid">Paid</option>
                </select>
                
                <table>
                    <thead>
                        <tr>
                            <th>Staff Name</th>
                            <th>Role/Type</th>
                            <th>Month</th>
                            <th>Base Amount</th>
                            <th>Bonus</th>
                            <th>Deductions</th>
                            <th>Net Amount</th>
                            <th>Status</th>
                            <th>Payment Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="salaryTable">
                        <?php foreach($salaries as $s): ?>
                        <tr class="salary-row" data-month="<?php echo date('Y-m', strtotime($s['month'])); ?>" data-status="<?php echo $s['status']; ?>">
                            <td><?php echo $s['staff_name']; ?></td>
                            <td><?php echo ucfirst($s['staff_role']); ?></td>
                            <td><?php echo date('M Y', strtotime($s['month'])); ?></td>
                            <td>Rs <?php echo number_format($s['amount']); ?></td>
                            <td>Rs <?php echo number_format($s['bonus']); ?></td>
                            <td>Rs <?php echo number_format($s['deductions']); ?></td>
                            <td>Rs <?php echo number_format($s['net_amount']); ?></td>
                            <td class="status-<?php echo strtolower($s['status']); ?>"><?php echo $s['status']; ?></td>
                            <td><?php echo $s['payment_date'] ? date('d M Y', strtotime($s['payment_date'])) : '-'; ?></td>
                            <td>
                                <?php if($s['status'] == 'Pending'): ?>
                                <button class="edit-btn" onclick="openEditModal(<?php echo $s['id']; ?>, <?php echo $s['bonus']; ?>, <?php echo $s['deductions']; ?>)">Edit</button>
                                <a href="?pay=<?php echo $s['id']; ?>" class="pay-btn">Mark Paid</a>
                                <?php else: ?>
                                ✓
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Edit Salary Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Salary (Bonus/Deductions)</h3>
            <form method="POST">
                <input type="hidden" name="salary_id" id="edit_salary_id">
                <div class="form-group">
                    <label>Bonus (+)</label>
                    <input type="number" name="bonus" id="edit_bonus" step="100" value="0">
                </div>
                <div class="form-group">
                    <label>Deductions (-)</label>
                    <input type="number" name="deductions" id="edit_deductions" step="100" value="0">
                </div>
                <div class="modal-buttons">
                    <button type="submit" name="update_salary" class="btn btn-success">Update Salary</button>
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openEditModal(id, bonus, deductions) {
            document.getElementById('edit_salary_id').value = id;
            document.getElementById('edit_bonus').value = bonus;
            document.getElementById('edit_deductions').value = deductions;
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Filter functionality
        function filterTable() {
            let monthFilter = document.getElementById('monthFilter').value;
            let statusFilter = document.getElementById('statusFilter').value;
            let searchText = document.getElementById('searchSalary').value.toLowerCase();
            let rows = document.querySelectorAll('#salaryTable tr');
            
            rows.forEach(row => {
                let month = row.getAttribute('data-month') || '';
                let status = row.getAttribute('data-status') || '';
                let text = row.innerText.toLowerCase();
                
                let monthMatch = !monthFilter || month === monthFilter;
                let statusMatch = !statusFilter || status === statusFilter;
                let searchMatch = !searchText || text.includes(searchText);
                
                row.style.display = (monthMatch && statusMatch && searchMatch) ? '' : 'none';
            });
        }
        
        document.getElementById('searchSalary').addEventListener('keyup', filterTable);
        document.getElementById('monthFilter').addEventListener('change', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);
        
        function toggleTheme() { document.body.classList.toggle('dark-theme'); localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light'); }
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-theme');
    </script>
</body>
</html>