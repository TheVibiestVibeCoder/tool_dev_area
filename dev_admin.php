<?php
/**
 * Dev Admin Control Panel - Adapted to Workshop Control Design Language
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

// Helper functions (Logic remains identical to your source)
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }
        .section-panel h2 {
            font-family: var(--font-head); font-size: 2rem; margin: 0 0 1.5rem 0;
            border-bottom: 1px solid var(--text-main); padding-bottom: 10px;
        }

        /* --- TABLE STYLE --- */
        .data-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .data-table th {
            font-family: var(--font-head); text-align: left; padding: 12px;
            color: var(--text-muted); border-bottom: 2px solid var(--text-main);
            letter-spacing: 1px; font-size: 1.1rem;
        }
        .data-table td { padding: 15px 12px; border-bottom: 1px solid var(--border-color); font-size: 0.95rem; }
        .data-table tr:hover { background: #fafafa; }

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
            background: #fff; padding: 3rem; max-width: 700px; width: 90%;
            border-top: 10px solid var(--text-main); position: relative;
        }
        .code-box {
            background: #f8f8f8; padding: 15px; font-family: monospace;
            border: 1px solid var(--border-color); word-break: break-all; margin: 1.5rem 0;
        }

        .alert {
            padding: 1.5rem; margin-bottom: 2rem; border-left: 5px solid; font-weight: 600;
        }
        .alert-success { background: #e8f5e9; border-color: var(--color-green); color: #1b5e20; }
        .alert-error { background: #ffebee; border-color: var(--color-red); color: #b71c1c; }

        @media (max-width: 900px) {
            .admin-header { flex-direction: column; align-items: flex-start; gap: 1.5rem; padding: 1.5rem; }
            .admin-header h1 { font-size: 2.5rem; }
            .data-table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>

<div class="container">
    <header class="admin-header">
        <div class="header-title-group">
            <span class="subtitle">Platform Master Control &bull; DEV ACCESS</span>
            <h1>Dev Dashboard</h1>
        </div>
        <div class="header-actions">
            <div style="text-align: right; margin-right: 20px; font-family: var(--font-head);">
                <div style="font-size: 1.2rem;"><?= htmlspecialchars($devAdmin['full_name']) ?></div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">SUPERUSER</div>
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
        <div class="stat-card">
            <div class="stat-label">Total Platform Users</div>
            <div class="stat-value"><?= number_format($stats['total_users']); ?></div>
            <div class="stat-subtext"><?= number_format($stats['active_users']); ?> active this week</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Running Workshops</div>
            <div class="stat-value"><?= number_format($stats['total_workshops']); ?></div>
            <div class="stat-subtext">Configured environments</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Data Entries</div>
            <div class="stat-value"><?= number_format($stats['total_entries']); ?></div>
            <div class="stat-subtext">Total records stored</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Monthly MRR</div>
            <div class="stat-value">€<?= number_format($stats['revenue_monthly'], 2); ?></div>
            <div class="stat-subtext">+ €<?= number_format($stats['revenue_yearly'], 2); ?> ARR</div>
        </div>
    </div>

    <div class="section-panel">
        <h2>Plan Distribution</h2>
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            <div class="stat-card" style="border-left-color: #eee;">
                <div class="stat-label">Free</div>
                <div class="stat-value" style="font-size: 2rem;"><?= $stats['subscription_breakdown']['free']; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #f1c40f;">
                <div class="stat-label">Premium</div>
                <div class="stat-value" style="font-size: 2rem;"><?= $stats['subscription_breakdown']['premium']; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #9b59b6;">
                <div class="stat-label">Enterprise</div>
                <div class="stat-value" style="font-size: 2rem;"><?= $stats['subscription_breakdown']['enterprise']; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: var(--color-red);">
                <div class="stat-label">Churned</div>
                <div class="stat-value" style="font-size: 2rem;"><?= $stats['subscription_breakdown']['cancelled']; ?></div>
            </div>
        </div>
    </div>

    <div class="section-panel">
        <h2>User Database</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID (Short)</th>
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
                    <td style="font-family: monospace;"><?= htmlspecialchars(substr($user['id'] ?? 'N/A', 0, 8)); ?></td>
                    <td><strong><?= htmlspecialchars($user['email'] ?? 'N/A'); ?></strong></td>
                    <td>
                        <?php $plan = $user['subscription']['plan_id'] ?? 'free'; ?>
                        <span class="badge badge-<?= $plan; ?>"><?= strtoupper($plan); ?></span>
                    </td>
                    <td>
                        <?php
                        $status = $user['subscription']['status'] ?? 'active';
                        $cancelAtPeriodEnd = $user['subscription']['cancel_at_period_end'] ?? false;
                        $isCancelled = ($status === 'canceled' || $status === 'cancelled' || $cancelAtPeriodEnd);
                        ?>
                        <span class="badge <?= $isCancelled ? 'badge-cancelled' : 'badge-active'; ?>">
                            <?= $isCancelled ? ($cancelAtPeriodEnd ? 'PENDING CANCEL' : 'CANCELLED') : 'ACTIVE'; ?>
                        </span>
                    </td>
                    <td><?= $user['last_login'] ? date('d.m.Y H:i', is_numeric($user['last_login']) ? $user['last_login'] : strtotime($user['last_login'])) : 'Never'; ?></td>
                    <td style="text-align: right;">
                        <button class="btn btn-primary" style="padding: 6px 12px; font-size: 0.9rem;" onclick="sendResetLink('<?= $user['id'] ?>', '<?= $user['email'] ?>')">RESET PWD</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section-panel">
        <h2>Platform Activity: Live Workshops</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Owner</th>
                    <th>Categories</th>
                    <th>Entries</th>
                    <th style="text-align: right;">View</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allWorkshops as $workshop): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($workshop['title']); ?></strong></td>
                    <td><?= htmlspecialchars($workshop['user_email']); ?></td>
                    <td><?= $workshop['categories_count']; ?></td>
                    <td><?= $workshop['entries_count']; ?></td>
                    <td style="text-align: right;">
                        <a href="index.php?u=<?= urlencode($workshop['user_id']); ?>" target="_blank" class="btn">OPEN VIEW</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="resetModal" class="modal">
    <div class="modal-content">
        <h2 style="font-family: var(--font-head); font-size: 2.5rem; margin-top: 0;">RESET LINK GENERATED</h2>
        <p>User: <strong id="resetEmail"></strong></p>
        <div class="code-box" id="resetLink"></div>
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button class="btn btn-primary" onclick="copyResetLink()">COPY TO CLIPBOARD</button>
            <button class="btn" onclick="closeResetModal()">CLOSE</button>
        </div>
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
        if (confirm('GENERATE NEW ACCESS LINK FOR: ' + email + '?')) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetForm').submit();
        }
    }
    function showResetModal(email, link) {
        currentResetLink = link;
        document.getElementById('resetEmail').textContent = email;
        document.getElementById('resetLink').textContent = link;
        document.getElementById('resetModal').classList.add('show');
    }
    function closeResetModal() { document.getElementById('resetModal').classList.remove('show'); }
    function copyResetLink() {
        navigator.clipboard.writeText(currentResetLink).then(() => alert('Copied!'));
    }

    <?php if ($actionType === 'success' && isset($_POST['action']) && $_POST['action'] === 'send_reset_link'): ?>
        <?php $result = generatePasswordResetTokenForUser($_POST['user_id']); if ($result['success']): ?>
            showResetModal('<?= htmlspecialchars($result['email']); ?>', '<?= htmlspecialchars($result['link']); ?>');
        <?php endif; ?>
    <?php endif; ?>
</script>
</body>
</html>