<?php
/**
 * Dev Admin Authentication System - Production Security Hardened
 * REPLACE dev_admin_auth.php with this file for production
 *
 * Additional Security Features:
 * - Comprehensive security logging
 * - Optional IP whitelist
 * - Suspicious activity detection
 * - Account lockout notifications
 * - Session fingerprinting
 * - Timing attack prevention
 *
 * @version 2.0.0 - Production Ready
 */

require_once __DIR__ . '/file_handling_robust.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/dev_admin_security_logger.php';

// Constants
define('DEV_ADMINS_FILE', __DIR__ . '/dev_admins.json');
define('DEV_ADMIN_SESSION_PREFIX', 'dev_admin_');
define('DEV_ADMIN_SESSION_TIMEOUT', 3600); // 1 hour for production (stricter)
define('DEV_ADMIN_MAX_LOGIN_ATTEMPTS', 3); // Reduced to 3 for production
define('DEV_ADMIN_LOCKOUT_DURATION', 1800); // 30 minutes lockout

// Optional IP whitelist - comma separated IPs in environment variable
// Example: DEV_ADMIN_IP_WHITELIST="203.0.113.1,198.51.100.1"
define('DEV_ADMIN_IP_WHITELIST', getenv('DEV_ADMIN_IP_WHITELIST') ?: '');

/**
 * Check if IP is whitelisted (if whitelist is configured)
 */
function checkDevAdminIpWhitelist() {
    $whitelist = DEV_ADMIN_IP_WHITELIST;
    if (empty($whitelist)) {
        return true; // No whitelist configured, allow all
    }

    $allowedIps = array_map('trim', explode(',', $whitelist));
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!in_array($clientIp, $allowedIps)) {
        DevAdminSecurityLogger::security('IP_NOT_WHITELISTED', [
            'ip' => $clientIp,
            'allowed_ips' => $allowedIps
        ]);
        return false;
    }

    return true;
}

/**
 * Generate session fingerprint for additional security
 */
function generateDevAdminSessionFingerprint() {
    return hash('sha256',
        ($_SERVER['HTTP_USER_AGENT'] ?? '') .
        ($_SERVER['REMOTE_ADDR'] ?? '') .
        'dev_admin_salt_change_me'
    );
}

/**
 * Validate session fingerprint
 */
function validateDevAdminSessionFingerprint() {
    $currentFingerprint = generateDevAdminSessionFingerprint();
    $storedFingerprint = $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'fingerprint'] ?? '';

    return hash_equals($currentFingerprint, $storedFingerprint);
}

/**
 * Initialize secure session for dev admins
 */
function devAdminInitSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // More secure session configuration for production
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_lifetime', '0'); // Session cookie

        // Force HTTPS in production
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', '1');
        }

        session_name('DEV_ADMIN_SESSION');
        session_start();

        // Regenerate session ID periodically (every 15 minutes)
        if (!isset($_SESSION[DEV_ADMIN_SESSION_PREFIX . 'created'])) {
            $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'created'] = time();
        } else if (time() - $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'created'] > 900) {
            session_regenerate_id(true);
            $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'created'] = time();
        }
    }
}

/**
 * Check if dev admin is logged in
 */
function isDevAdminLoggedIn() {
    devAdminInitSession();

    // Check IP whitelist
    if (!checkDevAdminIpWhitelist()) {
        return false;
    }

    if (!isset($_SESSION[DEV_ADMIN_SESSION_PREFIX . 'logged_in']) ||
        !$_SESSION[DEV_ADMIN_SESSION_PREFIX . 'logged_in']) {
        return false;
    }

    // Validate session fingerprint
    if (!validateDevAdminSessionFingerprint()) {
        DevAdminSecurityLogger::security('SESSION_HIJACK_ATTEMPT', [
            'username' => $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'username'] ?? 'Unknown'
        ]);
        devAdminLogout();
        return false;
    }

    // Check session timeout
    if (isset($_SESSION[DEV_ADMIN_SESSION_PREFIX . 'last_activity'])) {
        $elapsed = time() - $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'last_activity'];
        if ($elapsed > DEV_ADMIN_SESSION_TIMEOUT) {
            DevAdminSecurityLogger::info('SESSION_TIMEOUT', [
                'username' => $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'username'] ?? 'Unknown',
                'elapsed_seconds' => $elapsed
            ]);
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
 */
function getCurrentDevAdmin() {
    if (!isDevAdminLoggedIn()) {
        return null;
    }
    return $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'username'] ?? null;
}

/**
 * Load all dev admin accounts
 */
function loadDevAdmins() {
    if (!file_exists(DEV_ADMINS_FILE)) {
        return [];
    }

    $content = file_get_contents(DEV_ADMINS_FILE);
    if ($content === false) {
        DevAdminSecurityLogger::error('FAILED_TO_READ_ADMINS_FILE');
        return [];
    }

    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

/**
 * Save dev admin accounts
 */
function saveDevAdmins($admins) {
    $content = json_encode($admins, JSON_PRETTY_PRINT);

    $fp = fopen(DEV_ADMINS_FILE, 'c');
    if (!$fp) {
        DevAdminSecurityLogger::error('FAILED_TO_OPEN_ADMINS_FILE');
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
 */
function createDevAdmin($username, $password, $email, $full_name) {
    // Validate input
    if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
        return ['success' => false, 'message' => 'All fields are required'];
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        return ['success' => false, 'message' => 'Username must be 3-30 alphanumeric characters or underscore'];
    }

    // Strengthen password requirements for production
    if (strlen($password) < 12) {
        return ['success' => false, 'message' => 'Password must be at least 12 characters for production security'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }

    $admins = loadDevAdmins();

    // Check duplicates
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
        DevAdminSecurityLogger::info('ADMIN_ACCOUNT_CREATED', [
            'username' => $username,
            'email' => $email
        ]);
        return ['success' => true, 'message' => 'Dev admin account created successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to save dev admin account'];
    }
}

/**
 * Authenticate dev admin login
 */
function devAdminLogin($username, $password) {
    // IP whitelist check
    if (!checkDevAdminIpWhitelist()) {
        return [
            'success' => false,
            'message' => 'Access denied from your IP address.'
        ];
    }

    // Rate limiting check
    $rateLimitResult = checkDevAdminRateLimit();
    if (!$rateLimitResult['allowed']) {
        DevAdminSecurityLogger::warning('RATE_LIMIT_EXCEEDED', [
            'username' => $username,
            'retry_after' => $rateLimitResult['retry_after']
        ]);
        return [
            'success' => false,
            'message' => 'Too many login attempts. Please try again in ' . ceil($rateLimitResult['retry_after'] / 60) . ' minutes.'
        ];
    }

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

    // Generic error message to prevent username enumeration
    $genericError = 'Invalid credentials';

    if ($admin === null) {
        recordDevAdminLoginAttempt(false);
        DevAdminSecurityLogger::warning('LOGIN_FAILED_INVALID_USERNAME', [
            'attempted_username' => $username
        ]);
        // Timing attack prevention - still verify a password even if user doesn't exist
        password_verify($password, '$2y$12$' . str_repeat('a', 53));
        return ['success' => false, 'message' => $genericError];
    }

    // Check if account is active
    if (!$admin['active']) {
        DevAdminSecurityLogger::warning('LOGIN_FAILED_INACTIVE_ACCOUNT', [
            'username' => $username
        ]);
        return ['success' => false, 'message' => 'Account is not active'];
    }

    // Check if account is locked
    if ($admin['locked_until'] !== null && strtotime($admin['locked_until']) > time()) {
        $remainingTime = ceil((strtotime($admin['locked_until']) - time()) / 60);
        DevAdminSecurityLogger::warning('LOGIN_FAILED_ACCOUNT_LOCKED', [
            'username' => $username,
            'locked_until' => $admin['locked_until']
        ]);
        return ['success' => false, 'message' => 'Account is locked. Try again in ' . $remainingTime . ' minutes.'];
    }

    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        // Increment login attempts
        $admins[$adminIndex]['login_attempts']++;
        if ($admins[$adminIndex]['login_attempts'] >= DEV_ADMIN_MAX_LOGIN_ATTEMPTS) {
            $admins[$adminIndex]['locked_until'] = date('Y-m-d H:i:s', time() + DEV_ADMIN_LOCKOUT_DURATION);
            DevAdminSecurityLogger::security('ACCOUNT_LOCKED_TOO_MANY_ATTEMPTS', [
                'username' => $username,
                'attempts' => $admins[$adminIndex]['login_attempts']
            ]);
        }
        saveDevAdmins($admins);
        recordDevAdminLoginAttempt(false);
        DevAdminSecurityLogger::warning('LOGIN_FAILED_INVALID_PASSWORD', [
            'username' => $username,
            'attempts' => $admins[$adminIndex]['login_attempts']
        ]);
        return ['success' => false, 'message' => $genericError];
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
    $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'fingerprint'] = generateDevAdminSessionFingerprint();

    recordDevAdminLoginAttempt(true);
    DevAdminSecurityLogger::info('LOGIN_SUCCESSFUL', [
        'username' => $username
    ]);

    return ['success' => true, 'message' => 'Login successful'];
}

/**
 * Logout dev admin
 */
function devAdminLogout() {
    $username = $_SESSION[DEV_ADMIN_SESSION_PREFIX . 'username'] ?? 'Unknown';

    devAdminInitSession();

    // Clear all dev admin session variables
    foreach (array_keys($_SESSION) as $key) {
        if (strpos($key, DEV_ADMIN_SESSION_PREFIX) === 0) {
            unset($_SESSION[$key]);
        }
    }

    // Get session parameters
    $params = session_get_cookie_params();

    // Delete the session cookie
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );

    // Destroy the session
    session_destroy();

    DevAdminSecurityLogger::info('LOGOUT', ['username' => $username]);
}

/**
 * Rate limiting for login attempts (IP-based)
 */
function checkDevAdminRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitFile = __DIR__ . '/data/dev_admin_rate_limit.json';

    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0750, true);
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
 */
function requireDevAdmin($redirectTo = 'dev_login.php') {
    if (!isDevAdminLoggedIn()) {
        header('Location: ' . $redirectTo);
        exit;
    }
}

/**
 * Get dev admin session info
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
