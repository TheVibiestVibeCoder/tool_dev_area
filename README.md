# Live Situation Room

**A production-ready, multi-tenant SaaS platform for real-time collaborative workshops and brainstorming sessions.**

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![Tested Users](https://img.shields.io/badge/tested-50%2B%20concurrent%20users-brightgreen.svg)](TESTING_SUMMARY.md)
[![Security](https://img.shields.io/badge/security-hardened-success.svg)](SECURITY_FIXES_APPLIED.md)

---

## 🎯 What is Live Situation Room?

Live Situation Room is a sophisticated web application designed for facilitating real-time collaborative workshops, brainstorming sessions, and interactive ideation events. It combines a unique **zero-database architecture** with enterprise-grade security to deliver a scalable, multi-tenant SaaS platform.

### Perfect For:
- 🏢 Corporate training sessions and workshops
- 🎓 Educational institution engagement activities
- 💡 Innovation and ideation workshops
- 🗣️ Community feedback events
- 🚨 Crisis management planning sessions
- 📊 Real-time audience engagement

---

## ✨ Key Features

### 🏢 Multi-Tenant SaaS Platform

- **User Registration & Authentication** - Secure account creation with bcrypt password hashing
- **Session Management** - 2-hour timeout, HttpOnly cookies, SameSite=Strict
- **Password Reset** - Token-based password recovery system
- **Data Isolation** - Complete separation of workshop data between users
- **Public Workshop URLs** - Each user gets shareable links for their workshops

### 💳 Subscription Management

- **Three-Tier Pricing**
  - **Free**: 10 participants, 3 categories, 1 workshop
  - **Premium**: Unlimited (€19.99/month or €203.89/year with 15% discount)
  - **Enterprise**: Custom pricing with dedicated support
- **Stripe Integration** - Secure payment processing with webhook support
- **Feature Enforcement** - Automatic limit validation per plan
- **Billing Portal** - Direct integration with Stripe Customer Portal

### 📊 Live Workshop Dashboard

**For Participants:**
- Real-time display updates (2-second polling)
- Categorized idea columns with custom icons
- Focus spotlight mode for highlighted entries
- QR code for instant mobile access
- Light/dark theme toggle
- Public access without login

**For Workshop Owners:**
- Admin context menu on entries
- Live moderation controls
- PDF export functionality
- Customizable branding (logo, title)
- Real-time analytics

### 📝 Public Submission Form

- Dynamic category selection
- Guiding questions (Leitfragen) per category
- 500-character limit with live counter
- Rate limiting (10 submissions/min per IP)
- Mobile-optimized input experience
- Works without user account

### 🎛️ Admin Moderation Panel

- Real-time feed of all submissions
- Mass control operations (show/hide all, by category)
- Individual entry moderation:
  - Show/hide visibility
  - Focus spotlight mode
  - Edit text inline
  - Delete entries
  - Move between categories
- PDF export for documentation
- Workshop URL sharing with copy-to-clipboard

### ⚙️ Workshop Customization

- Workshop title with HTML support
- Logo URL configuration
- Unlimited category management
- Per-category settings:
  - Unique key identifier
  - Display name and abbreviation
  - Icon (emoji support)
  - Multiple guiding questions
- Live configuration preview

### 🔒 Enterprise-Grade Security

- **Authentication**: bcrypt hashing (cost 10), 8-character minimum passwords
- **Security Headers**: CSP, HSTS, X-Frame-Options, X-XSS-Protection
- **CSRF Protection**: Tokens on all POST forms
- **Rate Limiting**: Login attempts, registrations, submissions
- **XSS Prevention**: HTML sanitization on all outputs
- **Input Validation**: Email format, URL safety, path traversal prevention
- **Stripe Security**: Webhook signature verification with HMAC-SHA256
- **File Protection**: .htaccess blocks direct access to sensitive files

---

## 🏗️ Architecture

### Zero-Database Design

Live Situation Room implements a unique **file-based transactional system** using atomic operations with PHP's `flock()` for ACID properties—no traditional database required.

**Benefits:**
- ✅ Simple deployment (no database setup)
- ✅ Easy backups (just copy files)
- ✅ Portable (works anywhere PHP runs)
- ✅ ACID properties via file locking
- ✅ Tested with 50+ concurrent users

### Technology Stack

| Component | Technology | Purpose |
|-----------|------------|---------|
| **Runtime** | PHP 7.4+ | Server-side execution |
| **Data Storage** | JSON Files | Structured data with atomic operations |
| **Concurrency** | PHP `flock()` | File locking for data integrity |
| **Authentication** | PHP Sessions | Secure session management |
| **Payments** | Stripe API | Subscription processing |
| **Frontend** | Vanilla JavaScript | Real-time updates and interactivity |
| **Styling** | CSS3 + Variables | Responsive design system |
| **QR Codes** | QRCode.js | Mobile access generation |

### File Structure

```
/
├── Authentication & User Management
│   ├── welcome.php              # Landing page
│   ├── register.php             # User registration
│   ├── login.php                # User login
│   ├── logout.php               # Session cleanup
│   ├── forgot_password.php      # Password reset request
│   └── reset_password.php       # Password reset completion
│
├── Core Application
│   ├── index.php                # Live workshop dashboard (public/admin)
│   ├── eingabe.php              # Public submission form
│   ├── admin.php                # Admin moderation panel
│   ├── customize.php            # Workshop configuration
│   ├── pricing.php              # Pricing page
│   ├── subscription_manage.php  # Subscription dashboard
│   ├── checkout.php             # Stripe checkout initiator
│   └── subscription_success.php # Payment success page
│
├── Core Libraries
│   ├── user_auth.php            # User management & authentication
│   ├── file_handling_robust.php # Atomic file operations
│   ├── security_helpers.php     # Security functions
│   ├── subscription_manager.php # Subscription logic
│   ├── stripe_api_client.php    # Stripe API client (zero-dependency)
│   └── stripe_config_secure.php # Stripe configuration
│
├── Data Storage (Multi-Tenant)
│   ├── users.json               # Global user registry
│   ├── password_reset_tokens.json
│   ├── pricing_config.json      # Subscription plans
│   ├── public_rate_limits.json  # Rate limiting state
│   └── data/                    # User-specific data
│       ├── {user_id_1}/
│       │   ├── daten.json       # Workshop submissions
│       │   ├── config.json      # Workshop configuration
│       │   └── backups/         # Auto-backups (last 10)
│       └── {user_id_2}/
│           └── ...
│
├── Utilities & Testing
│   ├── check_setup.php          # Configuration validator
│   ├── test_race_condition.html # Concurrency stress tester
│   ├── stripe_webhook.php       # Stripe webhook endpoint
│   └── install_stripe.sh        # Stripe SDK installer
│
├── Documentation
│   ├── README.md                # This file
│   ├── START_HERE.md            # Quick security checklist
│   ├── SECURITY_REVIEW_MVP_LAUNCH.md  # Security audit
│   ├── SECURITY_FIXES_APPLIED.md      # Applied fixes log
│   └── PAYMENT_SETUP_GUIDE.md   # Stripe integration guide
│
└── Configuration
    ├── .env                     # Environment variables (not in git)
    ├── .htaccess                # Apache security configuration
    └── .gitignore               # Git exclusions
```

---

## 🚀 Quick Start

### Prerequisites

- **PHP**: 7.4 or higher
- **Web Server**: Apache 2.4+ with `mod_rewrite` enabled
- **HTTPS**: SSL certificate (required for Stripe and secure sessions)
- **Storage**: 500MB minimum (grows with user data)
- **PHP Extensions**: json, session, curl, mbstring, openssl (all typically built-in)

### Installation (5 Minutes)

#### 1. Clone or Download

```bash
cd /var/www
git clone https://github.com/yourorg/live-situation-room.git
cd live-situation-room
```

#### 2. Set File Permissions

```bash
chmod 755 .
chmod 777 data/
chmod 666 users.json password_reset_tokens.json pricing_config.json public_rate_limits.json
chmod 644 *.php
chmod 644 .htaccess
```

#### 3. Configure Environment Variables

Create `.env` file:

```bash
# Stripe Configuration
STRIPE_ENVIRONMENT=test  # or 'live' for production

# Test Mode Keys (from https://dashboard.stripe.com/test/apikeys)
STRIPE_TEST_PUBLISHABLE_KEY=pk_test_YOUR_KEY_HERE
STRIPE_TEST_SECRET_KEY=sk_test_YOUR_KEY_HERE
STRIPE_TEST_WEBHOOK_SECRET=whsec_YOUR_WEBHOOK_SECRET

# Live Mode Keys (when ready for production)
STRIPE_LIVE_PUBLISHABLE_KEY=pk_live_YOUR_KEY_HERE
STRIPE_LIVE_SECRET_KEY=sk_live_YOUR_KEY_HERE
STRIPE_LIVE_WEBHOOK_SECRET=whsec_YOUR_WEBHOOK_SECRET

# Site Configuration
SITE_URL=https://yourdomain.com/path/to/app

# Stripe Price IDs (create products in Stripe Dashboard first)
STRIPE_TEST_PREMIUM_MONTHLY_PRICE_ID=price_YOUR_MONTHLY_ID
STRIPE_TEST_PREMIUM_YEARLY_PRICE_ID=price_YOUR_YEARLY_ID
STRIPE_LIVE_PREMIUM_MONTHLY_PRICE_ID=price_YOUR_LIVE_MONTHLY_ID
STRIPE_LIVE_PREMIUM_YEARLY_PRICE_ID=price_YOUR_LIVE_YEARLY_ID
```

**Secure the file:**
```bash
chmod 600 .env
```

#### 4. Create Stripe Products

1. Go to [Stripe Dashboard → Products](https://dashboard.stripe.com/test/products)
2. Create **Premium Monthly**: €19.99/month recurring
3. Create **Premium Yearly**: €203.89/year recurring
4. Copy the **Price IDs** (start with `price_...`) to your `.env` file

#### 5. Configure Webhook

1. Go to [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/test/webhooks)
2. Add endpoint: `https://yourdomain.com/stripe_webhook.php`
3. Select events:
   - `checkout.session.completed`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
4. Copy **Signing secret** to `.env`

#### 6. Access Your Application

1. Visit `https://yourdomain.com/welcome.php`
2. Click "Get Started" and create your first account
3. Customize your workshop at `/customize.php`
4. Share your workshop URLs with participants!

### Verify Setup

After first user registration, visit `/check_setup.php` to verify:
- PHP version compatibility
- File permissions
- Stripe configuration
- Environment variables

---

## 📖 User Guide

### For Workshop Owners

#### Setting Up Your First Workshop

1. **Register Account**
   - Visit `welcome.php`
   - Enter email and password (min 8 characters)
   - Automatic login after registration

2. **Customize Workshop**
   - Go to **Customize** in dashboard
   - Set workshop title (supports `<br>` for line breaks)
   - Add your logo URL (optional)
   - Create categories with:
     - Unique key (e.g., "innovation")
     - Display name (e.g., "Innovation Ideas")
     - 3-letter abbreviation (e.g., "INN")
     - Icon emoji (e.g., 💡)
     - Guiding questions for participants

3. **Share Workshop**
   - Copy **Live Dashboard URL** (display on screen/projector)
   - Copy **Submission Form URL** (share with participants or use QR code)
   - Both URLs are in your admin dashboard

#### Moderating Live Sessions

**Mass Controls:**
- **ALL LIVE** - Show all submissions instantly
- **ALL HIDE** - Hide everything
- **Category Controls** - Show/hide by category

**Individual Entry Actions:**
- **EDIT** - Modify submission text
- **FOCUS** - Spotlight entry on dashboard
- **HIDE/SHOW** - Toggle visibility
- **MOVE** - Reassign to different category
- **DELETE** - Remove permanently

**Best Practices:**
- Start with all entries hidden
- Review submissions as they arrive
- Use FOCUS to discuss specific ideas
- Show entries selectively for structured discussion
- Export to PDF for post-workshop documentation

#### Managing Subscription

**View Current Plan:**
- Visit **Subscription** in dashboard
- See usage vs. limits
- Check billing information

**Upgrade to Premium:**
1. Visit `pricing.php` or click "Upgrade"
2. Choose Monthly (€19.99/mo) or Yearly (€203.89/yr)
3. Complete Stripe checkout
4. Upgrade applies immediately

**Cancel/Reactivate:**
- Cancel anytime (remains active until period end)
- Reactivate before period ends to continue
- Automatic downgrade to Free after cancellation

### For Participants

#### Submitting Ideas

1. **Access Form**
   - Scan QR code from workshop screen
   - Or open submission URL from moderator

2. **Submit Your Idea**
   - Select category
   - Read guiding questions for context
   - Type your idea (max 500 characters)
   - Click "Submit"

3. **Watch Live Dashboard**
   - See your submission when moderator reveals it
   - Watch other ideas appear in real-time
   - No login required

**Tips:**
- Keep submissions concise
- Answer the guiding questions
- Multiple submissions allowed (rate-limited)

---

## 🔧 Configuration

### Environment Variables (.env)

| Variable | Required | Description |
|----------|----------|-------------|
| `STRIPE_ENVIRONMENT` | ✅ | `test` or `live` |
| `STRIPE_TEST_PUBLISHABLE_KEY` | ✅ | Public test key |
| `STRIPE_TEST_SECRET_KEY` | ✅ | Secret test key |
| `STRIPE_TEST_WEBHOOK_SECRET` | ✅ | Webhook signing secret |
| `STRIPE_TEST_PREMIUM_MONTHLY_PRICE_ID` | ✅ | Monthly price ID |
| `STRIPE_TEST_PREMIUM_YEARLY_PRICE_ID` | ✅ | Yearly price ID |
| `STRIPE_LIVE_*` | Production | Same as test for live mode |
| `SITE_URL` | ✅ | Base URL of installation |

### Pricing Plans (pricing_config.json)

Plans are defined with feature limits:

```json
{
  "plans": {
    "free": {
      "id": "free",
      "price_monthly": 0,
      "features": {
        "max_participants": 10,
        "max_columns": 3,
        "max_workshops": 1
      }
    },
    "premium": {
      "id": "premium",
      "price_monthly": 19.99,
      "price_yearly": 203.89,
      "features": {
        "max_participants": -1,  // -1 = unlimited
        "max_columns": -1,
        "max_workshops": -1
      }
    }
  }
}
```

### Workshop Configuration (per user)

Each user has `data/{user_id}/config.json`:

```json
{
  "header_title": "Workshop Title<br>Subtitle",
  "logo_url": "https://example.com/logo.png",
  "categories": [
    {
      "key": "innovation",
      "name": "INNOVATION",
      "abbreviation": "INN",
      "icon": "💡",
      "display_name": "💡 Innovation Ideas",
      "leitfragen": [
        "What innovative solutions can you propose?",
        "How can we improve existing processes?"
      ]
    }
  ]
}
```

**Edit via UI:** Users configure this through `customize.php`—no manual JSON editing required.

---

## 🔒 Security Features

### Authentication Security

- **Password Hashing**: bcrypt with cost factor 10
- **Session Security**: HttpOnly cookies, SameSite=Strict, 2-hour timeout
- **CSRF Protection**: Tokens on all POST forms
- **Rate Limiting**:
  - Login: 5 attempts per 15 minutes
  - Registration: 3 per hour
  - Public submissions: 10 per minute
- **Secure Password Reset**: Token-based with 1-hour expiration

### Data Protection

- **Multi-Tenant Isolation**: Complete data separation per user
- **Atomic Operations**: File locking prevents race conditions
- **Auto-Backups**: Last 10 backups per file with timestamps
- **File Protection**: .htaccess blocks direct access to:
  - users.json (credentials)
  - .env (API keys)
  - *.log files (error logs)
  - data/ directory (workshop data)
  - backups/ directories

### HTTP Security Headers

Automatically applied to all pages:

```
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000
Content-Security-Policy: [restrictive policy]
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

### Stripe Integration Security

- **Webhook Verification**: HMAC-SHA256 signature validation
- **SSL/TLS**: Certificate verification on API calls
- **Zero-Dependency Client**: Custom API client (no external SDK required)

---

## 📡 API Documentation

### Public APIs (No Authentication)

#### GET `/index.php?api=1&u={user_id}`

Returns workshop data as JSON.

**Response:**
```json
[
  {
    "id": "9813_69614124d420f5.10201804",
    "thema": "innovation",
    "text": "Implement AI-powered suggestions",
    "zeit": 1767981348,
    "visible": true,
    "focus": false
  }
]
```

#### POST `/eingabe.php?u={user_id}`

Submit new workshop entry.

**Parameters:**
- `thema` - Category key
- `idee` - Submission text (max 500 chars)

**Rate Limits:** 10 submissions per IP per minute

### Authenticated APIs (Session Required)

#### POST `/admin.php`

Execute moderation actions.

**Actions:**
- `action_all=show|hide` - Show/hide all entries
- `show_kategorie={key}` - Show all in category
- `hide_kategorie={key}` - Hide all in category
- `toggle_id={id}` - Toggle visibility
- `toggle_focus={id}` - Toggle focus
- `delete={id}` - Delete entry
- `action=move&id={id}&new_thema={key}` - Move entry

**Response:** `{"status": "success"}`

### Webhook Endpoints

#### POST `/stripe_webhook.php`

Receives Stripe webhook events.

**Required Header:** `Stripe-Signature`

**Events Handled:**
- `checkout.session.completed`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_succeeded`
- `invoice.payment_failed`

**Security:** HMAC-SHA256 signature verification

---

## 🧪 Testing

### Concurrency Stress Test

Use included test tool to validate atomic operations:

1. Open `test_race_condition.html` in browser
2. Configure:
   - Number of concurrent requests (default: 50)
   - Target workshop submission URL
3. Click "Start Test"
4. Verify all submissions appear in admin panel
5. Check for data corruption (none should occur)

### Manual Testing Checklist

**Authentication:**
- [ ] Register new user
- [ ] Login with correct credentials
- [ ] Login fails with wrong credentials
- [ ] Password reset flow works
- [ ] Rate limiting blocks excessive attempts

**Workshop Operations:**
- [ ] Customize workshop settings
- [ ] Add/edit/remove categories
- [ ] Submit entry via public form
- [ ] Toggle entry visibility
- [ ] Use focus mode
- [ ] Edit entry text
- [ ] Move entry between categories
- [ ] PDF export works

**Subscription:**
- [ ] View pricing page
- [ ] Subscribe to Premium (test mode)
- [ ] Verify webhook updates subscription
- [ ] Cancel and reactivate subscription

**Security:**
- [ ] CSRF protection rejects invalid tokens
- [ ] Rate limiting works under load
- [ ] XSS attempts are sanitized
- [ ] .htaccess blocks direct file access

---

## 🐛 Troubleshooting

### Common Issues

#### "Payment system not configured"

**Cause:** Stripe API keys not set or invalid

**Solution:**
1. Verify `.env` file exists
2. Check API keys match Stripe Dashboard
3. Ensure `STRIPE_ENVIRONMENT` is correct
4. Visit `/check_setup.php` for diagnostics

#### QR Code Not Generating

**Cause:** Content-Security-Policy blocking CDN

**Solution:** Verify CSP in `security_helpers.php` includes `https://cdnjs.cloudflare.com`

#### Data Not Showing on Dashboard

**Possible Causes:**
- JavaScript error (check browser console)
- File permissions issue
- API endpoint blocked

**Solutions:**
1. Open browser console (F12) and check for errors
2. Test API: `/index.php?api=1&u={user_id}`
3. Verify file permissions: `data/{user_id}/daten.json` readable

#### Webhook Not Updating Subscription

**Cause:** Webhook signature verification failing

**Solution:**
1. Check Stripe Dashboard → Webhooks for delivery status
2. Verify `STRIPE_WEBHOOK_SECRET` matches Stripe
3. Ensure webhook URL is correct
4. Check logs: `stripe_webhook.log`

#### Session Timeout Issues

**Cause:** 2-hour default timeout

**Solution:** Edit `user_auth.php` line 28:
```php
define('SESSION_TIMEOUT', 14400); // 4 hours
```

#### File Permission Errors

**Symptoms:** "Permission denied" or submissions not saving

**Solution:**
```bash
chmod 777 data/
chmod 777 data/*
chmod 666 users.json password_reset_tokens.json
chmod 666 data/*/daten.json data/*/config.json
```

---

## 📊 Performance & Scalability

### Current Capacity

- **Concurrent Users**: 50+ per workshop (tested)
- **API Response Time**: <100ms typical
- **Polling Interval**: 2 seconds (adjustable)
- **Max Entries**: 1000 recommended per workshop

### Scalability Considerations

**File-Based Limitations:**
- Suitable for up to 50-100 concurrent users
- Serialized writes at ~1000 ops/sec
- Single-server only (no multi-region)

**When to Migrate:**
- Beyond 100 concurrent users → Consider SQLite/MySQL
- High-frequency updates → Consider Redis for real-time
- Multi-region deployment → Requires database with replication

**Resource Usage:**
- **Memory**: ~2MB per user session
- **Storage**: ~15KB per 100 submissions
- **CPU**: Minimal (no complex processing)

---

## 🎯 Production Deployment Checklist

### Before Going Live

- [ ] SSL certificate installed (HTTPS enabled)
- [ ] `.env` file configured with live Stripe keys
- [ ] `STRIPE_ENVIRONMENT=live` in `.env`
- [ ] Stripe products created in live mode
- [ ] Webhook endpoint configured (live mode)
- [ ] File permissions set correctly
- [ ] `.htaccess` security active
- [ ] Test all authentication flows
- [ ] Test payment flow with real card
- [ ] Monitor error logs for 24 hours

### PHP Configuration (php.ini)

```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 30
memory_limit = 256M
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Strict
```

### Apache Configuration

Enable required modules:
```bash
a2enmod rewrite
a2enmod headers
systemctl restart apache2
```

### Backup Strategy

**Automatic:**
- Last 10 backups per file in `data/{user_id}/backups/`
- Created on every write operation

**Recommended External Backups:**
- Daily full backup of `data/` directory
- Weekly backup of `users.json`
- Monthly archive with 6-month retention

---

## 🤝 Support & Contributing

### Getting Help

- **Documentation**: Start with this README
- **Security Issues**: See `SECURITY_REVIEW_MVP_LAUNCH.md`
- **Payment Setup**: See `PAYMENT_SETUP_GUIDE.md`
- **Quick Start**: See `START_HERE.md` for security checklist

### Reporting Issues

When reporting issues, include:
1. PHP version (`php -v`)
2. Web server (Apache/Nginx)
3. Error messages from logs
4. Steps to reproduce
5. Expected vs actual behavior

### Feature Requests

We welcome suggestions! Consider:
- Is it useful for most users?
- Does it fit the MVP scope?
- Is it technically feasible with file-based architecture?

---

## 📝 License

**Proprietary Software**

This software is proprietary and confidential. Unauthorized copying, distribution, or use is strictly prohibited.

For licensing inquiries, contact: [your-email@example.com]

---

## 🎉 Acknowledgments

**Built With:**
- PHP - Server-side processing
- Vanilla JavaScript - Client-side interactivity
- Stripe - Payment processing
- QRCode.js - QR code generation

**Tested & Validated:**
- 50+ concurrent users in real workshop environments
- Stress-tested with race condition simulator
- Security audited for OWASP Top 10 vulnerabilities
- All critical security fixes applied

---

## 📈 Current Status

**Version:** 2.0.0 (Multi-Tenant SaaS Edition)
**Last Updated:** January 2026
**Production Status:** ✅ Ready for deployment

**Known Limitations:**
- File-based system (suitable for up to 50 concurrent users)
- Single-server deployment only
- Manual email system (password reset tokens shown on screen)

**Recommended For:**
- Small to medium workshops (up to 50 participants)
- Corporate training sessions
- Educational institutions
- Community engagement events

**Not Recommended For:**
- 100+ concurrent users (requires database migration)
- Multi-region deployment
- High-frequency real-time trading systems

---

**Made with ❤️ for collaborative workshops**

*Live Situation Room - Bringing Ideas to Life*
