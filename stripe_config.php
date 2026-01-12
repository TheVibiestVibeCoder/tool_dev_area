<?php
/**
 * Stripe Configuration
 *
 * SETUP INSTRUCTIONS:
 * 1. Create a Stripe account at https://stripe.com
 * 2. Get your API keys from https://dashboard.stripe.com/apikeys
 * 3. Replace the placeholder keys below with your actual keys
 * 4. For production, use live keys (starts with pk_live_ and sk_live_)
 * 5. For testing, use test keys (starts with pk_test_ and sk_test_)
 */

// Environment - set to 'live' for production
define('STRIPE_ENVIRONMENT', 'test'); // 'test' or 'live'

// Stripe API Keys
// IMPORTANT: Replace these with your actual Stripe keys
if (STRIPE_ENVIRONMENT === 'live') {
    // Live/Production keys
    define('STRIPE_PUBLISHABLE_KEY', 'pk_live_51SogyRBYVfeYce7i7gzZIJcSb97IZWWZ2iBudy5eVLDIt80QV77VxkUHwMG3tR4jeCLIM8r4IWEB8jsAR5PPkFqy00mGjSa4GZ');
    define('STRIPE_SECRET_KEY', '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');
    define('STRIPE_WEBHOOK_SECRET', 'whsec_eOCxECCveYlHUkeriUiMnqA0Jhblrkwq');
} else {
    // Test keys
    define('STRIPE_PUBLISHABLE_KEY', 'pk_test_51SogyRBYVfeYce7i7gzZIJcSb97IZWWZ2iBudy5eVLDIt80QV77VxkUHwMG3tR4jeCLIM8r4IWEB8jsAR5PPkFqy00mGjSa4GZ');
    define('STRIPE_SECRET_KEY', 'sk_test_51SogyRBYVfeYce7i64rJjnozoCE9PzvQtpjzxh5M8TrAtJ891cXGyblaZGnoVCPWd77vT0cvS4qLoPyjiQUQqyeE00wedwsFoX');
    define('STRIPE_WEBHOOK_SECRET', 'whsec_eOCxECCveYlHUkeriUiMnqA0Jhblrkwq');
}

// Stripe Configuration
define('STRIPE_CURRENCY', 'eur');
define('STRIPE_SUCCESS_URL', 'https://learn.disinfoconsulting.eu/tool_dev_area/subscription_success.php');
define('STRIPE_CANCEL_URL', 'https://learn.disinfoconsulting.eu/tool_dev_area/pricing.php');

// Product IDs (These will be created in Stripe Dashboard)
// You need to create these products in Stripe and get their price IDs
define('STRIPE_PREMIUM_MONTHLY_PRICE_ID', 'price_REPLACE_WITH_PREMIUM_MONTHLY_PRICE_ID');
define('STRIPE_PREMIUM_YEARLY_PRICE_ID', 'price_REPLACE_WITH_PREMIUM_YEARLY_PRICE_ID');

/**
 * HOW TO GET YOUR STRIPE KEYS:
 *
 * 1. Go to https://dashboard.stripe.com/register
 * 2. Create an account (EU region recommended for EU customers)
 * 3. Navigate to Developers > API keys
 * 4. Copy your Publishable key (pk_test_...) and Secret key (sk_test_...)
 * 5. For webhooks:
 *    - Go to Developers > Webhooks
 *    - Click "Add endpoint"
 *    - URL: https://yourdomain.com/stripe_webhook.php
 *    - Events to listen: customer.subscription.created, customer.subscription.updated,
 *                       customer.subscription.deleted, invoice.payment_succeeded,
 *                       invoice.payment_failed
 *    - Copy the webhook signing secret (whsec_...)
 *
 * 6. Create Products:
 *    - Go to Products > Add product
 *    - Create "Premium Monthly" - €19.99/month recurring
 *    - Create "Premium Yearly" - €203.89/year recurring
 *    - Copy the Price IDs (price_...) for each
 */

?>
