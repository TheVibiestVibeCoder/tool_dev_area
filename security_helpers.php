<?php
/**
 * Security Helper Functions
 *
 * Common security utilities to protect the application
 */

/**
 * Set secure HTTP headers to protect against common attacks
 */
function setSecurityHeaders() {
    // Prevent clickjacking attacks
    header('X-Frame-Options: DENY');

    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');

    // Enable XSS protection in browsers
    header('X-XSS-Protection: 1; mode=block');

    // Enforce HTTPS (only if on HTTPS)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Content Security Policy - basic policy, can be tightened
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https: blob:; frame-src https://js.stripe.com https://checkout.stripe.com; connect-src 'self' https://api.stripe.com;");

    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Permissions policy
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

/**
 * Configure secure session settings
 */
function configureSecureSession() {
    // Only configure if session hasn't started yet
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');

        // Enable secure flag if on HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }

        // Set session cookie parameters before starting session
        session_set_cookie_params([
            'lifetime' => 7200, // 2 hours
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
}

/**
 * Sanitize output for HTML display (prevent XSS)
 *
 * @param string $text Text to sanitize
 * @param bool $allow_html Whether to allow basic HTML tags
 * @return string Sanitized text
 */
function sanitizeOutput($text, $allow_html = false) {
    if ($allow_html) {
        // Allow only safe HTML tags
        $allowed_tags = '<p><br><b><strong><i><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6>';
        return strip_tags($text, $allowed_tags);
    }

    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Validate URL is safe (no javascript:, data:, etc.)
 *
 * @param string $url URL to validate
 * @return bool True if URL is safe
 */
function isUrlSafe($url) {
    if (empty($url)) {
        return true; // Empty URL is considered safe
    }

    // Parse URL
    $parsed = parse_url($url);

    if ($parsed === false) {
        return false; // Invalid URL
    }

    // Check scheme if present
    if (isset($parsed['scheme'])) {
        $scheme = strtolower($parsed['scheme']);
        // Only allow http, https, and relative URLs
        if (!in_array($scheme, ['http', 'https', ''])) {
            return false;
        }
    }

    return true;
}

/**
 * Simple rate limiting function - ATOMIC VERSION
 * 🔒 SECURITY: Uses flock() to prevent race conditions
 *
 * @param string $action Action identifier (e.g., 'public_submit')
 * @param string $identifier User identifier (e.g., IP address)
 * @param int $max_attempts Maximum attempts allowed
 * @param int $time_window Time window in seconds
 * @return bool True if rate limit not exceeded
 */
function checkPublicRateLimit($action, $identifier, $max_attempts = 10, $time_window = 60) {
    $rate_limit_file = 'public_rate_limits.json';

    // Ensure file exists
    if (!file_exists($rate_limit_file)) {
        file_put_contents($rate_limit_file, json_encode([]));
        @chmod($rate_limit_file, 0666);
    }

    // Open file for reading/writing
    $fp = fopen($rate_limit_file, 'c+');
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

    // Read current rate limits
    $filesize = filesize($rate_limit_file);
    $rate_limits = [];
    if ($filesize > 0) {
        $content = fread($fp, $filesize);
        $rate_limits = json_decode($content, true) ?: [];
    }

    $key = $action . '_' . $identifier;
    $current_time = time();

    // Clean old entries
    if (isset($rate_limits[$key])) {
        $rate_limits[$key] = array_filter($rate_limits[$key], function($timestamp) use ($current_time, $time_window) {
            return ($current_time - $timestamp) < $time_window;
        });
    } else {
        $rate_limits[$key] = [];
    }

    // Check if rate limit exceeded
    if (count($rate_limits[$key]) >= $max_attempts) {
        // Rate limit exceeded - don't add this attempt
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    // Add current attempt
    $rate_limits[$key][] = $current_time;

    // Write atomically
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($rate_limits));
    fflush($fp);

    // 🔓 Release lock and close
    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
}

/**
 * Validate email format
 *
 * @param string $email Email to validate
 * @return bool True if valid
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate and sanitize workshop name
 *
 * @param string $name Workshop name
 * @return string|false Sanitized name or false if invalid
 */
function sanitizeWorkshopName($name) {
    $name = trim($name);

    if (empty($name) || strlen($name) > 200) {
        return false;
    }

    // Remove any HTML tags
    $name = strip_tags($name);

    return $name;
}

/**
 * Check if request is from same origin (CSRF protection helper)
 *
 * @return bool True if same origin
 */
function isSameOrigin() {
    if (!isset($_SERVER['HTTP_REFERER'])) {
        return false; // No referer, might be direct access
    }

    $referer_host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    $current_host = $_SERVER['HTTP_HOST'];

    return $referer_host === $current_host;
}

/**
 * Generate secure random token
 *
 * @param int $length Token length
 * @return string Random token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Validate that a file path is within the allowed directory
 *
 * @param string $file_path File path to validate
 * @param string $base_dir Base directory (defaults to current directory)
 * @return bool True if path is safe
 */
function isPathSafe($file_path, $base_dir = __DIR__) {
    $real_base = realpath($base_dir);
    $real_path = realpath($file_path);

    // If file doesn't exist yet, check parent directory
    if ($real_path === false) {
        $real_path = realpath(dirname($file_path));
    }

    if ($real_path === false) {
        return false;
    }

    // Check if path starts with base directory
    return strpos($real_path, $real_base) === 0;
}

/**
 * CSRF Token Wrapper Functions
 * These functions provide compatibility wrappers for the CSRF functions
 * defined in user_auth.php (generateCSRFToken and validateCSRFToken)
 */

if (!function_exists('generateCsrfToken')) {
    /**
     * Generate CSRF token - wrapper for generateCSRFToken()
     * @return string CSRF token
     */
    function generateCsrfToken() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if user_auth.php function exists
        if (function_exists('generateCSRFToken')) {
            return generateCSRFToken();
        }

        // Fallback: generate token directly
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    /**
     * Verify CSRF token - wrapper for validateCSRFToken()
     * @param string $token Token to verify
     * @return bool True if token is valid
     */
    function verifyCsrfToken($token) {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if user_auth.php function exists
        if (function_exists('validateCSRFToken')) {
            return validateCSRFToken($token);
        }

        // Fallback: validate token directly
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

?>
