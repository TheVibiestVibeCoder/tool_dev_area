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

    // Load user's configuration for categories and title
    $config = loadConfig($config_file);

    // Build PDF labels from user's config
    $pdf_labels = [];
    $pdf_title = 'Workshop Protocol'; // Default

    if ($config && isset($config['categories'])) {
        foreach ($config['categories'] as $category) {
            // Use display_name if available, otherwise use name with icon
            if (isset($category['display_name'])) {
                $pdf_labels[$category['key']] = $category['display_name'];
            } else {
                $icon = $category['icon'] ?? '';
                $name = $category['name'] ?? $category['key'];
                $pdf_labels[$category['key']] = $icon . ' ' . $name;
            }
        }

        // Use custom header title if set
        if (isset($config['header_title'])) {
            $pdf_title = $config['header_title'];
        }
    } else {
        // Fallback if no config found
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
            /* PDF Style - Minimalist Black & White */
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

// --- ACTION HANDLER (ATOMIC) ---
// All actions now use user-specific data file

{
    $is_ajax = isset($_REQUEST['ajax']);
    $req = $_REQUEST;

    // 🔒 DELETE SINGLE ENTRY
    if (isset($req['delete'])) {
        $id = $req['delete'];
        atomicUpdate($data_file, function($data) use ($id) {
            return array_values(array_filter($data, fn($e) => $e['id'] !== $id));
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    // 🔒 DELETE ALL
    if (isset($req['deleteall']) && $req['deleteall'] === 'confirm') {
        atomicUpdate($data_file, function($data) {
            return [];
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    // 🔒 TOGGLE VISIBILITY
    if (isset($req['toggle_id'])) {
        $id = $req['toggle_id'];
        atomicUpdate($data_file, function($data) use ($id) {
            foreach ($data as &$entry) {
                if ($entry['id'] === $id) {
                    $entry['visible'] = !($entry['visible'] ?? false);
                }
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    // 🔒 TOGGLE FOCUS (nur EIN Eintrag kann focused sein)
    if (isset($req['toggle_focus'])) {
        $id = $req['toggle_focus'];
        atomicUpdate($data_file, function($data) use ($id) {
            foreach ($data as &$entry) {
                if ($entry['id'] === $id) {
                    $currentFocus = $entry['focus'] ?? false;
                    $entry['focus'] = !$currentFocus;
                } else {
                    $entry['focus'] = false; // Alle anderen ausschalten
                }
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    // 🔒 EDIT ENTRY TEXT
    if (isset($req['action']) && $req['action'] === 'edit' && isset($req['id']) && isset($req['new_text'])) {
        $id = $req['id'];
        $new_text = trim($req['new_text']);

        if (!empty($new_text)) {
            atomicUpdate($data_file, function($data) use ($id, $new_text) {
                foreach ($data as &$entry) {
                    if ($entry['id'] === $id) {
                        $entry['text'] = $new_text;
                    }
                }
                return $data;
            });
            if ($is_ajax) { echo "OK"; exit; }
        } else {
            if ($is_ajax) { echo "ERROR: Text cannot be empty"; exit; }
        }
    }

    // 🔒 MOVE TO DIFFERENT THEMA
    if (isset($req['action']) && $req['action'] === 'move' && isset($req['id']) && isset($req['new_thema'])) {
        $id = $req['id'];
        $new_thema = $req['new_thema'];
        atomicUpdate($data_file, function($data) use ($id, $new_thema) {
            foreach ($data as &$entry) {
                if ($entry['id'] === $id) {
                    $entry['thema'] = $new_thema;
                }
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    // 🔒 SHOW ALL IN COLUMN
    if (isset($req['action_col']) && $req['action_col'] === 'show' && isset($req['col'])) {
        $col = $req['col'];
        atomicUpdate($data_file, function($data) use ($col) {
            foreach ($data as &$entry) {
                if ($entry['thema'] === $col) {
                    $entry['visible'] = true;
                }
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    // 🔒 HIDE ALL IN COLUMN
    if (isset($req['action_col']) && $req['action_col'] === 'hide' && isset($req['col'])) {
        $col = $req['col'];
        atomicUpdate($data_file, function($data) use ($col) {
            foreach ($data as &$entry) {
                if ($entry['thema'] === $col) {
                    $entry['visible'] = false;
                }
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    // 🔒 SHOW ALL
    if (isset($req['action_all']) && $req['action_all'] === 'show') {
        atomicUpdate($data_file, function($data) {
            foreach ($data as &$entry) {
                $entry['visible'] = true;
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    // 🔒 HIDE ALL
    if (isset($req['action_all']) && $req['action_all'] === 'hide') {
        atomicUpdate($data_file, function($data) {
            foreach ($data as &$entry) {
                $entry['visible'] = false;
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }
}

// Daten für Display laden (Read-Only)
$data = safeReadJson($data_file);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | Live Situation Room</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        /* --- DESIGN SYSTEM (Monochrome / Bebas) --- */
        :root {
            /* Colors */
            --bg-body: #f5f5f5;
            --bg-card: #ffffff;
            --text-main: #111111;
            --text-muted: #666666;
            --border-color: #e0e0e0;
            --border-strong: #111111;
            
            /* Status Colors (Monochrome Mapping) */
            --danger: #d32f2f;
            --success: #111111; /* Live is Black */
            
            /* Typography */
            --font-head: 'Bebas Neue', sans-serif;
            --font-body: 'Inter', sans-serif;
            
            /* UI */
            --radius-btn: 2px;
            --radius-card: 4px;
            --shadow: 0 4px 6px rgba(0,0,0,0.02);
            --shadow-hover: 0 8px 15px rgba(0,0,0,0.05);
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-body);
            margin: 0; padding: 0;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; }

        /* HEADER */
        .admin-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            background: var(--bg-card);
            padding: 2rem 3rem; 
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            border-bottom: 2px solid var(--border-strong);
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
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 5px; }

        /* BUTTONS - Rectangle Style */
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
            transition: all 0.2s ease; 
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: var(--radius-btn);
            line-height: 1;
        }
        .btn:hover { 
            border-color: var(--text-main); 
            color: var(--text-main); 
            background: #fafafa;
            transform: translateY(-1px);
        }
        
        .btn-primary { 
            background: var(--text-main); color: #fff; border-color: var(--text-main); 
        }
        .btn-primary:hover { 
            background: #333; color: #fff; 
        }
        
        .btn-danger { color: var(--danger); border-color: #eee; }
        .btn-danger:hover { background: var(--danger); color: white; border-color: var(--danger); }
        
        .btn-success { color: var(--text-main); font-weight: bold; border-color: var(--text-main); }
        .btn-success:hover { background: var(--text-main); color: #fff; }

        .btn-sm { padding: 8px 16px; font-size: 1rem; }

        /* INFO BOX */
        .info-box {
            background: #fff; border: 1px solid var(--border-color);
            padding: 20px 30px; margin-bottom: 24px;
            display: flex; flex-direction: column; gap: 15px;
        }
        .info-box h3 { 
            margin: 0; color: var(--text-main); font-family: var(--font-head); 
            font-size: 1.5rem; letter-spacing: 0.5px; font-weight: 400;
        }
        .link-row { display: flex; align-items: center; gap: 15px; }
        .link-label { font-family: var(--font-head); font-size: 1rem; color: var(--text-muted); min-width: 180px; }
        .link-input { 
            flex: 1; padding: 10px; font-family: 'Inter', monospace; font-size: 0.9rem; 
            border: 1px solid var(--border-color); background: #fafafa; color: #333;
            cursor: pointer; transition: 0.2s;
        }
        .link-input:hover { border-color: #999; background: #fff; }

        /* COMMAND PANEL */
        .command-panel {
            background: var(--bg-card); 
            border: 1px solid var(--border-color);
            padding: 2rem 3rem; margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        .command-panel h3 { 
            margin: 0 0 1.5rem 0; font-family: var(--font-head); 
            font-weight: 400; color: var(--text-main); font-size: 2rem; 
            border-bottom: 1px solid var(--text-main); padding-bottom: 10px; 
        }
        
        .command-row { display: flex; gap: 4rem; align-items: flex-start; }
        .command-col { flex: 1; }
        .command-label { 
            display: block; color: var(--text-muted); font-size: 0.9rem; 
            margin-bottom: 15px; font-family: var(--font-head); 
            letter-spacing: 1px;
        }

        /* Global Buttons Layout */
        .global-btns { display: flex; gap: 10px; }
        .global-btns .btn { flex: 1; justify-content: center; }

        /* Sector Layout */
        .sector-container { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); 
            gap: 15px; 
        }

        .sector-ctrl {
            display: flex; align-items: center; justify-content: space-between;
            padding: 8px 15px; background: #fff; border: 1px solid var(--border-color);
            transition: 0.2s;
        }
        .sector-ctrl:hover { border-color: #999; }
        
        .sector-label { 
            font-size: 1.2rem; color: var(--text-main); 
            font-family: var(--font-head); line-height: 1;
        }
        
        .st-btn { 
            cursor: pointer; padding: 4px 8px; font-size: 0.75rem; 
            font-weight: 600; transition: 0.2s; user-select: none; 
            border: 1px solid transparent; letter-spacing: 1px;
        }
        
        .btn-on { color: #ccc; }
        .btn-on:hover, .btn-on.active-on { color: var(--text-main); font-weight: 900; border-bottom: 2px solid var(--text-main); }
        
        .btn-off { color: #ccc; }
        .btn-off:hover, .btn-off.active-off { color: var(--text-muted); text-decoration: line-through; }

        /* FEED GRID */
        #admin-feed {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 1.5rem;
        }

        .feed-header { 
            display: flex; justify-content: space-between; align-items: flex-end; 
            margin-bottom: 20px; border-bottom: 2px solid var(--border-strong); 
            padding-bottom: 10px; 
        }
        .feed-header h2 { 
            font-family: var(--font-head); color: var(--text-main); 
            margin: 0; font-size: 2.5rem; line-height: 1; font-weight: 400; 
        }

        /* CARDS */
        .admin-card {
            background: var(--bg-card); 
            border: 1px solid var(--border-color);
            padding: 1.5rem; transition: 0.3s ease; position: relative;
            box-shadow: var(--shadow);
            display: flex; flex-direction: column;
        }
        .admin-card:hover { 
            box-shadow: var(--shadow-hover); 
            transform: translateY(-2px);
            border-color: #999;
        }
        
        /* Status Styles */
        .admin-card.status-live { 
            border: 1px solid var(--text-main); 
        }
        .admin-card.status-hidden { 
            border: 1px dashed #ccc; 
            opacity: 0.75; 
            background: #fafafa;
        }

        .card-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 1rem; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; 
        }
        
        .admin-select { 
            padding: 5px; background: transparent; border: none; border-bottom: 1px solid #eee;
            color: var(--text-muted); font-size: 0.85rem; max-width: 60%; 
            font-family: var(--font-body); font-weight: 600; cursor: pointer;
        }
        .admin-select:hover { color: var(--text-main); border-color: #999; }
        
        .card-time { font-size: 0.75rem; color: #999; font-family: 'Inter', monospace; }
        
        .card-body { 
            font-size: 1rem; margin-bottom: 1.5rem; line-height: 1.6; 
            min-height: 60px; word-wrap: break-word; color: var(--text-main); flex-grow: 1;
        }

        /* Edit Mode Styles */
        .entry-text-edit {
            width: 100%; min-height: 120px; padding: 12px;
            border: 1px solid var(--text-main); background: #fff;
            font-family: var(--font-body); font-size: 1rem; line-height: 1.6;
            color: var(--text-main); resize: vertical; box-sizing: border-box;
        }
        .entry-text-edit:focus { outline: none; background: #fafafa; }

        .card-actions { display: grid; grid-template-columns: 1fr 1fr 2fr auto; gap: 8px; margin-top: auto; }
        .card-actions .btn { padding: 8px; font-size: 0.9rem; }

        .card-edit-actions { display: flex; gap: 8px; margin-top: 10px; }
        .card-edit-actions .btn { flex: 1; padding: 10px; font-size: 0.9rem; }

        /* Focus Button Special Style */
        .btn-focus { border-color: #ccc; color: #ccc; }
        .btn-focus:hover { border-color: var(--text-main); color: var(--text-main); }
        .btn-focus.is-focused { background: var(--text-main); color: #fff; border-color: var(--text-main); }
        
        /* =========================================
           MOBILE RESPONSIVENESS
           ========================================= */
        @media (max-width: 900px) {
            .container { padding: 1rem; }
            .admin-header { flex-direction: column; align-items: flex-start; gap: 1.5rem; padding: 1.5rem; }
            .admin-header h1 { font-size: 2.5rem; }
            .header-actions { width: 100%; flex-direction: column; }
            .header-actions .btn { width: 100%; }

            .command-row { flex-direction: column; gap: 2rem; }
            .sector-container { grid-template-columns: 1fr; }
            
            #admin-feed { grid-template-columns: 1fr; } 
            .feed-header { flex-direction: column; align-items: flex-start; }
            
            .card-actions { grid-template-columns: 1fr 1fr; }
            .card-actions .btn:last-child { grid-column: span 2; }
            
            .info-box { padding: 15px; }
            .link-row { flex-direction: column; align-items: flex-start; gap: 5px; margin-bottom: 15px; }
            .link-input { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <header class="admin-header">
        <div>
            <span class="subtitle">Dashboard &bull; <?= htmlspecialchars($current_user['email']) ?></span>
            <h1>Workshop Control</h1>
        </div>
        <div class="header-actions">
            <a href="customize.php" class="btn">Customize</a>
            <a href="subscription_manage.php" class="btn">Subscription</a>
            <a href="admin.php?mode=pdf" target="_blank" class="btn">PDF Export</a>
            <a href="index.php?u=<?= urlencode($user_id) ?>" target="_blank" class="btn btn-primary">Open Live View</a>
            <a href="logout.php" class="btn btn-danger" style="margin-left: 10px;">Logout</a>
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
            <div class="command-col" style="flex: 0 0 250px;">
                <span class="command-label">GLOBAL ACTIONS</span>
                <div class="global-btns">
                    <button onclick="if(confirm('Go LIVE with ALL cards?')) runCmd('action_all=show')" class="btn btn-success">ALL LIVE</button>
                    <button onclick="if(confirm('HIDE ALL cards?')) runCmd('action_all=hide')" class="btn">ALL HIDE</button>
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

    // HTML escape function to prevent XSS
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
            const btnClass = isVisible ? 'btn' : 'btn-success'; // Neutral if visible (to hide), Success if hidden (to show)
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

        // Temporarily pause auto-refresh during operation
        stopAutoRefresh();

        try {
            const response = await fetch('admin.php?' + queryParams + '&ajax=1');

            if (response.ok) {
                updateAdminBoard();
                // Resume auto-refresh after operation completes
                startAutoRefresh();
            } else {
                console.error("Server Error");
                startAutoRefresh(); // Resume even on error
            }
        } catch (e) {
            console.error(e);
            startAutoRefresh(); // Resume even on error
        } finally {
            document.body.style.cursor = 'default';
        }
    }

    // Auto-refresh control
    let refreshInterval = null;
    let isEditMode = false;

    function startAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
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

        // Toggle visibility
        if (display.style.display === 'none') {
            // Cancel edit mode
            display.style.display = 'block';
            textarea.style.display = 'none';
            normalActions.style.display = 'grid';
            editActions.style.display = 'none';
            isEditMode = false;
            startAutoRefresh(); // Resume auto-refresh
        } else {
            // Enter edit mode
            display.style.display = 'none';
            textarea.style.display = 'block';
            normalActions.style.display = 'none';
            editActions.style.display = 'flex';
            textarea.focus();
            isEditMode = true;
            stopAutoRefresh(); // Pause auto-refresh
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
                startAutoRefresh(); // Resume auto-refresh
                updateAdminBoard();
            } else {
                alert('❌ Error saving: ' + result);
                document.body.style.cursor = 'default';
            }
        } catch (e) {
            console.error(e);
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

        // Restore original text
        const originalText = card.getAttribute('data-original-text')
            .replace(/&quot;/g, '"')
            .replace(/&#39;/g, "'");
        textarea.value = originalText;

        // Exit edit mode
        display.style.display = 'block';
        textarea.style.display = 'none';
        normalActions.style.display = 'grid';
        editActions.style.display = 'none';
        isEditMode = false;
        startAutoRefresh(); // Resume auto-refresh
    }

    function updateAdminBoard() {
        // Don't refresh if in edit mode
        if (isEditMode) {
            return;
        }
        fetch('index.php?api=1&u=<?= urlencode($user_id) ?>')
            .then(response => response.json())
            .then(data => renderAdmin(data))
            .catch(err => console.error(err));
    }

    // Initialize
    updateAdminBoard();
    startAutoRefresh();

</script>
</body>
</html>