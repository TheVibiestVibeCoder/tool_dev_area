# 💳 Payment System Setup Guide

Complete guide to setting up the freemium payment system with Stripe for your Live Situation Room application.

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Pricing Structure](#pricing-structure)
3. [Quick Setup (5 Steps)](#quick-setup)
4. [Detailed Stripe Configuration](#detailed-stripe-configuration)
5. [Testing the Payment Flow](#testing-the-payment-flow)
6. [Going Live (Production)](#going-live-production)
7. [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

Your application now includes a complete freemium payment system with three tiers:

- **Free Tier**: Up to 10 participants, 3 columns/categories
- **Premium Tier**: €19.99/month (or €203.89/year with 15% discount = €17.00/month)
- **Enterprise Tier**: Custom pricing with personal offers

**Payment Provider**: Stripe (Best for EU region)

**Features Implemented**:
- ✅ Subscription management
- ✅ Stripe checkout integration
- ✅ Webhook handling for automatic updates
- ✅ Plan limit enforcement
- ✅ Upgrade/downgrade flows
- ✅ Cancellation handling
- ✅ Usage tracking
- ✅ Customer portal for payment methods

---

## 💰 Pricing Structure

### Free Plan
- **Price**: €0 (forever)
- **Limits**:
  - 10 participants max
  - 3 columns/categories max
  - 1 workshop
- **Features**:
  - PDF export
  - Basic functionality

### Premium Plan
- **Price**:
  - Monthly: €19.99/month
  - Yearly: €203.89/year (€17.00/month - 15% discount)
- **Limits**: Unlimited everything
- **Features**:
  - Unlimited participants
  - Unlimited columns
  - Unlimited workshops
  - Custom branding
  - Email support

### Enterprise Plan
- **Price**: Custom (contact sales)
- **Features**:
  - Everything in Premium
  - Multiple team accounts
  - Custom integrations
  - Priority support
  - Dedicated account manager

---

## ⚡ Quick Setup (5 Steps)

### Step 1: Install Stripe PHP SDK

```bash
cd /home/user/tool_dev_area
chmod +x install_stripe.sh
./install_stripe.sh
```

OR if you have Composer:

```bash
composer require stripe/stripe-php
```

### Step 2: Create Stripe Account

1. Go to https://stripe.com
2. Click "Start now" and create an account
3. Choose your country (Germany recommended for EU)
4. Complete business verification

### Step 3: Get API Keys

1. Log in to Stripe Dashboard: https://dashboard.stripe.com
2. Navigate to **Developers** > **API keys**
3. Copy your **Publishable key** (starts with `pk_test_...`)
4. Copy your **Secret key** (starts with `sk_test_...`)
5. Keep these safe - you'll need them next!

### Step 4: Configure Stripe Keys

Edit `stripe_config.php` and replace the placeholder keys:

```php
// Test keys (for development)
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_YOUR_KEY_HERE');
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_KEY_HERE');
```

**⚠️ IMPORTANT**: Update these URLs in `stripe_config.php`:

```php
define('STRIPE_SUCCESS_URL', 'https://YOURDOMAIN.com/subscription_success.php');
define('STRIPE_CANCEL_URL', 'https://YOURDOMAIN.com/pricing.php');
```

Replace `YOURDOMAIN.com` with your actual domain!

### Step 5: Create Stripe Products

1. Go to **Products** in Stripe Dashboard
2. Click **Add product**

**Product 1: Premium Monthly**
- Name: `Premium Monthly`
- Description: `Unlimited workshops with all features`
- Pricing model: `Recurring`
- Price: `€19.99`
- Billing period: `Monthly`
- Click **Save product**
- Copy the **Price ID** (starts with `price_...`)

**Product 2: Premium Yearly**
- Name: `Premium Yearly`
- Description: `Unlimited workshops with all features (15% discount)`
- Pricing model: `Recurring`
- Price: `€203.89`
- Billing period: `Yearly`
- Click **Save product**
- Copy the **Price ID** (starts with `price_...`)

Now update `pricing_config.json`:

```json
{
    "plans": {
        "premium": {
            "stripe_price_id_monthly": "price_YOUR_MONTHLY_PRICE_ID",
            "stripe_price_id_yearly": "price_YOUR_YEARLY_PRICE_ID"
        }
    }
}
```

**✅ You're done with basic setup!** Test it out by registering a new account and visiting the Pricing page.

---

## 🔧 Detailed Stripe Configuration

### Setting Up Webhooks

Webhooks allow Stripe to notify your application about subscription events (payments, cancellations, etc.).

1. Go to **Developers** > **Webhooks** in Stripe Dashboard
2. Click **Add endpoint**
3. Enter your webhook URL:
   ```
   https://YOURDOMAIN.com/stripe_webhook.php
   ```
4. Select events to listen to:
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
   - `checkout.session.completed`
5. Click **Add endpoint**
6. Copy the **Signing secret** (starts with `whsec_...`)
7. Update `stripe_config.php`:
   ```php
   define('STRIPE_WEBHOOK_SECRET', 'whsec_YOUR_WEBHOOK_SECRET');
   ```

### Testing Webhooks Locally

If you're developing locally, use the Stripe CLI:

```bash
# Install Stripe CLI
# https://stripe.com/docs/stripe-cli

# Login
stripe login

# Forward webhooks to local server
stripe listen --forward-to localhost:8000/stripe_webhook.php
```

This will give you a webhook secret starting with `whsec_...` - use this in your test config.

---

## 🧪 Testing the Payment Flow

### Using Stripe Test Mode

Stripe provides test card numbers that simulate different scenarios:

**Successful Payment:**
- Card Number: `4242 4242 4242 4242`
- Expiry: Any future date (e.g., `12/34`)
- CVC: Any 3 digits (e.g., `123`)
- ZIP: Any 5 digits (e.g., `12345`)

**Payment Requires Authentication:**
- Card Number: `4000 0027 6000 3184`

**Payment Declined:**
- Card Number: `4000 0000 0000 0002`

**SEPA Direct Debit (EU):**
- IBAN: `DE89370400440532013000`

### Test Flow Checklist

1. ✅ Register a new test account
2. ✅ Navigate to Pricing page (`/pricing.php`)
3. ✅ Click "Upgrade Now" on Premium plan
4. ✅ Complete checkout with test card `4242 4242 4242 4242`
5. ✅ Verify redirect to success page
6. ✅ Check subscription status in **Subscription Management** (`/subscription_manage.php`)
7. ✅ Verify plan limits are updated (try adding more than 3 columns in Customize)
8. ✅ Test cancellation flow
9. ✅ Check Stripe Dashboard for subscription

### Verifying Webhooks

Check the webhook log file:

```bash
tail -f /home/user/tool_dev_area/stripe_webhook.log
```

You should see entries like:

```
2026-01-12 10:30:45 - Received webhook
2026-01-12 10:30:45 - Event type: checkout.session.completed
2026-01-12 10:30:45 - Checkout completed for user user_abc123
```

---

## 🚀 Going Live (Production)

### Checklist Before Launch

#### 1. Switch to Live Mode

In `stripe_config.php`, change:

```php
define('STRIPE_ENVIRONMENT', 'live'); // Changed from 'test' to 'live'
```

#### 2. Get Live API Keys

1. Go to Stripe Dashboard
2. Toggle from **Test mode** to **Live mode** (top right)
3. Navigate to **Developers** > **API keys**
4. Copy your **Live** keys (start with `pk_live_...` and `sk_live_...`)
5. Update `stripe_config.php` with live keys

#### 3. Update Webhook for Live Mode

1. In Stripe Dashboard (Live mode)
2. Go to **Developers** > **Webhooks**
3. Create a new endpoint with your production URL
4. Select the same events as before
5. Copy the new **Live** webhook secret
6. Update `stripe_config.php` with live webhook secret

#### 4. Create Live Products

Repeat the product creation process in **Live mode** on Stripe:
- Premium Monthly (€19.99/month)
- Premium Yearly (€203.89/year)

Update `pricing_config.json` with the **Live** price IDs.

#### 5. Security Checklist

- ✅ Ensure `stripe_config.php` is NOT publicly accessible
- ✅ Add `stripe_config.php` to `.gitignore`
- ✅ Use HTTPS (SSL certificate required)
- ✅ Set proper file permissions:
  ```bash
  chmod 600 stripe_config.php
  chmod 644 stripe_webhook.php
  ```
- ✅ Enable error logging but disable display:
  ```php
  ini_set('display_errors', 0);
  ini_set('log_errors', 1);
  ```

#### 6. Email Configuration (Optional but Recommended)

For Enterprise inquiries, update the email in `pricing.php`:

```javascript
function contactEnterprise() {
    window.location.href = 'mailto:enterprise@YOURDOMAIN.com?subject=Enterprise%20Plan%20Inquiry';
}
```

---

## 🔧 File Structure Overview

```
/home/user/tool_dev_area/
├── stripe_config.php              # Stripe API keys & configuration
├── pricing_config.json            # Plan definitions & limits
├── subscription_manager.php       # Core subscription logic
├── pricing.php                    # Public pricing page
├── checkout.php                   # Stripe checkout flow
├── stripe_webhook.php             # Webhook handler
├── subscription_manage.php        # User subscription dashboard
├── subscription_success.php       # Post-checkout redirect
├── install_stripe.sh              # Stripe SDK installer
└── stripe-php/                    # Stripe PHP SDK (after install)
```

### Modified Files

These existing files were updated to include subscription features:

- `user_auth.php` - Added subscription fields to new users
- `customize.php` - Added column limit enforcement
- `admin.php` - Added subscription link to navigation

---

## 🐛 Troubleshooting

### Problem: "Payment system not configured" error

**Solution**: Make sure you've updated all placeholder values:
- Stripe API keys in `stripe_config.php`
- Price IDs in `pricing_config.json`
- URLs in `stripe_config.php` (success/cancel URLs)

### Problem: Webhooks not working

**Possible causes**:
1. Webhook secret is incorrect
2. Webhook URL is not publicly accessible
3. SSL certificate issues

**Debug steps**:
```bash
# Check webhook log
tail -f stripe_webhook.log

# Test webhook manually using Stripe CLI
stripe trigger checkout.session.completed
```

### Problem: "Can't add more columns" even after upgrading

**Solution**:
1. Check webhook was received: `tail stripe_webhook.log`
2. Check subscription status: Visit `/subscription_manage.php`
3. Verify `users.json` shows updated plan:
   ```bash
   cat users.json | grep -A 10 "subscription"
   ```

### Problem: Stripe SDK not loading

**Solution**:
```bash
# Reinstall Stripe SDK
./install_stripe.sh

# OR with Composer
composer require stripe/stripe-php
```

### Problem: Test payments not working

**Solution**: Ensure you're using test mode and test card numbers:
- Test card: `4242 4242 4242 4242`
- Check you're using `pk_test_...` keys, not `pk_live_...`

---

## 📊 Monitoring & Analytics

### Stripe Dashboard

Monitor your subscriptions in real-time:
- **Home** > Overview of revenue and customers
- **Customers** > List of all subscribers
- **Subscriptions** > Active, past due, and canceled subscriptions
- **Payments** > Payment history
- **Webhooks** > Webhook delivery status

### Application Logs

Check webhook activity:
```bash
tail -f stripe_webhook.log
```

Check PHP errors:
```bash
tail -f error.log
```

---

## 💡 Tips & Best Practices

### 1. Always Test First
- Use test mode extensively before going live
- Test all payment scenarios (success, failure, cancellation)
- Verify webhook handling

### 2. Monitor Webhooks
- Check webhook logs regularly
- Set up email alerts for failed webhooks in Stripe Dashboard
- Test webhook endpoint after any server changes

### 3. Handle Edge Cases
- Payment failures (webhooks handle this automatically)
- User downgrades (handled by cancellation flow)
- Subscription pausing (not implemented yet - add if needed)

### 4. Customer Communication
- Consider adding email notifications for:
  - Subscription activated
  - Payment succeeded
  - Payment failed
  - Subscription canceled
- Update `stripe_webhook.php` to send emails (use PHP mail or service like SendGrid)

### 5. Compliance
- Add Terms of Service link
- Add Privacy Policy link
- Include VAT handling for EU customers (Stripe handles this automatically)
- Consider adding invoice generation

---

## 🎓 Additional Resources

- [Stripe Documentation](https://stripe.com/docs)
- [Stripe Testing Guide](https://stripe.com/docs/testing)
- [Stripe Webhooks Guide](https://stripe.com/docs/webhooks)
- [Stripe CLI](https://stripe.com/docs/stripe-cli)
- [PHP SDK Reference](https://stripe.com/docs/api?lang=php)

---

## 📞 Support

For questions about the payment implementation:
- Check the troubleshooting section above
- Review Stripe logs and webhook logs
- Consult Stripe documentation
- Contact Stripe support for payment-related issues

---

**🎉 Congratulations!** Your freemium payment system is ready to go. Start with test mode, verify everything works, then switch to live mode when ready.

**Next Steps**:
1. Follow the Quick Setup guide above
2. Test the complete payment flow
3. Customize pricing/features as needed
4. Go live and start accepting payments! 🚀
