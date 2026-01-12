<?php
/**
 * Manual Account Upgrade Script
 * Use this to manually upgrade a user who paid but didn't get upgraded due to webhook issues
 */

require_once 'subscription_manager.php';
require_once 'user_auth.php';

// Configuration - UPDATE THESE VALUES
$user_email = 'schiwngi81@gmail.com';  // The email that made the payment
$plan_id = 'premium';                   // The plan they paid for
$stripe_customer_id = '';               // Get this from Stripe dashboard (starts with cus_)
$stripe_subscription_id = '';           // Get this from Stripe dashboard (starts with sub_)

// Find user by email
function findUserByEmail($email) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);

    foreach ($users_data['users'] as $user) {
        if (strtolower($user['email']) === strtolower($email)) {
            return $user;
        }
    }

    return null;
}

// Upgrade user
echo "<h2>Manual Account Upgrade</h2>\n\n";

$user = findUserByEmail($user_email);

if (!$user) {
    echo "<p style='color: red;'>ERROR: User with email '{$user_email}' not found!</p>\n";
    echo "<p>Make sure the user has registered an account first.</p>\n";
    exit;
}

echo "<p>Found user: {$user['email']} (ID: {$user['id']})</p>\n";

// Get subscription details from Stripe if provided
$sub_manager = new SubscriptionManager();

if (!empty($stripe_subscription_id)) {
    echo "<p>Fetching subscription details from Stripe...</p>\n";

    require_once 'stripe_config_secure.php';
    require_once 'stripe_api_client.php';

    try {
        $stripe_client = new StripeApiClient(STRIPE_SECRET_KEY);
        $subscription = $stripe_client->retrieveSubscription($stripe_subscription_id);

        echo "<p>✅ Found Stripe subscription: {$subscription['id']}</p>\n";
        echo "<p>Status: {$subscription['status']}</p>\n";
        echo "<p>Current period: " . date('Y-m-d', $subscription['current_period_start']) . " to " . date('Y-m-d', $subscription['current_period_end']) . "</p>\n";

        // Update user subscription
        $result = $sub_manager->updateUserSubscription($user['id'], [
            'plan_id' => $plan_id,
            'status' => $subscription['status'],
            'stripe_customer_id' => $subscription['customer'],
            'stripe_subscription_id' => $subscription['id'],
            'current_period_start' => $subscription['current_period_start'],
            'current_period_end' => $subscription['current_period_end'],
            'cancel_at_period_end' => $subscription['cancel_at_period_end'] ?? false
        ]);

        if ($result) {
            echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS! User upgraded to {$plan_id} plan!</p>\n";
            echo "<p>The user can now log in and access premium features.</p>\n";
        } else {
            echo "<p style='color: red;'>ERROR: Failed to update user subscription in database</p>\n";
        }

    } catch (Exception $e) {
        echo "<p style='color: red;'>ERROR: {$e->getMessage()}</p>\n";
    }

} else {
    echo "<p style='color: orange;'>⚠️ No Stripe subscription ID provided.</p>\n";
    echo "<p>To complete the upgrade, you need to:</p>\n";
    echo "<ol>\n";
    echo "  <li>Go to <a href='https://dashboard.stripe.com/test/subscriptions' target='_blank'>Stripe Dashboard → Subscriptions</a></li>\n";
    echo "  <li>Find the subscription for customer email: {$user_email}</li>\n";
    echo "  <li>Copy the Subscription ID (starts with 'sub_')</li>\n";
    echo "  <li>Copy the Customer ID (starts with 'cus_')</li>\n";
    echo "  <li>Update the variables at the top of this script</li>\n";
    echo "  <li>Reload this page</li>\n";
    echo "</ol>\n";
}

echo "\n<hr>\n";
echo "<p><strong>After upgrading, DELETE this file for security!</strong></p>\n";

?>
