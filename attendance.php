<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>DSR — Mark Attendance</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="attendance.css" />
    <style>
        .grade-select {
            padding: 6px;
            border-radius: 6px;
            border: 1px solid var(--border);
            font-family: 'Sora', sans-serif;
            font-size: 12px;
            background: var(--bg);
            cursor: pointer;
        }
        .grade-select:focus {
            border-color: var(--accent);
            outline: none;
        }
        .admin-only, .teacher-only, .student-only, .parent-only { display: none; }
        body.role-admin .admin-only { display: flex; }
        body.role-teacher .teacher-only { display: flex; }
        body.role-student .student-only { display: flex; }
        body.role-parent .parent-only { display: flex; }
        body.role-student .hide-for-student,
        body.role-parent .hide-for-parent { display: none; }
    </style>
</head>
<body class="role-<?php echo $user_role; ?>">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="sb-logo">DSR</div>
            <span class="sb-sub">Digital School Register</span>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section-label">Main</div>
            <a href="dashboard.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            <a href="attendance.php" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Attendance
            </a>
            <a href="attendance_history.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14"/><path d="M22 3h-6a4 4 0 0 0-4 4v14"/><path d="M12 21h10"/></svg>
                History
            </a>
        </nav>
        <div class="sidebar-profile">
            <div class="profile-avatar"><?php echo substr($user_name, 0, 1); ?></div>
            <div class="profile-info">
                <span class="profile-name"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="profile-role"><?php echo ucfirst($user_role); ?></span>
            </div>
            <a href="logout.php" class="logout-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-btn" onclick="toggleSidebar()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="page-title">
                    <h1>Mark Attendance</h1>
                    <span class="breadcrumb">Home / Attendance / Mark</span>
                </div>
            </div>
            <div class="topbar-right">
                <div class="save-status" id="save-status">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Unsaved changes
                </div>
                <button class="btn-save" onclick="saveAttendance()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Submit Attendance
                </button>
            </div>
        </header>

        <div class="att-body">
            <div class="controls-row">
                <div class="ctrl-group">
                    <label>Select Class</label>
                    <div class="select-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                        <select id="class-select" onchange="loadClass()">
                            <option value="">-- Choose Class --</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY class_name");
                            while ($class = $stmt->fetch()) {
                                $display = $class['class_name'] . ($class['section'] ? '-' . $class['section'] : '');
                                echo "<option value='{$class['id']}'>Class {$display}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="ctrl-group">
                    <label>Select Subject</label>
                    <div class="select-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        <select id="subject-select">
                            <option value="">-- No Subject --</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, subject_name FROM subjects");
                            while ($subject = $stmt->fetch()) {
                                echo "<option value='{$subject['id']}'>{$subject['subject_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="ctrl-group">
                    <label>Date</label>
                    <div class="select-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <input type="date" id="att-date" />
                    </div>
                </div>

                <div class="ctrl-group bulk-controls">
                    <label>Mark All As</label>
                    <div class="bulk-btns">
                        <button class="bulk-btn present" onclick="markAll('P')">All Present</button>
                        <button class="bulk-btn absent" onclick="markAll('A')">All Absent</button>
                        <button class="bulk-btn absent" onclick="markAll('L')">All Late</button>
                    </div>
                </div>
            </div>

            <div class="summary-strip" id="summary-strip" style="display: none;">
                <div class="sum-item total"><span class="sum-num" id="sum-total">0</span><span class="sum-lbl">Total</span></div>
                <div class="sum-item present"><span class="sum-num" id="sum-present">0</span><span class="sum-lbl">Present</span></div>
                <div class="sum-item absent"><span class="sum-num" id="sum-absent">0</span><span class="sum-lbl">Absent</span></div>
                <div class="sum-item late"><span class="sum-num" id="sum-late">0</span><span class="sum-lbl">Late</span></div>
                <div class="sum-item pct"><span class="sum-num" id="sum-pct">0%</span><span class="sum-lbl">Attendance %</span></div>
                <div class="progress-wrap"><div class="progress-bar" id="progress-bar" style="width:0%"></div></div>
            </div>

            <div class="att-card" id="att-card" style="display: none;">
                <div class="att-card-header">
                    <div class="att-card-title"><h3 id="class-title">Student List</h3><span class="att-session">Morning Session</span></div>
                    <div class="search-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" id="search-input" placeholder="Search student..." oninput="filterStudents(this.value)" /></div>
                </div>
                <table class="att-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Roll No</th>
                            <th>Student Name</th>
                            <th class="th-center">Present</th>
                            <th class="th-center">Absent</th>
                            <th class="th-center">Late</th>
                            <th class="th-center">Grade</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="student-tbody"></tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="toast" id="toast"></div>

    <script>
    let currentStudents = [];
    let currentClassId = 0;
    let hasChanges = false;

    document.getElementById('att-date').value = new Date().toISOString().split('T')[0];

    async function loadClass() {
        currentClassId = document.getElementById('class-select').value;
        if (!currentClassId) return;
        
        const date = document.getElementById('att-date').value;
        
        try {
            const response = await fetch(`api/get_students.php?class_id=${currentClassId}&date=${date}`);
            const result = await response.json();
            
            if (result.success) {
                currentStudents = result.data;
                renderTable(currentStudents);
                document.getElementById('att-card').style.display = 'block';
                document.getElementById('summary-strip').style.display = 'flex';
                const classOption = document.getElementById('class-select').options[document.getElementById('class-select').selectedIndex];
                document.getElementById('class-title').textContent = classOption.text + ' -- Student List';
                loadSummary();
                hasChanges = false;
                updateSaveStatus();
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            showToast('Error loading students: ' + error.message, 'error');
        }
    }

    function renderTable(students) {
        const tbody = document.getElementById('student-tbody');
        tbody.innerHTML = '';
        
        students.forEach((s, idx) => {
            const tr = document.createElement('tr');
            tr.setAttribute('data-name', s.name.toLowerCase());
            tr.setAttribute('data-student-id', s.id);
            tr.innerHTML = `
                <td class="td-num">${idx + 1}</td>
                <td class="td-roll">${s.roll_no}</td>
                <td class="td-name">${s.name}</td>
                <td class="td-radio">
                    <label class="radio-p"><input type="radio" name="att-${s.id}" value="P" ${s.status === 'P' ? 'checked' : ''} onchange="updateStatus(${s.id}, 'P')"/><span class="r-dot present-dot"></span></label>
                </td>
                <td class="td-radio">
                    <label class="radio-a"><input type="radio" name="att-${s.id}" value="A" ${s.status === 'A' ? 'checked' : ''} onchange="updateStatus(${s.id}, 'A')"/><span class="r-dot absent-dot"></span></label>
                </td>
                <td class="td-radio">
                    <label class="radio-l"><input type="radio" name="att-${s.id}" value="L" ${s.status === 'L' ? 'checked' : ''} onchange="updateStatus(${s.id}, 'L')"/><span class="r-dot late-dot"></span></label>
                </td>
                <td class="td-radio">
                    <select class="grade-select" id="grade-${s.id}" onchange="updateGrade(${s.id}, this.value)">
                        <option value="">--</option>
                        <option value="A+" ${s.grade === 'A+' ? 'selected' : ''}>A+</option>
                        <option value="A" ${s.grade === 'A' ? 'selected' : ''}>A</option>
                        <option value="B+" ${s.grade === 'B+' ? 'selected' : ''}>B+</option>
                        <option value="B" ${s.grade === 'B' ? 'selected' : ''}>B</option>
                        <option value="C" ${s.grade === 'C' ? 'selected' : ''}>C</option>
                        <option value="D" ${s.grade === 'D' ? 'selected' : ''}>D</option>
                        <option value="F" ${s.grade === 'F' ? 'selected' : ''}>F</option>
                    </select>
                </td>
                <td><input type="text" class="remarks-input" placeholder="optional..." id="remarks-${s.id}" value="${s.remarks || ''}" onchange="updateRemarks(${s.id}, this.value)" /></td>
            `;
            if (s.status) tr.classList.add('marked-' + s.status.toLowerCase());
            tbody.appendChild(tr);
        });
    }

    function updateStatus(studentId, status) {
        const student = currentStudents.find(s => s.id == studentId);
        if (student && student.status !== status) {
            student.status = status;
            const tr = document.querySelector(`tr[data-student-id="${studentId}"]`);
            if (tr) {
                tr.className = '';
                if (status) tr.classList.add('marked-' + status.toLowerCase());
            }
            hasChanges = true;
            updateSaveStatus();
            loadSummary();
        }
    }

    function updateGrade(studentId, grade) {
        const student = currentStudents.find(s => s.id == studentId);
        if (student) {
            student.grade = grade;
            hasChanges = true;
            updateSaveStatus();
        }
    }

    function updateRemarks(studentId, remarks) {
        const student = currentStudents.find(s => s.id == studentId);
        if (student && student.remarks !== remarks) {
            student.remarks = remarks;
            hasChanges = true;
            updateSaveStatus();
        }
    }

    function markAll(status) {
        currentStudents.forEach(s => {
            s.status = status;
        });
        renderTable(currentStudents);
        hasChanges = true;
        updateSaveStatus();
        loadSummary();
    }

    async function loadSummary() {
        if (!currentClassId) return;
        
        const date = document.getElementById('att-date').value;
        try {
            const response = await fetch(`api/get_summary.php?class_id=${currentClassId}&date=${date}`);
            const result = await response.json();
            
            if (result.success) {
                const data = result.data;
                document.getElementById('sum-total').textContent = data.total;
                document.getElementById('sum-present').textContent = data.present;
                document.getElementById('sum-absent').textContent = data.absent;
                document.getElementById('sum-late').textContent = data.late;
                document.getElementById('sum-pct').textContent = data.percentage + '%';
                const progressBar = document.getElementById('progress-bar');
                progressBar.style.width = data.percentage + '%';
                if (data.percentage >= 80) {
                    progressBar.style.background = 'linear-gradient(90deg,#2E86AB,#0EB1A8)';
                } else if (data.percentage >= 60) {
                    progressBar.style.background = 'linear-gradient(90deg,#F4A261,#E67E22)';
                } else {
                    progressBar.style.background = 'linear-gradient(90deg,#E74C3C,#C0392B)';
                }
            }
        } catch (error) {
            console.error('Error loading summary:', error);
        }
    }

    function filterStudents(query) {
        const rows = document.querySelectorAll('#student-tbody tr');
        rows.forEach(r => {
            const name = r.getAttribute('data-name') || '';
            r.style.display = name.includes(query.toLowerCase()) ? '' : 'none';
        });
    }

    function updateSaveStatus() {
        const statusDiv = document.getElementById('save-status');
        if (hasChanges) {
            statusDiv.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Unsaved changes`;
            statusDiv.classList.remove('saved');
        } else {
            statusDiv.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>Saved`;
            statusDiv.classList.add('saved');
        }
    }

    async function saveAttendance() {
        if (!currentClassId) {
            showToast('Please select a class first', 'error');
            return;
        }
        
        const date = document.getElementById('att-date').value;
        const subjectId = document.getElementById('subject-select').value;
        
        const attendanceData = currentStudents.map(s => ({
            student_id: s.id,
            status: s.status || 'A',
            remarks: s.remarks || '',
            grade: s.grade || ''
        }));
        
        try {
            const response = await fetch('api/mark_attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    class_id: currentClassId,
                    date: date,
                    subject_id: subjectId || null,
                    attendance: attendanceData
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast(result.message, 'success');
                hasChanges = false;
                updateSaveStatus();
                loadSummary();
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            showToast('Error saving attendance: ' + error.message, 'error');
        }
    }

    function showToast(message, type) {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = 'toast show ' + type;
        setTimeout(() => {
            toast.className = 'toast';
        }, 3000);
    }

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        document.querySelector('.main-content').classList.toggle('expanded');
    }
    </script>
</body>
</html>