<?php
/**
 * Subscription Success Page
 * Displayed after successful Stripe checkout
 */

require_once 'user_auth.php';
requireAuth();

// Redirect to subscription management with success message
header('Location: subscription_manage.php?success=1');
exit;
?>
