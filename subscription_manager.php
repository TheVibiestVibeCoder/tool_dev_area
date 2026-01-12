<?php
/**
 * Subscription Management System
 *
 * Handles user subscriptions, plan limits, and Stripe integration
 */

require_once 'file_handling_robust.php';

// Try to load secure config first, fallback to regular config
if (file_exists('stripe_config_secure.php')) {
    require_once 'stripe_config_secure.php';
} elseif (file_exists('stripe_config.php')) {
    require_once 'stripe_config.php';
} else {
    // Define minimal constants to prevent errors
    if (!defined('STRIPE_PREMIUM_MONTHLY_PRICE_ID')) {
        define('STRIPE_PREMIUM_MONTHLY_PRICE_ID', 'price_NOT_CONFIGURED');
    }
    if (!defined('STRIPE_PREMIUM_YEARLY_PRICE_ID')) {
        define('STRIPE_PREMIUM_YEARLY_PRICE_ID', 'price_NOT_CONFIGURED');
    }
    error_log('WARNING: Stripe configuration file not found');
}

class SubscriptionManager {

    private $pricing_config;
    private $users_file = 'users.json';
    private $stripe_available = false;

    public function __construct() {
        $this->loadPricingConfig();
        $this->checkStripeAvailability();
    }

    /**
     * Check if Stripe SDK is available
     */
    private function checkStripeAvailability() {
        // Check if Stripe SDK is installed
        if (file_exists(__DIR__ . '/stripe-php/init.php')) {
            $this->stripe_available = true;
        } elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
            // Check for Composer installation
            $this->stripe_available = true;
        } else {
            error_log('WARNING: Stripe PHP SDK not found. Please run install_stripe.sh or use Composer.');
            $this->stripe_available = false;
        }
    }

    /**
     * Load pricing configuration
     */
    private function loadPricingConfig() {
        $config = safeReadJson('pricing_config.json');
        if ($config === false) {
            die('Error: Could not load pricing configuration');
        }

        // Override price IDs from stripe_config if available
        if (defined('STRIPE_PREMIUM_MONTHLY_PRICE_ID') && defined('STRIPE_PREMIUM_YEARLY_PRICE_ID')) {
            if (isset($config['plans']['premium'])) {
                $config['plans']['premium']['stripe_price_id_monthly'] = STRIPE_PREMIUM_MONTHLY_PRICE_ID;
                $config['plans']['premium']['stripe_price_id_yearly'] = STRIPE_PREMIUM_YEARLY_PRICE_ID;
            }
        }

        $this->pricing_config = $config;
    }

    /**
     * Get all available plans
     */
    public function getPlans() {
        return $this->pricing_config['plans'];
    }

    /**
     * Get specific plan details
     */
    public function getPlan($plan_id) {
        return $this->pricing_config['plans'][$plan_id] ?? null;
    }

    /**
     * Get user's current subscription
     */
    public function getUserSubscription($user_id) {
        $users_data = safeReadJson($this->users_file);
        if (!$users_data || !isset($users_data['users'])) {
            return null;
        }

        foreach ($users_data['users'] as $user) {
            if ($user['id'] === $user_id) {
                // Return subscription info or default to free
                return $user['subscription'] ?? $this->getDefaultSubscription();
            }
        }

        return null;
    }

    /**
     * Get default subscription (Free plan)
     */
    private function getDefaultSubscription() {
        return [
            'plan_id' => 'free',
            'status' => 'active',
            'stripe_customer_id' => null,
            'stripe_subscription_id' => null,
            'current_period_start' => null,
            'current_period_end' => null,
            'cancel_at_period_end' => false,
            'created_at' => time(),
            'updated_at' => time()
        ];
    }

    /**
     * Update user subscription
     */
    public function updateUserSubscription($user_id, $subscription_data) {
        $result = atomicUpdate($this->users_file, function($data) use ($user_id, $subscription_data) {
            if (!isset($data['users'])) {
                return false;
            }

            $updated = false;
            foreach ($data['users'] as &$user) {
                if ($user['id'] === $user_id) {
                    $user['subscription'] = array_merge(
                        $user['subscription'] ?? [],
                        $subscription_data
                    );
                    $user['subscription']['updated_at'] = time();
                    $updated = true;
                    break;
                }
            }

            return $updated ? $data : false;
        });

        return $result !== false;
    }

    /**
     * Check if user can add more columns
     */
    public function canAddColumn($user_id, $current_column_count) {
        $subscription = $this->getUserSubscription($user_id);
        $plan = $this->getPlan($subscription['plan_id']);

        $max_columns = $plan['features']['max_columns'];

        // -1 means unlimited
        if ($max_columns === -1) {
            return true;
        }

        return $current_column_count < $max_columns;
    }

    /**
     * Check if user can add more participants
     */
    public function canAddParticipant($user_id, $current_participant_count) {
        $subscription = $this->getUserSubscription($user_id);
        $plan = $this->getPlan($subscription['plan_id']);

        $max_participants = $plan['features']['max_participants'];

        // -1 means unlimited
        if ($max_participants === -1) {
            return true;
        }

        return $current_participant_count < $max_participants;
    }

    /**
     * Get plan limits for user
     */
    public function getPlanLimits($user_id) {
        $subscription = $this->getUserSubscription($user_id);
        $plan = $this->getPlan($subscription['plan_id']);

        return [
            'plan_name' => $plan['name'],
            'max_participants' => $plan['features']['max_participants'],
            'max_columns' => $plan['features']['max_columns'],
            'max_workshops' => $plan['features']['max_workshops'],
            'features' => $plan['features']
        ];
    }

    /**
     * Check if user has access to feature
     */
    public function hasFeature($user_id, $feature_name) {
        $subscription = $this->getUserSubscription($user_id);
        $plan = $this->getPlan($subscription['plan_id']);

        return $plan['features'][$feature_name] ?? false;
    }

    /**
     * Get current usage for user
     */
    public function getCurrentUsage($user_id) {
        // Get user's config to count columns
        $config_file = getUserSpecificFile('config.json', $user_id);
        $config = loadConfig($config_file);
        $column_count = isset($config['categories']) ? count($config['categories']) : 0;

        // Get entries to estimate participants (unique submissions in last 30 days)
        $data_file = getUserSpecificFile('daten.json', $user_id);
        $entries = safeReadJson($data_file) ?? [];

        // Count unique IPs or estimate based on entries
        // For MVP, we'll estimate: 1 participant ≈ 5 entries
        $participant_estimate = min(50, ceil(count($entries) / 5));

        return [
            'columns' => $column_count,
            'participants_estimate' => $participant_estimate,
            'total_entries' => count($entries)
        ];
    }

    /**
     * Check if usage exceeds plan limits
     */
    public function isOverLimit($user_id) {
        $usage = $this->getCurrentUsage($user_id);
        $limits = $this->getPlanLimits($user_id);

        $over_limits = [];

        // Check columns
        if ($limits['max_columns'] !== -1 && $usage['columns'] > $limits['max_columns']) {
            $over_limits[] = 'columns';
        }

        // Check participants
        if ($limits['max_participants'] !== -1 && $usage['participants_estimate'] > $limits['max_participants']) {
            $over_limits[] = 'participants';
        }

        return [
            'is_over' => count($over_limits) > 0,
            'over_limits' => $over_limits,
            'usage' => $usage,
            'limits' => $limits
        ];
    }

    /**
     * Create Stripe Checkout Session
     */
    public function createCheckoutSession($user_id, $user_email, $plan_id, $billing_period = 'monthly') {
        // Check if Stripe is available
        if (!$this->stripe_available) {
            return ['error' => 'Payment system not available. Please contact administrator. (Stripe SDK not installed)'];
        }

        $plan = $this->getPlan($plan_id);

        if (!$plan || $plan['id'] === 'free') {
            return ['error' => 'Invalid plan selected'];
        }

        if ($plan['contact_for_pricing']) {
            return ['error' => 'Please contact us for enterprise pricing'];
        }

        // Get the correct Stripe price ID
        $price_id = $billing_period === 'yearly'
            ? $plan['stripe_price_id_yearly']
            : $plan['stripe_price_id_monthly'];

        if (!$price_id || strpos($price_id, 'REPLACE') !== false || strpos($price_id, 'NOT_CONFIGURED') !== false) {
            return ['error' => 'Payment system not configured. Please contact administrator.'];
        }

        // Initialize Stripe
        if (file_exists(__DIR__ . '/stripe-php/init.php')) {
            require_once __DIR__ . '/stripe-php/init.php';
        } elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        } else {
            return ['error' => 'Payment system unavailable. Stripe SDK not found.'];
        }

        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        try {
            $session = \Stripe\Checkout\Session::create([
                'customer_email' => $user_email,
                'client_reference_id' => $user_id,
                'payment_method_types' => ['card', 'sepa_debit'],
                'line_items' => [[
                    'price' => $price_id,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => STRIPE_SUCCESS_URL . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => STRIPE_CANCEL_URL,
                'metadata' => [
                    'user_id' => $user_id,
                    'plan_id' => $plan_id,
                    'billing_period' => $billing_period
                ]
            ]);

            return [
                'success' => true,
                'session_id' => $session->id,
                'checkout_url' => $session->url
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe API Error: ' . $e->getMessage());
            return ['error' => 'Payment processing error. Please try again.'];
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription($user_id) {
        $subscription = $this->getUserSubscription($user_id);

        if (!$subscription || !$subscription['stripe_subscription_id']) {
            return ['error' => 'No active subscription found'];
        }

        require_once 'stripe-php/init.php';
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        try {
            // Cancel at period end (don't terminate immediately)
            $stripe_subscription = \Stripe\Subscription::update(
                $subscription['stripe_subscription_id'],
                ['cancel_at_period_end' => true]
            );

            // Update local database
            $this->updateUserSubscription($user_id, [
                'cancel_at_period_end' => true,
                'cancels_at' => $stripe_subscription->current_period_end
            ]);

            return [
                'success' => true,
                'message' => 'Subscription will cancel at the end of the current billing period'
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe API Error: ' . $e->getMessage());
            return ['error' => 'Could not cancel subscription. Please try again.'];
        }
    }

    /**
     * Reactivate cancelled subscription
     */
    public function reactivateSubscription($user_id) {
        $subscription = $this->getUserSubscription($user_id);

        if (!$subscription || !$subscription['stripe_subscription_id']) {
            return ['error' => 'No subscription found'];
        }

        if (!$subscription['cancel_at_period_end']) {
            return ['error' => 'Subscription is not scheduled for cancellation'];
        }

        require_once 'stripe-php/init.php';
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        try {
            // Remove cancellation
            \Stripe\Subscription::update(
                $subscription['stripe_subscription_id'],
                ['cancel_at_period_end' => false]
            );

            // Update local database
            $this->updateUserSubscription($user_id, [
                'cancel_at_period_end' => false,
                'cancels_at' => null
            ]);

            return [
                'success' => true,
                'message' => 'Subscription reactivated successfully'
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe API Error: ' . $e->getMessage());
            return ['error' => 'Could not reactivate subscription. Please try again.'];
        }
    }

    /**
     * Get subscription portal URL (for managing payment methods, etc.)
     */
    public function getPortalUrl($user_id, $return_url) {
        $subscription = $this->getUserSubscription($user_id);

        if (!$subscription || !$subscription['stripe_customer_id']) {
            return ['error' => 'No Stripe customer found'];
        }

        require_once 'stripe-php/init.php';
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        try {
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $subscription['stripe_customer_id'],
                'return_url' => $return_url,
            ]);

            return [
                'success' => true,
                'portal_url' => $session->url
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe API Error: ' . $e->getMessage());
            return ['error' => 'Could not create portal session. Please try again.'];
        }
    }
}

?>
