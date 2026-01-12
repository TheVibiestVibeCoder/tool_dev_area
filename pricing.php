<?php
/**
 * Pricing Page
 * Displays available subscription plans with comparison
 */

require_once 'user_auth.php';
require_once 'subscription_manager.php';
require_once 'security_helpers.php';

// Set security headers
setSecurityHeaders();

// Check if user is logged in
$is_logged_in = isLoggedIn();
$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_email = $_SESSION['user_email'] ?? null;

$sub_manager = new SubscriptionManager();
$plans = $sub_manager->getPlans();

// Get current subscription if logged in
$current_subscription = null;
$current_plan_id = 'free';
if ($current_user_id) {
    $current_subscription = $sub_manager->getUserSubscription($current_user_id);
    $current_plan_id = $current_subscription['plan_id'] ?? 'free';
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pricing Plans - Live Situation Room</title>
    
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
            
            /* Accents */
            --color-green: #27ae60; /* Conversion Color */
            
            /* Typography */
            --font-head: 'Bebas Neue', sans-serif;
            --font-body: 'Inter', sans-serif;
            
            /* UI */
            --radius-btn: 4px;
            --radius-card: 4px;
            --shadow: 0 4px 6px rgba(0,0,0,0.03);
            --shadow-hover: 0 12px 24px rgba(0,0,0,0.08);
        }

        body {
            font-family: var(--font-body);
            background-color: var(--bg-body);
            color: var(--text-main);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* --- HEADER --- */
        .navbar {
            background: var(--bg-card);
            border-bottom: 3px solid var(--text-main);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h1 {
            font-family: var(--font-head);
            font-size: 2rem;
            color: var(--text-main);
            margin: 0;
            line-height: 1;
        }

        .nav-links { display: flex; gap: 20px; }

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
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }

        /* --- HERO SECTION --- */
        .header-section {
            text-align: center;
            margin-bottom: 4rem;
        }

        .header-section h2 {
            font-family: var(--font-head);
            font-size: 4rem;
            color: var(--text-main);
            margin-bottom: 0.5rem;
            line-height: 0.9;
        }

        .header-section p {
            font-size: 1.2rem;
            color: var(--text-muted);
            font-weight: 300;
        }

        /* --- BILLING TOGGLE --- */
        .billing-toggle {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 4rem;
        }

        .billing-toggle label {
            font-family: var(--font-head);
            font-size: 1.2rem;
            letter-spacing: 1px;
            cursor: pointer;
        }

        .toggle-switch {
            position: relative;
            width: 64px;
            height: 32px;
            background: #ddd;
            border-radius: 99px;
            cursor: pointer;
            transition: background 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .toggle-switch.active {
            background: var(--text-main);
        }

        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            top: 4px;
            left: 4px;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .toggle-switch.active::after {
            transform: translateX(32px);
        }

        .discount-badge {
            background: var(--color-green);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* --- PRICING GRID --- */
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
            align-items: start;
        }

        .plan-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 2.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: #ccc;
        }

        /* PREMIUM HIGHLIGHT */
        .plan-card.featured {
            border: 2px solid var(--text-main);
            transform: scale(1.05);
            z-index: 2;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .plan-card.featured:hover {
            transform: scale(1.05) translateY(-5px);
        }

        .plan-card.featured::before {
            content: 'BEST VALUE';
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--text-main);
            color: white;
            padding: 4px 16px;
            font-family: var(--font-head);
            font-size: 1rem;
            letter-spacing: 1px;
            border-radius: 2px;
        }

        /* CURRENT PLAN BADGE */
        .plan-card.current-plan {
            background: #f9f9f9;
        }
        
        .plan-card.current-plan::after {
            content: 'CURRENT';
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #ddd;
            color: #555;
            padding: 4px 8px;
            font-size: 0.7rem;
            font-weight: 700;
            border-radius: 2px;
            text-transform: uppercase;
        }

        /* PLAN CONTENT */
        .plan-name {
            font-family: var(--font-head);
            font-size: 2.5rem;
            line-height: 1;
            margin-bottom: 1rem;
            color: var(--text-main);
        }

        .plan-price {
            font-family: var(--font-head);
            font-size: 3.5rem;
            color: var(--text-main);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .plan-price span {
            font-size: 1.2rem;
            color: var(--text-muted);
            font-family: var(--font-body);
            font-weight: 400;
        }

        .plan-description {
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 0.95rem;
            min-height: 40px;
        }

        /* FEATURES */
        .plan-features {
            list-style: none;
            margin-bottom: 2.5rem;
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
        }

        .plan-features li {
            padding: 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            color: var(--text-main);
        }

        .plan-features .check {
            color: var(--text-main); /* Black Check */
            font-weight: 900;
            font-size: 1.1rem;
        }

        .plan-features .cross {
            color: #ddd;
            font-weight: 700;
        }

        /* BUTTONS */
        .plan-button {
            width: 100%;
            padding: 16px;
            border: 1px solid var(--text-main);
            background: transparent;
            color: var(--text-main);
            font-family: var(--font-head);
            font-size: 1.2rem;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: auto;
            text-transform: uppercase;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .plan-button:hover {
            background: var(--text-main);
            color: white;
            transform: translateY(-2px);
        }

        /* Primary Action (Black Fill) */
        .plan-button.primary {
            background: var(--text-main);
            color: white;
        }
        .plan-button.primary:hover {
            background: #333;
        }

        /* Disabled State */
        .plan-button.disabled {
            border-color: #ddd;
            color: #999;
            background: #f5f5f5;
            cursor: default;
        }
        .plan-button.disabled:hover {
            transform: none;
            background: #f5f5f5;
            color: #999;
        }

        /* --- FAQ SECTION --- */
        .faq-section {
            margin-top: 6rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .faq-section h3 {
            font-family: var(--font-head);
            font-size: 3rem;
            text-align: center;
            margin-bottom: 3rem;
            color: var(--text-main);
        }

        .faq-item {
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem 0;
        }

        .faq-item h4 {
            font-family: var(--font-head);
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
            color: var(--text-main);
            letter-spacing: 0.5px;
        }
        
        .faq-item p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* --- MOBILE OPTIMIZATION --- */
        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 1rem; padding: 1.5rem; }
            .nav-links { width: 100%; justify-content: space-between; }
            
            .header-section h2 { font-size: 3rem; }
            .container { padding: 2rem 1rem; }
            
            .pricing-grid { 
                grid-template-columns: 1fr; 
                gap: 3rem;
            }

            /* Reset scale on mobile so they stack normally */
            .plan-card.featured {
                transform: scale(1);
                order: -1; /* Show premium first on mobile! */
                border-width: 4px; /* Make border thicker to stand out without scale */
            }
            .plan-card.featured:hover { transform: translateY(-5px); }
            
            .billing-toggle { gap: 1rem; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Live Situation Room</h1>
        <div class="nav-links">
            <?php if ($is_logged_in): ?>
                <a href="admin.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <div class="header-section">
            <h2>Select Your Plan</h2>
            <p>Flexible pricing for teams of all sizes.</p>
        </div>

        <div class="billing-toggle">
            <label id="monthlyLabel">Monthly</label>
            <div class="toggle-switch" id="billingToggle"></div>
            <label id="yearlyLabel">Yearly</label>
            <span class="discount-badge">Save 15%</span>
        </div>

        <div class="pricing-grid">
            <div class="plan-card <?php echo $current_plan_id === 'free' ? 'current-plan' : ''; ?>">
                <div class="plan-name">Starter</div>
                <div class="plan-price">
                    €0
                    <span>/ forever</span>
                </div>
                <div class="plan-description">Essential features for individuals and small tests.</div>
                <ul class="plan-features">
                    <li><span class="check">✓</span> Up to 10 participants</li>
                    <li><span class="check">✓</span> 3 columns/categories</li>
                    <li><span class="check">✓</span> 1 active workshop</li>
                    <li><span class="check">✓</span> PDF export</li>
                    <li><span class="cross">✗</span> Custom branding</li>
                    <li><span class="cross">✗</span> Email support</li>
                </ul>
                <?php if ($current_plan_id === 'free'): ?>
                    <button class="plan-button disabled">Current Plan</button>
                <?php elseif ($is_logged_in): ?>
                    <button class="plan-button" onclick="downgradeToPlan('free')">Downgrade</button>
                <?php else: ?>
                    <a href="register.php" class="plan-button">Get Started</a>
                <?php endif; ?>
            </div>

            <div class="plan-card featured <?php echo $current_plan_id === 'premium' ? 'current-plan' : ''; ?>">
                <div class="plan-name">Pro</div>
                <div class="plan-price">
                    <span class="monthly-price">€19</span>
                    <span class="yearly-price" style="display: none;">€16</span>
                    <span>/ month</span>
                </div>
                <div class="plan-description">Unlimited power for professional facilitators.</div>
                <ul class="plan-features">
                    <li><span class="check">✓</span> Unlimited participants</li>
                    <li><span class="check">✓</span> Unlimited columns</li>
                    <li><span class="check">✓</span> Unlimited workshops</li>
                    <li><span class="check">✓</span> Custom branding</li>
                    <li><span class="check">✓</span> PDF export</li>
                    <li><span class="check">✓</span> Priority support</li>
                </ul>
                <?php if ($current_plan_id === 'premium'): ?>
                    <button class="plan-button disabled">Current Plan</button>
                <?php elseif ($is_logged_in): ?>
                    <button class="plan-button primary" onclick="upgradeToPlan('premium', 'monthly')">
                        <span class="monthly-btn-text">Upgrade to Pro</span>
                        <span class="yearly-btn-text" style="display: none;">Upgrade Yearly</span>
                    </button>
                <?php else: ?>
                    <a href="register.php" class="plan-button primary">Start Free Trial</a>
                <?php endif; ?>
            </div>

            <div class="plan-card <?php echo $current_plan_id === 'enterprise' ? 'current-plan' : ''; ?>">
                <div class="plan-name">Enterprise</div>
                <div class="plan-price">
                    Custom
                </div>
                <div class="plan-description">Dedicated infrastructure for large organizations.</div>
                <ul class="plan-features">
                    <li><span class="check">✓</span> Everything in Pro</li>
                    <li><span class="check">✓</span> SSO Integration</li>
                    <li><span class="check">✓</span> Custom Domain</li>
                    <li><span class="check">✓</span> API Access</li>
                    <li><span class="check">✓</span> 24/7 SLA Support</li>
                    <li><span class="check">✓</span> Dedicated Manager</li>
                </ul>
                <button class="plan-button" onclick="contactEnterprise()">Contact Sales</button>
            </div>
        </div>

        <div class="faq-section">
            <h3>FAQ</h3>

            <div class="faq-item">
                <h4>Can I change my plan later?</h4>
                <p>Yes. Upgrade or downgrade at any time. Changes apply at the end of your billing cycle.</p>
            </div>

            <div class="faq-item">
                <h4>What payment methods do you accept?</h4>
                <p>We accept all major credit cards (Visa, Mastercard, AMEX) and SEPA direct debit.</p>
            </div>

            <div class="faq-item">
                <h4>Is there a free trial?</h4>
                <p>Yes. You can start with the Starter plan for free, forever. No credit card required.</p>
            </div>

            <div class="faq-item">
                <h4>What happens if I cancel?</h4>
                <p>You retain access until the end of your paid period. After that, your account reverts to the Starter plan.</p>
            </div>
        </div>
    </div>

    <script>
        const billingToggle = document.getElementById('billingToggle');
        let isYearly = false;

        billingToggle.addEventListener('click', function() {
            isYearly = !isYearly;
            billingToggle.classList.toggle('active');

            // Update pricing display
            const monthlyPrices = document.querySelectorAll('.monthly-price');
            const yearlyPrices = document.querySelectorAll('.yearly-price');
            const monthlyBtns = document.querySelectorAll('.monthly-btn-text');
            const yearlyBtns = document.querySelectorAll('.yearly-btn-text');

            if (isYearly) {
                monthlyPrices.forEach(el => el.style.display = 'none');
                yearlyPrices.forEach(el => el.style.display = 'inline');
                monthlyBtns.forEach(el => el.style.display = 'none');
                yearlyBtns.forEach(el => el.style.display = 'inline');
            } else {
                monthlyPrices.forEach(el => el.style.display = 'inline');
                yearlyPrices.forEach(el => el.style.display = 'none');
                monthlyBtns.forEach(el => el.style.display = 'inline');
                yearlyBtns.forEach(el => el.style.display = 'none');
            }
        });

        function upgradeToPlan(planId, billingPeriod) {
            const period = isYearly ? 'yearly' : 'monthly';
            window.location.href = `checkout.php?plan=${planId}&period=${period}`;
        }

        function downgradeToPlan(planId) {
            if (confirm('Downgrade to Free? You will lose premium features at the end of your billing cycle.')) {
                window.location.href = `subscription_manage.php?action=cancel`;
            }
        }

        function contactEnterprise() {
            window.location.href = 'mailto:enterprise@yourservice.com?subject=Enterprise%20Inquiry';
        }
    </script>
</body>
</html>