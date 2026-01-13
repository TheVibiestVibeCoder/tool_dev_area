<?php
require_once 'user_auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('admin.php');
}

$error = '';
$success = '';
$email_sent = false;

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = $_POST['email'] ?? '';

        // Attempt to create reset token and send email
        $result = createPasswordResetToken($email);

        if ($result['success']) {
            $success = $result['message'];
            $email_sent = true;
        } else {
            $error = $result['message'];
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
            max-width: 500px;
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

        input[type="email"],
        input[type="text"] {
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
            text-decoration: none;
            display: inline-block;
            text-align: center;
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

        /* --- SUCCESS TOKEN BOX --- */
        .token-box {
            background: #fafafa;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-input);
            padding: 24px;
            margin-top: 24px;
        }

        .token-box h3 {
            font-family: var(--font-head);
            font-size: 1.4rem;
            color: var(--text-main);
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }

        .token-box p {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .token-box ul {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-left: 20px;
            line-height: 1.6;
            margin-bottom: 20px;
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
            
            <p>Enter your email address and we'll send you a password reset link.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($email_sent): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>

            <div class="token-box">
                <h3>Check Your Email</h3>
                <p>If your email is registered in our system, you will receive a password reset link shortly.</p>
                <p>The link will expire in 1 hour for security reasons.</p>
                <p style="margin-top: 16px; font-weight: 600; color: var(--text-main);">Didn't receive the email?</p>
                <ul>
                    <li>Check your spam/junk folder</li>
                    <li>Make sure you entered the correct email address</li>
                    <li>Wait a few minutes and try again</li>
                </ul>
                <a href="forgot_password.php" class="btn" style="margin-top: 10px;">Request Another Link</a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <?= getCSRFField() ?>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        autofocus
                        placeholder="your@email.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                </div>

                <button type="submit" class="btn">Send Reset Link</button>
            </form>
        <?php endif; ?>

        <div class="link-box">
            <a href="login.php">← Back to Sign In</a>
        </div>
    </div>

    <script>
        // Auto-focus email input if form is visible
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            if (emailInput) {
                emailInput.focus();
            }
        });
    </script>
</body>
</html>