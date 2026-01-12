<?php
// ============================================
// ADMIN.PHP - User Dashboard (Multi-tenant)
// ============================================

require_once 'file_handling_robust.php';
require_once 'user_auth.php';
require_once 'security_helpers.php';

// Set security headers
setSecurityHeaders();

// Require authentication
requireAuth();

// Get current user
$current_user = getCurrentUser();
$user_id = $current_user['id'];

// Load user-specific config and data
$config_file = getUserFile($user_id, 'config.json');
$data_file = getUserFile($user_id, 'daten.json');

$config = loadConfig($config_file);

// Build gruppen_labels from config
$gruppen_labels = [];
$logoUrl = ''; // Default (no logo)
if ($config && isset($config['categories'])) {
    foreach ($config['categories'] as $category) {
        $gruppen_labels[$category['key']] = $category['abbreviation'];
    }
    $logoUrl = $config['logo_url'] ?? $logoUrl;
} else {
    // Fallback if config cannot be loaded
    $gruppen_labels = [
        'general' => 'GEN'
    ];
}

// --- PDF EXPORT MODE ---
if (isset($_GET['mode']) && $_GET['mode'] === 'pdf') {
    $data = safeReadJson($data_file);
    $config = loadConfig($config_file);
    $pdf_labels = [];
    $pdf_title = 'Workshop Protocol'; 

    if ($config && isset($config['categories'])) {
        foreach ($config['categories'] as $category) {
            if (isset($category['display_name'])) {
                $pdf_labels[$category['key']] = $category['display_name'];
            } else {
                $icon = $category['icon'] ?? '';
                $name = $category['name'] ?? $category['key'];
                $pdf_labels[$category['key']] = $icon . ' ' . $name;
            }
        }
        if (isset($config['header_title'])) {
            $pdf_title = $config['header_title'];
        }
    } else {
        $pdf_labels = ['general' => '💡 General'];
        $pdf_title = 'Live Situation Room';
    }
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title><?= htmlspecialchars($pdf_title) ?> - Workshop Protokoll</title>
        <style>
            body { font-family: 'Helvetica', sans-serif; color: #111; line-height: 1.4; padding: 40px; max-width: 900px; margin: 0 auto; }
            h1 { font-family: 'Helvetica', sans-serif; font-size: 2.5rem; border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 5px; color: #111; text-transform: uppercase; font-weight: 800; letter-spacing: -1px; }
            .meta { color: #666; font-size: 0.8rem; margin-bottom: 4rem; text-transform: uppercase; letter-spacing: 1px; }
            .section { margin-bottom: 3rem; page-break-inside: avoid; }
            .section-title { font-size: 1.2rem; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; border-left: 4px solid #111; padding-left: 15px; margin-bottom: 1.5rem; color: #111; }
            .entry { margin-bottom: 1.5rem; padding: 0 0 15px 0; border-bottom: 1px solid #eee; }
            .entry-text { font-size: 1rem; margin-bottom: 5px; color: #000; }
            .entry-meta { font-size: 0.7rem; color: #999; text-transform: uppercase; letter-spacing: 0.5px; }
            .no-data { color: #999; font-style: italic; font-size: 0.8rem; }
        </style>
    </head>
    <body onload="window.print()">
        <h1><?= htmlspecialchars($pdf_title) ?></h1>
        <div class="meta">PROTOCOL • Generated: <?= date('d.m.Y H:i') ?></div>

        <?php foreach ($pdf_labels as $key => $label): ?>
            <div class="section">
                <div class="section-title"><?= htmlspecialchars($label) ?></div>
                <?php
                $hasEntries = false;
                foreach ($data as $entry) {
                    if (($entry['thema'] ?? '') === $key) {
                        $hasEntries = true;
                        $status = ($entry['visible'] ?? false) ? "LIVE" : "DRAFT";
                        echo '<div class="entry">';
                        echo '<div class="entry-text">' . nl2br(htmlspecialchars($entry['text'])) . '</div>';
                        echo '<div class="entry-meta">' . date('H:i', $entry['zeit']) . ' • ' . $status . '</div>';
                        echo '</div>';
                    }
                }
                if (!$hasEntries) echo '<div class="no-data">No entries found.</div>';
                ?>
            </div>
        <?php endforeach; ?>
    </body>
    </html>
    <?php
    exit;
}

// --- ACTION HANDLER ---
{
    $is_ajax = isset($_REQUEST['ajax']);
    $req = $_REQUEST;

    if (isset($req['delete'])) {
        $id = $req['delete'];
        atomicUpdate($data_file, function($data) use ($id) {
            return array_values(array_filter($data, fn($e) => $e['id'] !== $id));
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    if (isset($req['deleteall']) && $req['deleteall'] === 'confirm') {
        atomicUpdate($data_file, function($data) { return []; });
        if ($is_ajax) { echo "OK"; exit; }
    }

    if (isset($req['toggle_id'])) {
        $id = $req['toggle_id'];
        atomicUpdate($data_file, function($data) use ($id) {
            foreach ($data as &$entry) {
                if ($entry['id'] === $id) { $entry['visible'] = !($entry['visible'] ?? false); }
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    if (isset($req['toggle_focus'])) {
        $id = $req['toggle_focus'];
        atomicUpdate($data_file, function($data) use ($id) {
            foreach ($data as &$entry) {
                if ($entry['id'] === $id) {
                    $entry['focus'] = !($entry['focus'] ?? false);
                } else {
                    $entry['focus'] = false;
                }
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    if (isset($req['action']) && $req['action'] === 'edit' && isset($req['id']) && isset($req['new_text'])) {
        $id = $req['id'];
        $new_text = trim($req['new_text']);
        if (!empty($new_text)) {
            atomicUpdate($data_file, function($data) use ($id, $new_text) {
                foreach ($data as &$entry) {
                    if ($entry['id'] === $id) { $entry['text'] = $new_text; }
                }
                return $data;
            });
            if ($is_ajax) { echo "OK"; exit; }
        } else {
            if ($is_ajax) { echo "ERROR: Text cannot be empty"; exit; }
        }
    }

    if (isset($req['action']) && $req['action'] === 'move' && isset($req['id']) && isset($req['new_thema'])) {
        $id = $req['id'];
        $new_thema = $req['new_thema'];
        atomicUpdate($data_file, function($data) use ($id, $new_thema) {
            foreach ($data as &$entry) {
                if ($entry['id'] === $id) { $entry['thema'] = $new_thema; }
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    if (isset($req['action_col']) && $req['action_col'] === 'show' && isset($req['col'])) {
        $col = $req['col'];
        atomicUpdate($data_file, function($data) use ($col) {
            foreach ($data as &$entry) {
                if ($entry['thema'] === $col) { $entry['visible'] = true; }
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    if (isset($req['action_col']) && $req['action_col'] === 'hide' && isset($req['col'])) {
        $col = $req['col'];
        atomicUpdate($data_file, function($data) use ($col) {
            foreach ($data as &$entry) {
                if ($entry['thema'] === $col) { $entry['visible'] = false; }
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    if (isset($req['action_all']) && $req['action_all'] === 'show') {
        atomicUpdate($data_file, function($data) {
            foreach ($data as &$entry) { $entry['visible'] = true; }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    if (isset($req['action_all']) && $req['action_all'] === 'hide') {
        atomicUpdate($data_file, function($data) {
            foreach ($data as &$entry) { $entry['visible'] = false; }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }
}

// Data for Display
$data = safeReadJson($data_file);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Panel | Live Situation Room</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        /* --- RESET & VARIABLES --- */
        * { box-sizing: border-box; } 
        
        :root {
            --bg-body: #f5f5f5;
            --bg-card: #ffffff;
            --text-main: #111111;
            --text-muted: #666666;
            --border-color: #e0e0e0;
            
            --color-green: #27ae60; 
            --color-red: #e74c3c;   
            --color-focus: #f1c40f; 
            
            --font-head: 'Bebas Neue', sans-serif;
            --font-body: 'Inter', sans-serif;
            
            --radius-btn: 4px;
            --shadow: 0 4px 6px rgba(0,0,0,0.03);
            --shadow-hover: 0 8px 20px rgba(0,0,0,0.06);
            --trans-speed: 0.2s;
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

        .container { 
            max-width: 1600px; 
            margin: 0 auto; 
            padding: 2rem; 
            width: 100%;
        }

        /* --- HEADER --- */
        .admin-header {
            display: flex; 
            justify-content: space-between; /* DESKTOP: Pushes Title left, Buttons right */
            align-items: flex-end;
            background: var(--bg-card);
            padding: 2rem 3rem; 
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            border-bottom: 3px solid var(--text-main);
        }
        
        .header-title-group {
            /* Desktop: takes content width */
        }

        .admin-header h1 { 
            font-family: var(--font-head); font-size: 3.5rem; margin: 0; 
            line-height: 0.9; color: var(--text-main); font-weight: 400; 
            text-transform: uppercase;
        }
        .subtitle { 
            color: var(--text-muted); text-transform: uppercase; 
            letter-spacing: 2px; font-size: 0.85rem; font-weight: 600; 
            display: block; margin-bottom: 0.5rem; 
            font-family: var(--font-head);
        }
        
        /* Desktop: Buttons aligned normally */
        .header-actions { 
            display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 5px; 
        }

        /* --- BUTTONS --- */
        .btn {
            padding: 12px 20px; 
            background: #fff; 
            border: 1px solid var(--border-color);
            color: var(--text-muted); 
            text-decoration: none; 
            font-family: var(--font-head);
            font-size: 1.1rem;
            letter-spacing: 1px;
            cursor: pointer; 
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: var(--radius-btn);
            line-height: 1;
            transition: all var(--trans-speed) cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 44px; 
            white-space: nowrap;
        }
        
        .btn:hover, .btn:active { 
            border-color: var(--text-main); 
            color: var(--text-main); 
            transform: translateY(-1px);
        }
        
        .btn-primary { background: var(--text-main); color: #fff; border-color: var(--text-main); }
        .btn-primary:hover { background: #333; color: #fff; }
        
        .btn-danger { color: var(--color-red); border-color: rgba(231, 76, 60, 0.3); }
        .btn-danger:hover { background: var(--color-red); color: white; border-color: var(--color-red); }
        
        .btn-success { color: var(--color-green); border-color: rgba(39, 174, 96, 0.3); font-weight: bold; }
        .btn-success:hover { background: var(--color-green); color: #fff; border-color: var(--color-green); }

        .btn-focus { color: #ccc; border-color: #eee; }
        .btn-focus:hover { color: var(--color-focus); border-color: var(--color-focus); }
        .btn-focus.is-focused { background: var(--color-focus); color: #fff; border-color: var(--color-focus); }

        /* --- INFO BOX --- */
        .info-box {
            background: #fff; border: 1px solid var(--border-color);
            padding: 20px 30px; margin-bottom: 24px;
            display: flex; flex-direction: column; gap: 15px;
            width: 100%;
        }
        .info-box h3 { 
            margin: 0; color: var(--text-main); font-family: var(--font-head); 
            font-size: 1.5rem; letter-spacing: 0.5px; font-weight: 400;
        }
        .link-row { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .link-label { font-family: var(--font-head); font-size: 1rem; color: var(--text-muted); min-width: 180px; }
        .link-input { 
            flex: 1; min-width: 250px; padding: 12px; font-family: 'Inter', monospace; font-size: 0.9rem; 
            border: 1px solid var(--border-color); background: #fafafa; color: #333;
            cursor: pointer; transition: 0.2s; border-radius: var(--radius-btn);
            width: 100%;
        }

        /* --- COMMAND PANEL --- */
        .command-panel {
            background: var(--bg-card); 
            border: 1px solid var(--border-color);
            padding: 2rem 3rem; margin-bottom: 2rem;
            box-shadow: var(--shadow);
            width: 100%;
        }
        .command-panel h3 { 
            margin: 0 0 1.5rem 0; font-family: var(--font-head); 
            font-weight: 400; color: var(--text-main); font-size: 2rem; 
            border-bottom: 1px solid var(--text-main); padding-bottom: 10px; 
        }
        
        .command-row { display: flex; gap: 4rem; align-items: flex-start; flex-wrap: wrap; }
        .command-col { flex: 1; min-width: 250px; }
        .command-label { 
            display: block; color: var(--text-muted); font-size: 0.9rem; 
            margin-bottom: 15px; font-family: var(--font-head); 
            letter-spacing: 1px;
        }

        .global-btns { display: flex; gap: 10px; width: 100%; }
        .global-btns .btn { flex: 1; justify-content: center; height: 50px; font-size: 1.2rem; }

        /* Sector Grid */
        .sector-container { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
            gap: 15px; 
            width: 100%;
        }

        .sector-ctrl {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 15px; background: #fff; border: 1px solid var(--border-color);
            transition: 0.2s; border-radius: var(--radius-btn);
            min-height: 50px;
        }
        .sector-ctrl:hover { border-color: #999; transform: translateY(-1px); }
        
        .sector-label { 
            font-size: 1.3rem; color: var(--text-main); 
            font-family: var(--font-head); line-height: 1;
        }
        
        .st-btn { 
            cursor: pointer; padding: 6px 12px; font-size: 0.9rem; 
            font-weight: 600; transition: all 0.2s; user-select: none; 
            border-radius: 2px; letter-spacing: 1px; color: #ccc;
        }
        
        .btn-on.active-on { color: var(--color-green); font-weight: 900; }
        .btn-off.active-off { color: var(--color-red); text-decoration: line-through; }

        /* --- FEED GRID --- */
        #admin-feed {
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); 
            gap: 1.5rem;
            width: 100%;
        }

        .feed-header { 
            display: flex; justify-content: space-between; align-items: flex-end; 
            margin-bottom: 20px; border-bottom: 2px solid var(--text-main); 
            padding-bottom: 10px; flex-wrap: wrap; gap: 10px;
        }
        .feed-header h2 { 
            font-family: var(--font-head); color: var(--text-main); 
            margin: 0; font-size: 2.5rem; line-height: 1; font-weight: 400; 
        }

        /* --- CARDS --- */
        .admin-card {
            background: var(--bg-card); 
            border: 1px solid var(--border-color);
            padding: 1.5rem; transition: 0.3s ease; position: relative;
            box-shadow: var(--shadow);
            display: flex; flex-direction: column;
            border-radius: var(--radius-btn);
            width: 100%;
        }
        
        .admin-card.status-live { 
            border: 1px solid var(--color-green); 
            box-shadow: 0 0 15px rgba(39, 174, 96, 0.1);
        }
        .admin-card.status-hidden { 
            border: 1px dashed #ccc; opacity: 0.75; background: #fafafa;
        }

        .card-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 1rem; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; 
            flex-wrap: wrap; gap: 5px;
        }
        
        .admin-select { 
            padding: 8px 0; background: transparent; border: none; border-bottom: 1px solid #eee;
            color: var(--text-muted); font-size: 0.9rem; width: 100%; max-width: 200px;
            font-family: var(--font-body); font-weight: 600; cursor: pointer;
        }
        .card-time { font-size: 0.8rem; color: #999; font-family: 'Inter', monospace; white-space: nowrap; }
        .card-body { 
            font-size: 1rem; margin-bottom: 1.5rem; line-height: 1.6; 
            min-height: 60px; word-break: break-word; color: var(--text-main); flex-grow: 1;
        }

        .entry-text-edit {
            width: 100%; min-height: 120px; padding: 12px;
            border: 1px solid var(--text-main); background: #fff;
            font-family: var(--font-body); font-size: 1rem; line-height: 1.6;
            color: var(--text-main); resize: vertical; box-sizing: border-box;
        }

        .card-actions { 
            display: grid; 
            grid-template-columns: 1fr 1fr 2fr auto; 
            gap: 10px; margin-top: auto; 
        }
        .card-actions .btn { padding: 10px; font-size: 0.9rem; width: 100%; }

        .card-edit-actions { display: flex; gap: 10px; margin-top: 10px; }
        .card-edit-actions .btn { flex: 1; }

        /* =========================================
           MOBILE OPTIMIZATIONS (Max Width 900px)
           ========================================= */
        @media (max-width: 900px) {
            .container { padding: 1rem; }
            
            /* Header Stacking for Mobile */
            .admin-header { 
                flex-direction: column; align-items: flex-start; 
                gap: 1.5rem; padding: 1.5rem; 
            }
            .admin-header h1 { font-size: 2.5rem; }
            
            .header-title-group { width: 100%; margin-bottom: 1rem; }

            /* Header buttons: 2x2 Grid */
            .header-actions { 
                width: 100%; display: grid; 
                grid-template-columns: 1fr 1fr; gap: 8px; margin-left: 0;
            }
            .header-actions .btn { width: 100%; height: 50px; font-size: 1rem; }
            /* Last button spans full width if odd number */
            .header-actions .btn:last-child:nth-child(odd) { grid-column: span 2; }

            /* Command Panel vertical stack */
            .command-panel { padding: 1.5rem; }
            .command-row { flex-direction: column; gap: 2rem; }
            .command-col { width: 100%; }

            .global-btns { gap: 15px; }
            .global-btns .btn { height: 60px; font-size: 1.4rem; }

            /* Sectors: Clean Vertical List for Mobile */
            .sector-container { 
                grid-template-columns: 1fr 1fr; /* 2 columns for thumb reach */
                gap: 10px;
            }
            .sector-ctrl {
                flex-direction: column; align-items: flex-start; justify-content: center;
                height: 80px; padding: 10px;
                gap: 5px;
            }
            .sector-ctrl > div { width: 100%; display: flex; justify-content: space-between; font-size: 1.2rem; }
            .st-btn { padding: 8px; font-size: 1rem; }

            /* Feed goes single column */
            #admin-feed { grid-template-columns: 1fr; }
            
            /* Card Buttons: 2x2 Grid to prevent squishing */
            .card-actions { 
                grid-template-columns: 1fr 1fr; 
                gap: 10px;
            }
            /* Make the Delete button fit in the grid logic */
            .card-actions .btn:nth-child(4) { grid-column: auto; } 
            .card-actions .btn { height: 50px; font-size: 1.1rem; }

            .info-box { padding: 1.5rem; }
            .link-row { flex-direction: column; align-items: flex-start; gap: 5px; }
            .link-input { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <header class="admin-header">
        <div class="header-title-group">
            <span class="subtitle">Dashboard &bull; <?= htmlspecialchars($current_user['email']) ?></span>
            <h1>Workshop Control</h1>
        </div>
        <div class="header-actions">
            <a href="customize.php" class="btn">Customize</a>
            <a href="subscription_manage.php" class="btn">Subscription</a>
            <a href="admin.php?mode=pdf" target="_blank" class="btn">PDF Export</a>
            <a href="index.php?u=<?= urlencode($user_id) ?>" target="_blank" class="btn btn-primary">Open Live View</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </header>

    <div class="info-box">
        <h3>🔗 Connection Links</h3>
        <div class="link-row">
            <span class="link-label">VISITOR VIEW (READ ONLY):</span>
            <input type="text" class="link-input" readonly value="<?= getPublicWorkshopURL($user_id) ?>" onclick="this.select(); document.execCommand('copy');" title="Click to copy">
        </div>
        <div class="link-row">
            <span class="link-label">PARTICIPANT FORM (INPUT):</span>
            <input type="text" class="link-input" readonly value="<?= getPublicInputURL($user_id) ?>" onclick="this.select(); document.execCommand('copy');" title="Click to copy">
        </div>
    </div>

    <div class="command-panel">
        <h3>Session Controls</h3>
        
        <div class="command-row">
            <div class="command-col">
                <span class="command-label">GLOBAL ACTIONS</span>
                <div class="global-btns">
                    <button onclick="if(confirm('Go LIVE with ALL cards?')) runCmd('action_all=show')" class="btn btn-success">ALL LIVE</button>
                    <button onclick="if(confirm('HIDE ALL cards?')) runCmd('action_all=hide')" class="btn btn-danger">ALL HIDE</button>
                </div>
            </div>
            
            <div class="command-col">
                <span class="command-label">SECTOR VISIBILITY</span>
                <div class="sector-container">
                    <?php foreach ($gruppen_labels as $key => $label): ?>
                        <div class="sector-ctrl" id="ctrl-<?= $key ?>">
                            <span class="sector-label"><?= strtoupper(substr($label,0,4)) ?></span>
                            <div>
                                <span onclick="runCmd('action_col=show&col=<?= $key ?>')" class="st-btn btn-on">ON</span>
                                <span style="color:#eee; margin: 0 4px;">|</span>
                                <span onclick="runCmd('action_col=hide&col=<?= $key ?>')" class="st-btn btn-off">OFF</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-bottom: 2rem;">
        <div class="feed-header">
            <h2>Incoming Data Feed</h2>
            <div id="purge-btn-wrapper"></div>
        </div>
    </div>

    <div id="admin-feed">
         <div style="padding: 3rem; text-align: center; color: #999; grid-column: 1 / -1; font-family: var(--font-head); font-size: 1.5rem;">Loading Data Stream...</div>
    </div>

</div>

<script>
    const gruppenLabels = <?= json_encode($gruppen_labels) ?>;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderAdmin(data) {
        const feed = document.getElementById('admin-feed');
        const purgeWrapper = document.getElementById('purge-btn-wrapper');
        
        if (data.length > 0) {
            purgeWrapper.innerHTML = `<button onclick="if(confirm('WARNING: PERMANENTLY DELETE ALL?')) runCmd('deleteall=confirm')" class="btn btn-danger btn-sm">PURGE ALL DATA</button>`;
        } else {
            purgeWrapper.innerHTML = '';
            feed.innerHTML = '<div style="padding: 4rem; text-align: center; color: #ccc; grid-column: 1 / -1; font-family: var(--font-head); font-size: 2rem;">NO DATA AVAILABLE</div>';
            return;
        }

        let html = '';
        const sectorCounts = {}; 

        Object.keys(gruppenLabels).forEach(k => sectorCounts[k] = { total: 0, visible: 0 });

        data.forEach(entry => {
            const isVisible = (entry.visible === true || entry.visible === "true");
            const isFocused = (entry.focus === true || entry.focus === "true");
            
            if(sectorCounts[entry.thema]) {
                sectorCounts[entry.thema].total++;
                if(isVisible) sectorCounts[entry.thema].visible++;
            }

            let optionsHtml = '';
            for (const [key, label] of Object.entries(gruppenLabels)) {
                const selected = (entry.thema === key) ? 'selected' : '';
                optionsHtml += `<option value="${key}" ${selected}>📂 ${label}</option>`;
            }
            
            const cardStatusClass = isVisible ? 'status-live' : 'status-hidden';
            const btnClass = isVisible ? 'btn-danger' : 'btn-success'; 
            const btnText = isVisible ? 'HIDE' : 'GO LIVE';
            const focusClass = isFocused ? 'is-focused' : '';

            const escapedText = entry.text.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            const escapedId = escapeHtml(entry.id);

            html += `
            <div class="admin-card ${cardStatusClass}" id="card-${escapedId}" data-original-text="${escapedText}">
                <div class="card-header">
                    <select class="admin-select" onchange="runCmd('action=move&id=${escapedId}&new_thema='+this.value)">
                        ${optionsHtml}
                    </select>
                    <span class="card-time">${new Date(entry.zeit * 1000).toLocaleTimeString('de-DE', {hour: '2-digit', minute:'2-digit'})}</span>
                </div>

                <div class="card-body" data-id="${escapedId}">
                    <div class="entry-text-display">${escapeHtml(entry.text)}</div>
                    <textarea class="entry-text-edit" style="display: none;">${escapeHtml(entry.text)}</textarea>
                </div>

                <div class="card-actions">
                    <button onclick="toggleEditMode('${escapedId}')" class="btn" data-id="${escapedId}">EDIT</button>

                    <button onclick="runCmd('toggle_focus=${escapedId}')" class="btn btn-focus ${focusClass}">FOCUS</button>

                    <button onclick="runCmd('toggle_id=${escapedId}')" class="btn ${btnClass}">
                        ${btnText}
                    </button>

                    <button onclick="if(confirm('Delete?')) runCmd('delete=${escapedId}')" class="btn btn-danger">✕</button>
                </div>

                <div class="card-edit-actions" style="display: none;">
                    <button onclick="saveEdit('${escapedId}')" class="btn btn-success">💾 SAVE</button>
                    <button onclick="cancelEdit('${escapedId}')" class="btn">CANCEL</button>
                </div>
            </div>`;
        });
        
        feed.innerHTML = html;

        // Update Sector Button States
        Object.keys(sectorCounts).forEach(key => {
            const ctrl = document.getElementById('ctrl-' + key);
            if(ctrl) {
                const stats = sectorCounts[key];
                const btnOn = ctrl.querySelector('.btn-on');
                const btnOff = ctrl.querySelector('.btn-off');
                
                btnOn.classList.remove('active-on');
                btnOff.classList.remove('active-off');

                if (stats.total > 0) {
                    if (stats.visible === stats.total) {
                        btnOn.classList.add('active-on');
                    } else if (stats.visible === 0) {
                        btnOff.classList.add('active-off');
                    }
                }
            }
        });
    }

    async function runCmd(queryParams) {
        document.body.style.cursor = 'wait';
        stopAutoRefresh();

        try {
            const response = await fetch('admin.php?' + queryParams + '&ajax=1');
            if (response.ok) {
                updateAdminBoard();
                startAutoRefresh();
            } else {
                console.error("Server Error");
                startAutoRefresh(); 
            }
        } catch (e) {
            console.error(e);
            startAutoRefresh();
        } finally {
            document.body.style.cursor = 'default';
        }
    }

    let refreshInterval = null;
    let isEditMode = false;

    function startAutoRefresh() {
        if (refreshInterval) clearInterval(refreshInterval);
        refreshInterval = setInterval(updateAdminBoard, 2000);
    }

    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }

    function toggleEditMode(entryId) {
        const card = document.getElementById('card-' + entryId);
        const cardBody = card.querySelector('.card-body');
        const display = cardBody.querySelector('.entry-text-display');
        const textarea = cardBody.querySelector('.entry-text-edit');
        const normalActions = card.querySelector('.card-actions');
        const editActions = card.querySelector('.card-edit-actions');

        if (display.style.display === 'none') {
            display.style.display = 'block';
            textarea.style.display = 'none';
            normalActions.style.display = 'grid';
            editActions.style.display = 'none';
            isEditMode = false;
            startAutoRefresh();
        } else {
            display.style.display = 'none';
            textarea.style.display = 'block';
            normalActions.style.display = 'none';
            editActions.style.display = 'flex';
            textarea.focus();
            isEditMode = true;
            stopAutoRefresh();
        }
    }

    async function saveEdit(entryId) {
        const card = document.getElementById('card-' + entryId);
        const textarea = card.querySelector('.entry-text-edit');
        const newText = textarea.value.trim();

        if (!newText) {
            alert('⚠️ Text cannot be empty!');
            return;
        }
        document.body.style.cursor = 'wait';
        try {
            const response = await fetch('admin.php?action=edit&id=' + entryId + '&new_text=' + encodeURIComponent(newText) + '&ajax=1');
            const result = await response.text();
            if (result === 'OK') {
                isEditMode = false;
                startAutoRefresh(); 
                updateAdminBoard();
            } else {
                alert('❌ Error saving: ' + result);
                document.body.style.cursor = 'default';
            }
        } catch (e) {
            alert('❌ Network error');
            document.body.style.cursor = 'default';
        }
    }

    function cancelEdit(entryId) {
        const card = document.getElementById('card-' + entryId);
        const cardBody = card.querySelector('.card-body');
        const display = cardBody.querySelector('.entry-text-display');
        const textarea = cardBody.querySelector('.entry-text-edit');
        const normalActions = card.querySelector('.card-actions');
        const editActions = card.querySelector('.card-edit-actions');
        const originalText = card.getAttribute('data-original-text')
            .replace(/&quot;/g, '"')
            .replace(/&#39;/g, "'");
        textarea.value = originalText;

        display.style.display = 'block';
        textarea.style.display = 'none';
        normalActions.style.display = 'grid';
        editActions.style.display = 'none';
        isEditMode = false;
        startAutoRefresh();
    }

    function updateAdminBoard() {
        if (isEditMode) return;
        fetch('index.php?api=1&u=<?= urlencode($user_id) ?>')
            .then(response => response.json())
            .then(data => renderAdmin(data))
            .catch(err => console.error(err));
    }

    updateAdminBoard();
    startAutoRefresh();

</script>
</body>
</html>