<?php
/**
 * Subscription Management Dashboard
 * Allows users to view and manage their subscription
 */

require_once 'user_auth.php';
require_once 'subscription_manager.php';

// Must be logged in
requireAuth();

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'];

$sub_manager = new SubscriptionManager();
$subscription = $sub_manager->getUserSubscription($user_id);
$plan = $sub_manager->getPlan($subscription['plan_id']);
$usage = $sub_manager->getCurrentUsage($user_id);
$limits = $sub_manager->getPlanLimits($user_id);
$limit_check = $sub_manager->isOverLimit($user_id);

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'cancel') {
        $result = $sub_manager->cancelSubscription($user_id);
        if (isset($result['success'])) {
            $message = $result['message'];
            // Reload subscription data
            $subscription = $sub_manager->getUserSubscription($user_id);
        } else {
            $error = $result['error'];
        }
    } elseif ($action === 'reactivate') {
        $result = $sub_manager->reactivateSubscription($user_id);
        if (isset($result['success'])) {
            $message = $result['message'];
            // Reload subscription data
            $subscription = $sub_manager->getUserSubscription($user_id);
        } else {
            $error = $result['error'];
        }
    } elseif ($action === 'portal') {
        $result = $sub_manager->getPortalUrl($user_id, 'https://' . $_SERVER['HTTP_HOST'] . '/subscription_manage.php');
        if (isset($result['success'])) {
            header('Location: ' . $result['portal_url']);
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// Check for URL parameters
if (isset($_GET['success'])) {
    $message = 'Subscription activated successfully! Welcome to ' . $plan['name'] . '.';
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --ip-blue: #00658b;
            --ip-dark: #32373c;
            --ip-grey-bg: #f4f4f4;
            --ip-card-bg: #ffffff;
            --ip-border: #e0e0e0;
            --accent-success: #00d084;
            --accent-danger: #cf2e2e;
            --accent-warning: #ffa500;
            --font-heading: 'Montserrat', sans-serif;
            --font-body: 'Roboto', sans-serif;
        }

        body {
            font-family: var(--font-body);
            background-color: var(--ip-grey-bg);
            color: var(--ip-dark);
            line-height: 1.6;
        }

        .navbar {
            background: var(--ip-blue);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar h1 {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .navbar a:hover {
            background: rgba(255,255,255,0.1);
        }

        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .card {
            background: var(--ip-card-bg);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .card h2 {
            font-family: var(--font-heading);
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: var(--ip-blue);
        }

        .card h3 {
            font-family: var(--font-heading);
            font-size: 1.4rem;
            margin-bottom: 1rem;
            margin-top: 1.5rem;
        }

        .subscription-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .plan-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .plan-badge.free {
            background: #e0e0e0;
            color: #666;
        }

        .plan-badge.premium {
            background: var(--ip-blue);
            color: white;
        }

        .plan-badge.enterprise {
            background: var(--accent-warning);
            color: white;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-badge.active {
            background: var(--accent-success);
            color: white;
        }

        .status-badge.canceled {
            background: var(--accent-danger);
            color: white;
        }

        .status-badge.past_due {
            background: var(--accent-warning);
            color: white;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-item {
            padding: 1rem;
            background: var(--ip-grey-bg);
            border-radius: 8px;
        }

        .info-item label {
            display: block;
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .info-item .value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--ip-dark);
        }

        .usage-bar-container {
            margin: 1rem 0;
        }

        .usage-bar-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .usage-bar {
            height: 24px;
            background: var(--ip-grey-bg);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }

        .usage-bar-fill {
            height: 100%;
            background: var(--ip-blue);
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .usage-bar-fill.warning {
            background: var(--accent-warning);
        }

        .usage-bar-fill.danger {
            background: var(--accent-danger);
        }

        .feature-list {
            list-style: none;
        }

        .feature-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--ip-border);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .check {
            color: var(--accent-success);
            font-weight: 700;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: var(--font-heading);
            text-decoration: none;
            display: inline-block;
        }

        .btn.primary {
            background: var(--ip-blue);
            color: white;
        }

        .btn.primary:hover {
            background: #004a66;
        }

        .btn.secondary {
            background: transparent;
            color: var(--ip-blue);
            border: 2px solid var(--ip-blue);
        }

        .btn.secondary:hover {
            background: var(--ip-blue);
            color: white;
        }

        .btn.danger {
            background: var(--accent-danger);
            color: white;
        }

        .btn.danger:hover {
            background: #a82424;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .subscription-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
                width: 100%;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Subscription Management</h1>
        <div>
            <a href="admin.php">Dashboard</a>
            <a href="pricing.php">View Plans</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($limit_check['is_over']): ?>
            <div class="alert warning">
                <strong>⚠ Usage Limit Exceeded</strong><br>
                You've exceeded your plan limits (<?php echo implode(', ', $limit_check['over_limits']); ?>).
                Please <a href="pricing.php" style="color: inherit; text-decoration: underline;">upgrade your plan</a> to continue using all features.
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="subscription-header">
                <div>
                    <h2>Current Plan</h2>
                    <span class="plan-badge <?php echo $subscription['plan_id']; ?>">
                        <?php echo $plan['name']; ?>
                    </span>
                    <span class="status-badge <?php echo $subscription['status']; ?>">
                        <?php echo ucfirst($subscription['status']); ?>
                    </span>
                </div>
                <?php if ($subscription['plan_id'] !== 'free'): ?>
                    <div>
                        <strong><?php echo $plan['price_monthly'] ? '€' . number_format($plan['price_monthly'], 2) : 'Custom'; ?></strong>
                        <?php if ($plan['price_monthly']): ?>
                            <span style="color: #666;"> / month</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-grid">
                <?php if ($subscription['current_period_end']): ?>
                    <div class="info-item">
                        <label>Billing Period</label>
                        <div class="value">
                            <?php echo date('M d, Y', $subscription['current_period_end']); ?>
                        </div>
                        <small>Renews on</small>
                    </div>
                <?php endif; ?>

                <?php if ($subscription['cancel_at_period_end']): ?>
                    <div class="info-item">
                        <label>Status</label>
                        <div class="value" style="color: var(--accent-danger);">
                            Canceling
                        </div>
                        <small>Ends <?php echo date('M d, Y', $subscription['current_period_end']); ?></small>
                    </div>
                <?php endif; ?>

                <div class="info-item">
                    <label>Account Email</label>
                    <div class="value" style="font-size: 1rem;">
                        <?php echo htmlspecialchars($user_email); ?>
                    </div>
                </div>
            </div>

            <h3>Usage Overview</h3>

            <div class="usage-bar-container">
                <div class="usage-bar-label">
                    <span>Columns / Categories</span>
                    <span>
                        <?php echo $usage['columns']; ?>
                        <?php if ($limits['max_columns'] !== -1): ?>
                            / <?php echo $limits['max_columns']; ?>
                        <?php else: ?>
                            / Unlimited
                        <?php endif; ?>
                    </span>
                </div>
                <div class="usage-bar">
                    <?php
                    $column_percentage = $limits['max_columns'] !== -1
                        ? min(100, ($usage['columns'] / $limits['max_columns']) * 100)
                        : 10; // Show small bar for unlimited
                    $column_class = $column_percentage > 90 ? 'danger' : ($column_percentage > 70 ? 'warning' : '');
                    ?>
                    <div class="usage-bar-fill <?php echo $column_class; ?>" style="width: <?php echo $column_percentage; ?>%">
                        <?php if ($column_percentage > 20): ?>
                            <?php echo $limits['max_columns'] !== -1 ? round($column_percentage) . '%' : 'Unlimited'; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="usage-bar-container">
                <div class="usage-bar-label">
                    <span>Estimated Participants</span>
                    <span>
                        ~<?php echo $usage['participants_estimate']; ?>
                        <?php if ($limits['max_participants'] !== -1): ?>
                            / <?php echo $limits['max_participants']; ?>
                        <?php else: ?>
                            / Unlimited
                        <?php endif; ?>
                    </span>
                </div>
                <div class="usage-bar">
                    <?php
                    $participant_percentage = $limits['max_participants'] !== -1
                        ? min(100, ($usage['participants_estimate'] / $limits['max_participants']) * 100)
                        : 10;
                    $participant_class = $participant_percentage > 90 ? 'danger' : ($participant_percentage > 70 ? 'warning' : '');
                    ?>
                    <div class="usage-bar-fill <?php echo $participant_class; ?>" style="width: <?php echo $participant_percentage; ?>%">
                        <?php if ($participant_percentage > 20): ?>
                            <?php echo $limits['max_participants'] !== -1 ? round($participant_percentage) . '%' : 'Unlimited'; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <h3>Plan Features</h3>
            <ul class="feature-list">
                <?php foreach ($plan['features'] as $feature => $value): ?>
                    <li>
                        <span class="check">✓</span>
                        <?php
                        $feature_name = ucfirst(str_replace('_', ' ', $feature));
                        if (is_bool($value)) {
                            echo $feature_name;
                        } elseif ($value === -1) {
                            echo $feature_name . ': Unlimited';
                        } else {
                            echo $feature_name . ': ' . $value;
                        }
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <h3>Actions</h3>
            <div class="btn-group">
                <?php if ($subscription['plan_id'] === 'free'): ?>
                    <a href="pricing.php" class="btn primary">Upgrade Plan</a>
                <?php else: ?>
                    <a href="pricing.php" class="btn secondary">Change Plan</a>

                    <?php if ($subscription['stripe_customer_id']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="portal">
                            <button type="submit" class="btn secondary">Manage Payment Method</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($subscription['cancel_at_period_end']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="reactivate">
                            <button type="submit" class="btn primary">Reactivate Subscription</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel your subscription? You will still have access until the end of your billing period.');">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn danger">Cancel Subscription</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
