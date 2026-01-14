# Production Security Guide - Dev Admin Control Panel

This document provides a comprehensive security checklist and operational procedures for running the Dev Admin Control Panel in production.

## 🔒 Pre-Production Security Checklist

### 1. File Permissions

Ensure correct file permissions to prevent unauthorized access:

```bash
# Set restrictive permissions on sensitive files
chmod 600 dev_admins.json
chmod 600 .env
chmod 600 data/dev_admin_rate_limit.json

# Ensure logs directory is writable but not world-readable
chmod 750 logs/
chmod 640 logs/*.log

# Make sure .htaccess is readable
chmod 644 .htaccess
```

### 2. Replace Production Files

**CRITICAL**: Replace development authentication files with production-hardened versions:

```bash
# Backup current files
cp dev_admin_auth.php dev_admin_auth.php.backup

# Replace with secure production version
cp dev_admin_auth_secure.php dev_admin_auth.php

# Replace .htaccess with production version
cp .htaccess_production .htaccess
```

### 3. Configure IP Whitelist (Recommended)

For maximum security, restrict dev admin access to specific IP addresses:

```bash
# Add to your server environment variables or .env file
# Format: Comma-separated list of allowed IPs
DEV_ADMIN_IP_WHITELIST="203.0.113.1,203.0.113.2,198.51.100.5"
```

Or configure directly in Apache:

```apache
# Add to .htaccess or Apache config
<Location "/dev_admin.php">
    Require ip 203.0.113.1 203.0.113.2 198.51.100.5
</Location>
<Location "/dev_login.php">
    Require ip 203.0.113.1 203.0.113.2 198.51.100.5
</Location>
```

### 4. Remove Development/Setup Files

**CRITICAL**: Delete these files from production:

```bash
# Remove setup tools
rm -f dev_admin_setup.php
rm -f create_dev_admin.php
rm -f clear_cache.php

# Remove backup files
rm -f *.backup
rm -f *~

# Remove development docs (optional)
rm -f README_DEV_ADMIN.md
```

### 5. Configure HTTPS

**MANDATORY**: The dev admin panel MUST run over HTTPS.

The production `.htaccess` automatically redirects HTTP to HTTPS. Ensure you have:

1. Valid SSL certificate installed
2. Certificate auto-renewal configured (Let's Encrypt recommended)
3. HSTS enabled (already configured in `.htaccess_production`)

### 6. Environment Configuration

Create or update `.env` file with production settings:

```bash
# Production settings
ENVIRONMENT=production

# Optional: IP whitelist
DEV_ADMIN_IP_WHITELIST="YOUR_IP_HERE"

# Optional: Security alert email
SECURITY_ALERT_EMAIL="security@yourdomain.com"
```

### 7. PHP Configuration

Recommended PHP settings for production (php.ini or .htaccess):

```ini
# Disable error display (log only)
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /path/to/logs/php_error.log

# Security settings
expose_php = Off
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Strict

# Enable OpCache
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
```

## 🛡️ Security Features Implemented

### Authentication Security
- ✅ **Bcrypt password hashing** (cost factor 12)
- ✅ **Rate limiting** (3 attempts / 15 minutes in production)
- ✅ **Account lockout** after failed attempts
- ✅ **Session timeout** (1 hour in production)
- ✅ **Session fingerprinting** (prevents session hijacking)
- ✅ **CSRF protection** on all forms
- ✅ **Separate session namespace** (isolated from user sessions)

### Access Control
- ✅ **IP whitelist support** (optional but recommended)
- ✅ **Secure session cookies** (HttpOnly, Secure, SameSite=Strict)
- ✅ **Force HTTPS** via .htaccess
- ✅ **Generic error messages** (no information disclosure)

### Monitoring & Logging
- ✅ **Comprehensive security logging** (all auth events, admin actions)
- ✅ **Automatic log rotation** (10MB max size)
- ✅ **90-day log retention**
- ✅ **Security alerts** for critical events
- ✅ **Audit trail** for all administrative actions

### Infrastructure Protection
- ✅ **Hardened .htaccess rules**
- ✅ **Block direct access to library files**
- ✅ **Security headers** (CSP, HSTS, X-Frame-Options, etc.)
- ✅ **Block suspicious user agents**
- ✅ **Limit HTTP methods** (only GET, POST, HEAD)

## 📊 Monitoring Security Logs

### View Recent Security Events

```php
// In a secure admin dashboard page
require_once 'dev_admin_security_logger.php';

// Get recent security events
$events = DevAdminSecurityLogger::getRecentEvents(100);

// Get only critical events
$criticalEvents = DevAdminSecurityLogger::getRecentEvents(50, 'CRITICAL');

// Get security alerts
$securityAlerts = DevAdminSecurityLogger::getRecentEvents(50, 'SECURITY');
```

### Log File Location

Security logs are stored at: `logs/dev_admin_security.log`

Format: JSON (one event per line)

```json
{
  "timestamp": "2024-01-14 15:23:45",
  "level": "WARNING",
  "event": "LOGIN_FAILED_WRONG_PASSWORD",
  "username": "admin",
  "ip": "203.0.113.1",
  "user_agent": "Mozilla/5.0...",
  "request_uri": "/dev_login.php",
  "context": {"attempt_count": 2}
}
```

### Important Events to Monitor

| Event Type | Severity | Description |
|------------|----------|-------------|
| `LOGIN_RATE_LIMITED` | SECURITY | Too many login attempts from IP |
| `LOGIN_FAILED_ACCOUNT_LOCKED_NOW` | SECURITY | Account locked due to failed attempts |
| `LOGIN_FAILED_WRONG_PASSWORD` | WARNING | Failed login attempt |
| `LOGIN_SUCCESS` | INFO | Successful login |
| `LOGOUT` | INFO | Admin logged out |
| `PASSWORD_RESET_GENERATED` | WARNING | Password reset link created |
| `ADMIN_PANEL_ACCESS` | INFO | Admin panel accessed |

### Automated Monitoring

Set up automated log monitoring using:

1. **Logwatch** - Daily security digest emails
2. **Fail2ban** - Auto-ban IPs with repeated failures
3. **SIEM Integration** - Forward logs to security monitoring system

## 🚨 Incident Response Procedures

### Suspicious Activity Detected

If you notice suspicious activity in logs:

1. **Lock affected accounts immediately**:
   ```php
   // Manually edit dev_admins.json
   // Set "active": false for the account
   ```

2. **Review security logs**:
   ```bash
   tail -n 100 logs/dev_admin_security.log | grep SECURITY
   ```

3. **Check for unauthorized access**:
   ```bash
   grep "LOGIN_SUCCESS" logs/dev_admin_security.log | tail -n 50
   ```

4. **Block offending IPs** (if needed):
   ```apache
   # Add to .htaccess
   Require not ip 203.0.113.999
   ```

### Account Compromise Response

If an admin account is compromised:

1. **Immediately disable the account**
2. **Force logout all sessions** (restart web server or delete session files)
3. **Review all actions** taken by the account
4. **Rotate all admin passwords**
5. **Enable IP whitelist** if not already active
6. **Review and strengthen access controls**

### Password Reset for Locked Admin

If a legitimate admin is locked out:

1. **Wait for lockout to expire** (15 minutes), OR
2. **Manually unlock**:
   ```php
   // Edit dev_admins.json
   // Set "login_attempts": 0
   // Set "locked_until": null
   ```

## 🔐 Password Requirements & Best Practices

### Production Password Requirements

- **Minimum length**: 12 characters (enforced by `dev_admin_auth_secure.php`)
- **Recommended**: 16+ characters with mixed case, numbers, symbols
- **Avoid**: Dictionary words, personal information, common patterns

### Creating Strong Admin Passwords

```bash
# Generate a strong random password (Linux/Mac)
openssl rand -base64 20

# Or use a password manager (recommended)
# - 1Password
# - LastPass
# - Bitwarden
```

### Password Rotation Policy

- **Regular admins**: Change every 90 days
- **After suspected compromise**: Immediately
- **Departing team members**: Immediately revoke access

## 📧 Security Notifications

### Email Alerts (Optional Configuration)

To enable email alerts for critical security events, update `dev_admin_security_logger.php`:

```php
private static function sendSecurityAlert($logEntry) {
    // Configure your email settings
    $to = getenv('SECURITY_ALERT_EMAIL') ?: 'security@yourdomain.com';
    $subject = 'SECURITY ALERT: ' . $logEntry['event'];
    $message = json_encode($logEntry, JSON_PRETTY_PRINT);

    $headers = [
        'From: noreply@yourdomain.com',
        'Content-Type: text/plain; charset=UTF-8'
    ];

    mail($to, $subject, $message, implode("\r\n", $headers));
}
```

### Slack/Webhook Integration

For Slack notifications:

```php
private static function sendSecurityAlert($logEntry) {
    $webhookUrl = getenv('SLACK_WEBHOOK_URL');
    if (empty($webhookUrl)) return;

    $payload = json_encode([
        'text' => '🚨 Security Alert: ' . $logEntry['event'],
        'blocks' => [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => sprintf(
                        "*Event:* %s\n*IP:* %s\n*User:* %s\n*Time:* %s",
                        $logEntry['event'],
                        $logEntry['ip'],
                        $logEntry['username'],
                        $logEntry['timestamp']
                    )
                ]
            ]
        ]
    ]);

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
```

## 🔄 Regular Maintenance Tasks

### Daily
- [ ] Review security log for alerts
- [ ] Check for failed login patterns

### Weekly
- [ ] Review all admin panel access logs
- [ ] Verify backups are working
- [ ] Check for software updates

### Monthly
- [ ] Review and rotate old logs
- [ ] Audit active admin accounts
- [ ] Test disaster recovery procedures
- [ ] Review and update IP whitelist if needed

### Quarterly
- [ ] Rotate admin passwords
- [ ] Security audit of all configurations
- [ ] Review and update security policies
- [ ] Penetration testing (recommended)

## 📝 Compliance & Auditing

### Data Retention

Security logs are retained for **90 days** by default. Adjust if needed:

```php
// In dev_admin_security_logger.php
private const LOG_RETENTION_DAYS = 365; // Keep for 1 year
```

### Export Audit Logs

For compliance reporting:

```bash
# Export all security events for date range
grep '"timestamp":"2024-01"' logs/dev_admin_security.log > audit_jan_2024.json

# Export by event type
grep '"event":"PASSWORD_RESET_GENERATED"' logs/dev_admin_security.log > password_resets.json
```

## 🆘 Support & Security Issues

### Reporting Security Vulnerabilities

If you discover a security vulnerability:

1. **DO NOT** open a public issue
2. Email security@yourdomain.com immediately
3. Include: Description, reproduction steps, impact assessment
4. Allow 48 hours for initial response

### Getting Help

- Internal documentation: `README_DEV_ADMIN.md`
- Security logs: `logs/dev_admin_security.log`
- PHP error logs: Check your server's error log location

## ✅ Final Production Checklist

Before going live, verify:

- [ ] ✅ Replaced `dev_admin_auth.php` with `dev_admin_auth_secure.php`
- [ ] ✅ Replaced `.htaccess` with `.htaccess_production`
- [ ] ✅ Deleted `dev_admin_setup.php`, `clear_cache.php`, `create_dev_admin.php`
- [ ] ✅ Set correct file permissions (600 for sensitive files)
- [ ] ✅ HTTPS is working and forced
- [ ] ✅ IP whitelist configured (recommended)
- [ ] ✅ Security logging is working
- [ ] ✅ Email alerts configured (optional)
- [ ] ✅ All admin accounts have strong passwords (12+ chars)
- [ ] ✅ Backup procedures in place
- [ ] ✅ Monitoring configured
- [ ] ✅ Team trained on security procedures

---

**Document Version**: 1.0.0
**Last Updated**: 2024-01-14
**Next Review**: 2024-04-14
