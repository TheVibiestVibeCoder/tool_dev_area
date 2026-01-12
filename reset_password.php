<?php
require_once 'user_auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('admin.php');
}

$error = '';
$success = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$token_valid = false;

// Validate token if provided
if ($token) {
    $validation = validateResetToken($token);
    $token_valid = $validation['valid'];
    if (!$token_valid && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $error = $validation['message'];
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $new_password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validate passwords match
        if ($new_password !== $password_confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Attempt password reset
            $result = resetPasswordWithToken($token, $new_password);

            if ($result['success']) {
                // Password reset successful - user is auto-logged in
                redirect('admin.php');
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Live Situation Room</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        /* --- DESIGN SYSTEM (Monochrome / Bebas) --- */
        :root {
            /* Neutrals */
            --bg-body: #f5f5f5;
            --bg-card: #ffffff;
            --text-main: #111111;
            --text-muted: #666666;
            --border-color: #e0e0e0;
            
            /* Status Colors */
            --color-green: #27ae60; 
            --color-red: #e74c3c;   
            
            /* Typography */
            --font-head: 'Bebas Neue', sans-serif;
            --font-body: 'Inter', sans-serif;
            
            /* UI */
            --radius-input: 4px;
            --radius-btn: 4px;
            --shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-body);
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            max-width: 450px;
            width: 100%;
            padding: 40px;
            border-radius: var(--radius-btn);
        }

        /* --- HEADER --- */
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            font-family: var(--font-head);
            font-size: 3rem;
            line-height: 1;
            color: var(--text-main);
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .logo p {
            font-size: 0.95rem;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        /* --- FORM ELEMENTS --- */
        .form-group { margin-bottom: 20px; }

        label {
            display: block;
            font-family: var(--font-head);
            font-size: 1.1rem;
            color: var(--text-main);
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 14px;
            border: 1px solid var(--border-color);
            background: #fafafa;
            border-radius: var(--radius-input);
            font-size: 1rem;
            font-family: var(--font-body);
            color: var(--text-main);
            transition: all 0.2s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--text-main);
            background: #fff;
        }

        .password-hint {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* --- BUTTONS --- */
        .btn {
            width: 100%;
            padding: 16px;
            background: var(--text-main);
            color: #fff;
            border: 1px solid var(--text-main);
            border-radius: var(--radius-btn);
            font-family: var(--font-head);
            font-size: 1.2rem;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 10px;
            text-transform: uppercase;
        }

        .btn:hover {
            background: #333;
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* --- ALERTS --- */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-input);
            margin-bottom: 24px;
            font-size: 0.9rem;
            font-weight: 500;
            border-left: 4px solid;
        }

        .alert-error {
            background: #fff5f5;
            color: var(--color-red);
            border-color: var(--color-red);
        }

        .alert-success {
            background: #f0fff4;
            color: var(--color-green);
            border-color: var(--color-green);
        }

        /* --- LINK BOX --- */
        .link-box {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }

        .link-box a {
            color: var(--text-main);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            border-bottom: 1px solid transparent;
            transition: 0.2s;
        }

        .link-box a:hover {
            border-bottom-color: var(--text-main);
        }

        @media (max-width: 600px) {
            .container { padding: 30px 20px; border: none; box-shadow: none; background: transparent; }
            .logo h1 { font-size: 2.5rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>Reset Password</h1>
            <p>Enter your new password below</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!$token): ?>
            <div class="alert alert-error">
                No reset token provided. Please request a new password reset.
            </div>
            <div class="link-box">
                <a href="forgot_password.php">Request Password Reset</a>
            </div>
        <?php elseif (!$token_valid && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
            <div class="link-box">
                <a href="forgot_password.php">Request New Password Reset</a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <?= getCSRFField() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <?php if (!$token_valid): ?>
                    <div class="form-group">
                        <label for="token">Reset Token</label>
                        <input
                            type="text"
                            id="token"
                            name="token"
                            required
                            placeholder="Paste your reset token here"
                            value="<?= htmlspecialchars($token) ?>"
                        >
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="password">New Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        minlength="8"
                        placeholder="Choose a strong password"
                        autofocus
                    >
                    <div class="password-hint">Minimum 8 characters</div>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm New Password</label>
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        required
                        minlength="8"
                        placeholder="Re-enter your password"
                    >
                </div>

                <button type="submit" class="btn">Reset Password</button>
            </form>
        <?php endif; ?>

        <div class="link-box">
            <a href="login.php">← Back to Sign In</a>
        </div>
    </div>

    <script>
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirm = document.getElementById('password_confirm').value;

                if (password !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match. Please check and try again.');
                    return false;
                }

                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    return false;
                }
            });

            // Real-time password match indicator
            document.getElementById('password_confirm').addEventListener('input', function() {
                const password = document.getElementById('password').value;
                const confirm = this.value;

                if (confirm.length > 0) {
                    if (password === confirm) {
                        this.style.borderColor = '#27ae60'; // Green
                        this.style.boxShadow = '0 0 0 2px rgba(39, 174, 96, 0.1)';
                    } else {
                        this.style.borderColor = '#e74c3c'; // Red
                        this.style.boxShadow = '0 0 0 2px rgba(231, 76, 60, 0.1)';
                    }
                } else {
                    this.style.borderColor = '#e0e0e0';
                    this.style.boxShadow = 'none';
                }
            });
        }
    </script>
</body>
</html>