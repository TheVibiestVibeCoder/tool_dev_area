<?php
/**
 * FIXED VERSION - user_auth.php
 * CHANGES: Made checkRateLimit() atomic with flock()
 *
 * INSTRUCTIONS:
 * 1. Replace the checkRateLimit() function in user_auth.php (lines 573-626)
 *    with the version below
 */

/**
 * Check rate limit for an action - ATOMIC VERSION
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

    // Acquire exclusive lock
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

    // Release lock and close
    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
}
