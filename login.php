<?php

require_once 'includes/auth.php';
require_once 'includes/db.php';
 
// If already logged in, go straight to dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}
 
$error = '';
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    // FIX: Do NOT trim the password — leading/trailing spaces are valid
    $password = $_POST['password'] ?? '';
 
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $conn = getConnection();
 
        $stmt = $conn->prepare(
            "SELECT id, full_name, password, role, is_active
             FROM users
             WHERE username = ? AND role = 'secretary'
             LIMIT 1"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();
 
        if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
 
            // ---- Regenerate session ID to prevent session fixation ----
            session_regenerate_id(true);
 
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
 
            // Update last login timestamp
            $upd = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $upd->bind_param("i", $user['id']);
            $upd->execute();
            $upd->close();
 
            // Log successful login
            $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log = $conn->prepare(
                "INSERT INTO audit_log (user_id, action, details, ip_address)
                 VALUES (?, 'LOGIN', 'Successful login', ?)"
            );
            $log->bind_param("is", $user['id'], $ip);
            $log->execute();
            $log->close();
 
            $conn->close();
            header("Location: dashboard.php");
            exit();
 
        } elseif ($user && !$user['is_active']) {
            $error = 'Your account has been deactivated. Contact the system administrator.';
        } else {
            $error = 'Invalid username or password.';
 
            // Log failed attempt
            $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log = $conn->prepare(
                "INSERT INTO audit_log (user_id, action, details, ip_address)
                 SELECT id, 'LOGIN_FAILED', CONCAT('Failed attempt for username: ', ?), ?
                 FROM users WHERE username = ? LIMIT 1"
            );
            $log->bind_param("sss", $username, $ip, $username);
            $log->execute();
            $log->close();
        }
 
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay E-Records System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
 
        body {
            min-height: 100vh;
            background: #0a2a5e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Source Sans 3', sans-serif;
            padding: 2rem 1rem;
            position: relative;
            overflow: hidden;
        }
 
        body::before {
            content: '';
            position: fixed; inset: 0;
            background-image:
                repeating-linear-gradient(45deg, rgba(255,255,255,0.015) 0, rgba(255,255,255,0.015) 1px, transparent 1px, transparent 40px),
                repeating-linear-gradient(-45deg, rgba(255,255,255,0.015) 0, rgba(255,255,255,0.015) 1px, transparent 1px, transparent 40px);
            pointer-events: none;
        }
 
        body::after {
            content: '';
            position: fixed; bottom: 0; left: 0; right: 0;
            height: 5px;
            background: linear-gradient(90deg, #c8a84b, #f0d080, #c8a84b);
        }
 
        .card {
            background: #ffffff;
            border-radius: 18px;
            width: 100%;
            max-width: 430px;
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,0.4);
            animation: slideUp 0.4s ease;
        }
 
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
 
        .card-header {
            background: #0a2a5e;
            padding: 2.25rem 2rem 1.75rem;
            text-align: center;
            border-bottom: 4px solid #c8a84b;
        }
 
        .seal {
            width: 72px; height: 72px;
            border: 3px solid #c8a84b;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            background: rgba(200,168,75,0.12);
            font-size: 30px;
        }
 
        .card-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            color: #f0d080;
            line-height: 1.35;
            margin-bottom: 6px;
        }
 
        .card-header p {
            font-size: 11px;
            color: rgba(255,255,255,0.45);
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }
 
        .card-body { padding: 1.75rem 2rem 1.5rem; }
 
        .field { margin-bottom: 1.1rem; }
 
        .field label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #3a4a6b;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
 
        .field input {
            width: 100%;
            border: 1.5px solid #d0d8ea;
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 14px;
            font-family: 'Source Sans 3', sans-serif;
            color: #1a2a4a;
            background: #f8fafc;
            outline: none;
            transition: border-color 0.15s, background 0.15s;
        }
 
        .field input:focus { border-color: #0a2a5e; background: #fff; }
        .field input::placeholder { color: #a8b4cc; }
 
        .input-wrap { position: relative; }
        .input-wrap input { padding-right: 44px; }
 
        .toggle-pw {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer;
            color: #7a8aaa;
            font-size: 16px;
            padding: 4px; line-height: 1;
        }
        .toggle-pw:hover { color: #0a2a5e; }
 
        .error-box {
            background: #fff0f0;
            border: 1px solid #f0b0b0;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 1.1rem;
            font-size: 13px;
            color: #a82020;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .error-box .err-icon { font-size: 14px; flex-shrink: 0; margin-top: 1px; }
 
        .btn-login {
            width: 100%;
            background: #0a2a5e;
            color: #f0d080;
            border: none;
            border-radius: 8px;
            padding: 13px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Source Sans 3', sans-serif;
            letter-spacing: 0.04em;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            margin-top: 0.25rem;
        }
        .btn-login:hover  { background: #0d3575; }
        .btn-login:active { background: #071e47; transform: scale(0.99); }
 
        .card-footer {
            border-top: 1px solid #eef0f6;
            padding: 12px 2rem;
            text-align: right;
            font-size: 11px;
            color: #8a98b4;
        }
 
        @media (max-width: 480px) {
            .card-header h1 { font-size: 15px; }
            .card-body { padding: 1.5rem 1.25rem 1.25rem; }
            .card-footer { padding: 10px 1.25rem; }
        }
    </style>
</head>
<body>
 
<div class="card" role="main">
 
    <div class="card-header">
        <div class="seal" aria-hidden="true">&#127963;</div>
        <h1>Barangay E-Records<br>File Management System</h1>
        <p>Secretary Portal</p>
    </div>
 
    <div class="card-body">
 
        <?php if (!empty($error)): ?>
        <div class="error-box" role="alert">
            <span class="err-icon">&#9888;</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>
 
        <form method="POST" action="login.php" novalidate>
 
            <div class="field">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    required
                    autofocus
                    autocomplete="username"
                >
            </div>
 
            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="toggle-pw" onclick="togglePassword()" aria-label="Toggle password visibility">
                        <span id="eyeIcon">&#128065;</span>
                    </button>
                </div>
            </div>
 
            <button type="submit" class="btn-login">Sign In to System</button>
 
        </form>
    </div>
 
    <div class="card-footer">
        Barangay E-Records &copy; <?= date('Y') ?>
    </div>
 
</div>
 
<script>
    function togglePassword() {
        var pw   = document.getElementById('password');
        var icon = document.getElementById('eyeIcon');
        if (pw.type === 'password') {
            pw.type = 'text';
            icon.innerHTML = '&#128683;';
        } else {
            pw.type = 'password';
            icon.innerHTML = '&#128065;';
        }
    }
</script>
 
</body>
</html>