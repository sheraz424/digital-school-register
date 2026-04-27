<?php
require_once 'db_connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();
    
    if ($user && $password === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        
        if ($user['role'] === 'accountant') {
            header('Location: accountant_dashboard.php');
        } else {
            header('Location: dashboard.php');
        }
        exit;
    } else {
        $error = 'Invalid email or password!';
    }
}

// Check if admin exists, if not create default admin
$checkAdmin = $pdo->query("SELECT * FROM users WHERE email = 'admin@dsr.com'");
if ($checkAdmin->rowCount() == 0) {
    $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES ('admin', 'admin@dsr.com', 'admin123', 'School Administrator', 'admin')")->execute();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSR - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sora', sans-serif;
            background: #0F2447;
            min-height: 100vh;
            display: flex;
            position: relative;
        }

        body.dark {
            background: #0a0a1a;
        }

        body.dark .right-panel {
            background: #0f0f1a;
        }

        body.dark .form-card {
            background: #16213e;
        }

        body.dark .form-card h2,
        body.dark .form-card p,
        body.dark .field-group label {
            color: #eeeeee;
        }

        body.dark .input-wrap input {
            background: #0f0f23;
            border-color: #2c3e50;
            color: #eeeeee;
        }

        body.dark .test-credentials {
            background: #1a1a2e;
        }

        body.dark .role-tab.active {
            background: #00b4d8;
            color: white;
        }

        .bg-grid {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image: linear-gradient(rgba(46,134,171,0.06) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(46,134,171,0.06) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        .blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            pointer-events: none;
        }

        .blob-1 {
            width: 500px;
            height: 500px;
            background: rgba(46,134,171,0.18);
            top: -100px;
            left: -100px;
            animation: drift 12s ease-in-out infinite alternate;
        }

        .blob-2 {
            width: 400px;
            height: 400px;
            background: rgba(14,177,168,0.12);
            bottom: -80px;
            right: -80px;
            animation: drift 15s ease-in-out infinite alternate-reverse;
        }

        @keyframes drift {
            from { transform: translate(0,0); }
            to { transform: translate(40px, 30px); }
        }

        .page-wrap {
            display: flex;
            width: 100%;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        .left-panel {
            flex: 0 0 42%;
            background: linear-gradient(145deg, #1A3A5C 0%, #0F2447 60%, #0a1929 100%);
            padding: 48px 52px;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255,255,255,0.07);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo-ring {
            width: 54px;
            height: 54px;
            background: rgba(255,255,255,0.08);
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.15);
            padding: 8px;
        }

        .brand-name {
            font-family: 'Space Mono', monospace;
            font-size: 22px;
            font-weight: 700;
            color: white;
            letter-spacing: 2px;
        }

        .brand-sub {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
        }

        .left-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px 0 40px;
        }

        .left-content h1 {
            font-size: 48px;
            font-weight: 700;
            line-height: 1.15;
            color: white;
            margin-bottom: 20px;
        }

        .left-content h1 em {
            font-style: normal;
            background: linear-gradient(90deg, #0EB1A8, #F4A261);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .left-content p {
            font-size: 15px;
            color: rgba(255,255,255,0.6);
            line-height: 1.7;
            max-width: 340px;
            margin-bottom: 48px;
        }

        .stats-row {
            display: flex;
            gap: 32px;
        }

        .stat-num {
            font-family: 'Space Mono', monospace;
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(90deg, #0EB1A8, #2E86AB);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            font-size: 11px;
            color: rgba(255,255,255,0.45);
            text-transform: uppercase;
            margin-top: 4px;
        }

        .right-panel {
            flex: 1;
            background: #F7FAFD;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 40px;
        }

        .form-card {
            width: 100%;
            max-width: 420px;
            background: white;
            border-radius: 24px;
            padding: 36px 32px;
            box-shadow: 0 4px 40px rgba(0,0,0,0.1);
        }

        .form-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .form-header h2 {
            font-size: 26px;
            font-weight: 700;
            color: #1E2D3D;
            margin-bottom: 6px;
        }

        .form-header p {
            font-size: 14px;
            color: #6B7A8D;
        }

        .role-tabs {
            display: flex;
            gap: 8px;
            background: #E8F4FD;
            border-radius: 12px;
            padding: 6px;
            margin-bottom: 28px;
        }

        .role-tab {
            flex: 1;
            padding: 10px 0;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Sora', sans-serif;
            font-size: 13px;
            font-weight: 500;
            color: #6B7A8D;
            text-align: center;
            transition: all 0.2s;
        }

        .role-tab.active {
            background: white;
            color: #1A3A5C;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            font-weight: 600;
        }

        .field-group {
            margin-bottom: 20px;
        }

        .field-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #1E2D3D;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 17px;
            height: 17px;
            color: #6B7A8D;
        }

        .input-wrap input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1.5px solid #D0DCE8;
            border-radius: 10px;
            font-family: 'Sora', sans-serif;
            font-size: 14px;
            outline: none;
        }

        .input-wrap input:focus {
            border-color: #2E86AB;
            box-shadow: 0 0 0 3px rgba(46,134,171,0.1);
        }

        .toggle-pw {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #6B7A8D;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #6B7A8D;
            cursor: pointer;
        }

        .checkbox-label input {
            display: none;
        }

        .checkmark {
            width: 16px;
            height: 16px;
            border: 1.5px solid #D0DCE8;
            border-radius: 4px;
            background: white;
        }

        .checkbox-label input:checked + .checkmark {
            background: #2E86AB;
            border-color: #2E86AB;
            position: relative;
        }

        .checkbox-label input:checked + .checkmark::after {
            content: '✓';
            position: absolute;
            top: -1px;
            left: 3px;
            color: white;
            font-size: 11px;
        }

        .forgot-link {
            font-size: 13px;
            color: #2E86AB;
            text-decoration: none;
        }

        .btn-login {
            width: 100%;
            padding: 12px 20px;
            background: linear-gradient(135deg, #1A3A5C, #2E86AB);
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'Sora', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26,58,92,0.3);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0 16px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #D0DCE8;
        }

        .divider span {
            font-size: 12px;
            color: #6B7A8D;
        }

        .help-text {
            text-align: center;
            font-size: 13px;
            color: #6B7A8D;
        }

        .help-text a {
            color: #2E86AB;
            text-decoration: none;
        }

        .alert.error {
            background: #FEE8E8;
            color: #B91C1C;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
        }

        .test-credentials {
            font-size: 11px;
            color: #6B7A8D;
            text-align: center;
            margin-top: 16px;
            padding: 10px;
            background: #F1F5F9;
            border-radius: 8px;
        }

        .test-credentials strong {
            color: #2E86AB;
        }

        .test-credentials span {
            display: inline-block;
            margin: 2px;
            padding: 2px 6px;
            background: white;
            border-radius: 4px;
        }

        .right-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #6B7A8D;
        }

        .theme-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }

        @media (max-width: 900px) {
            .left-panel { display: none; }
            .right-panel { background: #0F2447; }
            .form-card { box-shadow: 0 8px 40px rgba(0,0,0,0.3); }
            .theme-btn { left: auto; right: 20px; }
            body.dark .right-panel { background: #0a0a1a; }
        }

        @media (max-width: 500px) {
            .form-card { padding: 24px 20px; }
            .role-tabs { flex-wrap: nowrap; overflow-x: auto; }
            .role-tab { flex: 0 0 auto; padding: 8px 16px; }
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="page-wrap">
        <div class="left-panel">
            <div class="brand">
                <div class="logo-ring">
                    <svg viewBox="0 0 60 60" fill="none">
                        <circle cx="30" cy="30" r="28" stroke="rgba(255,255,255,0.3)" stroke-width="2"/>
                        <path d="M18 20h6v20h-6zM26 20h10c4.4 0 8 3.6 8 8s-3.6 8-8 8H26V20z" fill="white"/>
                        <circle cx="36" cy="28" r="3" fill="rgba(255,255,255,0.4)"/>
                    </svg>
                </div>
                <div class="brand-text">
                    <span class="brand-name">DSR</span>
                    <span class="brand-sub">Digital School Register</span>
                </div>
            </div>
            <div class="left-content">
                <h1>Smart School<br/>Management<br/><em>Redefined.</em></h1>
                <p>Unified platform for administrators, teachers, accountants, students, and parents.</p>
                <div class="stats-row">
                    <div class="stat">
                        <div class="stat-num">5</div>
                        <div class="stat-label">Roles</div>
                    </div>
                    <div class="stat">
                        <div class="stat-num">15+</div>
                        <div class="stat-label">Modules</div>
                    </div>
                    <div class="stat">
                        <div class="stat-num">∞</div>
                        <div class="stat-label">Records</div>
                    </div>
                </div>
            </div>
            <div class="left-footer">
                <span>FAST NUCES — Faisalabad</span>
                <span>SE Web Engineering Project</span>
            </div>
        </div>

        <div class="right-panel">
            <div class="form-card">
                <div class="form-header">
                    <h2>Welcome Back</h2>
                    <p>Sign in to your account</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert error"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="role-tabs">
                        <button type="button" class="role-tab" data-role="admin" onclick="setRole('admin')">Admin</button>
                        <button type="button" class="role-tab" data-role="teacher" onclick="setRole('teacher')">Teacher</button>
                        <button type="button" class="role-tab" data-role="accountant" onclick="setRole('accountant')">Accountant</button>
                        <button type="button" class="role-tab" data-role="student" onclick="setRole('student')">Student</button>
                        <button type="button" class="role-tab" data-role="parent" onclick="setRole('parent')">Parent</button>
                    </div>
                    <input type="hidden" name="role" id="selected-role" value="admin">

                    <div class="field-group">
                        <label>Email</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2z"/>
                                <polyline points="22 6 12 13 2 6"/>
                            </svg>
                            <!-- NO DUMMY EMAIL - EMPTY FIELD -->
                            <input type="email" id="email" name="email" placeholder="Enter your email" value="">
                        </div>
                    </div>

                    <div class="field-group">
                        <label>Password</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            <!-- NO DUMMY PASSWORD - EMPTY FIELD -->
                            <input type="password" id="password" name="password" placeholder="Enter your password" value="">
                            <button type="button" class="toggle-pw" onclick="togglePassword()">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox">
                            <span class="checkmark"></span>
                            Remember me
                        </label>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-login">
                        Sign In
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </button>
                </form>

                <div class="test-credentials">
                    <strong>Test Credentials (Password: admin123)</strong><br>
                    <span>Admin: admin@dsr.com</span>
                    <span>Teacher: teacher@dsr.com</span>
                    <span>Accountant: accountant@dsr.com</span>
                    <span>Student: student1@dsr.com</span>
                    <span>Parent: parent@dsr.com</span>
                </div>

                <div class="divider"><span>or</span></div>
                <div class="help-text">
                    Need an account? <a href="#">Contact administrator</a>
                </div>
            </div>
            <div class="right-footer">
                © 2025 DSR System | FAST NUCES Faisalabad
            </div>
        </div>
    </div>

    <button class="theme-btn" onclick="toggleTheme()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
    </button>

    <script>
        function setRole(role) {
            document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById('selected-role').value = role;
            
            // Auto-fill email based on selected role for testing convenience
            const emails = {
                admin: 'admin@dsr.com',
                teacher: 'teacher@dsr.com',
                accountant: 'accountant@dsr.com',
                student: 'student1@dsr.com',
                parent: 'parent@dsr.com'
            };
            document.getElementById('email').value = emails[role];
            document.getElementById('password').value = 'admin123';
        }

        function togglePassword() {
            const pwd = document.getElementById('password');
            pwd.type = pwd.type === 'password' ? 'text' : 'password';
        }

        function toggleTheme() {
            document.body.classList.toggle('dark');
            localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
        }

        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark');
        }
        
        // NO DEFAULT EMAIL - Fields are empty on page load
        // Users must select a role or type manually
    </script>
</body>
</html>