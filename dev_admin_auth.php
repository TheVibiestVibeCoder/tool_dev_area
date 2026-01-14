<?php
/**
 * Dev Admin Authentication System
 * Separate authentication system for developer/admin access to master control panel
 *
 * Security Features:
 * - Separate session namespace (dev_admin_*)
 * - Rate limiting on login attempts
 * - Bcrypt password hashing
 * - CSRF protection
 * - Secure session configuration
 *
 * @version 1.0.0
 * @author Live Situation Room Dev Team
 */

require_once __DIR__ . '/file_handling_robust.php';
require_once __DIR__ . '/security_helpers.php';

// Constants
define('DEV_ADMINS_FILE', __DIR__ . '/dev_admins.json');
define('DEV_ADMIN_SESSION_PREFIX', 'dev_admin_');
define('DEV_ADMIN_SESSION_TIMEOUT', 7200); // 2 hours
define('DEV_ADMIN_MAX_LOGIN_ATTEMPTS', 5);
define('DEV_ADMIN_LOCKOUT_DURATION', 900); // 15 minutes

/**
 * Initialize secure session for dev admins
 */
function devAdminInitSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', '1');
        }
        session_name('DEV_ADMIN_SESSION');
        session_start();
    }
}

/**
 * Check if dev admin is logged in
 *
 * @return bool True if logged in and session valid
 */
function isDevAdminLoggedIn() {
    devAdminInitSession();

    if (!isset($_SESSION[DEV_ADMIN_SESSION_PREFIX . 'logged_in']) ||
        !$_SESSION[DEV_ADMIN_SESSION_PREFIX . 'logged_in']) {
        return false;
    }

    // Check session timeout
    if (isset($_SESSION[DEV_ADMIN_SESSION_PREFIX . 'last_activity'])) {
        $elapsed = time() - $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'last_activity'];
        if ($elapsed > DEV_ADMIN_SESSION_TIMEOUT) {
            devAdminLogout();
            return false;
        }
    }

    // Update last activity time
    $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'last_activity'] = time();

    return true;
}

/**
 * Get current dev admin username
 *
 * @return string|null Username or null if not logged in
 */
function getCurrentDevAdmin() {
    if (!isDevAdminLoggedIn()) {
        return null;
    }
    return $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'username'] ?? null;
}

/**
 * Load all dev admin accounts
 *
 * @return array Array of dev admin accounts
 */
function loadDevAdmins() {
    if (!file_exists(DEV_ADMINS_FILE)) {
        return [];
    }

    $content = file_get_contents(DEV_ADMINS_FILE);
    if ($content === false) {
        return [];
    }

    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

/**
 * Save dev admin accounts
 *
 * @param array $admins Array of dev admin accounts
 * @return bool Success status
 */
function saveDevAdmins($admins) {
    $content = json_encode($admins, JSON_PRETTY_PRINT);

    // Use atomic file operations with locking
    $fp = fopen(DEV_ADMINS_FILE, 'c');
    if (!$fp) {
        return false;
    }

    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        $result = fwrite($fp, $content);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $result !== false;
    }

    fclose($fp);
    return false;
}

/**
 * Create a new dev admin account
 *
 * @param string $username Username (alphanumeric + underscore)
 * @param string $password Plain text password (will be hashed)
 * @param string $email Email address
 * @param string $full_name Full name of the admin
 * @return array Result with 'success' boolean and 'message' string
 */
function createDevAdmin($username, $password, $email, $full_name) {
    // Validate input
    if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
        return ['success' => false, 'message' => 'All fields are required'];
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        return ['success' => false, 'message' => 'Username must be 3-30 alphanumeric characters or underscore'];
    }

    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }

    // Load existing admins
    $admins = loadDevAdmins();

    // Check if username already exists
    foreach ($admins as $admin) {
        if ($admin['username'] === $username) {
            return ['success' => false, 'message' => 'Username already exists'];
        }
        if ($admin['email'] === $email) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
    }

    // Create new admin account
    $newAdmin = [
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
        'email' => $email,
        'full_name' => $full_name,
        'created_at' => date('Y-m-d H:i:s'),
        'last_login' => null,
        'login_attempts' => 0,
        'locked_until' => null,
        'active' => true
    ];

    $admins[] = $newAdmin;

    if (saveDevAdmins($admins)) {
        return ['success' => true, 'message' => 'Dev admin account created successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to save dev admin account'];
    }
}

/**
 * Authenticate dev admin login
 *
 * @param string $username Username
 * @param string $password Plain text password
 * @return array Result with 'success' boolean and 'message' string
 */
function devAdminLogin($username, $password) {
    // Rate limiting check
    $rateLimitResult = checkDevAdminRateLimit();
    if (!$rateLimitResult['allowed']) {
        return [
            'success' => false,
            'message' => 'Too many login attempts. Please try again in ' . ceil($rateLimitResult['retry_after'] / 60) . ' minutes.'
        ];
    }

    // Load admins
    $admins = loadDevAdmins();

    // Find admin by username
    $adminIndex = null;
    $admin = null;
    foreach ($admins as $index => $a) {
        if ($a['username'] === $username) {
            $adminIndex = $index;
            $admin = $a;
            break;
        }
    }

    if ($admin === null) {
        recordDevAdminLoginAttempt(false);
        return ['success' => false, 'message' => 'Invalid username or password'];
    }

    // Check if account is active
    if (!$admin['active']) {
        return ['success' => false, 'message' => 'This account has been deactivated'];
    }

    // Check if account is locked
    if ($admin['locked_until'] !== null && strtotime($admin['locked_until']) > time()) {
        $remainingTime = ceil((strtotime($admin['locked_until']) - time()) / 60);
        return ['success' => false, 'message' => 'Account is locked. Try again in ' . $remainingTime . ' minutes.'];
    }

    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        // Increment login attempts
        $admins[$adminIndex]['login_attempts']++;
        if ($admins[$adminIndex]['login_attempts'] >= DEV_ADMIN_MAX_LOGIN_ATTEMPTS) {
            $admins[$adminIndex]['locked_until'] = date('Y-m-d H:i:s', time() + DEV_ADMIN_LOCKOUT_DURATION);
        }
        saveDevAdmins($admins);
        recordDevAdminLoginAttempt(false);
        return ['success' => false, 'message' => 'Invalid username or password'];
    }

    // Successful login - reset attempts and update last login
    $admins[$adminIndex]['login_attempts'] = 0;
    $admins[$adminIndex]['locked_until'] = null;
    $admins[$adminIndex]['last_login'] = date('Y-m-d H:i:s');
    saveDevAdmins($admins);

    // Initialize session
    devAdminInitSession();
    session_regenerate_id(true);
    $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'logged_in'] = true;
    $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'username'] = $username;
    $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'email'] = $admin['email'];
    $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'full_name'] = $admin['full_name'];
    $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'login_time'] = time();
    $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'last_activity'] = time();

    recordDevAdminLoginAttempt(true);

    return ['success' => true, 'message' => 'Login successful'];
}

/**
 * Logout dev admin
 */
function devAdminLogout() {
    devAdminInitSession();

    // Clear all dev admin session variables
    foreach (array_keys($_SESSION) as $key) {
        if (strpos($key, DEV_ADMIN_SESSION_PREFIX) === 0) {
            unset($_SESSION[$key]);
        }
    }

    session_destroy();
}

/**
 * Rate limiting for login attempts (IP-based)
 *
 * @return array ['allowed' => bool, 'retry_after' => int]
 */
function checkDevAdminRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitFile = __DIR__ . '/data/dev_admin_rate_limit.json';

    // Ensure data directory exists
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0777, true);
    }

    $fp = fopen($rateLimitFile, 'c+');
    if (!$fp) {
        return ['allowed' => true, 'retry_after' => 0];
    }

    if (flock($fp, LOCK_EX)) {
        $content = stream_get_contents($fp);
        $data = json_decode($content, true) ?: [];

        $now = time();
        $windowStart = $now - DEV_ADMIN_LOCKOUT_DURATION;

        // Clean old entries
        foreach ($data as $key => $entry) {
            if ($entry['timestamp'] < $windowStart) {
                unset($data[$key]);
            }
        }

        // Count attempts for this IP in current window
        $ipAttempts = array_filter($data, function($entry) use ($ip, $windowStart) {
            return $entry['ip'] === $ip &&
                   $entry['timestamp'] >= $windowStart &&
                   !$entry['success'];
        });

        $allowed = count($ipAttempts) < DEV_ADMIN_MAX_LOGIN_ATTEMPTS;
        $retryAfter = 0;

        if (!$allowed && !empty($ipAttempts)) {
            $oldestAttempt = min(array_column($ipAttempts, 'timestamp'));
            $retryAfter = ($oldestAttempt + DEV_ADMIN_LOCKOUT_DURATION) - $now;
        }

        // Save cleaned data
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode(array_values($data)));

        flock($fp, LOCK_UN);
    }

    fclose($fp);

    return ['allowed' => $allowed, 'retry_after' => max(0, $retryAfter)];
}

/**
 * Record a login attempt for rate limiting
 *
 * @param bool $success Whether the login was successful
 */
function recordDevAdminLoginAttempt($success) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitFile = __DIR__ . '/data/dev_admin_rate_limit.json';

    $fp = fopen($rateLimitFile, 'c+');
    if (!$fp) {
        return;
    }

    if (flock($fp, LOCK_EX)) {
        $content = stream_get_contents($fp);
        $data = json_decode($content, true) ?: [];

        $data[] = [
            'ip' => $ip,
            'timestamp' => time(),
            'success' => $success
        ];

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));

        flock($fp, LOCK_UN);
    }

    fclose($fp);
}

/**
 * Require dev admin authentication (redirect if not logged in)
 *
 * @param string $redirectTo URL to redirect to if not authenticated
 */
function requireDevAdmin($redirectTo = 'dev_login.php') {
    if (!isDevAdminLoggedIn()) {
        header('Location: ' . $redirectTo);
        exit;
    }
}

/**
 * Get dev admin session info
 *
 * @return array|null Session info or null if not logged in
 */
function getDevAdminInfo() {
    if (!isDevAdminLoggedIn()) {
        return null;
    }

    return [
        'username' => $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'username'] ?? null,
        'email' => $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'email'] ?? null,
        'full_name' => $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'full_name'] ?? null,
        'login_time' => $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'login_time'] ?? null,
        'last_activity' => $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'last_activity'] ?? null
    ];
}
