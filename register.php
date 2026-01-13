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

        input[type="email"],
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

        .link-box p {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 8px;
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

        /* --- FEATURES LIST --- */
        .features {
            margin-top: 30px;
            padding: 20px;
            background: #fafafa;
            border-radius: var(--radius-btn);
            border: 1px solid var(--border-color);
        }

        .features h3 {
            font-family: var(--font-head);
            font-size: 1.2rem;
            color: var(--text-main);
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }

        .features ul {
            list-style: none;
            padding: 0;
        }

        .features li {
            font-size: 0.85rem;
            color: var(--text-muted);
            padding: 6px 0;
            padding-left: 24px;
            position: relative;
        }

        .features li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--color-green);
            font-weight: bold;
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
                    placeholder="name@company.com"
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
                    placeholder="Create a password"
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
                    placeholder="Repeat password"
                >
            </div>

            <button type="submit" class="btn">Create Account</button>
        </form>

        <div class="link-box">
            <p>Already have an account?</p>
            <a href="login.php">Sign In →</a>
        </div>

        <div class="features">
            <h3>Included in Free Plan:</h3>
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
                    this.style.borderColor = '#27ae60'; // Design Green
                    this.style.boxShadow = '0 0 0 2px rgba(39, 174, 96, 0.1)';
                } else {
                    this.style.borderColor = '#e74c3c'; // Design Red
                    this.style.boxShadow = '0 0 0 2px rgba(231, 76, 60, 0.1)';
                }
            } else {
                this.style.borderColor = '#e0e0e0';
                this.style.boxShadow = 'none';
            }
        });
    </script>
</body>
</html>