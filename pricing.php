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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Plans - Live Situation Room</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .header-section {
            text-align: center;
            margin-bottom: 3rem;
        }

        .header-section h2 {
            font-family: var(--font-heading);
            font-size: 2.5rem;
            color: var(--ip-dark);
            margin-bottom: 1rem;
        }

        .header-section p {
            font-size: 1.2rem;
            color: #666;
        }

        .billing-toggle {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin: 2rem 0;
        }

        .billing-toggle label {
            font-weight: 500;
            font-size: 1.1rem;
        }

        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
            background: var(--ip-border);
            border-radius: 30px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .toggle-switch.active {
            background: var(--ip-blue);
        }

        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            top: 3px;
            left: 3px;
            transition: transform 0.3s;
        }

        .toggle-switch.active::after {
            transform: translateX(30px);
        }

        .discount-badge {
            background: var(--accent-success);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .plan-card {
            background: var(--ip-card-bg);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            border: 2px solid transparent;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.15);
        }

        .plan-card.featured {
            border-color: var(--ip-blue);
            transform: scale(1.05);
        }

        .plan-card.featured::before {
            content: 'MOST POPULAR';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--ip-blue);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .plan-card.current-plan {
            border-color: var(--accent-success);
        }

        .plan-card.current-plan::after {
            content: 'CURRENT PLAN';
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--accent-success);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .plan-name {
            font-family: var(--font-heading);
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--ip-dark);
            margin-bottom: 1rem;
        }

        .plan-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--ip-blue);
            margin-bottom: 0.5rem;
        }

        .plan-price span {
            font-size: 1rem;
            color: #666;
            font-weight: 400;
        }

        .plan-description {
            color: #666;
            margin-bottom: 1.5rem;
            min-height: 48px;
        }

        .plan-features {
            list-style: none;
            margin-bottom: 2rem;
        }

        .plan-features li {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--ip-border);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .plan-features li:last-child {
            border-bottom: none;
        }

        .plan-features .check {
            color: var(--accent-success);
            font-weight: 700;
            font-size: 1.2rem;
        }

        .plan-features .cross {
            color: #ccc;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .plan-button {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: var(--font-heading);
        }

        .plan-button.primary {
            background: var(--ip-blue);
            color: white;
        }

        .plan-button.primary:hover {
            background: #004a66;
            transform: scale(1.02);
        }

        .plan-button.secondary {
            background: transparent;
            color: var(--ip-blue);
            border: 2px solid var(--ip-blue);
        }

        .plan-button.secondary:hover {
            background: var(--ip-blue);
            color: white;
        }

        .plan-button.disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
        }

        .plan-button.disabled:hover {
            transform: none;
        }

        .plan-button.contact {
            background: var(--accent-warning);
            color: white;
        }

        .plan-button.contact:hover {
            background: #e69500;
        }

        .faq-section {
            margin-top: 4rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .faq-section h3 {
            font-family: var(--font-heading);
            font-size: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }

        .faq-item {
            background: var(--ip-card-bg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .faq-item h4 {
            font-family: var(--font-heading);
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--ip-blue);
        }

        @media (max-width: 768px) {
            .pricing-grid {
                grid-template-columns: 1fr;
            }

            .plan-card.featured {
                transform: scale(1);
            }

            .header-section h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Live Situation Room</h1>
        <div>
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
            <h2>Choose Your Plan</h2>
            <p>Start free, upgrade as you grow</p>
        </div>

        <div class="billing-toggle">
            <label>Monthly</label>
            <div class="toggle-switch" id="billingToggle"></div>
            <label>Yearly</label>
            <span class="discount-badge">Save 15%</span>
        </div>

        <div class="pricing-grid">
            <!-- Free Plan -->
            <div class="plan-card <?php echo $current_plan_id === 'free' ? 'current-plan' : ''; ?>">
                <div class="plan-name">Free</div>
                <div class="plan-price">
                    €0
                    <span>/ forever</span>
                </div>
                <div class="plan-description">Perfect for trying out the platform</div>
                <ul class="plan-features">
                    <li><span class="check">✓</span> Up to 10 participants</li>
                    <li><span class="check">✓</span> 3 columns/categories</li>
                    <li><span class="check">✓</span> 1 workshop</li>
                    <li><span class="check">✓</span> PDF export</li>
                    <li><span class="cross">✗</span> Custom branding</li>
                    <li><span class="cross">✗</span> Email support</li>
                </ul>
                <?php if ($current_plan_id === 'free'): ?>
                    <button class="plan-button disabled">Current Plan</button>
                <?php elseif ($is_logged_in): ?>
                    <button class="plan-button secondary" onclick="downgradeToPlan('free')">Downgrade</button>
                <?php else: ?>
                    <a href="register.php" style="text-decoration: none;">
                        <button class="plan-button secondary">Get Started</button>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Premium Plan -->
            <div class="plan-card featured <?php echo $current_plan_id === 'premium' ? 'current-plan' : ''; ?>">
                <div class="plan-name">Premium</div>
                <div class="plan-price">
                    <span class="monthly-price">€19.99</span>
                    <span class="yearly-price" style="display: none;">€17.00</span>
                    <span>/ month</span>
                </div>
                <div class="plan-description">For professional facilitators and teams</div>
                <ul class="plan-features">
                    <li><span class="check">✓</span> Unlimited participants</li>
                    <li><span class="check">✓</span> Unlimited columns</li>
                    <li><span class="check">✓</span> Unlimited workshops</li>
                    <li><span class="check">✓</span> Custom branding</li>
                    <li><span class="check">✓</span> PDF export</li>
                    <li><span class="check">✓</span> Email support</li>
                </ul>
                <?php if ($current_plan_id === 'premium'): ?>
                    <button class="plan-button disabled">Current Plan</button>
                <?php elseif ($is_logged_in): ?>
                    <button class="plan-button primary" onclick="upgradeToPlan('premium', 'monthly')">
                        <span class="monthly-btn-text">Upgrade Now</span>
                        <span class="yearly-btn-text" style="display: none;">Upgrade Now (Yearly)</span>
                    </button>
                <?php else: ?>
                    <a href="register.php" style="text-decoration: none;">
                        <button class="plan-button primary">Start Free Trial</button>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Enterprise Plan -->
            <div class="plan-card <?php echo $current_plan_id === 'enterprise' ? 'current-plan' : ''; ?>">
                <div class="plan-name">Enterprise</div>
                <div class="plan-price">
                    Custom
                    <span>/ pricing</span>
                </div>
                <div class="plan-description">Tailored solutions for large organizations</div>
                <ul class="plan-features">
                    <li><span class="check">✓</span> Everything in Premium</li>
                    <li><span class="check">✓</span> Multiple team accounts</li>
                    <li><span class="check">✓</span> Custom integrations</li>
                    <li><span class="check">✓</span> API access</li>
                    <li><span class="check">✓</span> Priority support</li>
                    <li><span class="check">✓</span> Dedicated account manager</li>
                </ul>
                <button class="plan-button contact" onclick="contactEnterprise()">Contact Sales</button>
            </div>
        </div>

        <div class="faq-section">
            <h3>Frequently Asked Questions</h3>

            <div class="faq-item">
                <h4>Can I change my plan later?</h4>
                <p>Yes! You can upgrade or downgrade your plan at any time. Changes take effect at the end of your current billing period.</p>
            </div>

            <div class="faq-item">
                <h4>What payment methods do you accept?</h4>
                <p>We accept all major credit cards (Visa, Mastercard, American Express) and SEPA direct debit for EU customers.</p>
            </div>

            <div class="faq-item">
                <h4>Is there a free trial?</h4>
                <p>Yes! All new users start with our Free plan. You can try the platform with up to 10 participants and 3 columns at no cost.</p>
            </div>

            <div class="faq-item">
                <h4>What happens if I exceed my plan limits?</h4>
                <p>You'll be prompted to upgrade to a higher plan. Your existing data remains safe, but you won't be able to add more columns or participants until you upgrade.</p>
            </div>

            <div class="faq-item">
                <h4>Can I cancel my subscription?</h4>
                <p>Yes, you can cancel anytime. Your subscription will remain active until the end of the billing period, then revert to the Free plan.</p>
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
            if (confirm('Are you sure you want to downgrade to the Free plan? This will take effect at the end of your current billing period.')) {
                window.location.href = `subscription_manage.php?action=cancel`;
            }
        }

        function contactEnterprise() {
            window.location.href = 'mailto:enterprise@yourdomain.com?subject=Enterprise%20Plan%20Inquiry';
        }
    </script>
</body>
</html>
