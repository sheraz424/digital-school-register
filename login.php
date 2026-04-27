<?php
require_once 'db_connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'teacher';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();
    
    // Plain text password comparison
    if ($user && $password === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        
        // Redirect based on role
        switch($user['role']) {
            case 'admin':
                header('Location: dashboard.php');
                break;
            case 'accountant':
                header('Location: accountant_dashboard.php');
                break;
            case 'teacher':
                header('Location: dashboard.php');
                break;
            case 'student':
                header('Location: dashboard.php');
                break;
            case 'parent':
                header('Location: dashboard.php');
                break;
            default:
                header('Location: dashboard.php');
        }
        exit;
    } else {
        $error = 'Invalid email or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>DSR — Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css" />
    <style>
        .alert.error {
            display: block;
            margin-bottom: 16px;
            padding: 12px;
            background: #FEE8E8;
            color: #B91C1C;
            border-radius: 8px;
            border: 1px solid #F8BABA;
        }
        .test-credentials {
            font-size: 11px;
            color: var(--muted);
            text-align: center;
            margin-top: 12px;
            padding: 10px;
            background: var(--bg);
            border-radius: 8px;
        }
        .test-credentials strong {
            color: var(--accent);
        }
        .test-credentials span {
            display: inline-block;
            margin: 0 5px;
            padding: 2px 6px;
            background: white;
            border-radius: 4px;
        }
        .theme-toggle-login {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            backdrop-filter: blur(5px);
            transition: all 0.3s;
        }
        .theme-toggle-login:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }
        .theme-toggle-login svg {
            width: 20px;
            height: 20px;
            color: white;
        }
        body.dark-theme .theme-toggle-login {
            background: rgba(0,0,0,0.3);
        }
        @media (max-width: 900px) {
            .theme-toggle-login { left: auto; right: 20px; bottom: 20px; }
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
                    <svg viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
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
                <p>A unified platform for administrators, teachers, accountants, students, and parents.</p>
                <div class="stats-row">
                    <div class="stat">
                        <span class="stat-num">5</span>
                        <span class="stat-label">User Roles</span>
                    </div>
                    <div class="stat">
                        <span class="stat-num">15+</span>
                        <span class="stat-label">Modules</span>
                    </div>
                    <div class="stat">
                        <span class="stat-num">∞</span>
                        <span class="stat-label">Records</span>
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
                    <p>Sign in to your DSR account</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="role-tabs">
                        <button type="button" class="role-tab" data-role="admin" onclick="setRole('admin')">Admin</button>
                        <button type="button" class="role-tab" data-role="teacher" onclick="setRole('teacher')">Teacher</button>
                        <button type="button" class="role-tab" data-role="accountant" onclick="setRole('accountant')">Accountant</button>
                        <button type="button" class="role-tab" data-role="student" onclick="setRole('student')">Student</button>
                        <button type="button" class="role-tab" data-role="parent" onclick="setRole('parent')">Parent</button>
                    </div>
                    <input type="hidden" name="role" id="selected-role" value="teacher">

                    <div class="field-group">
                        <label for="email">Email / Username</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            <input type="password" id="password" name="password" placeholder="Enter your password" required>
                            <button type="button" class="toggle-pw" onclick="togglePw()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" />
                            <span class="checkmark"></span>
                            Remember me
                        </label>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-login">
                        <span class="btn-text">Sign In</span>
                        <svg class="btn-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
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
                    Need an account? <a href="#">Contact your school administrator</a>
                </div>
            </div>
            <div class="right-footer">
                © 2025 DSR System &nbsp;·&nbsp; FAST NUCES Faisalabad
            </div>
        </div>
    </div>

    <button class="theme-toggle-login" onclick="toggleLoginTheme()" title="Dark/Light Mode">
        <svg id="loginThemeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
    </button>

    <script>
        function setRole(role) {
            document.querySelectorAll('.role-tab').forEach(function(t) {
                t.classList.remove('active');
            });
            event.target.classList.add('active');
            document.getElementById('selected-role').value = role;
            
            var emailInput = document.getElementById('email');
            
            switch(role) {
                case 'admin':
                    emailInput.value = 'admin@dsr.com';
                    break;
                case 'teacher':
                    emailInput.value = 'teacher@dsr.com';
                    break;
                case 'accountant':
                    emailInput.value = 'accountant@dsr.com';
                    break;
                case 'student':
                    emailInput.value = 'student1@dsr.com';
                    break;
                case 'parent':
                    emailInput.value = 'parent@dsr.com';
                    break;
                default:
                    emailInput.value = 'teacher@dsr.com';
            }
        }
        
        function togglePw() {
            var pw = document.getElementById('password');
            pw.type = pw.type === 'password' ? 'text' : 'password';
        }
        
        function toggleLoginTheme() {
            document.body.classList.toggle('dark-theme');
            const theme = document.body.classList.contains('dark-theme') ? 'dark' : 'light';
            localStorage.setItem('login_theme', theme);
            
            const icon = document.getElementById('loginThemeIcon');
            if (theme === 'dark') {
                icon.innerHTML = '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';
            } else {
                icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
            }
        }
        
        // Load saved theme on page load
        const savedLoginTheme = localStorage.getItem('login_theme');
        if (savedLoginTheme === 'dark') {
            document.body.classList.add('dark-theme');
            const icon = document.getElementById('loginThemeIcon');
            icon.innerHTML = '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';
        }
    </script>
</body>
</html>