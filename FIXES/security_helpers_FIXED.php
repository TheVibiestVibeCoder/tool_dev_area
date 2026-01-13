<?php
/**
 * FIXED VERSION - security_helpers.php
 * CHANGES: Made checkPublicRateLimit() atomic with flock()
 *
 * INSTRUCTIONS:
 * 1. Replace the checkPublicRateLimit() function in security_helpers.php (lines 120-153)
 *    with the version below
 */

/**
 * Simple rate limiting function - ATOMIC VERSION
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

    // Acquire exclusive lock
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

    // Release lock and close
    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
}
