<?php
/**
 * Dev Admin First-Time Setup Page
 * Web-based interface to create the first dev admin account
 *
 * Security:
 * - Only works when no dev admins exist
 * - Requires a setup key for additional security
 * - Should be deleted after first admin is created
 */

require_once __DIR__ . '/dev_admin_auth.php';
require_once __DIR__ . '/security_helpers.php';

// Initialize security
setSecurityHeaders();
devAdminInitSession();

// Check if dev admins already exist
$existingAdmins = loadDevAdmins();
$setupComplete = !empty($existingAdmins);

// Setup key for security (change this to a random value or use environment variable)
define('SETUP_KEY', 'CHANGE_THIS_TO_RANDOM_STRING_12345');

$error = '';
$success = '';
$showSetupKeyForm = true;
$setupKeyVerified = false;

// Handle setup key verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_setup_key'])) {
    $submittedKey = $_POST['setup_key'] ?? '';
    if ($submittedKey === SETUP_KEY) {
        $_SESSION['setup_key_verified'] = true;
        $setupKeyVerified = true;
        $showSetupKeyForm = false;
    } else {
        $error = 'Invalid setup key. Please check your configuration.';
    }
}

// Check if setup key was previously verified in this session
if (isset($_SESSION['setup_key_verified']) && $_SESSION['setup_key_verified']) {
    $setupKeyVerified = true;
    $showSetupKeyForm = false;
}

// Handle account creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin']) && $setupKeyVerified && !$setupComplete) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // Validate input
        if (empty($username) || empty($fullName) || empty($email) || empty($password)) {
            $error = 'All fields are required.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Passwords do not match.';
        } else {
            // Create the admin account
            $result = createDevAdmin($username, $password, $email, $fullName);
            if ($result['success']) {
                $success = 'Dev admin account created successfully! You can now login at dev_login.php';
                $setupComplete = true;
                unset($_SESSION['setup_key_verified']);
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Generate CSRF token
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dev Admin Setup - Live Situation Room</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #fff;
        }

        .setup-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-section h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-section p {
            color: #a0a0a0;
            font-size: 14px;
        }

        .setup-badge {
            display: inline-block;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #e0e0e0;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 14px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            font-size: 15px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input::placeholder {
            color: #808080;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fbbf24;
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #93c5fd;
        }

        .security-notice {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #fbbf24;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .security-notice::before {
            content: '⚠️';
            font-size: 18px;
            flex-shrink: 0;
        }

        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #93c5fd;
        }

        .info-box strong {
            color: #bfdbfe;
        }

        .setup-complete {
            text-align: center;
            padding: 40px 20px;
        }

        .success-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: #764ba2;
        }

        .requirements {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #a0a0a0;
        }

        .requirements ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .requirements li {
            margin: 5px 0;
        }

        @media (max-width: 480px) {
            .setup-container {
                padding: 30px 20px;
            }

            .logo-section h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="logo-section">
            <h1>🛠️ Dev Admin Setup</h1>
            <p>First-Time Configuration</p>
            <span class="setup-badge">Initial Setup</span>
        </div>

        <?php if ($setupComplete): ?>
            <!-- Setup Complete -->
            <div class="setup-complete">
                <div class="success-icon">✅</div>
                <div class="alert alert-success">
                    Setup is complete! A dev admin account already exists.
                </div>
                <p style="color: #a0a0a0; margin-top: 15px;">
                    You can now proceed to the login page.
                </p>
                <div style="margin-top: 30px;">
                    <a href="dev_login.php" class="btn">Go to Login</a>
                </div>
            </div>

            <div class="security-notice">
                <div>
                    <strong>Security Recommendation:</strong> For security reasons, you should delete this setup file (dev_admin_setup.php) now that setup is complete.
                </div>
            </div>

        <?php elseif ($showSetupKeyForm): ?>
            <!-- Setup Key Verification -->
            <div class="info-box">
                <strong>Setup Key Required</strong><br>
                To proceed with creating the first dev admin account, you need to provide the setup key.
                <br><br>
                The setup key is defined in <code>dev_admin_setup.php</code> on line 22.<br>
                Default: <code>CHANGE_THIS_TO_RANDOM_STRING_12345</code>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div class="form-group">
                    <label for="setup_key">Setup Key</label>
                    <input
                        type="password"
                        id="setup_key"
                        name="setup_key"
                        placeholder="Enter the setup key"
                        required
                        autofocus
                    >
                </div>

                <button type="submit" name="verify_setup_key" class="btn">
                    🔐 Verify Setup Key
                </button>
            </form>

            <div class="security-notice" style="margin-top: 20px;">
                <div>
                    If you don't know the setup key, open <code>dev_admin_setup.php</code> in a text editor and look at line 22. You can change it to any value you want.
                </div>
            </div>

        <?php else: ?>
            <!-- Account Creation Form -->
            <div class="info-box">
                <strong>Create First Dev Admin Account</strong><br>
                This will create your first developer admin account. You'll use this account to access the dev control panel and manage your entire platform.
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <div style="margin-top: 20px;">
                    <a href="dev_login.php" class="btn">Go to Login</a>
                </div>
            <?php else: ?>

                <div class="requirements">
                    <strong>Requirements:</strong>
                    <ul>
                        <li>Username: 3-30 characters (alphanumeric + underscore)</li>
                        <li>Password: Minimum 8 characters (12+ recommended)</li>
                        <li>Email: Valid email format</li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="e.g., admin or john_dev"
                            required
                            pattern="[a-zA-Z0-9_]{3,30}"
                            title="3-30 characters, alphanumeric and underscore only"
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            placeholder="e.g., John Developer"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="your@email.com"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Minimum 8 characters"
                            required
                            minlength="8"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirm Password *</label>
                        <input
                            type="password"
                            id="password_confirm"
                            name="password_confirm"
                            placeholder="Re-enter your password"
                            required
                            minlength="8"
                        >
                    </div>

                    <button type="submit" name="create_admin" class="btn">
                        ✨ Create Dev Admin Account
                    </button>
                </form>

                <div class="security-notice" style="margin-top: 20px;">
                    <div>
                        <strong>After creating your account:</strong><br>
                        1. Login at dev_login.php<br>
                        2. Delete this setup file (dev_admin_setup.php) for security<br>
                        3. Create additional admin accounts from the CLI if needed
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="back-link">
            <a href="welcome.php">← Back to Main Site</a>
        </div>
    </div>

    <script>
        // Client-side password match validation
        const password = document.getElementById('password');
        const passwordConfirm = document.getElementById('password_confirm');

        if (passwordConfirm) {
            passwordConfirm.addEventListener('input', function() {
                if (password.value !== passwordConfirm.value) {
                    passwordConfirm.setCustomValidity('Passwords do not match');
                } else {
                    passwordConfirm.setCustomValidity('');
                }
            });
        }
    </script>
</body>
</html>
