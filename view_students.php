<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'principal') {
    header('Location: dashboard.php');
    exit;
}

// Get filters
$class_filter = $_GET['class_id'] ?? '';
$search_filter = $_GET['search'] ?? '';
$section_filter = $_GET['section'] ?? '';

// Build query
$query = "
    SELECT s.roll_no, s.name, c.class_name, c.section, s.parent_email, s.contact
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE 1=1
";

if ($class_filter) {
    $query .= " AND c.id = '$class_filter'";
}
if ($section_filter) {
    $query .= " AND c.section = '$section_filter'";
}
if ($search_filter) {
    $query .= " AND (s.name LIKE '%$search_filter%' OR s.roll_no LIKE '%$search_filter%')";
}

$query .= " ORDER BY c.class_name, s.roll_no";

$students = $pdo->query($query)->fetchAll();

$classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY class_name")->fetchAll();
$sections = $pdo->query("SELECT DISTINCT section FROM classes WHERE section != '' ORDER BY section")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Students - Principal</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .btn { background: #2E86AB; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2E86AB; color: white; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #1A3A5C; color: white; text-decoration: none; border-radius: 8px; }
        .filter-row { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 12px; }
        select, input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; }
        .export-btn { background: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <a href="principal_dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>Students List</h1>
        
        <div class="card">
            <h3>Filters</h3>
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search by Name or Roll No</label>
                        <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search_filter); ?>">
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
                        <label>Filter by Section</label>
                        <select name="section">
                            <option value="">All Sections</option>
                            <?php foreach($sections as $s): ?>
                                <option value="<?php echo $s['section']; ?>" <?php echo ($section_filter == $s['section']) ? 'selected' : ''; ?>>
                                    <?php echo $s['section']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="view_students.php" class="btn" style="background: #6c757d;">Reset</a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap;">
                <h3>Student Records (<?php echo count($students); ?> students)</h3>
                <button onclick="exportToExcel()" class="btn export-btn">📎 Export to Excel</button>
            </div>
            <input type="text" id="tableSearch" placeholder="Search in table..." style="width:100%; padding:8px; margin-bottom:15px; border:1px solid #ddd; border-radius:6px;">
            <div style="overflow-x: auto;">
                <table id="studentTable">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Parent Email</th>
                            <th>Contact</th>
                        </thead>
                        <tbody>
                            <?php foreach($students as $s): ?>
                            <tr>
                                <td><?php echo $s['roll_no']; ?></td>
                                <td><?php echo $s['name']; ?></td>
                                <td><?php echo $s['class_name']; ?></td>
                                <td><?php echo $s['section'] ?: '-'; ?></td>
                                <td><?php echo $s['parent_email'] ?: '-'; ?></td>
                                <td><?php echo $s['contact'] ?: '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
            </div>
        </div>
    </div>
    
    <script>
        // Table search
        document.getElementById('tableSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#studentTable tbody tr');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
        
        // Export to Excel
        function exportToExcel() {
            let table = document.getElementById('studentTable');
            let html = table.outerHTML;
            let url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            let link = document.createElement('a');
            link.href = url;
            link.download = 'students_list.xls';
            link.click();
        }
    </script>
</body>
</html>