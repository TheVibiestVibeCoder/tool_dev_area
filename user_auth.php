<?php
/**
 * USER AUTHENTICATION LIBRARY
 * Multi-tenant user management for Live Situation Room SaaS
 *
 * Features:
 * - User registration with password hashing
 * - User authentication
 * - Password reset with tokens
 * - Session management
 * - Rate limiting
 * - CSRF protection
 * - User data isolation
 */

// Load security helpers
require_once __DIR__ . '/security_helpers.php';

// Configure secure session before starting
configureSecureSession();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration
define('USERS_FILE', 'users.json');
define('RESET_TOKENS_FILE', 'password_reset_tokens.json');
define('DATA_DIR', 'data');
define('RATE_LIMIT_FILE', 'rate_limits.json');
define('PASSWORD_MIN_LENGTH', 8);
define('RESET_TOKEN_EXPIRY', 3600); // 1 hour
define('SESSION_TIMEOUT', 7200); // 2 hours

/**
 * INITIALIZATION FUNCTIONS
 */

/**
 * Initialize system files if they don't exist
 */
function initializeSystemFiles() {
    // Create users.json
    if (!file_exists(USERS_FILE)) {
        file_put_contents(USERS_FILE, json_encode(['users' => []], JSON_PRETTY_PRINT));
        @chmod(USERS_FILE, 0666);
    }

    // Create password_reset_tokens.json
    if (!file_exists(RESET_TOKENS_FILE)) {
        file_put_contents(RESET_TOKENS_FILE, json_encode(['tokens' => []], JSON_PRETTY_PRINT));
        @chmod(RESET_TOKENS_FILE, 0666);
    }

    // Create data directory
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);

        // Create .htaccess to protect data directory
        $htaccess = <<<'HTACCESS'
# Deny direct access to user data
Order Deny,Allow
Deny from all
HTACCESS;
        file_put_contents(DATA_DIR . '/.htaccess', $htaccess);
    }

    // Create rate_limits.json
    if (!file_exists(RATE_LIMIT_FILE)) {
        file_put_contents(RATE_LIMIT_FILE, json_encode(['limits' => []], JSON_PRETTY_PRINT));
        @chmod(RATE_LIMIT_FILE, 0666);
    }
}

/**
 * USER REGISTRATION
 */

/**
 * Register a new user
 *
 * @param string $email User email (used as username)
 * @param string $password Plain text password
 * @return array ['success' => bool, 'message' => string, 'user_id' => string|null]
 */
function registerUser($email, $password) {
    initializeSystemFiles();

    // Validate email
    $email = trim(strtolower($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address format.', 'user_id' => null];
    }

    // Validate password
    // 🔒 SECURITY: Check both min and max length (prevent DoS via very long passwords)
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.', 'user_id' => null];
    }
    if (strlen($password) > 128) {
        return ['success' => false, 'message' => 'Password must be less than 128 characters.', 'user_id' => null];
    }

    // Check rate limiting
    if (!checkRateLimit('register', $_SERVER['REMOTE_ADDR'], 3, 3600)) {
        return ['success' => false, 'message' => 'Too many registration attempts. Please try again later.', 'user_id' => null];
    }

    // Load existing users
    $users_data = json_decode(file_get_contents(USERS_FILE), true);

    // Check if email already exists
    // 🔒 SECURITY: Use generic error message to prevent user enumeration
    foreach ($users_data['users'] as $user) {
        if ($user['email'] === $email) {
            return ['success' => false, 'message' => 'Registration failed. Please try a different email or contact support.', 'user_id' => null];
        }
    }

    // Generate unique user ID
    $user_id = uniqid('user_', true);

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Create user record
    $new_user = [
        'id' => $user_id,
        'email' => $email,
        'password_hash' => $password_hash,
        'created_at' => time(),
        'last_login' => time(),
        'subscription' => [
            'plan_id' => 'free',
            'status' => 'active',
            'stripe_customer_id' => null,
            'stripe_subscription_id' => null,
            'current_period_start' => null,
            'current_period_end' => null,
            'cancel_at_period_end' => false,
            'created_at' => time(),
            'updated_at' => time()
        ]
    ];

    // Add user to database
    $users_data['users'][] = $new_user;
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Initialize user data directory
    $init_result = initializeUserData($user_id);
    if (!$init_result['success']) {
        return ['success' => false, 'message' => 'Failed to initialize user data: ' . $init_result['message'], 'user_id' => null];
    }

    // Auto-login user
    loginUserSession($new_user);

    return ['success' => true, 'message' => 'Account created successfully!', 'user_id' => $user_id];
}

/**
 * USER AUTHENTICATION
 */

/**
 * Authenticate a user
 *
 * @param string $email User email
 * @param string $password Plain text password
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function authenticateUser($email, $password) {
    initializeSystemFiles();

    $email = trim(strtolower($email));

    // Check rate limiting (20 attempts per 15 minutes)
    if (!checkRateLimit('login', $_SERVER['REMOTE_ADDR'], 20, 900)) {
        return ['success' => false, 'message' => 'Too many login attempts. Please try again later.', 'user' => null];
    }

    // Load users
    $users_data = json_decode(file_get_contents(USERS_FILE), true);

    // Find user by email
    $user = null;
    foreach ($users_data['users'] as &$u) {
        if ($u['email'] === $email) {
            $user = &$u;
            break;
        }
    }

    // Generic error message to prevent user enumeration
    $generic_error = 'Invalid email or password.';

    if ($user === null) {
        // Sleep to prevent timing attacks
        usleep(random_int(100000, 300000)); // 0.1-0.3 seconds
        return ['success' => false, 'message' => $generic_error, 'user' => null];
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        usleep(random_int(100000, 300000));
        return ['success' => false, 'message' => $generic_error, 'user' => null];
    }

    // Reset rate limit counter on successful login
    resetRateLimit('login', $_SERVER['REMOTE_ADDR']);

    // Update last login
    $user['last_login'] = time();
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Set session
    loginUserSession($user);

    return ['success' => true, 'message' => 'Login successful!', 'user' => $user];
}

/**
 * Set user session after successful authentication
 *
 * @param array $user User data
 */
function loginUserSession($user) {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
}

/**
 * SESSION MANAGEMENT
 */

/**
 * Check if user is logged in
 *
 * @return bool
 */
function isLoggedIn() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }

    // Check session timeout
    if (isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            logoutUser();
            return false;
        }
    }

    return true;
}

/**
 * Get current logged-in user data
 *
 * @return array|null User data or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    $user_id = $_SESSION['user_id'];
    $users_data = json_decode(file_get_contents(USERS_FILE), true);

    foreach ($users_data['users'] as $user) {
        if ($user['id'] === $user_id) {
            return $user;
        }
    }

    return null;
}

/**
 * Logout current user
 */
function logoutUser() {
    $_SESSION = [];
    session_destroy();
}

/**
 * Require authentication (redirect to login if not authenticated)
 *
 * @param string $redirect_to URL to redirect to after login
 */
function requireAuth($redirect_to = null) {
    if (!isLoggedIn()) {
        $redirect_url = 'login.php';
        if ($redirect_to) {
            $redirect_url .= '?redirect=' . urlencode($redirect_to);
        }
        header('Location: ' . $redirect_url);
        exit;
    }
}

/**
 * USER DATA MANAGEMENT
 */

/**
 * Get user data directory path
 *
 * @param string $user_id User ID
 * @return string Full path to user data directory
 */
function getUserDataPath($user_id) {
    return DATA_DIR . '/' . $user_id;
}

/**
 * Get user-specific file path
 *
 * @param string $user_id User ID
 * @param string $filename File name (e.g., 'daten.json', 'config.json')
 * @return string Full path to file
 */
function getUserFile($user_id, $filename) {
    return getUserDataPath($user_id) . '/' . $filename;
}

/**
 * Initialize user data directory with default files
 *
 * @param string $user_id User ID
 * @return array ['success' => bool, 'message' => string]
 */
function initializeUserData($user_id) {
    $user_dir = getUserDataPath($user_id);

    // Create user directory
    if (!is_dir($user_dir)) {
        if (!mkdir($user_dir, 0777, true)) {
            return ['success' => false, 'message' => 'Failed to create user directory'];
        }
    }

    // Create backups directory
    $backups_dir = $user_dir . '/backups';
    if (!is_dir($backups_dir)) {
        if (!mkdir($backups_dir, 0777, true)) {
            return ['success' => false, 'message' => 'Failed to create backups directory'];
        }
    }

    // Create empty daten.json
    $daten_file = getUserFile($user_id, 'daten.json');
    if (!file_exists($daten_file)) {
        file_put_contents($daten_file, '[]');
        @chmod($daten_file, 0666);
    }

    // Create default config.json
    $config_file = getUserFile($user_id, 'config.json');
    if (!file_exists($config_file)) {
        $default_config = [
            'header_title' => 'Live Situation Room',
            'logo_url' => '',
            'categories' => [
                [
                    'key' => 'general',
                    'name' => 'GENERAL',
                    'abbreviation' => 'GEN',
                    'icon' => '💡',
                    'display_name' => '💡 General Ideas',
                    'leitfragen' => [
                        'What ideas do you have?',
                        'What would you like to discuss?'
                    ]
                ]
            ]
        ];
        file_put_contents($config_file, json_encode($default_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        @chmod($config_file, 0666);
    }

    return ['success' => true, 'message' => 'User data initialized successfully'];
}

/**
 * PASSWORD RESET
 */

/**
 * Create a password reset token for user and send email
 *
 * @param string $email User email
 * @return array ['success' => bool, 'message' => string, 'token' => string|null]
 */
function createPasswordResetToken($email) {
    initializeSystemFiles();

    $email = trim(strtolower($email));

    // Check rate limiting (3 attempts per hour)
    if (!checkRateLimit('password_reset', $_SERVER['REMOTE_ADDR'], 3, 3600)) {
        return ['success' => false, 'message' => 'Too many password reset requests. Please try again later.', 'token' => null];
    }

    // Load users
    $users_data = json_decode(file_get_contents(USERS_FILE), true);

    // Find user
    $user = null;
    foreach ($users_data['users'] as $u) {
        if ($u['email'] === $email) {
            $user = $u;
            break;
        }
    }

    // Return generic success message even if email doesn't exist (prevent enumeration)
    if ($user === null) {
        return ['success' => true, 'message' => 'If this email is registered, a password reset link has been sent.', 'token' => null];
    }

    // Generate secure token
    $token = bin2hex(random_bytes(32));

    // Load tokens
    $tokens_data = json_decode(file_get_contents(RESET_TOKENS_FILE), true);

    // Create token record
    $new_token = [
        'token' => $token,
        'user_id' => $user['id'],
        'created_at' => time(),
        'expires_at' => time() + RESET_TOKEN_EXPIRY,
        'used' => false
    ];

    $tokens_data['tokens'][] = $new_token;
    file_put_contents(RESET_TOKENS_FILE, json_encode($tokens_data, JSON_PRETTY_PRINT));

    // Clean up expired tokens
    cleanupExpiredTokens();

    // Send email
    $email_sent = sendPasswordResetEmail($email, $token);

    if (!$email_sent) {
        // Email failed to send, but don't reveal this to prevent enumeration
        error_log("Failed to send password reset email to: " . $email);
    }

    return ['success' => true, 'message' => 'If this email is registered, a password reset link has been sent.', 'token' => null];
}

/**
 * Validate a password reset token
 *
 * @param string $token Reset token
 * @return array ['valid' => bool, 'message' => string, 'user_id' => string|null]
 */
function validateResetToken($token) {
    initializeSystemFiles();

    $tokens_data = json_decode(file_get_contents(RESET_TOKENS_FILE), true);

    foreach ($tokens_data['tokens'] as $t) {
        if ($t['token'] === $token) {
            // Check if token is expired
            if ($t['expires_at'] < time()) {
                return ['valid' => false, 'message' => 'Token has expired.', 'user_id' => null];
            }

            // Check if token is already used
            if ($t['used'] === true) {
                return ['valid' => false, 'message' => 'Token has already been used.', 'user_id' => null];
            }

            return ['valid' => true, 'message' => 'Token is valid.', 'user_id' => $t['user_id']];
        }
    }

    return ['valid' => false, 'message' => 'Invalid token.', 'user_id' => null];
}

/**
 * Reset password using token
 *
 * @param string $token Reset token
 * @param string $new_password New plain text password
 * @return array ['success' => bool, 'message' => string]
 */
function resetPasswordWithToken($token, $new_password) {
    initializeSystemFiles();

    // Validate password
    if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
        return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.'];
    }

    // Validate token
    $validation = validateResetToken($token);
    if (!$validation['valid']) {
        return ['success' => false, 'message' => $validation['message']];
    }

    $user_id = $validation['user_id'];

    // Update password
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user_found = false;

    foreach ($users_data['users'] as &$user) {
        if ($user['id'] === $user_id) {
            $user['password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
            $user_found = true;

            // Auto-login user
            loginUserSession($user);
            break;
        }
    }

    if (!$user_found) {
        return ['success' => false, 'message' => 'User not found.'];
    }

    // Save updated users
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Mark token as used
    $tokens_data = json_decode(file_get_contents(RESET_TOKENS_FILE), true);
    foreach ($tokens_data['tokens'] as &$t) {
        if ($t['token'] === $token) {
            $t['used'] = true;
            break;
        }
    }
    file_put_contents(RESET_TOKENS_FILE, json_encode($tokens_data, JSON_PRETTY_PRINT));

    return ['success' => true, 'message' => 'Password reset successfully!'];
}

/**
 * Clean up expired password reset tokens
 */
function cleanupExpiredTokens() {
    $tokens_data = json_decode(file_get_contents(RESET_TOKENS_FILE), true);
    $current_time = time();

    $tokens_data['tokens'] = array_filter($tokens_data['tokens'], function($token) use ($current_time) {
        // Keep tokens that haven't expired yet
        return $token['expires_at'] > $current_time;
    });

    // Re-index array
    $tokens_data['tokens'] = array_values($tokens_data['tokens']);

    file_put_contents(RESET_TOKENS_FILE, json_encode($tokens_data, JSON_PRETTY_PRINT));
}

/**
 * RATE LIMITING
 */

/**
 * Check rate limit for an action - ATOMIC VERSION
 * 🔒 SECURITY: Uses flock() to prevent race conditions
 *
 * @param string $action Action name (e.g., 'login', 'register')
 * @param string $identifier Identifier (e.g., IP address)
 * @param int $max_attempts Maximum attempts allowed
 * @param int $window_seconds Time window in seconds
 * @return bool True if allowed, false if rate limited
 */
function checkRateLimit($action, $identifier, $max_attempts, $window_seconds) {
    if (!file_exists(RATE_LIMIT_FILE)) {
        file_put_contents(RATE_LIMIT_FILE, json_encode(['limits' => []]));
        @chmod(RATE_LIMIT_FILE, 0666);
    }

    // Open file for reading/writing
    $fp = fopen(RATE_LIMIT_FILE, 'c+');
    if (!$fp) {
        // If we can't open file, fail open (allow request)
        error_log("Rate limit: Failed to open file");
        return true;
    }

    // 🔒 Acquire exclusive lock
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        // If we can't get lock, fail open (allow request)
        error_log("Rate limit: Failed to acquire lock");
        return true;
    }

    // Read current limits
    $filesize = filesize(RATE_LIMIT_FILE);
    $limits_data = ['limits' => []];
    if ($filesize > 0) {
        $content = fread($fp, $filesize);
        $limits_data = json_decode($content, true) ?: ['limits' => []];
    }

    $key = $action . '_' . $identifier;
    $current_time = time();

    // Find existing limit record
    $limit_record = null;
    $limit_index = null;
    foreach ($limits_data['limits'] as $index => $record) {
        if ($record['key'] === $key) {
            $limit_record = $record;
            $limit_index = $index;
            break;
        }
    }

    // If no record exists, create one
    if ($limit_record === null) {
        $limits_data['limits'][] = [
            'key' => $key,
            'attempts' => 1,
            'window_start' => $current_time
        ];

        // Write atomically
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($limits_data, JSON_PRETTY_PRINT));
        fflush($fp);

        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    // Check if window has expired
    if ($current_time - $limit_record['window_start'] > $window_seconds) {
        // Reset window
        $limits_data['limits'][$limit_index] = [
            'key' => $key,
            'attempts' => 1,
            'window_start' => $current_time
        ];

        // Write atomically
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($limits_data, JSON_PRETTY_PRINT));
        fflush($fp);

        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    // Check if max attempts exceeded
    if ($limit_record['attempts'] >= $max_attempts) {
        // Rate limit exceeded
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    // Increment attempts
    $limits_data['limits'][$limit_index]['attempts']++;

    // Write atomically
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($limits_data, JSON_PRETTY_PRINT));
    fflush($fp);

    // 🔓 Release lock and close
    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
}

/**
 * Reset rate limit for a specific action and identifier
 * 🔒 SECURITY: Uses flock() to prevent race conditions
 *
 * @param string $action Action name (e.g., 'login', 'register')
 * @param string $identifier Identifier (e.g., IP address)
 * @return bool True if reset successful, false otherwise
 */
function resetRateLimit($action, $identifier) {
    if (!file_exists(RATE_LIMIT_FILE)) {
        // No rate limit file, nothing to reset
        return true;
    }

    // Open file for reading/writing
    $fp = fopen(RATE_LIMIT_FILE, 'c+');
    if (!$fp) {
        error_log("Rate limit reset: Failed to open file");
        return false;
    }

    // 🔒 Acquire exclusive lock
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        error_log("Rate limit reset: Failed to acquire lock");
        return false;
    }

    // Read current limits
    $filesize = filesize(RATE_LIMIT_FILE);
    $limits_data = ['limits' => []];
    if ($filesize > 0) {
        $content = fread($fp, $filesize);
        $limits_data = json_decode($content, true) ?: ['limits' => []];
    }

    $key = $action . '_' . $identifier;

    // Find and remove the limit record
    $found = false;
    foreach ($limits_data['limits'] as $index => $record) {
        if ($record['key'] === $key) {
            unset($limits_data['limits'][$index]);
            $found = true;
            break;
        }
    }

    // Re-index array after unset
    $limits_data['limits'] = array_values($limits_data['limits']);

    // Write atomically
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($limits_data, JSON_PRETTY_PRINT));
    fflush($fp);

    // 🔓 Release lock and close
    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
}

/**
 * CSRF PROTECTION
 */

/**
 * Generate CSRF token
 *
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 *
 * @param string $token Token to validate
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token HTML input field
 *
 * @return string HTML input field
 */
function getCSRFField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * UTILITY FUNCTIONS
 */

/**
 * Redirect to a page
 *
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Get base URL path (e.g., /tool_dev_area or empty string)
 *
 * @return string Base path
 */
function getBasePath() {
    // Get the directory where the script is located
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    // Remove trailing slash if present
    return rtrim($script_dir, '/');
}

/**
 * Get user's public workshop URL
 *
 * @param string $user_id User ID
 * @return string Public workshop URL
 */
function getPublicWorkshopURL($user_id) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base_path = getBasePath();
    return $protocol . '://' . $host . $base_path . '/index.php?u=' . $user_id;
}

/**
 * Get user's public input form URL
 *
 * @param string $user_id User ID
 * @return string Public input form URL
 */
function getPublicInputURL($user_id) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base_path = getBasePath();
    return $protocol . '://' . $host . $base_path . '/eingabe.php?u=' . $user_id;
}

/**
 * Get password reset URL with token
 *
 * @param string $token Reset token
 * @return string Password reset URL
 */
function getPasswordResetURL($token) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base_path = getBasePath();
    return $protocol . '://' . $host . $base_path . '/reset_password.php?token=' . urlencode($token);
}

/**
 * Send password reset email
 *
 * @param string $email Recipient email
 * @param string $token Reset token
 * @return bool True if email sent successfully
 */
function sendPasswordResetEmail($email, $token) {
    $reset_url = getPasswordResetURL($token);

    $subject = 'Password Reset Request - Live Situation Room';

    $message = "Hello,\n\n";
    $message .= "You have requested to reset your password for Live Situation Room.\n\n";
    $message .= "Click the link below to reset your password:\n";
    $message .= $reset_url . "\n\n";
    $message .= "This link will expire in 1 hour.\n\n";
    $message .= "If you did not request this password reset, please ignore this email.\n\n";
    $message .= "Best regards,\n";
    $message .= "Live Situation Room Team";

    $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return mail($email, $subject, $message, $headers);
}
