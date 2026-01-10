<?php
// ============================================
// ROBUSTE ADMIN.PHP - ATOMIC OPERATIONS
// ============================================

session_start();

$admin_passwort = "workshop2025";

// Load config to get dynamic categories
require_once 'file_handling_robust.php';
$config = loadConfig('config.json');

// Build gruppen_labels from config
$gruppen_labels = [];
if ($config && isset($config['categories'])) {
    foreach ($config['categories'] as $category) {
        $gruppen_labels[$category['key']] = $category['abbreviation'];
    }
} else {
    // Fallback if config cannot be loaded
    $gruppen_labels = [
        'bildung' => 'BIL',
        'social' => 'SOC',
        'individuell' => 'IND',
        'politik' => 'POL',
        'kreativ' => 'INN'
    ];
}

// --- PDF EXPORT MODE ---
if (isset($_GET['mode']) && $_GET['mode'] === 'pdf' && isset($_SESSION['is_admin'])) {
    $file = 'daten.json';
    $data = safeReadJson($file);
    
    $pdf_labels = [
        'bildung' => 'Bildung & Schule',
        'social' => 'Verantwortung Social Media',
        'individuell' => 'Individuelle Verantwortung',
        'politik' => 'Politik & Recht',
        'kreativ' => 'Kreative & innovative Ansätze'
    ];
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Infraprotect Strategie Protokoll</title>
        <style>
            /* PDF Style angepasst an Infraprotect Clean Look */
            body { font-family: sans-serif; color: #32373c; line-height: 1.5; padding: 40px; max-width: 900px; margin: 0 auto; }
            h1 { font-family: sans-serif; font-size: 2.2rem; border-bottom: 3px solid #00658b; padding-bottom: 10px; margin-bottom: 5px; color: #00658b; text-transform: uppercase; }
            .meta { color: #666; font-size: 0.9rem; margin-bottom: 3rem; }
            .section { margin-bottom: 3rem; page-break-inside: avoid; }
            .section-title { font-size: 1.1rem; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; border-left: 5px solid #00658b; padding-left: 10px; margin-bottom: 1.5rem; color: #00658b; }
            .entry { margin-bottom: 1.5rem; padding: 15px; background: #f9f9f9; border-radius: 4px; }
            .entry-text { font-size: 1rem; margin-bottom: 5px; }
            .entry-meta { font-size: 0.75rem; color: #888; text-transform: uppercase; }
            .no-data { color: #999; font-style: italic; }
        </style>
    </head>
    <body onload="window.print()">
        <h1>Strategische Maßnahmen</h1>
        <div class="meta">Infraprotect Workshop Ergebnisse • Generiert am <?= date('d.m.Y \u\m H:i') ?> Uhr</div>

        <?php foreach ($pdf_labels as $key => $label): ?>
            <div class="section">
                <div class="section-title"><?= $label ?></div>
                <?php 
                $hasEntries = false;
                foreach ($data as $entry) {
                    if (($entry['thema'] ?? '') === $key) {
                        $hasEntries = true;
                        $status = ($entry['visible'] ?? false) ? "LIVE" : "ENTWURF";
                        echo '<div class="entry">';
                        echo '<div class="entry-text">' . nl2br(htmlspecialchars($entry['text'])) . '</div>';
                        echo '<div class="entry-meta">' . date('H:i', $entry['zeit']) . ' Uhr • Status: ' . $status . '</div>';
                        echo '</div>';
                    }
                }
                if (!$hasEntries) echo '<div class="no-data">Keine Einträge vorhanden.</div>';
                ?>
            </div>
        <?php endforeach; ?>
    </body>
    </html>
    <?php
    exit;
}

// --- LOGIN & LOGOUT ---
if (isset($_POST['login'])) {
    if ($_POST['password'] === $admin_passwort) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
    } else {
        $error = "ACCESS DENIED";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// --- ACTION HANDLER (ATOMIC) ---
$file = 'daten.json';

if (isset($_SESSION['is_admin'])) {
    $is_ajax = isset($_REQUEST['ajax']);
    $req = $_REQUEST;

    // 🔒 DELETE SINGLE ENTRY
    if (isset($req['delete'])) {
        $id = $req['delete'];
        atomicUpdate($file, function($data) use ($id) {
            return array_values(array_filter($data, fn($e) => $e['id'] !== $id));
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    // 🔒 DELETE ALL
    if (isset($req['deleteall']) && $req['deleteall'] === 'confirm') {
        atomicUpdate($file, function($data) {
            return [];
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    // 🔒 TOGGLE VISIBILITY
    if (isset($req['toggle_id'])) {
        $id = $req['toggle_id'];
        atomicUpdate($file, function($data) use ($id) {
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
        atomicUpdate($file, function($data) use ($id) {
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
            atomicUpdate($file, function($data) use ($id, $new_text) {
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
        atomicUpdate($file, function($data) use ($id, $new_thema) {
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
        atomicUpdate($file, function($data) use ($col) {
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
        atomicUpdate($file, function($data) use ($col) {
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
        atomicUpdate($file, function($data) {
            foreach ($data as &$entry) {
                $entry['visible'] = true;
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }

    // 🔒 HIDE ALL
    if (isset($req['action_all']) && $req['action_all'] === 'hide') {
        atomicUpdate($file, function($data) {
            foreach ($data as &$entry) {
                $entry['visible'] = false;
            }
            return $data;
        });
        if ($is_ajax) { echo "OK"; exit; }
    }
}

// Daten für Display laden (Read-Only)
$data = safeReadJson($file);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | Infraprotect</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    
    <style>
        /* --- INFRAPROTECT DESIGN SYSTEM --- */
        :root {
            /* Colors */
            --ip-blue: #00658b;       /* Primary Brand Color */
            --ip-dark: #32373c;       /* Text / Dark Elements */
            --ip-light: #ffffff;      /* Backgrounds */
            --ip-grey-bg: #f4f4f4;    /* Secondary Background */
            --ip-border: #e0e0e0;     /* Subtle Borders */
            
            /* Action Colors */
            --accent-success: #00d084;
            --accent-danger: #cf2e2e;
            --accent-warning: #ff6900;

            /* Typography */
            --font-heading: 'Montserrat', sans-serif;
            --font-body: 'Roboto', sans-serif;
            
            /* Radius */
            --radius-pill: 9999px;
            --radius-card: 4px;
        }

        body {
            background-color: var(--ip-grey-bg);
            color: var(--ip-dark);
            font-family: var(--font-body);
            margin: 0; padding: 0;
            line-height: 1.6;
        }

        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }

        /* Login Screen */
        .login-wrapper {
            max-width: 400px; margin: 10vh auto; padding: 3rem;
            background: var(--ip-light); 
            border-top: 5px solid var(--ip-blue);
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border-radius: var(--radius-card);
        }
        .login-wrapper h1 { font-family: var(--font-heading); font-size: 2rem; margin: 0 0 1rem 0; text-align: center; color: var(--ip-blue); }
        .login-wrapper input, .login-wrapper button { width: 100%; box-sizing: border-box; padding: 14px; font-size: 1rem; margin-bottom: 1rem; border-radius: var(--radius-pill); }
        .login-wrapper input { background: #f9f9f9; border: 1px solid var(--ip-border); color: var(--ip-dark); }
        .login-wrapper input:focus { outline: none; border-color: var(--ip-blue); }
        .login-wrapper button { border-radius: var(--radius-pill); cursor: pointer; }
        .error-msg { background: rgba(207, 46, 46, 0.1); border-left: 3px solid var(--accent-danger); padding: 1rem; margin-bottom: 1rem; color: var(--accent-danger); }

        /* HEADER */
        .admin-header {
            display: flex; justify-content: space-between; align-items: center;
            background: var(--ip-light);
            padding: 1.5rem 2rem; 
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            border-radius: var(--radius-card);
            flex-wrap: wrap; gap: 1rem;
        }
        .admin-header h1 { font-family: var(--font-heading); font-size: 2rem; margin: 0; line-height: 1; color: var(--ip-blue); font-weight: 700; }
        .subtitle { color: var(--ip-dark); text-transform: uppercase; letter-spacing: 1px; font-size: 0.75rem; font-weight: 600; display: block; margin-bottom: 0.2rem; opacity: 0.6; }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }

        /* BUTTONS - Pill Shaped */
        .btn {
            padding: 10px 24px; 
            background: var(--ip-dark); 
            border: none;
            color: #fff; 
            text-decoration: none; 
            font-weight: 600; 
            font-family: var(--font-heading);
            letter-spacing: 0.5px;
            cursor: pointer; 
            transition: 0.3s; 
            font-size: 0.85rem; 
            display: inline-block;
            text-align: center;
            border-radius: var(--radius-pill);
            box-shadow: none;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        .btn-danger { background: var(--accent-danger); color: white; }
        .btn-success { background: var(--accent-success); color: white; }
        .btn-neutral { background: #e0e0e0; color: #555; }
        .btn-neutral:hover { background: #d0d0d0; }
        .btn-primary { background: var(--ip-blue); color: white; }
        .btn-sm { padding: 6px 16px; font-size: 0.75rem; }

        /* COMMAND PANEL */
        .command-panel {
            background: var(--ip-light); 
            border: 1px solid var(--ip-border);
            padding: 2rem; margin-bottom: 2rem;
            border-radius: var(--radius-card);
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .command-panel h3 { margin: 0 0 1.5rem 0; font-family: var(--font-heading); font-weight: 600; color: var(--ip-blue); font-size: 1.2rem; border-bottom: 1px solid var(--ip-border); padding-bottom: 10px; }
        
        .command-row { display: flex; gap: 3rem; align-items: flex-start; }
        .command-label { display: block; color: var(--ip-dark); font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 12px; font-family: var(--font-heading); text-transform: uppercase;}

        /* Global Buttons Layout */
        .global-btns { display: flex; gap: 10px; }

        /* Sector Layout */
        .sector-group { flex: 1; }
        .sector-container { display: flex; flex-wrap: wrap; width: 100%; gap: 10px; }

        .sector-ctrl {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 8px 16px; background: #f9f9f9; border: 1px solid var(--ip-border);
            border-radius: var(--radius-pill);
        }
        .sector-label { font-weight: 700; font-size: 0.8rem; color: var(--ip-blue); font-family: var(--font-heading); }
        .st-btn { cursor: pointer; padding: 4px 10px; font-size: 0.75rem; font-weight: 600; transition: 0.2s; user-select: none; border-radius: var(--radius-pill); }
        
        .btn-on { color: #ccc; }
        .btn-on:hover, .btn-on.active-on { background: var(--accent-success); color: white; }
        
        .btn-off { color: #ccc; }
        .btn-off:hover, .btn-off.active-off { background: var(--accent-danger); color: white; }

        /* FEED GRID */
        #admin-feed {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem;
        }

        .admin-card {
            background: var(--ip-light); 
            border: 1px solid var(--ip-border);
            padding: 1.5rem; transition: 0.3s; position: relative;
            border-radius: var(--radius-card);
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .admin-card:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        
        /* Status Colors using Borders */
        .admin-card.status-live { border-top: 4px solid var(--accent-success); }
        .admin-card.status-hidden { border-top: 4px solid #ccc; opacity: 0.8; }

        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; }
        
        .admin-select { 
            padding: 6px 12px; background: #fff; border: 1px solid #ddd; 
            color: var(--ip-dark); font-size: 0.8rem; max-width: 60%; 
            border-radius: 4px; font-family: var(--font-body);
        }
        
        .card-time { font-size: 0.75rem; color: #999; font-weight: 500; }
        .card-body { font-size: 1rem; margin-bottom: 1.5rem; line-height: 1.6; min-height: 40px; word-wrap: break-word; color: #444; }

        /* Edit Mode Styles */
        .entry-text-edit {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border: 2px solid var(--ip-blue);
            border-radius: 4px;
            font-family: var(--font-body);
            font-size: 1rem;
            line-height: 1.6;
            color: #444;
            resize: vertical;
            box-sizing: border-box;
        }
        .entry-text-edit:focus {
            outline: none;
            border-color: var(--ip-blue);
            box-shadow: 0 0 0 3px rgba(0, 101, 139, 0.1);
        }

        .card-actions { display: flex; gap: 8px; }
        .card-actions .btn { flex: 1; padding: 10px; font-size: 0.75rem; border-radius: 4px; }

        .card-edit-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        .card-edit-actions .btn {
            flex: 1;
            padding: 10px;
            font-size: 0.75rem;
            border-radius: 4px;
        }

        /* Focus Button Special Style */
        .btn-focus { background: white; border: 1px solid var(--accent-warning); color: var(--accent-warning); }
        .btn-focus:hover { background: var(--accent-warning); color: white; }
        .btn-focus.is-focused { background: var(--accent-warning); color: white; }
        
        .feed-header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid var(--ip-border); padding-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .feed-header h2 { font-family: var(--font-heading); color: var(--ip-dark); margin: 0; font-size: 1.5rem; font-weight: 700; }

        /* =========================================
           MOBILE RESPONSIVENESS
           ========================================= */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            
            .admin-header { flex-direction: column; align-items: flex-start; gap: 1.5rem; padding: 1.5rem; }
            .admin-header h1 { font-size: 1.8rem; }
            .header-actions { width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
            .header-actions .btn { width: 100%; box-sizing: border-box; }
            .header-actions .btn:last-child:nth-child(odd) { grid-column: span 2; }

            .login-wrapper { width: 100%; margin: 2rem 0; box-sizing: border-box; padding: 1.5rem; }

            .command-row { flex-direction: column; gap: 2rem; }
            .command-row > div { width: 100%; } 
            
            .global-btns { width: 100%; gap: 10px; }
            .global-btns .btn { flex: 1; }

            .sector-container { display: flex; flex-direction: column; gap: 10px; width: 100%; }
            .sector-ctrl { 
                display: flex; justify-content: space-between; width: 100%; 
                box-sizing: border-box; margin: 0; padding: 12px 20px;
            }
            .st-btn { padding: 6px 14px; font-size: 0.85rem; } 
            
            #admin-feed { grid-template-columns: 1fr; } 
            .feed-header { flex-direction: column; align-items: flex-start; }
            .admin-select { max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <?php if (!isset($_SESSION['is_admin'])): ?>
        <div class="login-wrapper">
            <h1>ADMIN LOGIN</h1>
            <?php if (isset($error)): ?>
                <div class="error-msg">⚠️ <?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="password" required autofocus placeholder="Passwort">
                <button type="submit" name="login" class="btn btn-success">UNLOCK</button>
            </form>
        </div>
    <?php else: ?>

        <header class="admin-header">
            <div>
                <span class="subtitle">Infraprotect Board</span>
                <h1>Moderation</h1>
            </div>
            <div class="header-actions">
                <a href="customize.php" class="btn btn-primary">Anpassen</a>
                <a href="admin.php?mode=pdf" target="_blank" class="btn btn-neutral">PDF Export</a>
                <a href="index.php" target="_blank" class="btn btn-neutral">View Live</a>
                <a href="admin.php?logout=1" class="btn btn-danger">Logout</a>
            </div>
        </header>

        <div class="command-panel">
            <h3>Mass Control</h3>
            
            <div class="command-row">
                <div>
                    <span class="command-label">GLOBAL ACTIONS</span>
                    <div class="global-btns">
                        <button onclick="if(confirm('ALLES Live schalten?')) runCmd('action_all=show')" class="btn btn-sm btn-success" style="flex:1">ALL LIVE</button>
                        <button onclick="if(confirm('ALLES verstecken?')) runCmd('action_all=hide')" class="btn btn-sm btn-neutral" style="flex:1">ALL HIDE</button>
                    </div>
                </div>
                
                <div class="sector-group">
                    <span class="command-label">SECTOR CONTROL</span>
                    <div class="sector-container">
                        <?php foreach ($gruppen_labels as $key => $label): ?>
                            <div class="sector-ctrl" id="ctrl-<?= $key ?>">
                                <span class="sector-label"><?= strtoupper(substr($label,0,3)) ?></span>
                                <div>
                                    <span onclick="runCmd('action_col=show&col=<?= $key ?>')" class="st-btn btn-on">ON</span>
                                    <span style="color:#ddd; margin: 0 4px;">|</span>
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
             <div style="padding: 3rem; text-align: center; color: #999; grid-column: 1 / -1;">Loading Data...</div>
        </div>

    <?php endif; ?>
</div>

<?php if (isset($_SESSION['is_admin'])): ?>
<script>
    const gruppenLabels = <?= json_encode($gruppen_labels) ?>;
    
    function renderAdmin(data) {
        const feed = document.getElementById('admin-feed');
        const purgeWrapper = document.getElementById('purge-btn-wrapper');
        
        if (data.length > 0) {
            purgeWrapper.innerHTML = `<button onclick="if(confirm('WARNING: PURGE ALL?')) runCmd('deleteall=confirm')" class="btn btn-danger" style="font-size: 0.7rem;">PURGE ALL</button>`;
        } else {
            purgeWrapper.innerHTML = '';
            feed.innerHTML = '<div style="padding: 3rem; text-align: center; color: #999; grid-column: 1 / -1;">NO DATA AVAILABLE</div>';
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
            const btnClass = isVisible ? 'btn-neutral' : 'btn-success';
            const btnText = isVisible ? 'HIDE' : 'GO LIVE';
            const focusClass = isFocused ? 'is-focused' : '';

            const escapedText = entry.text.replace(/"/g, '&quot;').replace(/'/g, '&#39;');

            html += `
            <div class="admin-card ${cardStatusClass}" id="card-${entry.id}" data-original-text="${escapedText}">
                <div class="card-header">
                    <select class="admin-select" onchange="runCmd('action=move&id=${entry.id}&new_thema='+this.value)">
                        ${optionsHtml}
                    </select>
                    <span class="card-time">${new Date(entry.zeit * 1000).toLocaleTimeString('de-DE', {hour: '2-digit', minute:'2-digit'})}</span>
                </div>

                <div class="card-body" data-id="${entry.id}">
                    <div class="entry-text-display">${entry.text}</div>
                    <textarea class="entry-text-edit" style="display: none;">${entry.text}</textarea>
                </div>

                <div class="card-actions">
                    <button onclick="toggleEditMode('${entry.id}')" class="btn btn-neutral btn-edit" data-id="${entry.id}">EDIT</button>

                    <button onclick="runCmd('toggle_focus=${entry.id}')" class="btn btn-focus ${focusClass}">FOCUS</button>

                    <button onclick="runCmd('toggle_id=${entry.id}')" class="btn ${btnClass}">
                        ${btnText}
                    </button>

                    <button onclick="if(confirm('Delete?')) runCmd('delete=${entry.id}')" class="btn btn-danger" style="flex: 0 0 auto;">✕</button>
                </div>

                <div class="card-edit-actions" style="display: none;">
                    <button onclick="saveEdit('${entry.id}')" class="btn btn-success">💾 SAVE</button>
                    <button onclick="cancelEdit('${entry.id}')" class="btn btn-neutral">CANCEL</button>
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
            normalActions.style.display = 'flex';
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
        normalActions.style.display = 'flex';
        editActions.style.display = 'none';
        isEditMode = false;
        startAutoRefresh(); // Resume auto-refresh
    }

    function updateAdminBoard() {
        // Don't refresh if in edit mode
        if (isEditMode) {
            return;
        }
        fetch('index.php?api=1')
            .then(response => response.json())
            .then(data => renderAdmin(data))
            .catch(err => console.error(err));
    }

    // Initialize
    updateAdminBoard();
    startAutoRefresh();

</script>
<?php endif; ?>
</body>
</html>