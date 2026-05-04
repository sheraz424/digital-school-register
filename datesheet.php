<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'principal' && $_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'teacher') {
    header('Location: dashboard.php');
    exit;
}

$class_id = $_GET['class_id'] ?? 0;

$classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY FIELD(class_name, 'Nursery','Prep','1','2','3','4','5','6','7','8','9','10'), section")->fetchAll();

// Get datesheet data
if ($class_id) {
    $datesheet = $pdo->prepare("
        SELECT d.*, s.subject_name 
        FROM datesheet d
        JOIN subjects s ON d.subject_id = s.id
        WHERE d.class_id = ?
        ORDER BY d.exam_date ASC
    ");
    $datesheet->execute([$class_id]);
    $datesheet = $datesheet->fetchAll();
} else {
    $datesheet = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Datesheet - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .btn { background: #2E86AB; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .filter-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 12px; }
        select, input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="principal_dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>Exam Datesheet</h1>
        
        <div class="card">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Select Class</label>
                        <select name="class_id" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($class_id == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo $c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn">View Datesheet</button>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if($class_id && !empty($datesheet)): ?>
        <div class="card">
            <h3>Exam Schedule</h3>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>Subject</th><th>Exam Date</th><th>Time</th><th>Room</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($datesheet as $d): ?>
                        <tr>
                            <td><?php echo $d['subject_name']; ?></td>
                            <td><?php echo date('d M Y', strtotime($d['exam_date'])); ?></td>
                            <td><?php echo date('g:i A', strtotime($d['start_time'])); ?> - <?php echo date('g:i A', strtotime($d['end_time'])); ?></td>
                            <td><?php echo $d['room_no']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php elseif($class_id): ?>
        <div class="card"><p>No datesheet found for this class.</p></div>
        <?php endif; ?>
    </div>
</body>
</html>