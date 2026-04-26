<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSR — Attendance History</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="attendance.css">
    <style>
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th, .history-table td { padding: 12px; border-bottom: 1px solid var(--border); text-align: left; }
        .history-table th { background: var(--bg); font-size: 12px; text-transform: uppercase; }
        .status-p { color: var(--green); font-weight: bold; }
        .status-a { color: var(--red); font-weight: bold; }
        .status-l { color: var(--orange); font-weight: bold; }
        .filter-bar { display: flex; gap: 16px; margin-bottom: 24px; align-items: flex-end; }
        .delete-btn { background: var(--red); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; }
        .delete-btn:hover { opacity: 0.8; }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand"><div class="sb-logo">DSR</div><span class="sb-sub">Digital School Register</span></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">Dashboard</a>
            <a href="attendance.php" class="nav-item">Mark Attendance</a>
            <a href="attendance_history.php" class="nav-item active">History</a>
        </nav>
        <div class="sidebar-profile">
            <div class="profile-avatar"><?php echo substr($_SESSION['user_name'], 0, 1); ?></div>
            <div class="profile-info"><span class="profile-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span><span class="profile-role">Teacher</span></div>
            <a href="logout.php" class="logout-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="menu-btn" onclick="toggleSidebar()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            <div class="page-title"><h1>Attendance History</h1><span class="breadcrumb">Home / Attendance / History</span></div></div>
        </header>

        <div class="att-body">
            <div class="filter-bar">
                <div class="ctrl-group"><label>Select Class</label>
                    <select id="class-select" class="select-wrap" style="padding:10px;">
                        <option value="">— Choose Class —</option>
                        <?php
                        $stmt = $pdo->query("SELECT id, class_name, section FROM classes");
                        while ($class = $stmt->fetch()) {
                            echo "<option value='{$class['id']}'>Class {$class['class_name']}{$class['section']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="ctrl-group"><label>Select Month</label><input type="month" id="month-select" class="select-wrap" style="padding:10px;"></div>
                <div class="ctrl-group"><button class="btn-save" onclick="loadHistory()" style="margin-top:20px;">View History</button></div>
            </div>

            <div class="att-card" id="history-card" style="display:none;">
                <div class="att-card-header"><h3>Attendance Records</h3></div>
                <div style="overflow-x:auto;"><table class="history-table" id="history-table"><thead><tr><th>Date</th><th>Student</th><th>Roll No</th><th>Status</th><th>Remarks</th><th>Action</th></tr></thead><tbody id="history-body"></tbody></table></div>
            </div>
        </div>
    </main>

    <div class="toast" id="toast"></div>

    <script>
        async function loadHistory() {
            const classId = document.getElementById('class-select').value;
            const month = document.getElementById('month-select').value || new Date().toISOString().slice(0,7);
            
            if (!classId) { showToast('Please select a class', 'error'); return; }
            
            try {
                const response = await fetch(`api/get_attendance.php?class_id=${classId}&month=${month}`);
                const result = await response.json();
                
                if (result.success) {
                    displayHistory(result);
                    document.getElementById('history-card').style.display = 'block';
                } else { showToast(result.message, 'error'); }
            } catch(error) { showToast('Error loading history', 'error'); }
        }
        
        function displayHistory(data) {
            const tbody = document.getElementById('history-body');
            tbody.innerHTML = '';
            
            for (const student of data.data.students) {
                const records = data.data.attendance[student.id] || [];
                records.forEach(rec => {
                    const row = tbody.insertRow();
                    row.insertCell(0).textContent = rec.attendance_date;
                    row.insertCell(1).textContent = student.name;
                    row.insertCell(2).textContent = student.roll_no;
                    row.insertCell(3).innerHTML = `<span class="status-${rec.status.toLowerCase()}">${rec.status === 'P' ? 'Present' : (rec.status === 'A' ? 'Absent' : 'Late')}</span>`;
                    row.insertCell(4).textContent = rec.remarks || '-';
                    row.insertCell(5).innerHTML = `<button class="delete-btn" onclick="deleteRecord(${rec.id})">Delete</button>`;
                });
            }
        }
        
        async function deleteRecord(id) {
            if (!confirm('Delete this attendance record?')) return;
            try {
                const response = await fetch('api/delete_attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ attendance_id: id })
                });
                const result = await response.json();
                if (result.success) { showToast('Record deleted', 'success'); loadHistory(); }
                else { showToast(result.message, 'error'); }
            } catch(error) { showToast('Error deleting', 'error'); }
        }
        
        function showToast(msg, type) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.className = 'toast show ' + type;
            setTimeout(() => toast.className = 'toast', 3000);
        }
        
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
        
        document.getElementById('month-select').value = new Date().toISOString().slice(0,7);
    </script>
</body>
</html>