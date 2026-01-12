# Multi-Tenant SaaS Architecture Design

## Overview
Transforming the Live Situation Room from single-tenant to multi-tenant SaaS platform with user authentication and data isolation.

## Architecture Changes

### 1. File Structure
```
/
├── users.json                      # User accounts database
├── password_reset_tokens.json     # Temporary reset tokens
├── user_auth.php                  # Authentication library
├── register.php                   # User registration
├── login.php                      # User login
├── logout.php                     # Logout handler
├── forgot_password.php            # Password reset request
├── reset_password.php             # Reset with token
├── welcome.php                    # Landing page
├── data/                          # User-specific data (auto-created)
│   ├── user_{id}/
│   │   ├── daten.json
│   │   ├── config.json
│   │   └── backups/
│   └── .htaccess                  # Security: block direct access
├── [existing files - modified for multi-tenancy]
```

### 2. Data Schemas

#### users.json
```json
{
    "users": [
        {
            "id": "unique_id_here",
            "email": "user@example.com",
            "password_hash": "$2y$10$hashed_password",
            "created_at": 1234567890,
            "last_login": 1234567890
        }
    ]
}
```

#### password_reset_tokens.json
```json
{
    "tokens": [
        {
            "token": "secure_random_token",
            "user_id": "unique_id_here",
            "created_at": 1234567890,
            "expires_at": 1234567890,
            "used": false
        }
    ]
}
```

### 3. Session Management
```php
$_SESSION['user_id']     = 'abc123';
$_SESSION['user_email']  = 'user@example.com';
$_SESSION['logged_in']   = true;
```

### 4. User Data Isolation
- Each user gets: `data/user_{id}/daten.json`
- Each user gets: `data/user_{id}/config.json`
- Each user gets: `data/user_{id}/backups/`
- Default config created on first user registration

### 5. Authentication Flow

**Registration:**
1. User fills register.php form (email + password)
2. Validate email format and password strength
3. Check if email already exists
4. Hash password with `password_hash()`
5. Create user ID
6. Initialize user data directory
7. Create default config.json for user
8. Auto-login user
9. Redirect to admin.php (dashboard)

**Login:**
1. User enters email + password
2. Look up user by email
3. Verify password with `password_verify()`
4. Set session variables
5. Update last_login timestamp
6. Redirect to admin.php

**Forgot Password:**
1. User enters email
2. Generate secure random token
3. Store token with expiry (1 hour)
4. Display token to user (MVP: no email sending)
5. User copies token

**Reset Password:**
1. User enters token + new password
2. Validate token (exists, not expired, not used)
3. Hash new password
4. Update user record
5. Mark token as used
6. Auto-login user

### 6. Security Measures

**Password Security:**
- Minimum 8 characters
- bcrypt hashing via `password_hash()`
- Password strength indicator (optional)

**Session Security:**
- `session_regenerate_id()` on login
- HTTPOnly and Secure flags (in production)
- Session timeout (2 hours)

**CSRF Protection:**
- Token in all forms
- Validation on POST

**Rate Limiting:**
- Login attempts: 5 per 15 minutes per IP
- Registration: 3 per hour per IP

**User Enumeration Prevention:**
- Generic error messages ("Invalid credentials" not "Email not found")
- Same response time for existing/non-existing emails

### 7. File Modifications

**file_handling_robust.php:**
- Add `getUserDataPath($user_id)` - returns data directory path
- Add `getUserFile($user_id, $filename)` - returns full file path
- Add `initializeUserData($user_id)` - creates user directory structure
- Modify all functions to accept optional $user_id parameter

**index.php:**
- Add auth check: redirect to login if not authenticated
- Load user-specific daten.json
- Display user email in header
- Add logout button

**eingabe.php:**
- Add auth check
- Load user-specific daten.json and config.json
- Save submissions to user-specific file

**admin.php:**
- Add auth check
- Load user-specific daten.json
- Remove old password authentication (now session-based)
- Add "My Account" section with email display

**customize.php:**
- Add auth check
- Load/save user-specific config.json

### 8. New Helper Functions (user_auth.php)

```php
// User registration
registerUser($email, $password)

// User authentication
authenticateUser($email, $password)

// Get current logged-in user
getCurrentUser()

// Check if user is logged in
isLoggedIn()

// Get user data directory path
getUserDataPath($user_id)

// Initialize new user data
initializeUserData($user_id)

// Password reset request
createPasswordResetToken($email)

// Validate reset token
validateResetToken($token)

// Reset password with token
resetPasswordWithToken($token, $new_password)

// Rate limiting
checkRateLimit($action, $identifier)

// Generate CSRF token
generateCSRFToken()

// Validate CSRF token
validateCSRFToken($token)
```

### 9. URL Structure

**Public (no auth required):**
- `/welcome.php` - Landing page
- `/register.php` - Sign up
- `/login.php` - Sign in
- `/forgot_password.php` - Request reset
- `/reset_password.php?token=xyz` - Reset password

**Protected (auth required):**
- `/admin.php` - User dashboard
- `/index.php?u={user_id}` - Public view of user's workshop
- `/eingabe.php?u={user_id}` - Public submission form for user's workshop
- `/customize.php` - User settings

### 10. Public Workshop Sharing

Users can share their workshop with participants:
- **Admin panel**: `admin.php` (private, requires login)
- **Public dashboard**: `index.php?u={user_id}` (public, anyone can view)
- **Public input form**: `eingabe.php?u={user_id}` (public, anyone can submit)

### 11. Migration Strategy

**For existing installation:**
1. Create `data/` directory
2. Create `data/default_user/` directory
3. Move existing `daten.json` to `data/default_user/daten.json`
4. Move existing `config.json` to `data/default_user/config.json`
5. Move existing `backups/` to `data/default_user/backups/`
6. Create default admin user in `users.json`
7. Update all file references

## Implementation Order

1. ✅ Design document (this file)
2. Create `user_auth.php` - core authentication library
3. Create `users.json` and `password_reset_tokens.json` - initialize empty
4. Create `register.php` - user registration
5. Create `login.php` - user authentication
6. Create `logout.php` - session cleanup
7. Create `forgot_password.php` - reset request
8. Create `reset_password.php` - reset with token
9. Create `welcome.php` - landing page
10. Modify `file_handling_robust.php` - add user context support
11. Modify `index.php` - add auth + user data
12. Modify `eingabe.php` - add auth + user data
13. Modify `admin.php` - replace old auth with session-based
14. Modify `customize.php` - add auth + user data
15. Create `data/.htaccess` - security
16. Test complete flow
17. Update README.md - multi-tenant documentation

## MVP Limitations (for now)

- No email sending (password reset tokens shown on screen)
- No email verification (users can login immediately)
- No profile management (just email + password)
- No user deletion (manual file deletion if needed)
- Basic rate limiting (simple, not distributed)
- No admin panel to manage all users
- No billing/subscriptions

## Future Enhancements (post-MVP)

- Email integration (SendGrid/Mailgun)
- Email verification workflow
- User profile management
- Password change in settings
- Account deletion
- Super admin panel
- Usage analytics per user
- Billing integration (Stripe)
- User roles (admin, moderator, viewer)
- Workshop templates
- Data export per user
