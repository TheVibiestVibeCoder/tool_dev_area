<?php
/**
 * Secure Stripe Configuration
 *
 * This file loads Stripe configuration from environment variables or .env file
 *
 * SETUP:
 * 1. Copy .env.example to .env
 * 2. Fill in your actual Stripe keys in .env
 * 3. Make sure .env is in .gitignore
 * 4. Set proper file permissions: chmod 600 .env
 */

// Load environment variables from .env file if it exists
function loadEnv($file = '.env') {
    if (!file_exists($file)) {
        return false;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Set as environment variable if not already set
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    return true;
}

// Try to load .env file
$env_loaded = loadEnv(__DIR__ . '/.env');

// Get environment setting (test or live)
$stripe_env = getenv('STRIPE_ENVIRONMENT') ?: 'test';
define('STRIPE_ENVIRONMENT', $stripe_env);

// Load appropriate keys based on environment
if (STRIPE_ENVIRONMENT === 'live') {
    // Live/Production keys
    $publishable_key = getenv('STRIPE_LIVE_PUBLISHABLE_KEY');
    $secret_key = getenv('STRIPE_LIVE_SECRET_KEY');
    $webhook_secret = getenv('STRIPE_LIVE_WEBHOOK_SECRET');
    $premium_monthly_price_id = getenv('STRIPE_LIVE_PREMIUM_MONTHLY_PRICE_ID');
    $premium_yearly_price_id = getenv('STRIPE_LIVE_PREMIUM_YEARLY_PRICE_ID');
} else {
    // Test keys
    $publishable_key = getenv('STRIPE_TEST_PUBLISHABLE_KEY');
    $secret_key = getenv('STRIPE_TEST_SECRET_KEY');
    $webhook_secret = getenv('STRIPE_TEST_WEBHOOK_SECRET');
    $premium_monthly_price_id = getenv('STRIPE_TEST_PREMIUM_MONTHLY_PRICE_ID');
    $premium_yearly_price_id = getenv('STRIPE_TEST_PREMIUM_YEARLY_PRICE_ID');
}

// Fallback to hardcoded values if environment variables are not set
// IMPORTANT: For production, always use .env file or server environment variables!
if (empty($publishable_key)) {
    $publishable_key = 'pk_test_REPLACE_WITH_YOUR_TEST_PUBLISHABLE_KEY';
}
if (empty($secret_key)) {
    $secret_key = 'sk_test_REPLACE_WITH_YOUR_TEST_SECRET_KEY';
}
if (empty($webhook_secret)) {
    $webhook_secret = 'whsec_REPLACE_WITH_YOUR_TEST_WEBHOOK_SECRET';
}
if (empty($premium_monthly_price_id)) {
    $premium_monthly_price_id = 'price_REPLACE_WITH_PREMIUM_MONTHLY_PRICE_ID';
}
if (empty($premium_yearly_price_id)) {
    $premium_yearly_price_id = 'price_REPLACE_WITH_PREMIUM_YEARLY_PRICE_ID';
}

// Define constants
define('STRIPE_PUBLISHABLE_KEY', $publishable_key);
define('STRIPE_SECRET_KEY', $secret_key);
define('STRIPE_WEBHOOK_SECRET', $webhook_secret);

// Site URL
$site_url = getenv('SITE_URL') ?: 'https://learn.disinfoconsulting.eu/tool_dev_area';
define('SITE_URL', rtrim($site_url, '/'));

// Stripe Configuration
define('STRIPE_CURRENCY', 'eur');
define('STRIPE_SUCCESS_URL', SITE_URL . '/subscription_success.php');
define('STRIPE_CANCEL_URL', SITE_URL . '/pricing.php');

// Product Price IDs
define('STRIPE_PREMIUM_MONTHLY_PRICE_ID', $premium_monthly_price_id);
define('STRIPE_PREMIUM_YEARLY_PRICE_ID', $premium_yearly_price_id);

// Validate configuration
function validateStripeConfig() {
    $errors = [];

    if (strpos(STRIPE_PUBLISHABLE_KEY, 'REPLACE') !== false) {
        $errors[] = 'STRIPE_PUBLISHABLE_KEY not configured';
    }
    if (strpos(STRIPE_SECRET_KEY, 'REPLACE') !== false) {
        $errors[] = 'STRIPE_SECRET_KEY not configured';
    }
    if (strpos(STRIPE_WEBHOOK_SECRET, 'REPLACE') !== false) {
        $errors[] = 'STRIPE_WEBHOOK_SECRET not configured';
    }
    if (strpos(STRIPE_PREMIUM_MONTHLY_PRICE_ID, 'REPLACE') !== false) {
        $errors[] = 'STRIPE_PREMIUM_MONTHLY_PRICE_ID not configured';
    }
    if (strpos(STRIPE_PREMIUM_YEARLY_PRICE_ID, 'REPLACE') !== false) {
        $errors[] = 'STRIPE_PREMIUM_YEARLY_PRICE_ID not configured';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

// Check if configuration is valid
$config_check = validateStripeConfig();
if (!$config_check['valid']) {
    // Log configuration errors
    error_log('Stripe Configuration Errors: ' . implode(', ', $config_check['errors']));

    // In development, show helpful error
    if (STRIPE_ENVIRONMENT === 'test') {
        // Don't throw error in test mode, just log it
        if (php_sapi_name() !== 'cli') {
            // Only show warning if not CLI
            // error_log('WARNING: Stripe not fully configured. Please update .env file.');
        }
    }
}

/**
 * HOW TO SET UP:
 *
 * Option 1: Using .env file (RECOMMENDED)
 * ========================================
 * 1. Copy .env.example to .env
 * 2. Edit .env and add your Stripe keys
 * 3. Make sure .env is in .gitignore
 * 4. Set file permissions: chmod 600 .env
 *
 * Option 2: Using server environment variables
 * ============================================
 * Set these in your server configuration (Apache, Nginx, or hosting panel):
 * - STRIPE_ENVIRONMENT=test
 * - STRIPE_TEST_PUBLISHABLE_KEY=pk_test_...
 * - STRIPE_TEST_SECRET_KEY=sk_test_...
 * - STRIPE_TEST_WEBHOOK_SECRET=whsec_...
 * - STRIPE_TEST_PREMIUM_MONTHLY_PRICE_ID=price_...
 * - STRIPE_TEST_PREMIUM_YEARLY_PRICE_ID=price_...
 * - SITE_URL=https://yourdomain.com/path
 *
 * Option 3: Hardcoded (NOT RECOMMENDED for production)
 * ====================================================
 * Edit the fallback values above (lines with 'REPLACE_WITH_...')
 * This is only acceptable for development/testing
 */

?>
