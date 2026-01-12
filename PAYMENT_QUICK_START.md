# 🚀 Payment System Quick Start

Your freemium payment system is installed! Follow these 3 simple steps to get it working.

## ✅ Step 1: Run the Setup Checker

Visit this URL in your browser:
```
https://learn.disinfoconsulting.eu/tool_dev_area/check_setup.php
```

This will tell you exactly what needs to be configured.

## ✅ Step 2: Install Stripe SDK

SSH into your server and run:
```bash
cd /path/to/tool_dev_area
chmod +x install_stripe.sh
./install_stripe.sh
```

**Alternative (if you have Composer):**
```bash
composer require stripe/stripe-php
```

## ✅ Step 3: Configure Stripe Keys

### Get Your Stripe Keys

1. Go to https://dashboard.stripe.com/register
2. Create an account (select Germany/EU as region)
3. Navigate to **Developers** → **API keys**
4. Copy your **Publishable key** (starts with `pk_test_...`)
5. Copy your **Secret key** (starts with `sk_test_...`)

### Update Configuration

Edit the `.env` file:
```bash
nano .env
```

Update these lines:
```
STRIPE_TEST_PUBLISHABLE_KEY=pk_test_YOUR_ACTUAL_KEY_HERE
STRIPE_TEST_SECRET_KEY=sk_test_YOUR_ACTUAL_KEY_HERE
```

### Create Stripe Products

1. Go to https://dashboard.stripe.com/products
2. Click **Add product**

**Product 1: Premium Monthly**
- Name: `Premium Monthly`
- Price: `€19.99`
- Billing period: `Monthly`
- Recurring: ✓
- Copy the **Price ID** (starts with `price_...`)

**Product 2: Premium Yearly**
- Name: `Premium Yearly`
- Price: `€203.89`
- Billing period: `Yearly`
- Recurring: ✓
- Copy the **Price ID** (starts with `price_...`)

Update `.env` with Price IDs:
```
STRIPE_TEST_PREMIUM_MONTHLY_PRICE_ID=price_YOUR_MONTHLY_ID
STRIPE_TEST_PREMIUM_YEARLY_PRICE_ID=price_YOUR_YEARLY_ID
```

## ✅ Step 4: Test It!

### Test the Pricing Page
Visit:
```
https://learn.disinfoconsulting.eu/tool_dev_area/pricing.php
```

### Test with Stripe Test Card
Use this card number for testing:
- **Card**: `4242 4242 4242 4242`
- **Expiry**: Any future date (e.g., `12/34`)
- **CVC**: Any 3 digits (e.g., `123`)
- **ZIP**: Any 5 digits (e.g., `12345`)

### Complete Test Flow
1. Register a new account at `/register.php`
2. Go to `/pricing.php`
3. Click "Upgrade Now" on Premium plan
4. Use test card `4242 4242 4242 4242`
5. Complete checkout
6. Check `/subscription_manage.php` to see your subscription

## 📋 What You Get

### Free Plan (Default)
- Up to 10 participants
- 3 columns/categories
- Perfect for testing

### Premium Plan (€19.99/month or €203.89/year)
- Unlimited participants
- Unlimited columns
- Unlimited workshops
- Custom branding

### Enterprise Plan
- Custom pricing
- Multiple team accounts
- Dedicated support

## 🔒 Security

**IMPORTANT:** The `.env` file contains sensitive information!

✅ **Already Done:**
- `.env` is in `.gitignore`
- Configuration loads from environment variables
- Sensitive keys are not in code

✅ **You Should:**
1. Set file permissions: `chmod 600 .env`
2. Never commit `.env` to Git
3. Use different keys for test and live mode

## 🐛 Troubleshooting

### "Payment system not available" error
**Solution:** Install Stripe SDK (see Step 2)

### "Payment system not configured" error
**Solution:** Update `.env` with your Stripe keys (see Step 3)

### Pricing page shows blank or error
**Solution:** Run the setup checker at `/check_setup.php`

### Checkout doesn't work
**Solution:** Make sure Price IDs are correct in `.env`

## 📞 Need Help?

1. **Run Setup Checker**: `/check_setup.php`
2. **Check Logs**: Look for errors in browser console and server logs
3. **Read Full Guide**: `PAYMENT_SETUP_GUIDE.md`
4. **Stripe Dashboard**: https://dashboard.stripe.com

## 🎯 Next Steps (Optional)

### Setup Webhooks (Recommended)
Webhooks automatically update subscriptions when payments succeed/fail.

1. Go to https://dashboard.stripe.com/webhooks
2. Click **Add endpoint**
3. URL: `https://learn.disinfoconsulting.eu/tool_dev_area/stripe_webhook.php`
4. Events: Select these:
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
   - `checkout.session.completed`
5. Copy webhook secret to `.env`:
   ```
   STRIPE_TEST_WEBHOOK_SECRET=whsec_YOUR_SECRET
   ```

### Go Live (When Ready)
1. Get live API keys from Stripe Dashboard
2. Create live products (same as test mode)
3. Update `.env`:
   ```
   STRIPE_ENVIRONMENT=live
   STRIPE_LIVE_PUBLISHABLE_KEY=pk_live_...
   STRIPE_LIVE_SECRET_KEY=sk_live_...
   STRIPE_LIVE_PREMIUM_MONTHLY_PRICE_ID=price_...
   STRIPE_LIVE_PREMIUM_YEARLY_PRICE_ID=price_...
   ```
4. Setup live webhooks
5. Test with real card before announcing!

## 📁 Important Files

- `.env` - Your configuration (edit this!)
- `check_setup.php` - Setup checker tool
- `pricing.php` - Public pricing page
- `subscription_manage.php` - User dashboard
- `stripe_webhook.php` - Webhook handler
- `PAYMENT_SETUP_GUIDE.md` - Complete documentation

---

**🎉 That's it! Your payment system is ready to accept subscriptions.**

Questions? Check `PAYMENT_SETUP_GUIDE.md` for detailed instructions.
