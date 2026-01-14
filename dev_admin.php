<?php
/**
 * Dev Admin Control Panel - Adapted & Mobile Optimized
 * No-scroll card layout for mobile viewports
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

$csrfToken = generateCSRFToken();
$allUsers = getAllUsersDetailed();
$stats = calculatePlatformStats($allUsers);
$allWorkshops = getAllWorkshops($allUsers);

// --- Logic remains untouched per your strict instruction ---
function getAllUsersDetailed() {
    if (!file_exists(USERS_FILE)) return [];
    $data = json_decode(file_get_contents(USERS_FILE), true);
    return $data['users'] ?? [];
}

function calculatePlatformStats($users) {
    $stats = [
        'total_users' => count($users), 'active_users' => 0, 'total_entries' => 0, 'total_workshops' => 0,
        'subscription_breakdown' => ['free' => 0, 'premium' => 0, 'enterprise' => 0, 'trial' => 0, 'cancelled' => 0],
        'revenue_monthly' => 0, 'revenue_yearly' => 0
    ];
    $oneWeekAgo = time() - (7 * 24 * 60 * 60);
    foreach ($users as $user) {
        if (isset($user['last_login'])) {
            $lastLogin = is_numeric($user['last_login']) ? $user['last_login'] : strtotime($user['last_login']);
            if ($lastLogin && $lastLogin > $oneWeekAgo) $stats['active_users']++;
        }
        $plan = $user['subscription']['plan_id'] ?? 'free';
        $status = $user['subscription']['status'] ?? 'active';
        $cancelAtPeriodEnd = $user['subscription']['cancel_at_period_end'] ?? false;
        if ($status === 'canceled' || $status === 'cancelled' || $cancelAtPeriodEnd) {
            $stats['subscription_breakdown']['cancelled']++;
        } elseif ($status === 'active' || $status === 'trialing') {
            if (isset($stats['subscription_breakdown'][$plan])) $stats['subscription_breakdown'][$plan]++;
            if ($plan === 'premium' && !$cancelAtPeriodEnd) {
                $billingCycle = $user['subscription']['billing_cycle'] ?? 'monthly';
                if ($billingCycle === 'monthly') $stats['revenue_monthly'] += 19.99;
                else $stats['revenue_yearly'] += 203.89;
            }
        }
        $userId = $user['id'] ?? null;
        if ($userId) {
            $userDataDir = __DIR__ . '/data/' . $userId;
            if (is_dir($userDataDir)) {
                if (file_exists($userDataDir . '/config.json')) $stats['total_workshops']++;
                $datenFile = $userDataDir . '/daten.json';
                if (file_exists($datenFile)) {
                    $data = json_decode(file_get_contents($datenFile), true);
                    if (isset($data['data'])) $stats['total_entries'] += count($data['data']);
                }
            }
        }
    }
    return $stats;
}

function getAllWorkshops($users) {
    $workshops = [];
    foreach ($users as $user) {
        $userId = $user['id'] ?? null;
        if (!$userId) continue;
        $userDataDir = __DIR__ . '/data/' . $userId;
        $configFile = $userDataDir . '/config.json';
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            if ($config) {
                $entryCount = 0;
                $datenFile = $userDataDir . '/daten.json';
                if (file_exists($datenFile)) {
                    $datenData = json_decode(file_get_contents($datenFile), true);
                    if (isset($datenData['data'])) $entryCount = count($datenData['data']);
                }
                $title = strip_tags($config['header_title'] ?? $config['title'] ?? 'Untitled Workshop');
                $workshops[] = [
                    'user_id' => $userId, 'user_email' => $user['email'] ?? 'N/A',
                    'title' => str_replace("\n", ' ', $title),
                    'categories_count' => isset($config['categories']) ? count($config['categories']) : 0,
                    'entries_count' => $entryCount, 'created_at' => $user['created_at'] ?? 'N/A'
                ];
            }
        }
    }
    return $workshops;
}

function generatePasswordResetTokenForUser($userId) {
    if (!file_exists(USERS_FILE)) return ['success' => false, 'message' => 'Users file not found'];
    $data = json_decode(file_get_contents(USERS_FILE), true);
    $userFound = false; $userEmail = '';
    foreach ($data['users'] as $user) {
        if ($user['id'] === $userId) { $userFound = true; $userEmail = $user['email']; break; }
    }
    if (!$userFound) return ['success' => false, 'message' => 'User not found'];
    $token = bin2hex(random_bytes(32)); $expiry = time() + 3600;
    $tokensFile = RESET_TOKENS_FILE;
    $tokens = file_exists($tokensFile) ? json_decode(file_get_contents($tokensFile), true) : [];
    $tokens[] = ['token' => $token, 'user_id' => $userId, 'email' => $userEmail, 'expiry' => $expiry, 'used' => false, 'created_at' => date('Y-m-d H:i:s'), 'created_by' => 'dev_admin'];
    if (file_put_contents($tokensFile, json_encode($tokens, JSON_PRETTY_PRINT))) {
        $resetLink = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;
        return ['success' => true, 'message' => 'Password reset link generated', 'link' => $resetLink, 'email' => $userEmail];
    }
    return ['success' => false, 'message' => 'Failed to save reset token'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dev Panel | Live Situation Room</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        * { box-sizing: border-box; } 
        :root {
            --bg-body: #f5f5f5;
            --bg-card: #ffffff;
            --text-main: #111111;
            --text-muted: #666666;
            --border-color: #e0e0e0;
            --color-green: #27ae60; 
            --color-red: #e74c3c;   
            --font-head: 'Bebas Neue', sans-serif;
            --font-body: 'Inter', sans-serif;
            --radius-btn: 4px;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-body);
            margin: 0; padding: 0;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; width: 100%; }

        /* --- HEADER --- */
        .admin-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            background: var(--bg-card); padding: 2rem 3rem; margin-bottom: 2rem;
            border: 1px solid var(--border-color); border-bottom: 3px solid var(--text-main);
        }
        .admin-header h1 { 
            font-family: var(--font-head); font-size: 3.5rem; margin: 0; 
            line-height: 0.9; color: var(--text-main); text-transform: uppercase;
        }
        .subtitle { 
            color: var(--text-muted); text-transform: uppercase; 
            letter-spacing: 2px; font-size: 0.85rem; font-weight: 600; 
            display: block; margin-bottom: 0.5rem; font-family: var(--font-head);
        }

        /* --- BUTTONS --- */
        .btn {
            padding: 12px 20px; background: #fff; border: 1px solid var(--border-color);
            color: var(--text-muted); text-decoration: none; font-family: var(--font-head);
            font-size: 1.1rem; letter-spacing: 1px; cursor: pointer; 
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: var(--radius-btn); line-height: 1; transition: 0.2s;
            min-height: 44px;
        }
        .btn:hover { border-color: var(--text-main); color: var(--text-main); transform: translateY(-1px); }
        .btn-primary { background: var(--text-main); color: #fff; border-color: var(--text-main); }
        .btn-danger { color: var(--color-red); border-color: rgba(231, 76, 60, 0.3); }
        .btn-danger:hover { background: var(--color-red); color: white; }

        /* --- STATS GRID --- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem; margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--bg-card); border: 1px solid var(--border-color);
            padding: 1.5rem 2rem; border-left: 5px solid var(--text-main);
        }
        .stat-label { 
            font-family: var(--font-head); font-size: 1.1rem; color: var(--text-muted); 
            letter-spacing: 1px; margin-bottom: 5px;
        }
        .stat-value { 
            font-family: var(--font-head); font-size: 3rem; line-height: 1; color: var(--text-main); 
        }
        .stat-subtext { font-size: 0.8rem; color: var(--text-muted); margin-top: 5px; text-transform: uppercase; }

        /* --- SECTIONS --- */
        .section-panel {
            background: var(--bg-card); border: 1px solid var(--border-color);
            padding: 2rem; margin-bottom: 2rem;
        }
        .section-panel h2 {
            font-family: var(--font-head); font-size: 2rem; margin: 0 0 1.5rem 0;
            border-bottom: 1px solid var(--text-main); padding-bottom: 10px;
        }

        /* --- DATA TABLE & MOBILE CARDS --- */
        .table-wrapper { width: 100%; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            font-family: var(--font-head); text-align: left; padding: 12px;
            color: var(--text-muted); border-bottom: 2px solid var(--text-main);
            letter-spacing: 1px; font-size: 1.1rem;
        }
        .data-table td { padding: 15px 12px; border-bottom: 1px solid var(--border-color); font-size: 0.95rem; }

        /* Mobile Responsive Strategy: Table -> Cards */
        @media (max-width: 900px) {
            .container { padding: 1rem; }
            .admin-header { flex-direction: column; align-items: flex-start; padding: 1.5rem; }
            .admin-header h1 { font-size: 2.5rem; }

            .stats-grid { grid-template-columns: 1fr; gap: 10px; }
            .section-panel { padding: 1.2rem; }

            /* Hide table headers on mobile */
            .data-table thead { display: none; }
            
            /* Transform rows into individual cards */
            .data-table tr { 
                display: flex; flex-direction: column; 
                border: 1px solid var(--border-color); 
                margin-bottom: 1.5rem; padding: 1rem; 
                background: #fff;
            }
            .data-table td { 
                display: flex; justify-content: space-between; align-items: center;
                padding: 8px 0; border-bottom: 1px dashed #eee;
            }
            .data-table td::before { 
                content: attr(data-label); 
                font-family: var(--font-head); color: var(--text-muted); 
                font-size: 0.9rem; letter-spacing: 1px;
            }
            .data-table td:last-child { border-bottom: none; padding-top: 15px; }
        }

        /* --- BADGES --- */
        .badge {
            font-family: var(--font-head); font-size: 0.9rem; padding: 4px 10px;
            border-radius: 2px; letter-spacing: 0.5px; display: inline-block;
        }
        .badge-premium { background: #f1c40f; color: #000; }
        .badge-enterprise { background: #9b59b6; color: #fff; }
        .badge-free { background: #eee; color: #666; }
        .badge-active { color: var(--color-green); border: 1px solid var(--color-green); }
        .badge-cancelled { color: var(--color-red); border: 1px solid var(--color-red); }

        /* --- MODAL --- */
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: #fff; padding: 2rem; max-width: 600px; width: 95%;
            border-top: 8px solid var(--text-main);
        }
        .code-box {
            background: #f8f8f8; padding: 15px; font-family: monospace; font-size: 0.85rem;
            border: 1px solid var(--border-color); word-break: break-all; margin: 1rem 0;
        }
    </style>
</head>
<body>

<div class="container">
    <header class="admin-header">
        <div>
            <span class="subtitle">Platform Master &bull; DEV ACCESS</span>
            <h1>Dev Dashboard</h1>
        </div>
        <div style="display: flex; gap: 15px; align-items: center; margin-top: 15px;">
            <div class="mobile-hide" style="text-align: right; font-family: var(--font-head);">
                <div style="font-size: 1.1rem;"><?= htmlspecialchars($devAdmin['full_name']) ?></div>
            </div>
            <a href="dev_logout.php" class="btn btn-danger">System Logout</a>
        </div>
    </header>

    <?php if ($actionMessage): ?>
        <div class="alert alert-<?= $actionType; ?>">
            <?= $actionType === 'success' ? '✔' : '✘'; ?> <?= htmlspecialchars($actionMessage); ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-label">Total Users</div><div class="stat-value"><?= number_format($stats['total_users']); ?></div></div>
        <div class="stat-card"><div class="stat-label">Active Workshops</div><div class="stat-value"><?= number_format($stats['total_workshops']); ?></div></div>
        <div class="stat-card"><div class="stat-label">Monthly MRR</div><div class="stat-value">€<?= number_format($stats['revenue_monthly'], 2); ?></div></div>
    </div>

    <div class="section-panel">
        <h2>User Database</h2>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Email Address</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Last Activity</th>
                        <th style="text-align: right;">Operations</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allUsers as $user): ?>
                    <tr>
                        <td data-label="EMAIL"><strong><?= htmlspecialchars($user['email'] ?? 'N/A'); ?></strong></td>
                        <td data-label="PLAN">
                            <?php $plan = $user['subscription']['plan_id'] ?? 'free'; ?>
                            <span class="badge badge-<?= $plan; ?>"><?= strtoupper($plan); ?></span>
                        </td>
                        <td data-label="STATUS">
                            <?php
                            $status = $user['subscription']['status'] ?? 'active';
                            $cancelAtPeriodEnd = $user['subscription']['cancel_at_period_end'] ?? false;
                            $isCancelled = ($status === 'canceled' || $status === 'cancelled' || $cancelAtPeriodEnd);
                            ?>
                            <span class="badge <?= $isCancelled ? 'badge-cancelled' : 'badge-active'; ?>">
                                <?= $isCancelled ? 'CANCELLED' : 'ACTIVE'; ?>
                            </span>
                        </td>
                        <td data-label="ACTIVITY"><?= $user['last_login'] ? date('d.m.y', is_numeric($user['last_login']) ? $user['last_login'] : strtotime($user['last_login'])) : 'Never'; ?></td>
                        <td style="text-align: right;">
                            <button class="btn btn-primary" style="width: 100%;" onclick="sendResetLink('<?= $user['id'] ?>', '<?= $user['email'] ?>')">RESET PWD</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="section-panel">
        <h2>Live Workshops</h2>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Workshop Title</th>
                        <th>Owner</th>
                        <th>Entries</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allWorkshops as $workshop): ?>
                    <tr>
                        <td data-label="TITLE"><strong><?= htmlspecialchars($workshop['title']); ?></strong></td>
                        <td data-label="OWNER"><?= htmlspecialchars($workshop['user_email']); ?></td>
                        <td data-label="DATA"><?= $workshop['entries_count']; ?> entries</td>
                        <td style="text-align: right;">
                            <a href="index.php?u=<?= urlencode($workshop['user_id']); ?>" target="_blank" class="btn" style="width: 100%;">OPEN VIEW</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="resetModal" class="modal">
    <div class="modal-content">
        <h2 style="font-family: var(--font-head); font-size: 2rem;">RESET LINK READY</h2>
        <div class="code-box" id="resetLink"></div>
        <button class="btn btn-primary" style="width: 100%; margin-bottom: 10px;" onclick="copyResetLink()">COPY LINK</button>
        <button class="btn" style="width: 100%;" onclick="closeResetModal()">CLOSE</button>
    </div>
</div>

<form id="resetForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="action" value="send_reset_link">
    <input type="hidden" name="user_id" id="resetUserId">
</form>

<script>
    let currentResetLink = '';
    function sendResetLink(userId, email) {
        if (confirm('GENERATE RESET LINK?')) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetForm').submit();
        }
    }
    function showResetModal(email, link) {
        currentResetLink = link;
        document.getElementById('resetLink').textContent = link;
        document.getElementById('resetModal').classList.add('show');
    }
    function closeResetModal() { document.getElementById('resetModal').classList.remove('show'); }
    function copyResetLink() { navigator.clipboard.writeText(currentResetLink).then(() => alert('Copied!')); }

    <?php if ($actionType === 'success' && isset($_POST['action']) && $_POST['action'] === 'send_reset_link'): ?>
        <?php $result = generatePasswordResetTokenForUser($_POST['user_id']); if ($result['success']): ?>
            showResetModal('<?= htmlspecialchars($result['email']); ?>', '<?= htmlspecialchars($result['link']); ?>');
        <?php endif; ?>
    <?php endif; ?>
</script>
</body>
</html>