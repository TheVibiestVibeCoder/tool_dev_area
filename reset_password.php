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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --ip-blue: #00658b;
            --ip-dark: #32373c;
            --ip-grey-bg: #f4f4f4;
            --accent-success: #00d084;
            --accent-danger: #cf2e2e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--ip-blue) 0%, #004d6b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 480px;
            width: 100%;
            padding: 48px 40px;
        }

        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 28px;
            color: var(--ip-blue);
            margin-bottom: 8px;
        }

        .logo p {
            font-size: 14px;
            color: #767676;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            font-weight: 500;
            color: var(--ip-dark);
            margin-bottom: 8px;
            font-size: 14px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Roboto', sans-serif;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--ip-blue);
            box-shadow: 0 0 0 3px rgba(0, 101, 139, 0.1);
        }

        .password-hint {
            font-size: 12px;
            color: #767676;
            margin-top: 6px;
        }

        .btn {
            width: 100%;
            padding: 16px;
            background: var(--ip-blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            font-family: 'Roboto', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
        }

        .btn:hover {
            background: #004d6b;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 101, 139, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .alert-error {
            background: #ffe6e6;
            color: var(--accent-danger);
            border: 1px solid #ffcccc;
        }

        .alert-success {
            background: #e6f9f0;
            color: #00855a;
            border: 1px solid #b3e6d1;
        }

        .link-box {
            text-align: center;
            padding: 16px;
            background: #f8f8f8;
            border-radius: 8px;
            margin-top: 24px;
        }

        .link-box a {
            color: var(--ip-blue);
            text-decoration: none;
            font-size: 14px;
        }

        .link-box a:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .container {
                padding: 32px 24px;
            }

            .logo h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>Set New Password</h1>
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
        // Client-side password match validation
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
                        this.style.borderColor = '#00d084';
                    } else {
                        this.style.borderColor = '#cf2e2e';
                    }
                } else {
                    this.style.borderColor = '#e0e0e0';
                }
            });
        }
    </script>
</body>
</html>
