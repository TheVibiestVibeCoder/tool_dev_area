# 💳 Payment System - Quick Reference Card

## 🔑 Where to Input What Data

### 1. Stripe API Keys

**File**: `stripe_config.php`

**What to replace**:
```php
// Line 18-20 (Test mode)
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_YOUR_KEY_HERE');
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_KEY_HERE');
define('STRIPE_WEBHOOK_SECRET', 'whsec_YOUR_WEBHOOK_SECRET_HERE');

// Line 24-26 (Live mode)
define('STRIPE_PUBLISHABLE_KEY', 'pk_live_YOUR_KEY_HERE');
define('STRIPE_SECRET_KEY', 'sk_live_YOUR_KEY_HERE');
define('STRIPE_WEBHOOK_SECRET', 'whsec_YOUR_WEBHOOK_SECRET_HERE');
```

**Where to get these values**:
- Go to https://dashboard.stripe.com
- Click **Developers** > **API keys**
- Copy Publishable and Secret keys
- For webhook secret: **Developers** > **Webhooks** > Create endpoint > Copy signing secret

---

### 2. Your Domain URLs

**File**: `stripe_config.php`

**What to replace** (Lines 32-33):
```php
define('STRIPE_SUCCESS_URL', 'https://YOURDOMAIN.com/subscription_success.php');
define('STRIPE_CANCEL_URL', 'https://YOURDOMAIN.com/pricing.php');
```

**Replace `YOURDOMAIN.com` with**: Your actual domain (e.g., `myworkshop.com`)

---

### 3. Stripe Product Price IDs

**File**: `pricing_config.json`

**What to replace** (Lines 24-25):
```json
"stripe_price_id_monthly": "price_YOUR_MONTHLY_PRICE_ID_HERE",
"stripe_price_id_yearly": "price_YOUR_YEARLY_PRICE_ID_HERE"
```

**Where to get these values**:
1. Go to https://dashboard.stripe.com/products
2. Click **Add product**
3. Create "Premium Monthly" product:
   - Price: €19.99
   - Billing: Monthly recurring
   - Save and copy the **Price ID** (starts with `price_...`)
4. Create "Premium Yearly" product:
   - Price: €203.89
   - Billing: Yearly recurring
   - Save and copy the **Price ID**

---

### 4. Enterprise Contact Email

**File**: `pricing.php`

**What to replace** (Line ~590):
```javascript
function contactEnterprise() {
    window.location.href = 'mailto:enterprise@YOURDOMAIN.com?subject=Enterprise%20Plan%20Inquiry';
}
```

**Replace**: `enterprise@YOURDOMAIN.com` with your sales email

---

### 5. Webhook Endpoint URL

**Where**: Stripe Dashboard (https://dashboard.stripe.com/webhooks)

**What to configure**:
- Endpoint URL: `https://YOURDOMAIN.com/stripe_webhook.php`
- Events to select:
  - ✅ `customer.subscription.created`
  - ✅ `customer.subscription.updated`
  - ✅ `customer.subscription.deleted`
  - ✅ `invoice.payment_succeeded`
  - ✅ `invoice.payment_failed`
  - ✅ `checkout.session.completed`

---

## ⚡ Quick Setup Commands

```bash
# 1. Install Stripe SDK
cd /home/user/tool_dev_area
./install_stripe.sh

# 2. Set file permissions
chmod 600 stripe_config.php
chmod 644 stripe_webhook.php
chmod 666 pricing_config.json

# 3. Test the setup
# Visit: https://YOURDOMAIN.com/pricing.php
```

---

## 🧪 Test Card Numbers

Use these in Stripe test mode:

| Scenario | Card Number | Result |
|----------|-------------|--------|
| Success | `4242 4242 4242 4242` | Payment succeeds |
| Decline | `4000 0000 0000 0002` | Payment declined |
| Authentication | `4000 0027 6000 3184` | Requires 3D Secure |

**All test cards**:
- Expiry: Any future date (e.g., `12/34`)
- CVC: Any 3 digits (e.g., `123`)
- ZIP: Any 5 digits (e.g., `12345`)

---

## 📂 Files You Need to Edit

| File | What to Configure | Priority |
|------|-------------------|----------|
| `stripe_config.php` | API keys, domain URLs | 🔴 CRITICAL |
| `pricing_config.json` | Stripe price IDs | 🔴 CRITICAL |
| `pricing.php` | Enterprise email | 🟡 Optional |

---

## ✅ Pre-Launch Checklist

**Test Mode** (Start here):
- [ ] Stripe SDK installed (`./install_stripe.sh`)
- [ ] Test API keys configured in `stripe_config.php`
- [ ] Domain URLs updated in `stripe_config.php`
- [ ] Test products created in Stripe Dashboard
- [ ] Price IDs updated in `pricing_config.json`
- [ ] Webhook endpoint configured and secret added
- [ ] Test payment with card `4242 4242 4242 4242`
- [ ] Verify subscription appears in dashboard
- [ ] Test column limit enforcement (try adding 4th column on free plan)
- [ ] Test cancellation flow

**Live Mode** (After testing):
- [ ] Switch to live mode in `stripe_config.php` (line 12)
- [ ] Live API keys configured
- [ ] Live products created in Stripe
- [ ] Live price IDs updated in `pricing_config.json`
- [ ] Live webhook endpoint configured
- [ ] SSL certificate installed (HTTPS)
- [ ] `.htaccess` security configured
- [ ] File permissions set correctly
- [ ] Error logging enabled, display disabled

---

## 🔥 Emergency Rollback

If something goes wrong, disable payments temporarily:

```php
// In pricing.php, around line 155
<?php if ($current_plan_id === 'premium'): ?>
    <button class="plan-button disabled">Temporarily Unavailable</button>
<?php endif; ?>
```

---

## 📞 Key Support Links

- **Stripe Dashboard**: https://dashboard.stripe.com
- **API Keys**: https://dashboard.stripe.com/apikeys
- **Webhooks**: https://dashboard.stripe.com/webhooks
- **Products**: https://dashboard.stripe.com/products
- **Testing Guide**: https://stripe.com/docs/testing
- **Stripe Support**: https://support.stripe.com

---

## 🎯 Summary

**3 Critical Steps**:

1. **Get Stripe keys** → Update `stripe_config.php`
2. **Create products** → Update `pricing_config.json` with price IDs
3. **Setup webhook** → Add webhook URL in Stripe Dashboard

**That's it!** Everything else is already coded and ready to go.

---

**Questions?** See full guide: `PAYMENT_SETUP_GUIDE.md`
