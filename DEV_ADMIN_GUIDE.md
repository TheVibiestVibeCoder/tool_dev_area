# Dev Admin Control Panel - User Guide

## 🛠️ Overview

The Dev Admin Control Panel is a secure master dashboard that allows the development team to monitor and manage the entire Live Situation Room SaaS platform. This system operates completely separately from the regular user authentication system, providing enhanced security and administrative capabilities.

## 📋 Table of Contents

1. [System Architecture](#system-architecture)
2. [Getting Started](#getting-started)
3. [Creating Dev Admin Accounts](#creating-dev-admin-accounts)
4. [Dashboard Features](#dashboard-features)
5. [Security Features](#security-features)
6. [Troubleshooting](#troubleshooting)
7. [API Reference](#api-reference)

---

## System Architecture

### Files Structure

```
dev_admin_auth.php        # Core authentication system for dev admins
dev_login.php             # Login page for dev admins
dev_admin.php             # Main control panel dashboard
dev_logout.php            # Logout handler
create_dev_admin.php      # CLI script to create dev admin accounts
dev_admins.json           # Encrypted credentials (auto-created, in .gitignore)
dev_admin_rate_limit.json # Rate limiting data (auto-created)
```

### Security Architecture

- **Separate Session Namespace**: Uses `DEV_ADMIN_SESSION` to avoid conflicts
- **Bcrypt Password Hashing**: Cost factor 12 for enhanced security
- **Rate Limiting**: 5 failed attempts = 15-minute lockout
- **CSRF Protection**: All forms use token validation
- **JSON File Protection**: .htaccess blocks direct access
- **Session Timeout**: 2-hour automatic logout
- **Account Locking**: Per-user lockout after failed attempts

---

## Getting Started

### Prerequisites

- Server access (SSH or terminal)
- PHP 7.4+ with CLI enabled
- Existing Live Situation Room installation

### First-Time Setup

1. **Navigate to the installation directory**:
   ```bash
   cd /path/to/live-situation-room
   ```

2. **Create your first dev admin account**:
   ```bash
   php create_dev_admin.php
   ```

3. **Follow the interactive prompts**:
   - Username (3-30 alphanumeric + underscore)
   - Full name
   - Email address
   - Password (min 8 characters)
   - Confirm password
   - Confirm account creation

4. **Access the control panel**:
   - Navigate to: `https://your-domain.com/dev_login.php`
   - Enter your credentials
   - You'll be redirected to the control panel

---

## Creating Dev Admin Accounts

### Method 1: CLI Script (Recommended)

The most secure method is using the command-line script:

```bash
php create_dev_admin.php
```

**Interactive Example**:
```
╔═══════════════════════════════════════════════════════════════╗
║          🛠️  DEV ADMIN ACCOUNT CREATION TOOL  🛠️             ║
╚═══════════════════════════════════════════════════════════════╝

📋 Existing Dev Admin Accounts:
─────────────────────────────────────────────────────────────
  • admin (admin@example.com) - ✅ Active

Let's create a new dev admin account.
─────────────────────────────────────────────────────────────

👤 Enter username (alphanumeric + underscore, 3-30 chars): john_dev
📝 Enter full name: John Developer
📧 Enter email address: john@example.com
🔐 Enter password (min 8 characters): ********
🔐 Confirm password: ********

─────────────────────────────────────────────────────────────
📝 Account Details:
   Username:  john_dev
   Full Name: John Developer
   Email:     john@example.com
─────────────────────────────────────────────────────────────

✅ Create this dev admin account? (yes/no): yes

🔄 Creating dev admin account...

╔═══════════════════════════════════════════════════════════════╗
║                   ✅ SUCCESS! ✅                              ║
╚═══════════════════════════════════════════════════════════════╝

🎉 Dev admin account created successfully!
```

### Method 2: Programmatic Creation

You can also create accounts programmatically in PHP:

```php
require_once 'dev_admin_auth.php';

$result = createDevAdmin(
    'username',
    'password',
    'email@example.com',
    'Full Name'
);

if ($result['success']) {
    echo "Account created!";
} else {
    echo "Error: " . $result['message'];
}
```

### Account Requirements

- **Username**: 3-30 characters, alphanumeric + underscore only
- **Password**: Minimum 8 characters (recommend 12+ with mixed case, numbers, symbols)
- **Email**: Valid email format
- **Full Name**: Any non-empty string

---

## Dashboard Features

### 1. Platform Statistics

**Real-Time Metrics**:
- **Total Users**: All registered users across the platform
- **Active Users**: Users who logged in within the last 7 days
- **Total Workshops**: Count of all created workshops
- **Total Entries**: Sum of all data entries across all workshops
- **Revenue Tracking**: Monthly and yearly subscription revenue

**Subscription Breakdown**:
- Free plan users
- Premium subscribers (monthly/yearly)
- Enterprise customers
- Cancelled subscriptions

### 2. User Management

**View All Users**:
The user table displays comprehensive information:

| Column | Description |
|--------|-------------|
| User ID | Unique identifier (truncated for display) |
| Email | User's email address |
| Plan | Current subscription plan (Free/Premium/Enterprise) |
| Status | Subscription status (Active/Cancelled/Trialing) |
| Registered | Account creation date |
| Last Login | Most recent login timestamp |
| Actions | Available actions (password reset) |

**User Actions**:
- **Password Reset**: Generate secure password reset links for users

### 3. Workshop Monitoring

**Active Workshops Table**:
- Workshop title and configuration
- Owner email address
- Number of categories/columns
- Total entries count
- Direct view link to the workshop

**Actions**:
- **View Workshop**: Opens the workshop in a new tab
- Inspect workshop configuration
- Monitor activity levels

### 4. Password Reset Management

**Generate Reset Links**:
1. Click "🔐 Reset Password" next to any user
2. Confirm the action
3. A modal displays the generated reset link
4. Copy and send the link to the user
5. Link expires in 1 hour

**Reset Link Format**:
```
https://your-domain.com/reset_password.php?token=SECURE_TOKEN_HERE
```

**Security Notes**:
- Tokens expire after 1 hour
- Single-use tokens (marked as used after reset)
- All resets are logged with "created_by: dev_admin"

---

## Security Features

### Authentication Security

1. **Separate Session System**:
   - Uses dedicated session namespace
   - No interference with user sessions
   - Secure cookie configuration (HttpOnly, SameSite=Strict)

2. **Rate Limiting**:
   - **Login Attempts**: 5 failed attempts per 15 minutes
   - **IP-Based Tracking**: Prevents brute force attacks
   - **Account Lockout**: Individual accounts lock after 5 failed attempts
   - **Lockout Duration**: 15 minutes

3. **Session Management**:
   - **Timeout**: 2 hours of inactivity
   - **Session Regeneration**: On successful login
   - **Activity Tracking**: Updates on each request
   - **Secure Cookies**: HTTPS-only in production

4. **CSRF Protection**:
   - All forms include CSRF tokens
   - Tokens validated on submission
   - Prevents cross-site request forgery

### File Security

1. **JSON File Protection**:
   - `.htaccess` blocks direct access to all JSON files
   - Files only accessible via PHP scripts
   - `dev_admins.json` in `.gitignore`

2. **Password Hashing**:
   - Bcrypt algorithm (cost factor 12)
   - Salted automatically
   - Resistant to rainbow table attacks

3. **Access Control**:
   - `requireDevAdmin()` function protects all pages
   - Automatic redirect to login if not authenticated
   - No bypass mechanisms

---

## Troubleshooting

### Common Issues

#### 1. "Account is locked" Error

**Cause**: Too many failed login attempts

**Solution**:
```bash
# Method 1: Wait 15 minutes for automatic unlock
# Method 2: Manually edit dev_admins.json (requires server access)
php -r "
\$file = 'dev_admins.json';
\$data = json_decode(file_get_contents(\$file), true);
foreach (\$data as &\$admin) {
    if (\$admin['username'] === 'YOUR_USERNAME') {
        \$admin['login_attempts'] = 0;
        \$admin['locked_until'] = null;
    }
}
file_put_contents(\$file, json_encode(\$data, JSON_PRETTY_PRINT));
echo 'Account unlocked!';
"
```

#### 2. "Session timeout" Issue

**Cause**: No activity for 2 hours

**Solution**: Simply log in again. Sessions timeout for security.

#### 3. Can't Access dev_login.php

**Possible Causes**:
- File permissions incorrect
- .htaccess blocking PHP files
- Server configuration issue

**Solution**:
```bash
# Check file exists
ls -la dev_login.php

# Check permissions (should be 644)
chmod 644 dev_login.php

# Test PHP is working
php -v
```

#### 4. Rate Limit Not Resetting

**Cause**: Rate limit file corruption or stuck entries

**Solution**:
```bash
# Delete rate limit file (it will regenerate)
rm -f data/dev_admin_rate_limit.json

# Or clear old entries manually
php -r "file_put_contents('data/dev_admin_rate_limit.json', '[]');"
```

#### 5. Statistics Not Showing Correctly

**Cause**: Missing user data or corrupted JSON files

**Solution**:
```bash
# Verify users.json exists and is valid
php -r "
if (file_exists('users.json')) {
    \$data = json_decode(file_get_contents('users.json'), true);
    echo 'Users: ' . count(\$data['users'] ?? []) . PHP_EOL;
} else {
    echo 'users.json not found!' . PHP_EOL;
}
"

# Check data directory
ls -la data/
```

---

## API Reference

### Authentication Functions

#### `devAdminInitSession()`
Initializes a secure session for dev admins.
```php
devAdminInitSession();
```

#### `isDevAdminLoggedIn()`
Checks if a dev admin is currently logged in.
```php
if (isDevAdminLoggedIn()) {
    // User is authenticated
}
```
**Returns**: `bool`

#### `getCurrentDevAdmin()`
Gets the username of the currently logged-in dev admin.
```php
$username = getCurrentDevAdmin();
```
**Returns**: `string|null`

#### `devAdminLogin($username, $password)`
Authenticates a dev admin login attempt.
```php
$result = devAdminLogin('admin', 'password123');
if ($result['success']) {
    // Login successful
}
```
**Parameters**:
- `$username` (string): Username
- `$password` (string): Plain text password

**Returns**: `array`
```php
[
    'success' => true|false,
    'message' => 'Login successful' | 'Error message'
]
```

#### `devAdminLogout()`
Logs out the current dev admin.
```php
devAdminLogout();
```

#### `requireDevAdmin($redirectTo = 'dev_login.php')`
Requires authentication, redirects if not logged in.
```php
requireDevAdmin(); // Redirects to dev_login.php if not authenticated
```

### Account Management Functions

#### `createDevAdmin($username, $password, $email, $full_name)`
Creates a new dev admin account.
```php
$result = createDevAdmin(
    'john_dev',
    'SecurePass123!',
    'john@example.com',
    'John Developer'
);
```
**Returns**: `array`
```php
[
    'success' => true|false,
    'message' => 'Success or error message'
]
```

#### `loadDevAdmins()`
Loads all dev admin accounts.
```php
$admins = loadDevAdmins();
```
**Returns**: `array` of admin objects

#### `getDevAdminInfo()`
Gets information about the current dev admin session.
```php
$info = getDevAdminInfo();
// [
//     'username' => 'admin',
//     'email' => 'admin@example.com',
//     'full_name' => 'Administrator',
//     'login_time' => 1234567890,
//     'last_activity' => 1234567890
// ]
```

---

## Best Practices

### 1. Account Management

✅ **DO**:
- Create individual accounts for each team member
- Use strong, unique passwords (12+ characters)
- Store credentials in a password manager
- Regularly review active accounts
- Remove accounts for departed team members

❌ **DON'T**:
- Share dev admin credentials
- Use weak or common passwords
- Leave accounts logged in on shared computers
- Create accounts for non-developers

### 2. Security

✅ **DO**:
- Always access dev_login.php over HTTPS in production
- Log out when finished with admin tasks
- Monitor the rate limit logs for suspicious activity
- Keep the dev_admins.json file in .gitignore
- Regularly audit password reset requests

❌ **DON'T**:
- Commit dev_admins.json to version control
- Disable rate limiting or CSRF protection
- Access the panel from unsecured networks
- Store credentials in plain text

### 3. Operations

✅ **DO**:
- Use the CLI script to create accounts
- Verify user identity before sending reset links
- Monitor platform statistics regularly
- Document any manual interventions
- Keep the dev admin system updated

❌ **DON'T**:
- Manually edit dev_admins.json unless necessary
- Send password reset links without verification
- Ignore unusual activity patterns
- Modify core authentication code without testing

---

## Maintenance

### Regular Tasks

**Weekly**:
- Review active users and subscription status
- Check for unusual activity patterns
- Verify all workshops are functioning

**Monthly**:
- Audit dev admin accounts (remove inactive)
- Review revenue metrics vs. projections
- Check storage usage (data directory)

**Quarterly**:
- Update passwords for dev admin accounts
- Review and update security policies
- Test disaster recovery procedures

### Backup Recommendations

**What to Backup**:
- `dev_admins.json` (encrypted storage only!)
- `users.json`
- `data/` directory (all user workshops)
- `password_reset_tokens.json`

**Backup Frequency**:
- Daily automated backups
- Before major system changes
- After bulk user operations

---

## Support & Contact

For issues with the Dev Admin Control Panel:

1. **Check this documentation first**
2. **Review the troubleshooting section**
3. **Check server logs**: `php_errors.log`
4. **Contact the development team lead**

---

## Changelog

### Version 1.0.0 (2026-01-14)
- Initial release of Dev Admin Control Panel
- Complete authentication system
- User management dashboard
- Workshop monitoring
- Password reset functionality
- Platform statistics
- Security features (rate limiting, CSRF, session management)

---

## License

This Dev Admin Control Panel is part of the Live Situation Room SaaS platform and is proprietary software. Unauthorized distribution or modification is prohibited.

---

**Last Updated**: 2026-01-14
**Version**: 1.0.0
**Maintained By**: Live Situation Room Development Team
