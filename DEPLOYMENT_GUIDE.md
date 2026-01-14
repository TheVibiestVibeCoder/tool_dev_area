# Dev Admin Control Panel - Deployment Guide

This guide provides step-by-step instructions for deploying the Dev Admin Control Panel to production safely and securely.

## 📋 Prerequisites

Before deploying, ensure you have:

- [ ] Root or SSH access to production server
- [ ] HTTPS certificate installed and working
- [ ] PHP 7.4+ with required extensions (json, mbstring, openssl)
- [ ] Apache with mod_rewrite enabled
- [ ] Access to server logs
- [ ] Backup of current production files

## 🚀 Deployment Steps

### Step 1: Pre-Deployment Testing

Test in a staging environment first:

```bash
# 1. Test login functionality
# Visit: https://staging.yourdomain.com/dev_login.php

# 2. Test password reset generation
# Generate a reset link from admin panel

# 3. Test rate limiting
# Attempt 6 failed logins, verify lockout

# 4. Test session timeout
# Stay inactive for 61 minutes, verify auto-logout

# 5. Verify all security logs are writing
tail -f logs/dev_admin_security.log
```

### Step 2: Backup Current Production

**CRITICAL**: Always backup before deploying:

```bash
# SSH into production server
ssh user@production.yourdomain.com

# Navigate to application directory
cd /var/www/yourdomain.com

# Create timestamped backup
BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
tar -czf ~/backups/pre_dev_admin_${BACKUP_DATE}.tar.gz \
    --exclude='data/*' \
    --exclude='logs/*' \
    .

# Verify backup created
ls -lh ~/backups/
```

### Step 3: Upload New Files

Upload the new dev admin files to production:

```bash
# Option A: Using rsync (recommended)
rsync -avz --exclude='data/' --exclude='logs/' \
    /local/path/to/project/ \
    user@production.yourdomain.com:/var/www/yourdomain.com/

# Option B: Using SCP
scp dev_admin*.php user@production.yourdomain.com:/var/www/yourdomain.com/
scp dev_login.php dev_logout.php user@production.yourdomain.com:/var/www/yourdomain.com/
scp .htaccess_production user@production.yourdomain.com:/var/www/yourdomain.com/
scp PRODUCTION_SECURITY.md user@production.yourdomain.com:/var/www/yourdomain.com/

# Option C: Using Git
ssh user@production.yourdomain.com
cd /var/www/yourdomain.com
git pull origin main
```

### Step 4: Replace Production Files

**IMPORTANT**: Replace development files with production-hardened versions:

```bash
# SSH into production
ssh user@production.yourdomain.com
cd /var/www/yourdomain.com

# Backup current auth file (if exists)
if [ -f dev_admin_auth.php ]; then
    cp dev_admin_auth.php dev_admin_auth.php.backup.$(date +%Y%m%d)
fi

# Replace with secure production version
cp dev_admin_auth_secure.php dev_admin_auth.php

# Replace .htaccess with production version
cp .htaccess .htaccess.backup.$(date +%Y%m%d)
cp .htaccess_production .htaccess

# Verify files replaced correctly
diff dev_admin_auth.php dev_admin_auth_secure.php  # Should show they're the same
head -n 5 .htaccess  # Should show "PRODUCTION SECURITY" comment
```

### Step 5: Set File Permissions

Set correct permissions for security:

```bash
# Sensitive configuration files (read/write by owner only)
chmod 600 .env 2>/dev/null || true
chmod 600 dev_admins.json 2>/dev/null || true

# PHP library files (read by owner and web server)
chmod 640 dev_admin_auth.php
chmod 640 dev_admin_security_logger.php
chmod 640 security_helpers.php
chmod 640 user_auth.php

# Public access files (read by all, write by owner)
chmod 644 dev_login.php
chmod 644 dev_admin.php
chmod 644 dev_logout.php
chmod 644 .htaccess

# Logs directory
mkdir -p logs
chmod 750 logs
chmod 640 logs/*.log 2>/dev/null || true

# Data directory
chmod 750 data
chmod 640 data/*.json 2>/dev/null || true

# Verify permissions
ls -la dev_admin*.php .htaccess logs/ data/
```

### Step 6: Delete Development Files

**CRITICAL**: Remove files that should not be in production:

```bash
# List files to be deleted (review before deleting)
ls -la dev_admin_setup.php create_dev_admin.php clear_cache.php 2>/dev/null

# Delete development/setup files
rm -f dev_admin_setup.php
rm -f create_dev_admin.php
rm -f clear_cache.php

# Delete any backup files
rm -f *.backup
rm -f *~
rm -f *.swp

# Optional: Delete development documentation
# rm -f README_DEV_ADMIN.md

# Verify files are deleted
ls -la *.php | grep -E "(setup|create|clear)"  # Should return nothing
```

### Step 7: Create First Admin Account

Create the initial admin account for production:

```bash
# If you have CLI access, use the secure method:
php -r '
require "dev_admin_auth.php";
$result = createDevAdmin(
    "admin",                          // username
    "CHANGE_THIS_STRONG_PASSWORD",   // IMPORTANT: Use strong password!
    "admin@yourdomain.com",          // email
    "Admin User"                      // full name
);
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
'

# Alternatively, temporarily upload dev_admin_setup.php:
# 1. Upload dev_admin_setup.php with a UNIQUE setup key
# 2. Visit https://yourdomain.com/dev_admin_setup.php
# 3. Create admin account
# 4. IMMEDIATELY DELETE dev_admin_setup.php

# Verify admin account created
cat dev_admins.json | python -m json.tool
```

### Step 8: Configure IP Whitelist (Recommended)

Restrict access to specific IP addresses:

```bash
# Method A: Using environment variable
cat >> .env << 'EOF'
DEV_ADMIN_IP_WHITELIST="203.0.113.1,203.0.113.2"
EOF

# Method B: Using Apache .htaccess (more secure)
cat >> .htaccess << 'EOF'

# Dev Admin IP Whitelist
<Location "/dev_admin.php">
    Require ip 203.0.113.1 203.0.113.2
</Location>
<Location "/dev_login.php">
    Require ip 203.0.113.1 203.0.113.2
</Location>
EOF

# Get your current IP address
curl -s https://ifconfig.me
echo ""

# Verify IP whitelist is working
# Try accessing from different IP - should be blocked
```

### Step 9: Test Production Deployment

Verify everything works correctly:

```bash
# 1. Test HTTPS redirect
curl -I http://yourdomain.com/dev_login.php
# Should return: HTTP/1.1 301 Moved Permanently
# Location: https://...

# 2. Test direct library file access (should be blocked)
curl -I https://yourdomain.com/dev_admin_auth.php
# Should return: HTTP/1.1 403 Forbidden

# 3. Test security headers
curl -I https://yourdomain.com/dev_login.php
# Should include:
# - Strict-Transport-Security
# - X-Content-Type-Options: nosniff
# - X-Frame-Options: DENY

# 4. Test login page loads
curl -s https://yourdomain.com/dev_login.php | grep "Dev Control Panel"
# Should return: <h1>🛠️ Dev Control Panel</h1>

# 5. Check logs are writing
tail -f logs/dev_admin_security.log &
# Visit login page, logs should show activity

# 6. Test rate limiting
# Attempt 4 failed logins, verify lockout on 4th attempt
```

### Step 10: Manual Testing

Perform manual tests in browser:

1. **Test Login Flow**:
   - Visit `https://yourdomain.com/dev_login.php`
   - Verify HTTPS and security warnings
   - Login with admin credentials
   - Verify redirect to dev_admin.php

2. **Test Admin Panel**:
   - Verify all stats display correctly
   - Check user list loads
   - Check workshop list loads
   - Test password reset link generation

3. **Test Session Timeout**:
   - Login and wait 61 minutes (production timeout)
   - Try to access admin panel
   - Should redirect to login with timeout message

4. **Test Logout**:
   - Click logout button
   - Verify redirect to login page
   - Try to access admin panel
   - Should redirect to login (not still logged in)

5. **Test Rate Limiting**:
   - Attempt 3 wrong passwords
   - On 4th attempt, should see rate limit message
   - Wait 15 minutes, verify can login again

### Step 11: Configure Monitoring

Set up log monitoring and alerts:

```bash
# Option A: Setup logwatch for daily security digest
sudo apt-get install logwatch
sudo cat > /etc/logwatch/conf/logfiles/devadmin.conf << 'EOF'
LogFile = /var/www/yourdomain.com/logs/dev_admin_security.log
Archive = /var/www/yourdomain.com/logs/dev_admin_security_*.log
EOF

# Option B: Setup fail2ban to auto-ban attackers
sudo cat > /etc/fail2ban/filter.d/devadmin.conf << 'EOF'
[Definition]
failregex = "event":"LOGIN_FAILED.*"ip":"<HOST>"
ignoreregex =
EOF

sudo cat > /etc/fail2ban/jail.d/devadmin.conf << 'EOF'
[devadmin]
enabled = true
port = http,https
filter = devadmin
logpath = /var/www/yourdomain.com/logs/dev_admin_security.log
maxretry = 5
findtime = 3600
bantime = 86400
EOF

sudo systemctl restart fail2ban

# Option C: Setup cron for daily log review
cat > /usr/local/bin/devadmin_security_check.sh << 'EOF'
#!/bin/bash
LOG_FILE="/var/www/yourdomain.com/logs/dev_admin_security.log"
EMAIL="security@yourdomain.com"

# Count security events in last 24 hours
YESTERDAY=$(date -d "yesterday" +%Y-%m-%d)
SECURITY_COUNT=$(grep -c "\"level\":\"SECURITY\"" "$LOG_FILE" | grep "$YESTERDAY" || echo 0)

if [ $SECURITY_COUNT -gt 0 ]; then
    echo "Security Alert: $SECURITY_COUNT security events detected on $YESTERDAY" | \
    mail -s "Dev Admin Security Alert" "$EMAIL"
fi
EOF

chmod +x /usr/local/bin/devadmin_security_check.sh

# Add to cron
(crontab -l 2>/dev/null; echo "0 9 * * * /usr/local/bin/devadmin_security_check.sh") | crontab -
```

### Step 12: Documentation & Team Training

Complete the deployment:

```bash
# 1. Document production credentials securely
# Store in password manager (1Password, LastPass, etc.)

# 2. Share access with team
# - Provide IP addresses that need whitelist access
# - Provide login credentials securely
# - Share this deployment guide

# 3. Schedule security training
# - Review PRODUCTION_SECURITY.md with team
# - Practice incident response procedures
# - Review log monitoring procedures
```

## 🔄 Rollback Procedure

If issues are detected, rollback immediately:

```bash
# 1. SSH to production
ssh user@production.yourdomain.com
cd /var/www/yourdomain.com

# 2. Restore from backup
BACKUP_FILE="~/backups/pre_dev_admin_YYYYMMDD_HHMMSS.tar.gz"
tar -xzf "$BACKUP_FILE"

# 3. Restore file permissions
chmod 644 .htaccess
chmod 755 .

# 4. Restart web server
sudo systemctl restart apache2  # or nginx

# 5. Verify site is working
curl -I https://yourdomain.com

# 6. Investigate issue
# - Check logs: tail -f logs/*.log
# - Check PHP errors: tail -f /var/log/apache2/error.log
# - Review changes that were made
```

## 📊 Post-Deployment Monitoring

Monitor for the first 48 hours:

### Hour 1: Critical Monitoring
- [ ] Check error logs every 10 minutes
- [ ] Monitor security log for suspicious activity
- [ ] Verify all team members can login
- [ ] Test all admin panel functions

### Hours 2-24: Active Monitoring
- [ ] Check logs every 2 hours
- [ ] Monitor for failed login attempts
- [ ] Verify automated backups running
- [ ] Respond to any security alerts

### Days 2-7: Regular Monitoring
- [ ] Daily log review
- [ ] Weekly security meeting
- [ ] Document any issues encountered
- [ ] Optimize based on usage patterns

## ✅ Deployment Checklist

Print and check off during deployment:

**Pre-Deployment**
- [ ] Tested in staging environment
- [ ] Created full backup of production
- [ ] Documented rollback procedure
- [ ] Scheduled maintenance window
- [ ] Notified team of deployment

**Deployment**
- [ ] Uploaded new files to production
- [ ] Replaced dev_admin_auth.php with dev_admin_auth_secure.php
- [ ] Replaced .htaccess with .htaccess_production
- [ ] Set correct file permissions
- [ ] Deleted development files (setup, clear_cache, etc.)
- [ ] Created first admin account
- [ ] Configured IP whitelist
- [ ] Tested HTTPS forcing
- [ ] Tested security headers
- [ ] Tested login/logout
- [ ] Tested session timeout
- [ ] Tested rate limiting
- [ ] Configured monitoring

**Post-Deployment**
- [ ] Verified security logs writing
- [ ] Tested all admin panel functions
- [ ] Documented any issues
- [ ] Updated team documentation
- [ ] Scheduled security review
- [ ] Archived deployment notes

## 🆘 Troubleshooting

### Issue: 500 Internal Server Error

```bash
# Check PHP error logs
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/php/error.log

# Check file permissions
ls -la dev_admin*.php

# Verify .htaccess syntax
apache2ctl configtest
```

### Issue: Login page not loading

```bash
# Check if file exists
ls -la dev_login.php

# Check Apache error log
tail -f /var/log/apache2/error.log

# Test direct PHP execution
php -l dev_login.php

# Check .htaccess rules
cat .htaccess | grep -A 5 "RewriteRule"
```

### Issue: Rate limiting not working

```bash
# Check logs directory exists and is writable
ls -ld logs/
touch logs/test.txt  # Should succeed

# Check rate limit file
ls -la data/dev_admin_rate_limit.json

# Review security logs
tail -f logs/dev_admin_security.log
```

### Issue: Session timeout not working

```bash
# Check PHP session settings
php -i | grep session.gc_maxlifetime

# Check session timeout in auth file
grep "SESSION_TIMEOUT" dev_admin_auth.php

# Verify session cleanup is running
ls -la /var/lib/php/sessions/DEV_ADMIN_*
```

### Issue: HTTPS not forcing

```bash
# Check mod_rewrite is enabled
apache2ctl -M | grep rewrite

# Enable if missing
sudo a2enmod rewrite
sudo systemctl restart apache2

# Test HTTPS redirect
curl -I http://yourdomain.com/dev_login.php
```

## 📞 Support Contacts

**Production Issues**:
- Email: ops@yourdomain.com
- Phone: +1-xxx-xxx-xxxx (24/7)

**Security Issues**:
- Email: security@yourdomain.com
- Response time: < 2 hours

**Development Team**:
- Email: dev@yourdomain.com
- Response time: < 24 hours

---

**Document Version**: 1.0.0
**Last Updated**: 2024-01-14
**Deployment Count**: Update this after each deployment
