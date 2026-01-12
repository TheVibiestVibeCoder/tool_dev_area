<?php
/**
 * Payment System Setup Checker
 *
 * This script checks your payment system configuration and identifies issues
 * Run this from your browser: https://yourdomain.com/tool_dev_area/check_setup.php
 *
 * SECURITY: Requires authentication to access
 */

require_once 'user_auth.php';
require_once 'security_helpers.php';

// Set security headers
setSecurityHeaders();

// Require authentication - only logged in users can access
requireAuth();

// Prevent execution in production (set this to false for testing)
$allow_execution = true;

if (!$allow_execution) {
    die('Setup checker is disabled. Edit check_setup.php to enable.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment System Setup Checker</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 2rem;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #00658b;
            padding-bottom: 1rem;
        }
        .check-item {
            padding: 1rem;
            margin: 1rem 0;
            border-left: 4px solid #ccc;
            background: #f9f9f9;
        }
        .check-item.success {
            border-left-color: #00d084;
            background: #f0fdf7;
        }
        .check-item.warning {
            border-left-color: #ffa500;
            background: #fff8e6;
        }
        .check-item.error {
            border-left-color: #cf2e2e;
            background: #fff1f1;
        }
        .check-item h3 {
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .status-icon {
            font-size: 1.5rem;
        }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 1rem;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        .summary {
            background: #e7f3f8;
            border: 2px solid #00658b;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .summary h2 {
            margin-top: 0;
            color: #00658b;
        }
        .file-list {
            background: #f9f9f9;
            padding: 1rem;
            border-radius: 4px;
            margin: 0.5rem 0;
        }
        .file-list ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💳 Payment System Setup Checker</h1>

        <?php
        $checks = [];
        $errors = 0;
        $warnings = 0;
        $successes = 0;

        // Check 1: Required Files
        $required_files = [
            'stripe_config_secure.php' => 'Stripe configuration (secure)',
            'subscription_manager.php' => 'Subscription manager',
            'pricing.php' => 'Pricing page',
            'checkout.php' => 'Checkout handler',
            'subscription_manage.php' => 'Subscription management page',
            'stripe_webhook.php' => 'Stripe webhook handler',
            'pricing_config.json' => 'Pricing configuration',
            'user_auth.php' => 'User authentication',
            'file_handling_robust.php' => 'File handling library'
        ];

        $missing_files = [];
        foreach ($required_files as $file => $description) {
            if (!file_exists($file)) {
                $missing_files[] = "$file ($description)";
            }
        }

        if (empty($missing_files)) {
            $checks[] = [
                'status' => 'success',
                'title' => 'Required Files',
                'message' => 'All required files are present.'
            ];
            $successes++;
        } else {
            $checks[] = [
                'status' => 'error',
                'title' => 'Required Files',
                'message' => 'Missing files: ' . implode(', ', $missing_files)
            ];
            $errors++;
        }

        // Check 2: Stripe API Client (built-in, no dependencies needed)
        if (file_exists('stripe_api_client.php')) {
            $checks[] = [
                'status' => 'success',
                'title' => 'Stripe API Client',
                'message' => 'Lightweight Stripe API client found. No external dependencies required!'
            ];
            $successes++;
        } else {
            $checks[] = [
                'status' => 'error',
                'title' => 'Stripe API Client',
                'message' => 'stripe_api_client.php is missing!'
            ];
            $errors++;
        }

        // Check 3: Configuration File
        if (file_exists('.env')) {
            $checks[] = [
                'status' => 'success',
                'title' => '.env Configuration',
                'message' => '.env file exists. Make sure it contains your Stripe keys.'
            ];
            $successes++;
        } elseif (file_exists('stripe_config.php')) {
            $checks[] = [
                'status' => 'warning',
                'title' => 'Configuration',
                'message' => 'Using stripe_config.php (old method). Consider migrating to .env for better security.'
            ];
            $warnings++;
        } else {
            $checks[] = [
                'status' => 'warning',
                'title' => 'Configuration',
                'message' => 'No .env file found. Copy .env.example to .env and add your Stripe keys.'
            ];
            $warnings++;
        }

        // Check 4: Load configuration
        if (file_exists('stripe_config_secure.php')) {
            require_once 'stripe_config_secure.php';

            // Check API keys
            if (defined('STRIPE_PUBLISHABLE_KEY') && strpos(STRIPE_PUBLISHABLE_KEY, 'REPLACE') === false) {
                $checks[] = [
                    'status' => 'success',
                    'title' => 'Stripe Publishable Key',
                    'message' => 'Publishable key is configured: ' . substr(STRIPE_PUBLISHABLE_KEY, 0, 20) . '...'
                ];
                $successes++;
            } else {
                $checks[] = [
                    'status' => 'error',
                    'title' => 'Stripe Publishable Key',
                    'message' => 'Publishable key not configured or contains placeholder.'
                ];
                $errors++;
            }

            if (defined('STRIPE_SECRET_KEY') && strpos(STRIPE_SECRET_KEY, 'REPLACE') === false) {
                $checks[] = [
                    'status' => 'success',
                    'title' => 'Stripe Secret Key',
                    'message' => 'Secret key is configured: ' . substr(STRIPE_SECRET_KEY, 0, 10) . '...'
                ];
                $successes++;
            } else {
                $checks[] = [
                    'status' => 'error',
                    'title' => 'Stripe Secret Key',
                    'message' => 'Secret key not configured or contains placeholder.'
                ];
                $errors++;
            }

            // Check webhook secret
            if (defined('STRIPE_WEBHOOK_SECRET') && strpos(STRIPE_WEBHOOK_SECRET, 'REPLACE') === false) {
                $checks[] = [
                    'status' => 'success',
                    'title' => 'Webhook Secret',
                    'message' => 'Webhook secret is configured.'
                ];
                $successes++;
            } else {
                $checks[] = [
                    'status' => 'warning',
                    'title' => 'Webhook Secret',
                    'message' => 'Webhook secret not configured. Required for automatic subscription updates.'
                ];
                $warnings++;
            }

            // Check price IDs
            if (defined('STRIPE_PREMIUM_MONTHLY_PRICE_ID') && strpos(STRIPE_PREMIUM_MONTHLY_PRICE_ID, 'REPLACE') === false && strpos(STRIPE_PREMIUM_MONTHLY_PRICE_ID, 'NOT_CONFIGURED') === false) {
                $checks[] = [
                    'status' => 'success',
                    'title' => 'Premium Monthly Price ID',
                    'message' => 'Price ID configured: ' . STRIPE_PREMIUM_MONTHLY_PRICE_ID
                ];
                $successes++;
            } else {
                $checks[] = [
                    'status' => 'error',
                    'title' => 'Premium Monthly Price ID',
                    'message' => 'Price ID not configured. Create product in Stripe Dashboard.'
                ];
                $errors++;
            }

            if (defined('STRIPE_PREMIUM_YEARLY_PRICE_ID') && strpos(STRIPE_PREMIUM_YEARLY_PRICE_ID, 'REPLACE') === false && strpos(STRIPE_PREMIUM_YEARLY_PRICE_ID, 'NOT_CONFIGURED') === false) {
                $checks[] = [
                    'status' => 'success',
                    'title' => 'Premium Yearly Price ID',
                    'message' => 'Price ID configured: ' . STRIPE_PREMIUM_YEARLY_PRICE_ID
                ];
                $successes++;
            } else {
                $checks[] = [
                    'status' => 'error',
                    'title' => 'Premium Yearly Price ID',
                    'message' => 'Price ID not configured. Create product in Stripe Dashboard.'
                ];
                $errors++;
            }

            // Check environment
            $checks[] = [
                'status' => 'success',
                'title' => 'Stripe Environment',
                'message' => 'Running in <strong>' . STRIPE_ENVIRONMENT . '</strong> mode.'
            ];
        }

        // Check 5: File Permissions
        $writable_files = ['users.json', 'pricing_config.json'];
        $permission_issues = [];
        foreach ($writable_files as $file) {
            if (file_exists($file) && !is_writable($file)) {
                $permission_issues[] = $file;
            }
        }

        if (empty($permission_issues)) {
            $checks[] = [
                'status' => 'success',
                'title' => 'File Permissions',
                'message' => 'All required files are writable.'
            ];
            $successes++;
        } else {
            $checks[] = [
                'status' => 'warning',
                'title' => 'File Permissions',
                'message' => 'These files may not be writable: ' . implode(', ', $permission_issues)
            ];
            $warnings++;
        }

        // Check 6: .gitignore
        if (file_exists('.gitignore')) {
            $gitignore_content = file_get_contents('.gitignore');
            if (strpos($gitignore_content, '.env') !== false) {
                $checks[] = [
                    'status' => 'success',
                    'title' => 'Git Security',
                    'message' => '.gitignore properly configured to exclude sensitive files.'
                ];
                $successes++;
            } else {
                $checks[] = [
                    'status' => 'warning',
                    'title' => 'Git Security',
                    'message' => '.gitignore exists but may not exclude .env file.'
                ];
                $warnings++;
            }
        } else {
            $checks[] = [
                'status' => 'warning',
                'title' => 'Git Security',
                'message' => 'No .gitignore file found. Sensitive files might be committed to Git.'
            ];
            $warnings++;
        }

        // Summary
        $total_checks = count($checks);
        ?>

        <div class="summary">
            <h2>Summary</h2>
            <p><strong><?php echo $total_checks; ?></strong> checks completed:</p>
            <ul>
                <li style="color: #00d084;">✓ <strong><?php echo $successes; ?></strong> passed</li>
                <li style="color: #ffa500;">⚠ <strong><?php echo $warnings; ?></strong> warnings</li>
                <li style="color: #cf2e2e;">✗ <strong><?php echo $errors; ?></strong> errors</li>
            </ul>

            <?php if ($errors === 0 && $warnings === 0): ?>
                <p style="color: #00d084; font-weight: bold;">🎉 Your payment system is fully configured and ready to use!</p>
            <?php elseif ($errors === 0): ?>
                <p style="color: #ffa500; font-weight: bold;">⚠️ Your payment system is functional but has some warnings to address.</p>
            <?php else: ?>
                <p style="color: #cf2e2e; font-weight: bold;">❌ Your payment system has errors that need to be fixed before it can work.</p>
            <?php endif; ?>
        </div>

        <h2>Detailed Checks</h2>

        <?php foreach ($checks as $check): ?>
            <div class="check-item <?php echo $check['status']; ?>">
                <h3>
                    <span class="status-icon">
                        <?php
                        echo $check['status'] === 'success' ? '✓' : ($check['status'] === 'warning' ? '⚠' : '✗');
                        ?>
                    </span>
                    <?php echo htmlspecialchars($check['title']); ?>
                </h3>
                <p><?php echo $check['message']; ?></p>
            </div>
        <?php endforeach; ?>

        <?php if ($errors > 0): ?>
            <h2>🔧 How to Fix</h2>

            <div class="file-list">
                <h3>Step 1: Configure Stripe Keys</h3>
                <p>Copy the example file and edit it:</p>
                <div class="code-block">cp .env.example .env<br>nano .env</div>
                <p>Get your keys from: <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard → API Keys</a></p>
            </div>

            <div class="file-list">
                <h3>Step 2: Create Stripe Products</h3>
                <ol>
                    <li>Go to <a href="https://dashboard.stripe.com/products" target="_blank">Stripe Dashboard → Products</a></li>
                    <li>Create "Premium Monthly" - €19.99/month recurring</li>
                    <li>Create "Premium Yearly" - €203.89/year recurring</li>
                    <li>Copy the Price IDs (start with "price_...") to your .env file</li>
                </ol>
            </div>

            <div class="file-list">
                <h3>Step 3: Setup Webhook (Optional but Recommended)</h3>
                <ol>
                    <li>Go to <a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Dashboard → Webhooks</a></li>
                    <li>Add endpoint: <code><?php echo (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/stripe_webhook.php'; ?></code></li>
                    <li>Select events: customer.subscription.*, invoice.payment_*, checkout.session.completed</li>
                    <li>Copy webhook secret to .env file</li>
                </ol>
            </div>
        <?php endif; ?>

        <h2>📚 Quick Reference</h2>
        <div class="file-list">
            <p><strong>Important Files:</strong></p>
            <ul>
                <li><code>.env</code> - Your Stripe configuration (create from .env.example)</li>
                <li><code>pricing.php</code> - Public pricing page</li>
                <li><code>subscription_manage.php</code> - User subscription dashboard</li>
                <li><code>PAYMENT_SETUP_GUIDE.md</code> - Complete setup documentation</li>
            </ul>

            <p><strong>Useful Links:</strong></p>
            <ul>
                <li><a href="pricing.php">View Pricing Page</a></li>
                <li><a href="https://dashboard.stripe.com" target="_blank">Stripe Dashboard</a></li>
                <li><a href="PAYMENT_SETUP_GUIDE.md" target="_blank">Setup Guide</a></li>
            </ul>
        </div>

        <p style="text-align: center; margin-top: 2rem; color: #666; font-size: 0.9rem;">
            🔒 This file should be deleted or disabled in production!<br>
            Edit check_setup.php and set $allow_execution = false;
        </p>
    </div>
</body>
</html>
