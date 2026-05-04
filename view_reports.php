<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'principal') {
    header('Location: dashboard.php');
    exit;
}

// Get summary statistics
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$totalStaff = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
$totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$totalFeesCollected = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments")->fetchColumn();
$pendingFees = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM fee_allocations WHERE status IN ('Pending', 'Partial')")->fetchColumn();

// Get attendance summary for last 7 days
$attendanceData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stats = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present FROM attendance WHERE attendance_date = ?");
    $stats->execute([$date]);
    $row = $stats->fetch();
    $total = $row['total'] ?? 0;
    $present = $row['present'] ?? 0;
    $percent = $total > 0 ? round(($present / $total) * 100) : 0;
    $attendanceData[] = ['date' => date('d M', strtotime($date)), 'percent' => $percent];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Principal</title>
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #ddd; }
        .stat-value { font-size: 28px; font-weight: bold; color: #2E86AB; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="principal_dashboard.php" class="back-btn"> Back to Dashboard</a>
        <h1>School Reports</h1>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo $totalStudents; ?></div><div>Total Students</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $totalTeachers; ?></div><div>Total Teachers</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $totalStaff; ?></div><div>Total Staff</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $totalClasses; ?></div><div>Total Classes</div></div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value" style="color: #28a745;">Rs <?php echo number_format($totalFeesCollected); ?></div><div>Total Fees Collected</div></div>
            <div class="stat-card"><div class="stat-value" style="color: #dc3545;">Rs <?php echo number_format($pendingFees); ?></div><div>Pending Fees</div></div>
        </div>
        
        <div class="card">
            <h3>Last 7 Days Attendance Trend</h3>
            <canvas id="attendanceChart" style="max-height: 300px; width: 100%;"></canvas>
        </div>
    </div>
    
    <script>
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($attendanceData, 'date')); ?>,
                datasets: [{
                    label: 'Attendance Percentage',
                    data: <?php echo json_encode(array_column($attendanceData, 'percent')); ?>,
                    borderColor: '#2E86AB',
                    backgroundColor: 'rgba(46, 134, 171, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Percentage (%)' } } }
            }
        });
    </script>
</body>
</html>