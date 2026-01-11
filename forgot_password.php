<?php
require_once 'user_auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('admin.php');
}

$error = '';
$success = '';
$token = '';

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = $_POST['email'] ?? '';

        // Attempt to create reset token
        $result = createPasswordResetToken($email);

        if ($result['success']) {
            $success = $result['message'];
            if ($result['token']) {
                $token = $result['token'];
            }
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
            max-width: 500px;
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
            line-height: 1.5;
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

        input[type="email"],
        input[type="text"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Roboto', sans-serif;
            transition: all 0.3s ease;
        }

        input[type="email"]:focus,
        input[type="text"]:focus {
            outline: none;
            border-color: var(--ip-blue);
            box-shadow: 0 0 0 3px rgba(0, 101, 139, 0.1);
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
        }

        .btn:hover {
            background: #004d6b;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 101, 139, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: #767676;
            margin-top: 12px;
        }

        .btn-secondary:hover {
            background: #5a5a5a;
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

        .token-box {
            background: #f8f8f8;
            border: 2px solid var(--accent-success);
            border-radius: 8px;
            padding: 20px;
            margin-top: 24px;
        }

        .token-box h3 {
            font-size: 16px;
            color: var(--ip-dark);
            margin-bottom: 12px;
        }

        .token-box .token {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 12px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            word-break: break-all;
            margin-bottom: 12px;
            color: var(--ip-dark);
        }

        .token-box p {
            font-size: 13px;
            color: #767676;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .copy-btn {
            width: 100%;
            padding: 12px;
            background: var(--ip-blue);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            margin-bottom: 8px;
        }

        .copy-btn:hover {
            background: #004d6b;
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
            <h1>Reset Password</h1>
            <p>Enter your email address and we'll generate a reset token for you.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success && !$token): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($token): ?>
            <div class="alert alert-success">
                Password reset token generated successfully!
            </div>

            <div class="token-box">
                <h3>Your Reset Token:</h3>
                <div class="token" id="token-display"><?= htmlspecialchars($token) ?></div>
                <p>Copy this token and use it on the password reset page. This token will expire in 1 hour.</p>
                <button type="button" class="copy-btn" onclick="copyToken()">📋 Copy Token</button>
                <a href="reset_password.php?token=<?= urlencode($token) ?>" class="btn">Continue to Reset Password →</a>
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

                <button type="submit" class="btn">Generate Reset Token</button>
            </form>
        <?php endif; ?>

        <div class="link-box">
            <a href="login.php">← Back to Sign In</a>
        </div>
    </div>

    <script>
        function copyToken() {
            const token = document.getElementById('token-display').textContent;
            navigator.clipboard.writeText(token).then(function() {
                const btn = document.querySelector('.copy-btn');
                const originalText = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.background = '#00d084';

                setTimeout(function() {
                    btn.textContent = originalText;
                    btn.style.background = '';
                }, 2000);
            }).catch(function(err) {
                alert('Failed to copy token. Please copy it manually.');
            });
        }
    </script>
</body>
</html>
