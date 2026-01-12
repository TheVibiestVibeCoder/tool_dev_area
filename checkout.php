<?php
/**
 * Stripe Checkout Flow
 * Handles subscription checkout process
 */

require_once 'user_auth.php';
require_once 'subscription_manager.php';

// Must be logged in
requireAuth();

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'];

// Get plan and period from URL
$plan_id = $_GET['plan'] ?? 'premium';
$billing_period = $_GET['period'] ?? 'monthly';

// Validate inputs
if (!in_array($plan_id, ['premium', 'enterprise'])) {
    die('Invalid plan selected.');
}

if (!in_array($billing_period, ['monthly', 'yearly'])) {
    die('Invalid billing period selected.');
}

$sub_manager = new SubscriptionManager();
$plan = $sub_manager->getPlan($plan_id);

if (!$plan) {
    die('Plan not found.');
}

// Enterprise requires contact
if ($plan['contact_for_pricing']) {
    header('Location: pricing.php');
    exit;
}

// Check if already on this plan
$current_subscription = $sub_manager->getUserSubscription($user_id);
if ($current_subscription['plan_id'] === $plan_id) {
    header('Location: subscription_manage.php?error=already_subscribed');
    exit;
}

// Create Stripe Checkout Session
$result = $sub_manager->createCheckoutSession($user_id, $user_email, $plan_id, $billing_period);

if (isset($result['error'])) {
    $error_message = htmlspecialchars($result['error']);
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Checkout Error</title>
        <style>
            body {
                font-family: 'Roboto', sans-serif;
                background: #f4f4f4;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .error-box {
                background: white;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                max-width: 500px;
                text-align: center;
            }
            .error-box h2 {
                color: #cf2e2e;
                margin-bottom: 1rem;
            }
            .error-box a {
                display: inline-block;
                margin-top: 1rem;
                padding: 0.75rem 1.5rem;
                background: #00658b;
                color: white;
                text-decoration: none;
                border-radius: 4px;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h2>Checkout Error</h2>
            <p><?php echo $error_message; ?></p>
            <a href="pricing.php">Back to Pricing</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Redirect to Stripe Checkout
if (isset($result['checkout_url'])) {
    header('Location: ' . $result['checkout_url']);
    exit;
}

// Fallback error
die('Unexpected error occurred. Please try again.');
?>
