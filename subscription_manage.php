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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Subscription Management</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        /* --- DESIGN SYSTEM (Monochrome / Bebas) --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            /* Neutrals */
            --bg-body: #f5f5f5;
            --bg-card: #ffffff;
            --text-main: #111111;
            --text-muted: #666666;
            --border-color: #e0e0e0;
            
            /* Status Colors */
            --color-green: #27ae60; 
            --color-red: #e74c3c;   
            --color-warning: #f39c12;
            
            /* Typography */
            --font-head: 'Bebas Neue', sans-serif;
            --font-body: 'Inter', sans-serif;
            
            /* UI */
            --radius-btn: 4px;
            --radius-card: 4px;
            --shadow: 0 4px 6px rgba(0,0,0,0.03);
        }

        body {
            font-family: var(--font-body);
            background-color: var(--bg-body);
            color: var(--text-main);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* --- HEADER --- */
        .navbar {
            background: var(--bg-card);
            border-bottom: 3px solid var(--text-main);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .navbar h1 {
            font-family: var(--font-head);
            font-size: 2rem;
            color: var(--text-main);
            margin: 0;
            line-height: 1;
        }

        .nav-links { display: flex; gap: 20px; flex-wrap: wrap; }

        .navbar a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: color 0.2s;
        }

        .navbar a:hover { color: var(--text-main); }

        .container {
            max-width: 1000px;
            margin: 3rem auto;
            padding: 0 2rem;
            width: 100%;
        }

        /* --- ALERTS --- */
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid;
            background: #fff;
            font-size: 0.95rem;
            box-shadow: var(--shadow);
            word-wrap: break-word;
        }
        .alert.success { border-color: var(--color-green); color: var(--color-green); }
        .alert.error { border-color: var(--color-red); color: var(--color-red); }
        .alert.warning { border-color: var(--color-warning); color: var(--color-warning); }
        .alert a { font-weight: 700; text-decoration: underline; }

        /* --- CARD --- */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 2.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border-radius: var(--radius-card);
            width: 100%;
        }

        .card h2 {
            font-family: var(--font-head);
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-main);
            line-height: 1;
        }

        .card h3 {
            font-family: var(--font-head);
            font-size: 1.8rem;
            margin: 2.5rem 0 1rem 0;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
        }

        /* SUBSCRIPTION HEADER */
        .subscription-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .plan-info-group { display: flex; flex-direction: column; gap: 8px; }

        .plan-badge {
            display: inline-block;
            padding: 6px 12px;
            border: 1px solid var(--text-main);
            font-family: var(--font-head);
            font-size: 1.1rem;
            letter-spacing: 1px;
            color: var(--text-main);
            background: transparent;
            width: fit-content;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #fff;
            vertical-align: middle;
            margin-left: 8px;
            border-radius: 2px;
        }
        .status-badge.active { background: var(--color-green); }
        .status-badge.canceled { background: var(--color-red); }
        .status-badge.past_due { background: var(--color-warning); }

        .price-tag { font-size: 2rem; font-family: var(--font-head); color: var(--text-main); }
        .price-period { font-size: 1rem; color: var(--text-muted); font-family: var(--font-body); }

        /* --- INFO GRID --- */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-bottom: 2rem;
            width: 100%;
        }

        .info-item {
            background: #fafafa;
            border: 1px solid #eee;
            padding: 1.2rem;
            border-radius: var(--radius-card);
            min-width: 0;
        }

        .info-item label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .info-item .value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
            word-wrap: break-word; 
            overflow-wrap: anywhere; 
            line-height: 1.3;
        }

        /* USAGE BARS */
        .usage-bar-container { margin: 1.5rem 0; }

        .usage-bar-label {
            display: flex; justify-content: space-between;
            margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500;
        }

        .usage-bar {
            height: 8px;
            background: #eee;
            overflow: hidden;
            border-radius: 4px; 
        }

        .usage-bar-fill {
            height: 100%;
            background: var(--text-main);
            transition: width 0.3s ease;
        }
        .usage-bar-fill.warning { background: var(--color-warning); }
        .usage-bar-fill.danger { background: var(--color-red); }

        /* FEATURE LIST */
        .feature-list { list-style: none; margin-top: 1rem; }
        .feature-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; gap: 10px;
            font-size: 0.95rem;
        }
        .check { color: var(--color-green); font-weight: bold; }

        /* --- BUTTONS --- */
        .btn-group { 
            display: flex; 
            gap: 1rem; 
            flex-wrap: wrap; 
            margin-top: 2rem; 
            width: 100%;
            align-items: stretch; /* KEY FIX: Forces equal height */
        }

        /* Make Forms flex containers too so their children expand */
        .btn-group form {
            display: flex;
            flex: 1;
            min-width: 200px;
        }

        .btn {
            padding: 0 24px; /* Use flex alignment for vertical */
            border: 1px solid var(--border-color);
            background: #fff;
            color: var(--text-muted);
            font-family: var(--font-head);
            font-size: 1.1rem;
            letter-spacing: 1px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-btn);
            
            /* HEIGHT FIX */
            min-height: 54px;
            height: 100%; 
            width: 100%;
            text-align: center;
        }
        
        .btn:hover { border-color: var(--text-main); color: var(--text-main); transform: translateY(-1px); }

        .btn.primary { background: var(--text-main); color: #fff; border-color: var(--text-main); }
        .btn.primary:hover { background: #333; }

        .btn.danger { color: var(--color-red); border-color: rgba(231, 76, 60, 0.3); }
        .btn.danger:hover { background: var(--color-red); color: white; }

        /* Anchor tags need flex-grow if not in a form */
        a.btn { flex: 1; min-width: 200px; }

        /* --- MOBILE OPTIMIZATION --- */
        @media (max-width: 768px) {
            .navbar { flex-direction: column; align-items: flex-start; gap: 1.5rem; padding: 1.5rem; }
            .nav-links { width: 100%; justify-content: space-between; }
            
            .container { padding: 1rem; margin: 1rem auto; width: 100%; }
            .card { padding: 1.5rem; }
            
            .subscription-header { flex-direction: column; align-items: flex-start; gap: 1.5rem; }
            
            .info-grid { 
                grid-template-columns: 1fr; 
                gap: 1rem; 
            }
            
            .info-item { 
                padding: 1rem; 
                width: 100%; 
            }
            
            .btn-group { flex-direction: column; gap: 10px; }
            .btn-group form, a.btn { width: 100%; min-width: 0; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Subscription</h1>
        <div class="nav-links">
            <a href="admin.php">Dashboard</a>
            <a href="pricing.php">Plans</a>
            <a href="logout.php" style="color: var(--color-red);">Logout</a>
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
                <strong>⚠ LIMIT EXCEEDED</strong><br>
                You have exceeded your plan limits (<?php echo implode(', ', $limit_check['over_limits']); ?>).
                <a href="pricing.php">UPGRADE PLAN</a>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="subscription-header">
                <div class="plan-info-group">
                    <h2>Current Plan</h2>
                    <div>
                        <span class="plan-badge">
                            <?php echo strtoupper($plan['name']); ?>
                        </span>
                        <span class="status-badge <?php echo $subscription['status']; ?>">
                            <?php echo strtoupper($subscription['status']); ?>
                        </span>
                    </div>
                </div>
                <?php if ($subscription['plan_id'] !== 'free'): ?>
                    <div>
                        <span class="price-tag"><?php echo $plan['price_monthly'] ? '€' . number_format($plan['price_monthly'], 2) : 'Custom'; ?></span>
                        <?php if ($plan['price_monthly']): ?>
                            <span class="price-period">/ month</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-grid">
                <?php if ($subscription['current_period_end']): ?>
                    <div class="info-item">
                        <label>Next Billing Date</label>
                        <div class="value">
                            <?php echo date('d. M Y', $subscription['current_period_end']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($subscription['cancel_at_period_end']): ?>
                    <div class="info-item">
                        <label>Status</label>
                        <div class="value" style="color: var(--color-red);">
                            Scheduled for Cancellation
                        </div>
                    </div>
                <?php endif; ?>

                <div class="info-item">
                    <label>Account Email</label>
                    <div class="value">
                        <?php echo htmlspecialchars($user_email); ?>
                    </div>
                </div>
            </div>

            <h3>Usage Overview</h3>

            <div class="usage-bar-container">
                <div class="usage-bar-label">
                    <span>Active Columns</span>
                    <span>
                        <?php echo $usage['columns']; ?> / 
                        <?php echo $limits['max_columns'] !== -1 ? $limits['max_columns'] : '∞'; ?>
                    </span>
                </div>
                <div class="usage-bar">
                    <?php
                    $column_percentage = $limits['max_columns'] !== -1
                        ? min(100, ($usage['columns'] / $limits['max_columns']) * 100)
                        : 5; 
                    $column_class = $column_percentage > 90 ? 'danger' : ($column_percentage > 70 ? 'warning' : '');
                    ?>
                    <div class="usage-bar-fill <?php echo $column_class; ?>" style="width: <?php echo $column_percentage; ?>%"></div>
                </div>
            </div>

            <div class="usage-bar-container">
                <div class="usage-bar-label">
                    <span>Est. Participants</span>
                    <span>
                        ~<?php echo $usage['participants_estimate']; ?> / 
                        <?php echo $limits['max_participants'] !== -1 ? $limits['max_participants'] : '∞'; ?>
                    </span>
                </div>
                <div class="usage-bar">
                    <?php
                    $participant_percentage = $limits['max_participants'] !== -1
                        ? min(100, ($usage['participants_estimate'] / $limits['max_participants']) * 100)
                        : 5;
                    $participant_class = $participant_percentage > 90 ? 'danger' : ($participant_percentage > 70 ? 'warning' : '');
                    ?>
                    <div class="usage-bar-fill <?php echo $participant_class; ?>" style="width: <?php echo $participant_percentage; ?>%"></div>
                </div>
            </div>

            <h3>Features Included</h3>
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

            <div class="btn-group">
                <?php if ($subscription['plan_id'] === 'free'): ?>
                    <a href="pricing.php" class="btn primary">UPGRADE PLAN</a>
                <?php else: ?>
                    <a href="pricing.php" class="btn">CHANGE PLAN</a>

                    <?php if ($subscription['stripe_customer_id']): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="portal">
                            <button type="submit" class="btn">MANAGE BILLING</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($subscription['cancel_at_period_end']): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="reactivate">
                            <button type="submit" class="btn primary">REACTIVATE</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" onsubmit="return confirm('Cancel subscription?');">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn danger">CANCEL SUBSCRIPTION</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>