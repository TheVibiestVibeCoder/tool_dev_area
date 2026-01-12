<?php
/**
 * Lightweight Stripe API Client
 *
 * Uses curl to communicate directly with Stripe API
 * No external dependencies required!
 */

class StripeApiClient {

    private $api_key;
    private $api_base = 'https://api.stripe.com';
    private $api_version = '2024-12-18.acacia';

    public function __construct($api_key) {
        $this->api_key = $api_key;
    }

    /**
     * Make API request to Stripe
     */
    private function request($method, $path, $params = []) {
        $url = $this->api_base . $path;

        $ch = curl_init();

        // Set auth header
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Stripe-Version: ' . $this->api_version,
            'Content-Type: application/x-www-form-urlencoded'
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Verify SSL certificate and hostname
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 second connection timeout

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_URL, $url);
        } elseif ($method === 'GET') {
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);

        curl_close($ch);

        if ($curl_error) {
            throw new Exception('Stripe API connection error: ' . $curl_error);
        }

        $decoded = json_decode($response, true);

        if ($http_code >= 400) {
            $error_message = $decoded['error']['message'] ?? 'Unknown error';
            throw new Exception('Stripe API error: ' . $error_message);
        }

        return $decoded;
    }

    /**
     * Create Checkout Session
     */
    public function createCheckoutSession($params) {
        // Flatten nested arrays for Stripe API format
        $formatted_params = [];

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $sub_key => $sub_value) {
                    if (is_array($sub_value)) {
                        foreach ($sub_value as $deep_key => $deep_value) {
                            $formatted_params["{$key}[{$sub_key}][{$deep_key}]"] = $deep_value;
                        }
                    } else {
                        $formatted_params["{$key}[{$sub_key}]"] = $sub_value;
                    }
                }
            } else {
                $formatted_params[$key] = $value;
            }
        }

        return $this->request('POST', '/v1/checkout/sessions', $formatted_params);
    }

    /**
     * Retrieve Subscription
     */
    public function retrieveSubscription($subscription_id) {
        return $this->request('GET', '/v1/subscriptions/' . $subscription_id);
    }

    /**
     * Update Subscription
     */
    public function updateSubscription($subscription_id, $params) {
        return $this->request('POST', '/v1/subscriptions/' . $subscription_id, $params);
    }

    /**
     * Cancel Subscription
     */
    public function cancelSubscription($subscription_id) {
        return $this->request('POST', '/v1/subscriptions/' . $subscription_id, [
            'cancel_at_period_end' => 'true'
        ]);
    }

    /**
     * Reactivate Subscription
     */
    public function reactivateSubscription($subscription_id) {
        return $this->request('POST', '/v1/subscriptions/' . $subscription_id, [
            'cancel_at_period_end' => 'false'
        ]);
    }

    /**
     * Create Billing Portal Session
     */
    public function createBillingPortalSession($customer_id, $return_url) {
        return $this->request('POST', '/v1/billing_portal/sessions', [
            'customer' => $customer_id,
            'return_url' => $return_url
        ]);
    }

    /**
     * Verify Webhook Signature
     */
    public static function verifyWebhookSignature($payload, $sig_header, $webhook_secret) {
        $tolerance = 300; // 5 minutes

        // Parse signature header
        $sig_parts = [];
        foreach (explode(',', $sig_header) as $part) {
            list($key, $value) = explode('=', $part, 2);
            $sig_parts[trim($key)] = trim($value);
        }

        if (!isset($sig_parts['t']) || !isset($sig_parts['v1'])) {
            throw new Exception('Invalid signature format');
        }

        $timestamp = $sig_parts['t'];
        $signature = $sig_parts['v1'];

        // Check timestamp
        if (abs(time() - $timestamp) > $tolerance) {
            throw new Exception('Webhook timestamp too old');
        }

        // Compute expected signature
        $signed_payload = $timestamp . '.' . $payload;
        $expected_signature = hash_hmac('sha256', $signed_payload, $webhook_secret);

        // Compare signatures
        if (!hash_equals($expected_signature, $signature)) {
            throw new Exception('Signature verification failed');
        }

        return true;
    }
}

?>
