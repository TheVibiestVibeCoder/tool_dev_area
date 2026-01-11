<?php
require_once 'user_auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('admin.php');
}

$error = '';
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validate passwords match
        if ($password !== $password_confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Attempt registration
            $result = registerUser($email, $password);

            if ($result['success']) {
                // Registration successful - user is auto-logged in
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
    <title>Create Account - Live Situation Room</title>
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

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Roboto', sans-serif;
            transition: all 0.3s ease;
        }

        input[type="email"]:focus,
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

        .divider {
            text-align: center;
            margin: 32px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e0e0e0;
        }

        .divider span {
            background: white;
            padding: 0 16px;
            color: #767676;
            font-size: 14px;
            position: relative;
            z-index: 1;
        }

        .link-box {
            text-align: center;
            padding: 16px;
            background: #f8f8f8;
            border-radius: 8px;
            margin-top: 24px;
        }

        .link-box p {
            font-size: 14px;
            color: #767676;
            margin-bottom: 8px;
        }

        .link-box a {
            color: var(--ip-blue);
            text-decoration: none;
            font-weight: 500;
        }

        .link-box a:hover {
            text-decoration: underline;
        }

        .features {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e0e0e0;
        }

        .features h3 {
            font-size: 14px;
            color: var(--ip-dark);
            margin-bottom: 12px;
            font-weight: 600;
        }

        .features ul {
            list-style: none;
            padding: 0;
        }

        .features li {
            font-size: 13px;
            color: #767676;
            padding: 6px 0;
            padding-left: 24px;
            position: relative;
        }

        .features li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--accent-success);
            font-weight: bold;
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
            <h1>Live Situation Room</h1>
            <p>Create Your Account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

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

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    minlength="8"
                    placeholder="Choose a strong password"
                >
                <div class="password-hint">Minimum 8 characters</div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    required
                    minlength="8"
                    placeholder="Re-enter your password"
                >
            </div>

            <button type="submit" class="btn">Create Account</button>
        </form>

        <div class="link-box">
            <p>Already have an account?</p>
            <a href="login.php">Sign In</a>
        </div>

        <div class="features">
            <h3>What You Get:</h3>
            <ul>
                <li>Real-time collaborative workshops</li>
                <li>Support for 50+ participants</li>
                <li>Customizable categories and themes</li>
                <li>Automatic backups and data safety</li>
                <li>QR code sharing for easy access</li>
            </ul>
        </div>
    </div>

    <script>
        // Client-side password match validation
        document.querySelector('form').addEventListener('submit', function(e) {
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
    </script>
</body>
</html>
