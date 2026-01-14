<?php
/**
 * Dev Admin Security Logger
 * Comprehensive security event logging for production monitoring
 *
 * @version 1.0.0
 */

class DevAdminSecurityLogger {

    private const LOG_FILE = __DIR__ . '/logs/dev_admin_security.log';
    private const MAX_LOG_SIZE = 10485760; // 10MB
    private const LOG_RETENTION_DAYS = 90;

    /**
     * Log levels
     */
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    const LEVEL_SECURITY = 'SECURITY';

    /**
     * Initialize logger - ensure log directory exists
     */
    public static function init() {
        $logDir = dirname(self::LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }

        // Rotate logs if needed
        self::rotateLogs();
    }

    /**
     * Log a security event
     *
     * @param string $level Log level
     * @param string $event Event name
     * @param array $context Additional context
     */
    public static function log($level, $event, $context = []) {
        self::init();

        $timestamp = date('Y-m-d H:i:s');
        $ip = self::getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $username = $_SESSION['dev_admin_username'] ?? 'Anonymous';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'Unknown';

        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'event' => $event,
            'username' => $username,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'request_uri' => $requestUri,
            'context' => $context
        ];

        $logLine = json_encode($logEntry) . "\n";

        // Atomic write with file locking
        $fp = fopen(self::LOG_FILE, 'a');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $logLine);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }

        // Alert on critical events
        if ($level === self::LEVEL_CRITICAL || $level === self::LEVEL_SECURITY) {
            self::sendSecurityAlert($logEntry);
        }
    }

    /**
     * Get client IP address (considering proxies)
     */
    private static function getClientIp() {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle multiple IPs (take the first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }

    /**
     * Rotate logs when they get too large
     */
    private static function rotateLogs() {
        if (!file_exists(self::LOG_FILE)) {
            return;
        }

        $fileSize = filesize(self::LOG_FILE);
        if ($fileSize > self::MAX_LOG_SIZE) {
            $timestamp = date('Y-m-d_H-i-s');
            $archiveName = dirname(self::LOG_FILE) . '/dev_admin_security_' . $timestamp . '.log';
            rename(self::LOG_FILE, $archiveName);

            // Clean up old logs
            self::cleanupOldLogs();
        }
    }

    /**
     * Clean up logs older than retention period
     */
    private static function cleanupOldLogs() {
        $logDir = dirname(self::LOG_FILE);
        $cutoffTime = time() - (self::LOG_RETENTION_DAYS * 24 * 60 * 60);

        $files = glob($logDir . '/dev_admin_security_*.log');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }

    /**
     * Send security alert (placeholder - implement email/Slack/etc.)
     */
    private static function sendSecurityAlert($logEntry) {
        // In production, send email or webhook notification
        // For now, just log to error_log
        error_log(
            'SECURITY ALERT: ' . $logEntry['event'] .
            ' from IP ' . $logEntry['ip'] .
            ' user ' . $logEntry['username']
        );

        // TODO: Implement email/Slack notification
        // Example:
        // mail('security@yourdomain.com', 'Security Alert', json_encode($logEntry));
    }

    /**
     * Convenience methods for different log levels
     */
    public static function info($event, $context = []) {
        self::log(self::LEVEL_INFO, $event, $context);
    }

    public static function warning($event, $context = []) {
        self::log(self::LEVEL_WARNING, $event, $context);
    }

    public static function error($event, $context = []) {
        self::log(self::LEVEL_ERROR, $event, $context);
    }

    public static function critical($event, $context = []) {
        self::log(self::LEVEL_CRITICAL, $event, $context);
    }

    public static function security($event, $context = []) {
        self::log(self::LEVEL_SECURITY, $event, $context);
    }

    /**
     * Get recent security events
     *
     * @param int $limit Number of events to retrieve
     * @param string $level Filter by log level
     * @return array Recent log entries
     */
    public static function getRecentEvents($limit = 100, $level = null) {
        if (!file_exists(self::LOG_FILE)) {
            return [];
        }

        $lines = file(self::LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_reverse($lines); // Most recent first

        $events = [];
        foreach ($lines as $line) {
            $event = json_decode($line, true);
            if ($event && ($level === null || $event['level'] === $level)) {
                $events[] = $event;
                if (count($events) >= $limit) {
                    break;
                }
            }
        }

        return $events;
    }
}
