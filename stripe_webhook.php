<?php
/**
 * Stripe Webhook Handler
 *
 * Handles Stripe webhook events for subscription management
 *
 * Events handled:
 * - customer.subscription.created
 * - customer.subscription.updated
 * - customer.subscription.deleted
 * - invoice.payment_succeeded
 * - invoice.payment_failed
 */

require_once 'subscription_manager.php';
require_once 'stripe_config.php';
require_once 'file_handling_robust.php';

// Get the webhook payload
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Log webhook for debugging (optional, disable in production)
$log_file = 'stripe_webhook.log';
$log_entry = date('Y-m-d H:i:s') . " - Received webhook\n";
file_put_contents($log_file, $log_entry, FILE_APPEND);

try {
    // Initialize Stripe
    require_once 'stripe-php/init.php';
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    // Verify webhook signature
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        STRIPE_WEBHOOK_SECRET
    );

    // Log event type
    $log_entry = date('Y-m-d H:i:s') . " - Event type: {$event->type}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);

    // Handle the event
    switch ($event->type) {
        case 'customer.subscription.created':
        case 'customer.subscription.updated':
            handleSubscriptionUpdate($event->data->object);
            break;

        case 'customer.subscription.deleted':
            handleSubscriptionCancellation($event->data->object);
            break;

        case 'invoice.payment_succeeded':
            handlePaymentSucceeded($event->data->object);
            break;

        case 'invoice.payment_failed':
            handlePaymentFailed($event->data->object);
            break;

        case 'checkout.session.completed':
            handleCheckoutCompleted($event->data->object);
            break;

        default:
            // Unhandled event type
            $log_entry = date('Y-m-d H:i:s') . " - Unhandled event type: {$event->type}\n";
            file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    // Return 200 OK
    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Invalid signature
    $log_entry = date('Y-m-d H:i:s') . " - Signature verification failed: {$e->getMessage()}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);

} catch (Exception $e) {
    // Other errors
    $log_entry = date('Y-m-d H:i:s') . " - Error: {$e->getMessage()}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Webhook handler error']);
}

/**
 * Handle subscription creation/update
 */
function handleSubscriptionUpdate($subscription) {
    $user_id = $subscription->metadata->user_id ?? null;
    $plan_id = $subscription->metadata->plan_id ?? 'premium';

    if (!$user_id) {
        error_log('Webhook: No user_id in subscription metadata');
        return;
    }

    // Determine subscription status
    $status = $subscription->status; // active, past_due, canceled, etc.

    // Update user subscription
    $sub_manager = new SubscriptionManager();
    $sub_manager->updateUserSubscription($user_id, [
        'plan_id' => $status === 'active' ? $plan_id : 'free',
        'status' => $status,
        'stripe_customer_id' => $subscription->customer,
        'stripe_subscription_id' => $subscription->id,
        'current_period_start' => $subscription->current_period_start,
        'current_period_end' => $subscription->current_period_end,
        'cancel_at_period_end' => $subscription->cancel_at_period_end ?? false
    ]);

    $log_entry = date('Y-m-d H:i:s') . " - Updated subscription for user {$user_id} to {$plan_id} ({$status})\n";
    file_put_contents('stripe_webhook.log', $log_entry, FILE_APPEND);
}

/**
 * Handle subscription cancellation
 */
function handleSubscriptionCancellation($subscription) {
    $user_id = $subscription->metadata->user_id ?? null;

    if (!$user_id) {
        error_log('Webhook: No user_id in subscription metadata');
        return;
    }

    // Downgrade to free plan
    $sub_manager = new SubscriptionManager();
    $sub_manager->updateUserSubscription($user_id, [
        'plan_id' => 'free',
        'status' => 'canceled',
        'stripe_subscription_id' => null,
        'current_period_start' => null,
        'current_period_end' => null,
        'cancel_at_period_end' => false
    ]);

    $log_entry = date('Y-m-d H:i:s') . " - Canceled subscription for user {$user_id}\n";
    file_put_contents('stripe_webhook.log', $log_entry, FILE_APPEND);
}

/**
 * Handle successful payment
 */
function handlePaymentSucceeded($invoice) {
    $customer_id = $invoice->customer;
    $subscription_id = $invoice->subscription;

    // Log successful payment
    $log_entry = date('Y-m-d H:i:s') . " - Payment succeeded for subscription {$subscription_id}\n";
    file_put_contents('stripe_webhook.log', $log_entry, FILE_APPEND);

    // You could send a receipt email here
}

/**
 * Handle failed payment
 */
function handlePaymentFailed($invoice) {
    $customer_id = $invoice->customer;
    $subscription_id = $invoice->subscription;

    // Log failed payment
    $log_entry = date('Y-m-d H:i:s') . " - Payment FAILED for subscription {$subscription_id}\n";
    file_put_contents('stripe_webhook.log', $log_entry, FILE_APPEND);

    // You could send a payment failure notification email here
}

/**
 * Handle checkout session completed
 */
function handleCheckoutCompleted($session) {
    $user_id = $session->client_reference_id;
    $customer_id = $session->customer;

    if (!$user_id) {
        error_log('Webhook: No user_id in checkout session');
        return;
    }

    // Get subscription details
    require_once 'stripe-php/init.php';
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    try {
        $subscription = \Stripe\Subscription::retrieve($session->subscription);

        // Update user subscription
        $sub_manager = new SubscriptionManager();
        $plan_id = $subscription->metadata->plan_id ?? 'premium';

        $sub_manager->updateUserSubscription($user_id, [
            'plan_id' => $plan_id,
            'status' => $subscription->status,
            'stripe_customer_id' => $customer_id,
            'stripe_subscription_id' => $subscription->id,
            'current_period_start' => $subscription->current_period_start,
            'current_period_end' => $subscription->current_period_end,
            'cancel_at_period_end' => false
        ]);

        $log_entry = date('Y-m-d H:i:s') . " - Checkout completed for user {$user_id}\n";
        file_put_contents('stripe_webhook.log', $log_entry, FILE_APPEND);

    } catch (Exception $e) {
        error_log('Webhook: Error retrieving subscription: ' . $e->getMessage());
    }
}

?>
