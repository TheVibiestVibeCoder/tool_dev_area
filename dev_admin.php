<?php
/**
 * Dev Admin Control Panel
 * Master dashboard for developers to manage the entire SaaS platform
 *
 * Features:
 * - View all users and their subscription status
 * - Platform statistics
 * - Send password reset links
 * - View active workshops/dashboards
 * - User activity monitoring
 */

require_once __DIR__ . '/dev_admin_auth.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/user_auth.php';
require_once __DIR__ . '/subscription_manager.php';
require_once __DIR__ . '/file_handling_robust.php';

// Initialize security
setSecurityHeaders();
requireDevAdmin();

$devAdmin = getDevAdminInfo();
$subscriptionManager = new SubscriptionManager();

// Handle actions
$actionMessage = '';
$actionType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $actionMessage = 'Invalid security token';
        $actionType = 'error';
    } else {
        switch ($_POST['action']) {
            case 'send_reset_link':
                $userId = $_POST['user_id'] ?? '';
                if (!empty($userId)) {
                    $result = generatePasswordResetTokenForUser($userId);
                    $actionMessage = $result['message'];
                    $actionType = $result['success'] ? 'success' : 'error';
                }
                break;
        }
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Load all users
$allUsers = getAllUsersDetailed();

// Calculate statistics
$stats = calculatePlatformStats($allUsers);

// Get all workshops
$allWorkshops = getAllWorkshops($allUsers);

/**
 * Get detailed information about all users
 */
function getAllUsersDetailed() {
    if (!file_exists(USERS_FILE)) {
        return [];
    }

    $content = file_get_contents(USERS_FILE);
    $data = json_decode($content, true);

    if (!isset($data['users']) || !is_array($data['users'])) {
        return [];
    }

    return $data['users'];
}

/**
 * Calculate platform statistics
 */
function calculatePlatformStats($users) {
    $stats = [
        'total_users' => count($users),
        'active_users' => 0,
        'total_entries' => 0,
        'total_workshops' => 0,
        'subscription_breakdown' => [
            'free' => 0,
            'premium' => 0,
            'enterprise' => 0,
            'trial' => 0,
            'cancelled' => 0
        ],
        'revenue_monthly' => 0,
        'revenue_yearly' => 0
    ];

    $oneWeekAgo = time() - (7 * 24 * 60 * 60);

    foreach ($users as $user) {
        // Count active users (logged in within last week)
        if (isset($user['last_login'])) {
            $lastLogin = is_numeric($user['last_login']) ? $user['last_login'] : strtotime($user['last_login']);
            if ($lastLogin && $lastLogin > $oneWeekAgo) {
                $stats['active_users']++;
            }
        }

        // Count subscription types - FIXED: use plan_id not plan
        $plan = $user['subscription']['plan_id'] ?? 'free';
        $status = $user['subscription']['status'] ?? 'active';
        $cancelAtPeriodEnd = $user['subscription']['cancel_at_period_end'] ?? false;

        // Check if subscription is cancelled (either status=canceled OR cancel_at_period_end=true)
        if ($status === 'canceled' || $status === 'cancelled' || $cancelAtPeriodEnd) {
            $stats['subscription_breakdown']['cancelled']++;
        } elseif ($status === 'active' || $status === 'trialing') {
            if (isset($stats['subscription_breakdown'][$plan])) {
                $stats['subscription_breakdown'][$plan]++;
            }

            // Calculate revenue (only for active, non-cancelled subscriptions)
            if ($plan === 'premium' && !$cancelAtPeriodEnd) {
                $billingCycle = $user['subscription']['billing_cycle'] ?? 'monthly';
                if ($billingCycle === 'monthly') {
                    $stats['revenue_monthly'] += 19.99;
                } else {
                    $stats['revenue_yearly'] += 203.89;
                }
            }
        }

        // Count workshops and entries for this user - FIXED: use 'id' not 'user_id'
        $userId = $user['id'] ?? null;
        if ($userId) {
            $userDataDir = __DIR__ . '/data/' . $userId;
            if (is_dir($userDataDir)) {
                // Count workshops (config.json presence)
                if (file_exists($userDataDir . '/config.json')) {
                    $stats['total_workshops']++;
                }

                // Count entries in daten.json
                $datenFile = $userDataDir . '/daten.json';
                if (file_exists($datenFile)) {
                    $content = file_get_contents($datenFile);
                    $data = json_decode($content, true);
                    if (isset($data['data']) && is_array($data['data'])) {
                        $stats['total_entries'] += count($data['data']);
                    }
                }
            }
        }
    }

    return $stats;
}

/**
 * Get all active workshops across the platform
 */
function getAllWorkshops($users) {
    $workshops = [];

    foreach ($users as $user) {
        $userId = $user['id'] ?? null; // FIXED: use 'id' not 'user_id'
        if (!$userId) continue;

        $userDataDir = __DIR__ . '/data/' . $userId;
        $configFile = $userDataDir . '/config.json';

        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            $config = json_decode($content, true);

            if ($config) {
                // Count entries
                $entryCount = 0;
                $datenFile = $userDataDir . '/daten.json';
                if (file_exists($datenFile)) {
                    $datenContent = file_get_contents($datenFile);
                    $datenData = json_decode($datenContent, true);
                    if (isset($datenData['data']) && is_array($datenData['data'])) {
                        $entryCount = count($datenData['data']);
                    }
                }

                $workshops[] = [
                    'user_id' => $userId,
                    'user_email' => $user['email'] ?? 'N/A',
                    'title' => $config['title'] ?? 'Untitled Workshop',
                    'categories_count' => isset($config['categories']) ? count($config['categories']) : 0,
                    'entries_count' => $entryCount,
                    'created_at' => $user['created_at'] ?? 'N/A'
                ];
            }
        }
    }

    return $workshops;
}

/**
 * Generate password reset token for a specific user
 */
function generatePasswordResetTokenForUser($userId) {
    // Load users
    if (!file_exists(USERS_FILE)) {
        return ['success' => false, 'message' => 'Users file not found'];
    }

    $content = file_get_contents(USERS_FILE);
    $data = json_decode($content, true);

    if (!isset($data['users']) || !is_array($data['users'])) {
        return ['success' => false, 'message' => 'Invalid users data'];
    }

    // Find user - FIXED: use 'id' not 'user_id'
    $userFound = false;
    $userEmail = '';
    foreach ($data['users'] as $user) {
        if ($user['id'] === $userId) {
            $userFound = true;
            $userEmail = $user['email'];
            break;
        }
    }

    if (!$userFound) {
        return ['success' => false, 'message' => 'User not found'];
    }

    // Generate token
    $token = bin2hex(random_bytes(32));
    $expiry = time() + RESET_TOKEN_EXPIRY;

    // Load existing tokens
    $tokensFile = RESET_TOKENS_FILE;
    $tokens = [];
    if (file_exists($tokensFile)) {
        $tokensContent = file_get_contents($tokensFile);
        $tokens = json_decode($tokensContent, true) ?: [];
    }

    // Add new token
    $tokens[] = [
        'token' => $token,
        'user_id' => $userId,
        'email' => $userEmail,
        'expiry' => $expiry,
        'used' => false,
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => 'dev_admin'
    ];

    // Save tokens
    if (file_put_contents($tokensFile, json_encode($tokens, JSON_PRETTY_PRINT))) {
        $resetLink = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;
        return [
            'success' => true,
            'message' => 'Password reset link generated',
            'link' => $resetLink,
            'email' => $userEmail
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to save reset token'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dev Control Panel - Live Situation Room</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0f0f1e;
            color: #e0e0e0;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 20px 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-title {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .dev-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
        }

        .user-role {
            font-size: 12px;
            color: #808080;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-logout {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-logout:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 40px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-label {
            font-size: 13px;
            color: #808080;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-subtext {
            font-size: 12px;
            color: #a0a0a0;
            margin-top: 5px;
        }

        .section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            padding: 30px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .table thead tr {
            background: rgba(255, 255, 255, 0.05);
        }

        .table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #a0a0a0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .table td {
            padding: 15px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 14px;
        }

        .table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .badge-free {
            background: rgba(156, 163, 175, 0.2);
            color: #d1d5db;
        }

        .badge-premium {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }

        .badge-enterprise {
            background: rgba(147, 51, 234, 0.2);
            color: #c084fc;
        }

        .badge-active {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }

        .badge-cancelled {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        .badge-trial {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: #1a1a2e;
            padding: 30px;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-header {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .code-box {
            background: rgba(0, 0, 0, 0.4);
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            word-break: break-all;
            margin: 15px 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #808080;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px 20px;
            }

            .container {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table {
                font-size: 12px;
            }

            .table th,
            .table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1 class="header-title">🛠️ Dev Control Panel</h1>
            <span class="dev-badge">Master Admin</span>
        </div>
        <div class="header-right">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($devAdmin['full_name']); ?></div>
                <div class="user-role">Developer Access</div>
            </div>
            <a href="dev_logout.php" class="btn btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($actionMessage): ?>
            <div class="alert alert-<?php echo $actionType; ?>">
                <?php echo $actionType === 'success' ? '✅' : '❌'; ?>
                <span><?php echo htmlspecialchars($actionMessage); ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-subtext"><?php echo number_format($stats['active_users']); ?> active last 7 days</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Workshops</div>
                <div class="stat-value"><?php echo number_format($stats['total_workshops']); ?></div>
                <div class="stat-subtext">Active dashboards</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Entries</div>
                <div class="stat-value"><?php echo number_format($stats['total_entries']); ?></div>
                <div class="stat-subtext">Across all workshops</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Monthly Revenue</div>
                <div class="stat-value">€<?php echo number_format($stats['revenue_monthly'], 2); ?></div>
                <div class="stat-subtext">+ €<?php echo number_format($stats['revenue_yearly'], 2); ?> yearly</div>
            </div>
        </div>

        <!-- Subscription Breakdown -->
        <div class="section">
            <h2 class="section-title">📊 Subscription Breakdown</h2>
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="stat-card">
                    <div class="stat-label">Free Plan</div>
                    <div class="stat-value"><?php echo $stats['subscription_breakdown']['free']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Premium</div>
                    <div class="stat-value"><?php echo $stats['subscription_breakdown']['premium']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Enterprise</div>
                    <div class="stat-value"><?php echo $stats['subscription_breakdown']['enterprise']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Cancelled</div>
                    <div class="stat-value"><?php echo $stats['subscription_breakdown']['cancelled']; ?></div>
                </div>
            </div>
        </div>

        <!-- All Users -->
        <div class="section">
            <h2 class="section-title">👥 All Users</h2>
            <?php if (empty($allUsers)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👤</div>
                    <p>No users registered yet</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Email</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(substr($user['id'] ?? 'N/A', 0, 8)); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $plan = $user['subscription']['plan_id'] ?? 'free';
                                    $badgeClass = 'badge-' . $plan;
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($plan); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $status = $user['subscription']['status'] ?? 'active';
                                    $cancelAtPeriodEnd = $user['subscription']['cancel_at_period_end'] ?? false;

                                    // Show cancelled if either status is canceled OR cancel_at_period_end is true
                                    if ($status === 'canceled' || $status === 'cancelled' || $cancelAtPeriodEnd) {
                                        $statusClass = 'badge-cancelled';
                                        $displayStatus = $cancelAtPeriodEnd ? 'Cancelling' : 'Cancelled';
                                    } else {
                                        $statusClass = 'badge-' . ($status === 'active' || $status === 'trialing' ? 'active' : 'cancelled');
                                        $displayStatus = ucfirst($status);
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>"><?php echo $displayStatus; ?></span>
                                </td>
                                <td><?php
                                    $createdAt = $user['created_at'] ?? null;
                                    echo $createdAt ? htmlspecialchars(date('d.m.Y', is_numeric($createdAt) ? $createdAt : strtotime($createdAt))) : 'N/A';
                                ?></td>
                                <td><?php
                                    $lastLogin = $user['last_login'] ?? null;
                                    echo $lastLogin ? htmlspecialchars(date('d.m.Y H:i', is_numeric($lastLogin) ? $lastLogin : strtotime($lastLogin))) : 'Never';
                                ?></td>
                                <td>
                                    <button class="btn btn-primary btn-small" onclick="sendResetLink('<?php echo htmlspecialchars($user['id'] ?? ''); ?>', '<?php echo htmlspecialchars($user['email'] ?? ''); ?>')">
                                        🔐 Reset Password
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Active Workshops -->
        <div class="section">
            <h2 class="section-title">🎯 Active Workshops</h2>
            <?php if (empty($allWorkshops)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <p>No workshops created yet</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Workshop Title</th>
                            <th>Owner Email</th>
                            <th>Categories</th>
                            <th>Entries</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allWorkshops as $workshop): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($workshop['title']); ?></td>
                                <td><?php echo htmlspecialchars($workshop['user_email']); ?></td>
                                <td><?php echo $workshop['categories_count']; ?></td>
                                <td><?php echo $workshop['entries_count']; ?></td>
                                <td>
                                    <a href="index.php?u=<?php echo htmlspecialchars($workshop['user_id']); ?>" target="_blank" class="btn btn-primary btn-small">
                                        👁️ View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Password Reset Modal -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">🔐 Password Reset Link Generated</div>
            <div class="modal-body">
                <p><strong>Email:</strong> <span id="resetEmail"></span></p>
                <p style="margin-top: 15px;"><strong>Reset Link:</strong></p>
                <div class="code-box" id="resetLink"></div>
                <p style="font-size: 12px; color: #808080; margin-top: 10px;">
                    Send this link to the user. It expires in 1 hour.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="copyResetLink()">📋 Copy Link</button>
                <button class="btn btn-logout" onclick="closeResetModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Hidden form for sending reset link -->
    <form id="resetForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" value="send_reset_link">
        <input type="hidden" name="user_id" id="resetUserId">
    </form>

    <script>
        let currentResetLink = '';

        function sendResetLink(userId, email) {
            if (!confirm('Generate password reset link for ' + email + '?')) {
                return;
            }

            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetForm').submit();
        }

        function showResetModal(email, link) {
            currentResetLink = link;
            document.getElementById('resetEmail').textContent = email;
            document.getElementById('resetLink').textContent = link;
            document.getElementById('resetModal').classList.add('show');
        }

        function closeResetModal() {
            document.getElementById('resetModal').classList.remove('show');
        }

        function copyResetLink() {
            navigator.clipboard.writeText(currentResetLink).then(() => {
                alert('Reset link copied to clipboard!');
            });
        }

        // Auto-show modal if reset link was generated
        <?php if ($actionType === 'success' && isset($_POST['action']) && $_POST['action'] === 'send_reset_link'): ?>
            <?php
            $result = generatePasswordResetTokenForUser($_POST['user_id']);
            if ($result['success']):
            ?>
            showResetModal('<?php echo htmlspecialchars($result['email']); ?>', '<?php echo htmlspecialchars($result['link']); ?>');
            <?php endif; ?>
        <?php endif; ?>

        // Close modal on outside click
        document.getElementById('resetModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeResetModal();
            }
        });
    </script>
</body>
</html>
