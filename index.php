<?php
// index.php - Multi-tenant Workshop Dashboard
require_once 'file_handling_robust.php';
require_once 'user_auth.php';
require_once 'security_helpers.php';

// Set security headers
setSecurityHeaders();

// ===== DETERMINE VIEWING MODE =====
$viewing_user_id = null;
$is_own_workshop = false;
$current_user = getCurrentUser();

if (isset($_GET['u'])) {
    $viewing_user_id = $_GET['u'];
    $is_own_workshop = false;
    if (!is_dir(getUserDataPath($viewing_user_id))) {
        die('Workshop not found.');
    }
} elseif ($current_user) {
    $viewing_user_id = $current_user['id'];
    $is_own_workshop = true;
} else {
    redirect('welcome.php');
}

// ===== LOAD USER-SPECIFIC DATA =====
$config_file = getUserFile($viewing_user_id, 'config.json');
$data_file = getUserFile($viewing_user_id, 'daten.json');

$config = loadConfig($config_file);
$data = safeReadJson($data_file);

// Build gruppen from config
$gruppen = [];
$headerTitle = 'Live Situation Room';
$logoUrl = '';
$logoSize = 100; // Default 100px

if ($config && isset($config['categories'])) {
    foreach ($config['categories'] as $category) {
        $gruppen[$category['key']] = [
            'title' => $category['name'],
            'icon' => $category['icon'] ?? ''
        ];
    }
    $headerTitle = $config['header_title'] ?? $headerTitle;
    $logoUrl = $config['logo_url'] ?? $logoUrl;
    $logoSize = $config['logo_size'] ?? $logoSize;
} else {
    $gruppen = [
        'general' => ['title' => 'GENERAL', 'icon' => '💡']
    ];
}

// ===== API MODE =====
if (isset($_GET['api']) && $_GET['api'] == '1') {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ===== ADMIN/CONTEXT MENU PERMISSIONS =====
$isAdmin = $is_own_workshop;
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Situation Room</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        /* --- MONOCHROME DESIGN SYSTEM --- */
        :root {
            /* Light Theme */
            --bg-body: #f5f5f5;
            --bg-card: #ffffff;
            --bg-header: rgba(255, 255, 255, 0.95);
            
            --text-primary: #111111;
            --text-secondary: #666666;
            
            --border-subtle: #e0e0e0;
            --border-strong: #111111;
            
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.03);
            --shadow-hover: 0 15px 30px rgba(0,0,0,0.08);
            
            --radius-card: 4px; 
            --radius-btn: 2px;
            
            --font-head: 'Bebas Neue', sans-serif;
            --font-body: 'Inter', sans-serif;
            
            --overlay-bg: rgba(255, 255, 255, 0.98);
            --focus-text-color: #000000;
            
            /* Hidden State Variables */
            --blur-shadow: rgba(0,0,0,0.2);
        }

        /* Dark Mode Override */
        body.light-mode {
            --bg-body: #0a0a0a;
            --bg-card: #141414;
            --bg-header: rgba(20, 20, 20, 0.95);
            
            --text-primary: #ffffff;
            --text-secondary: #888888;
            
            --border-subtle: #333333;
            --border-strong: #ffffff;
            
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.5);
            --shadow-hover: 0 15px 30px rgba(0,0,0,0.8);
            
            --overlay-bg: rgba(0, 0, 0, 0.95);
            --focus-text-color: #ffffff;
            
            --blur-shadow: rgba(255,255,255,0.3);
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-body);
            color: var(--text-primary);
            font-family: var(--font-body);
            margin: 0; padding: 0;
            overflow-x: hidden;
            transition: background 0.5s ease, color 0.5s ease;
            -webkit-font-smoothing: antialiased;
        }

        /* --- LAYOUT --- */
        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- HEADER --- */
        .header-split {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            background: var(--bg-header);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-subtle);

            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 3rem;
            transition: all 0.3s ease;
        }

        .header-content-left {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo-row {
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }
        
        .ep-logo {
            width: auto;
            filter: grayscale(100%);
            /* Height is dynamically set via inline style based on user config */
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            letter-spacing: 2px;
            font-family: var(--font-head);
            margin-bottom: -5px;
        }

        h1 {
            font-family: var(--font-head);
            font-size: 3rem;
            font-weight: 400;
            margin: 0;
            color: var(--text-primary);
            line-height: 0.9;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* --- QR & TOOLBAR --- */
        .qr-toolbar-container {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .qr-section {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--bg-body);
            padding: 10px 15px;
            border: 1px solid var(--border-subtle);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .qr-section:hover {
            border-color: var(--text-primary);
            background: var(--bg-card);
        }

        .qr-text {
            text-align: right;
            font-size: 1.2rem;
            color: var(--text-primary);
            font-family: var(--font-head);
            line-height: 0.9;
            letter-spacing: 0.5px;
        }

        .qr-wrapper {
            background: white;
            padding: 2px;
            display: block;
        }
        
        .qr-link {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 5px;
            display: block;
            text-align: right;
            font-weight: 600;
        }
        .qr-link:hover { color: var(--text-primary); text-decoration: underline; }

        /* --- BUTTONS --- */
        .toolbar-buttons {
            display: flex;
            gap: 10px;
        }

        .tool-btn {
            width: 40px; height: 40px;
            border: 1px solid var(--border-subtle);
            background: var(--bg-card);
            color: var(--text-secondary);
            display: flex;
            align-items: center; justify-content: center;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            border-radius: var(--radius-btn);
        }

        .tool-btn:hover {
            border-color: var(--text-primary);
            color: var(--text-primary);
            background: var(--bg-body);
        }

        .mobile-join-btn {
            display: none;
            background: var(--text-primary);
            color: var(--bg-card);
            padding: 12px 24px;
            font-family: var(--font-head);
            font-size: 1.2rem;
            letter-spacing: 1px;
            text-decoration: none;
            border-radius: var(--radius-btn);
            margin-top: 1rem;
            transition: opacity 0.2s;
        }
        
        .mobile-join-btn:hover {
            opacity: 0.8;
        }

        /* --- BOARD GRID --- */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            padding: 2rem 3rem 4rem 3rem;
            padding-top: 8rem; /* Space for fixed header */
            width: 100%;
        }

        .column { min-width: 0; }

        .column h2 {
            font-family: var(--font-head);
            font-size: 1.8rem;
            color: var(--text-primary);
            letter-spacing: 1px;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            padding-top: 1rem;
            border-bottom: 1px solid var(--border-strong);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 400;

            /* Sticky column titles that stay below fixed header */
            position: sticky;
            top: 6rem; /* Stays below the fixed header */
            background: var(--bg-body);
            z-index: 40;
            margin-top: -1rem;
        }

        /* --- CARDS & TRANSITIONS --- */
        .card-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            min-height: 200px;
        }

        .idea-card-wrapper {
            perspective: 1000px;
        }

        .idea-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 1.5rem;
            font-size: 0.95rem;
            line-height: 1.5;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            
            /* === SMOOTH TRANSITION MAGIC === */
            /* Physics-based cubic-bezier for a luxury feel */
            transition: 
                opacity 0.6s cubic-bezier(0.25, 0.8, 0.25, 1),
                transform 0.6s cubic-bezier(0.25, 0.8, 0.25, 1),
                filter 0.6s cubic-bezier(0.25, 0.8, 0.25, 1),
                color 0.6s ease,
                text-shadow 0.6s ease,
                border-color 0.3s ease,
                box-shadow 0.3s ease;
            
            /* Default Visible State */
            color: var(--text-primary);
            text-shadow: 0 0 0 transparent; /* Sharp text */
            opacity: 1;
            transform: scale(1);
            filter: blur(0);
        }

        .idea-card:hover:not(.blurred) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
            border-color: var(--text-primary);
        }

        /* Entry Animation (Page Load) */
        .idea-card.animate-in {
            animation: cardEnter 0.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        @keyframes cardEnter {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* === BLURRED / HIDDEN STATE === */
        /* The "Dissolve" Effect */
        .idea-card.blurred {
            /* Text Dissolves */
            color: transparent;
            text-shadow: 0 0 12px var(--blur-shadow); /* Creates a smoky blob where text was */

            /* Card Recedes */
            background: var(--bg-card); /* Keep background solid */
            opacity: 0.5; /* Fade out slightly */
            transform: scale(0.98); /* Recess slightly */

            /* No interaction */
            cursor: default;
            box-shadow: none;
            border-color: var(--border-subtle);

            /* Prevent text selection and copying */
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            pointer-events: none;
        }

        /* --- OVERLAYS --- */
        .overlay {
            position: fixed; inset: 0;
            background: var(--overlay-bg);
            z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none;
            transition: opacity 0.3s ease;
            cursor: pointer;
        }
        .overlay.active { opacity: 1; pointer-events: all; }

        .qr-overlay-content {
            background: white;
            padding: 20px;
            box-shadow: 0 0 50px rgba(0,0,0,0.1);
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }
        .overlay.active .qr-overlay-content { transform: scale(1); }

        .focus-text {
            color: var(--focus-text-color);
            font-family: var(--font-head);
            font-size: clamp(2.1rem, 5.6vw, 5.6rem);
            line-height: 0.9;
            text-align: center;
            max-width: 90%;
            text-transform: uppercase;
            transform: translateY(20px);
            transition: transform 0.4s ease;
        }
        .overlay.active .focus-text { transform: translateY(0); }
        
        .overlay-instruction { display: none; }

        /* --- CONTEXT MENU (ADMIN) --- */
        .context-menu {
            display: none;
            position: absolute; z-index: 10000;
            background: var(--bg-card);
            border: 1px solid var(--text-primary);
            box-shadow: var(--shadow-hover);
            min-width: 180px;
        }
        .context-menu-item {
            padding: 12px 16px;
            font-size: 0.85rem;
            color: var(--text-primary);
            font-family: var(--font-body);
            cursor: pointer;
            display: flex; align-items: center; gap: 10px;
            transition: background 0.1s;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .context-menu-item:hover { background: var(--border-subtle); }
        .context-menu-item.danger { color: #d32f2f; border-top: 1px solid var(--border-subtle); }
        .context-menu-item.danger:hover { background: #ffebeb; }

        /* --- RESPONSIVE --- */
        @media (max-width: 1600px) {
            .dashboard-grid { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); padding: 2rem; padding-top: 8rem; gap: 1.5rem; }
            h1 { font-size: 2.5rem; }
        }

        @media (max-width: 1200px) {
            .dashboard-grid { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
            .header-split { padding: 1.5rem 2rem; }
        }

        @media (max-width: 900px) {
            .header-split {
                flex-direction: column; align-items: flex-start; gap: 1.5rem;
                padding: 1.5rem; position: fixed; /* Keep fixed on mobile */
            }
            .dashboard-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); padding: 1rem; padding-top: 11rem; gap: 1rem; }
            .qr-section, .qr-link, .toolbar-buttons { display: none !important; }
            .mobile-join-btn { display: inline-block; width: 100%; text-align: center; }

            .toolbar { display: flex !important; position: absolute; top: 1.5rem; right: 1.5rem; gap: 8px; z-index: 100; }

            .column h2 {
                top: 8rem; /* Adjust for taller mobile header */
            }
        }

        @media (max-width: 600px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            h1 { font-size: 2.5rem; }
            .column h2 { font-size: 1.5rem; }
        }

        .toolbar { display: none; }
        
        .admin-badge {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: var(--text-primary); color: var(--bg-card);
            padding: 8px 16px; font-size: 0.75rem; font-weight: 600;
            letter-spacing: 1px; text-transform: uppercase;
            box-shadow: var(--shadow-sm); z-index: 90;
            opacity: 0.9;
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="tool-btn" id="themeToggleMobile" title="Theme">☀︎</button>
    <?php if ($is_own_workshop): ?>
        <a href="admin.php" class="tool-btn" title="Admin">⚙</a>
        <a href="logout.php" class="tool-btn" title="Logout" style="color: #d32f2f;">🚪</a>
    <?php else: ?>
        <a href="login.php" class="tool-btn" title="Login">⚙</a>
    <?php endif; ?>
</div>

<?php if ($is_own_workshop && $current_user): ?>
<div class="admin-badge">
    Admin Mode
</div>
<?php endif; ?>

<div class="overlay" id="qrOverlay">
    <div class="qr-overlay-content">
        <div id="qrcodeBig"></div>
    </div>
</div>

<div class="overlay" id="focusOverlay">
    <div class="focus-text" id="focusContent"></div>
</div>

<?php if ($isAdmin): ?>
    <div id="customContextMenu" class="context-menu">
        <div class="context-menu-item" id="ctxToggle">👁 VISIBILITY</div>
        <div class="context-menu-item danger" id="ctxDelete">🗑 DELETE</div>
    </div>
<?php endif; ?>

<div class="container">
    <header class="header-split">
        <div class="header-content-left">
            <?php if (!empty($logoUrl)): ?>
            <div class="logo-row">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="ep-logo" style="height: <?= intval($logoSize) ?>px;">
            </div>
            <?php endif; ?>

            <div>
                <span class="subtitle">Live Situation Room</span>
                <h1><?= $headerTitle ?></h1>
            </div>
            
            <a href="eingabe.php?u=<?= urlencode($viewing_user_id) ?>" class="mobile-join-btn">
               + BEITRAG
            </a>
        </div>

        <div class="qr-toolbar-container">
            <div class="qr-column">
                <div class="qr-section" id="openQr">
                    <div class="qr-text">JOIN<br>NOW</div>
                    <div class="qr-wrapper" id="qrcodeSmall"></div>
                </div>
                 <a href="eingabe.php?u=<?= urlencode($viewing_user_id) ?>" class="qr-link">Direct Link →</a>
            </div>
            
            <div class="toolbar-buttons">
                <button class="tool-btn" id="themeToggle" title="Toggle Theme">◐</button>
                <?php if ($is_own_workshop): ?>
                    <a href="admin.php" class="tool-btn" title="Admin Dashboard">⚙</a>
                    <a href="logout.php" class="tool-btn" title="Logout" style="color: #d32f2f;">🚪</a>
                <?php else: ?>
                    <a href="login.php" class="tool-btn" title="Login as Admin">⚙</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="dashboard-grid" id="board">
        <?php foreach ($gruppen as $key => $info): ?>
            <div class="column" id="col-<?= $key ?>">
                <h2><?= $info['title'] ?></h2>
                <div class="card-container">
                    </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    // --- THEME LOGIC ---
    const themeBtn = document.getElementById('themeToggle');
    const themeBtnMobile = document.getElementById('themeToggleMobile');
    const body = document.body;

    function updateIcon(isLight) {
        const icon = isLight ? '◐' : '◑';
        if (themeBtn) themeBtn.innerText = icon;
        if (themeBtnMobile) themeBtnMobile.innerText = icon;
    }

    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('light-mode');
        updateIcon(true);
    } else {
        updateIcon(false);
    }

    function toggleTheme() {
        body.classList.toggle('light-mode');
        const isDark = body.classList.contains('light-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        updateIcon(isDark);
    }

    if (themeBtn) themeBtn.addEventListener('click', toggleTheme);
    if (themeBtnMobile) themeBtnMobile.addEventListener('click', toggleTheme);

    // --- QR CODE LOGIC ---
    const currentUrl = window.location.href;
    const baseUrl = currentUrl.substring(0, currentUrl.lastIndexOf('/'));
    const inputUrl = baseUrl + '/eingabe.php?u=<?= urlencode($viewing_user_id) ?>';

    // 1. Small QR
    new QRCode(document.getElementById("qrcodeSmall"), {
        text: inputUrl, width: 45, height: 45,
        colorDark : "#000000", colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.L
    });

    // 2. Big QR
    const qrOverlay = document.getElementById('qrOverlay');
    const openQrBtn = document.getElementById('openQr');
    let bigQrGenerated = false;

    if(openQrBtn) {
        openQrBtn.addEventListener('click', () => {
            qrOverlay.classList.add('active');
            if (!bigQrGenerated) {
                new QRCode(document.getElementById("qrcodeBig"), { 
                    text: inputUrl, width: 400, height: 400, 
                    colorDark : "#000000", colorLight : "#ffffff", 
                    correctLevel : QRCode.CorrectLevel.H 
                });
                bigQrGenerated = true;
            }
        });
    }

    // --- FOCUS MODE LOGIC ---
    const focusOverlay = document.getElementById('focusOverlay');
    const focusContent = document.getElementById('focusContent');
    const board = document.getElementById('board');
    let remoteFocusActiveId = null; 

    // Local Click Event
    board.addEventListener('click', function(e) {
        const wrapper = e.target.closest('.idea-card-wrapper');
        if (wrapper) {
            const card = wrapper.querySelector('.idea-card');
            if (card && !card.classList.contains('blurred')) {
                const text = card.innerText;
                focusContent.innerText = text;
                focusOverlay.classList.add('active');
            }
        }
    });

    qrOverlay.addEventListener('click', () => qrOverlay.classList.remove('active'));
    focusOverlay.addEventListener('click', () => {
        focusOverlay.classList.remove('active');
    });

    // --- CONTEXT MENU LOGIC ---
    <?php if ($isAdmin): ?>
    (function() {
        const ctxMenu = document.getElementById('customContextMenu');
        const ctxToggle = document.getElementById('ctxToggle');
        const ctxDelete = document.getElementById('ctxDelete');
        let currentCardId = null;

        document.addEventListener('click', function(e) {
            if (!ctxMenu.contains(e.target)) {
                ctxMenu.style.display = 'none';
            }
        });

        document.addEventListener('contextmenu', function(e) {
            const wrapper = e.target.closest('.idea-card-wrapper');
            if (wrapper) {
                e.preventDefault();
                currentCardId = wrapper.getAttribute('data-id');
                const card = wrapper.querySelector('.idea-card');
                
                const isHidden = card.classList.contains('blurred');
                ctxToggle.innerHTML = isHidden ? '👁 SHOW CARD' : '🚫 HIDE CARD';

                ctxMenu.style.display = 'block';
                ctxMenu.style.left = e.pageX + 'px';
                ctxMenu.style.top = e.pageY + 'px';
            } else {
                ctxMenu.style.display = 'none';
            }
        });

        ctxToggle.addEventListener('click', function() {
            if (currentCardId) {
                fetch('admin.php?toggle_id=' + currentCardId + '&ajax=1')
                    .then(() => updateBoard())
                    .catch(err => console.error(err));
                ctxMenu.style.display = 'none';
            }
        });

        ctxDelete.addEventListener('click', function() {
            if (currentCardId && confirm('Delete this card?')) {
                fetch('admin.php?delete=' + currentCardId + '&ajax=1')
                    .then(() => updateBoard())
                    .catch(err => console.error(err));
                ctxMenu.style.display = 'none';
            }
        });
    })();
    <?php endif; ?>


    // --- DATA HANDLING ---
    const initialData = <?= json_encode($data) ?>;
    
    renderData(initialData);

    function renderData(data) {
        const existingIds = new Set();
        document.querySelectorAll('.idea-card-wrapper').forEach(el => existingIds.add(el.getAttribute('data-id')));
        const validIdsInNewData = new Set();

        checkRemoteFocus(data);

        data.forEach(entry => {
            validIdsInNewData.add(entry.id);
            const isVisible = (entry.visible === true || entry.visible === "true");
            const container = document.querySelector(`#col-${entry.thema} .card-container`);
            
            if (container) {
                let wrapper = document.getElementById('wrap-' + entry.id);
                let card;

                if (!wrapper) {
                    wrapper = document.createElement('div');
                    wrapper.id = 'wrap-' + entry.id;
                    wrapper.setAttribute('data-id', entry.id);
                    wrapper.className = 'idea-card-wrapper';
                    
                    card = document.createElement('div');
                    card.id = 'card-' + entry.id;
                    card.className = 'idea-card ' + (!isVisible ? 'blurred' : 'animate-in');
                    card.innerText = entry.text;
                    
                    wrapper.appendChild(card);
                    if(container.firstChild) {
                        container.insertBefore(wrapper, container.firstChild);
                    } else {
                        container.appendChild(wrapper);
                    }
                } else {
                    card = document.getElementById('card-' + entry.id);
                    if(card) {
                        if (!container.contains(wrapper)) container.prepend(wrapper);
                        
                        if (isVisible) {
                            card.classList.remove('blurred');
                        } else {
                            card.classList.add('blurred');
                        }
                        
                        if (card.innerText !== entry.text) card.innerText = entry.text;
                    }
                }
            }
        });

        existingIds.forEach(id => {
            if (!validIdsInNewData.has(id)) {
                const el = document.getElementById('wrap-' + id);
                if (el) el.remove();
            }
        });
    }

    function checkRemoteFocus(data) {
        const focusedEntry = data.find(e => e.focus === true || e.focus === "true");

        if (focusedEntry) {
            focusContent.innerText = focusedEntry.text;
            focusOverlay.classList.add('active');
            remoteFocusActiveId = focusedEntry.id;
        } else {
            if (remoteFocusActiveId !== null) {
                focusOverlay.classList.remove('active');
                remoteFocusActiveId = null;
            }
        }
    }

    function updateBoard() {
        fetch('index.php?api=1&u=<?= urlencode($viewing_user_id) ?>')
            .then(response => response.json())
            .then(data => renderData(data))
            .catch(err => console.error(err));
    }
    
    setInterval(updateBoard, 2000);
</script>
</body>
</html>