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

if ($config && isset($config['categories'])) {
    foreach ($config['categories'] as $category) {
        $gruppen[$category['key']] = [
            'title' => $category['name'],
            'icon' => $category['icon'] ?? ''
        ];
    }
    $headerTitle = $config['header_title'] ?? $headerTitle;
    $logoUrl = $config['logo_url'] ?? $logoUrl;
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        /* --- DESIGN SYSTEM (AGENCY THEME) --- */
        :root {
            /* Light Theme (Default) */
            --bg-body: #F3F4F6;
            --bg-pattern: #E5E7EB;
            --bg-card: #FFFFFF;
            --bg-header: rgba(255, 255, 255, 0.85);
            
            --text-primary: #111827;
            --text-secondary: #6B7280;
            --text-accent: #00658b;
            
            --border-subtle: #E5E7EB;
            --border-hover: #00658b;
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            
            --primary-color: #00658b;
            --primary-hover: #004e6d;
            --accent-glow: rgba(0, 101, 139, 0.15);
            
            --radius-card: 16px;
            --radius-btn: 12px;
            
            --font-sans: 'Inter', system-ui, -apple-system, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        /* Dark Mode (Class toggle compatible with existing JS) */
        body.light-mode {
            --bg-body: #0f1115;
            --bg-pattern: #1f2937;
            --bg-card: #1f2937;
            --bg-header: rgba(15, 17, 21, 0.85);
            
            --text-primary: #F9FAFB;
            --text-secondary: #9CA3AF;
            --text-accent: #38bdf8;
            
            --border-subtle: #374151;
            --border-hover: #38bdf8;
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
            
            --primary-color: #38bdf8; 
            --primary-hover: #0ea5e9;
            --accent-glow: rgba(56, 189, 248, 0.15);
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-body);
            background-image: radial-gradient(var(--bg-pattern) 1px, transparent 1px);
            background-size: 24px 24px; /* Dot pattern grid */
            color: var(--text-primary);
            font-family: var(--font-sans);
            margin: 0; padding: 0;
            overflow-x: hidden;
            transition: background 0.3s ease, color 0.3s ease;
            -webkit-font-smoothing: antialiased;
        }

        /* --- LAYOUT --- */
        .container {
            max-width: 1800px;
            margin: 0 auto;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- HEADER --- */
        .header-split {
            position: sticky;
            top: 20px;
            z-index: 50;
            margin: 0 2rem 2rem 2rem;
            padding: 1.25rem 2rem;
            
            background: var(--bg-header);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-subtle);
            border-radius: 24px;
            box-shadow: var(--shadow-md);
            
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .header-content-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .logo-row {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .ep-logo {
            height: 32px;
            width: auto;
            /* Adaptive logo color via filter if needed, otherwise normal */
        }

        .subtitle {
            color: var(--primary-color);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-family: var(--font-mono);
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            line-height: 1.2;
        }

        /* --- QR & TOOLBAR --- */
        .qr-toolbar-container {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .qr-toolbar-row {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .qr-section {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--bg-card);
            padding: 8px 12px 8px 16px;
            border-radius: 16px;
            border: 1px solid var(--border-subtle);
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        }

        .qr-section:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-color);
        }

        .qr-text {
            text-align: right;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            font-family: var(--font-mono);
            line-height: 1.4;
        }

        .qr-wrapper {
            background: white; /* QR needs white bg always */
            padding: 4px;
            border-radius: 6px;
        }
        
        .qr-link {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s;
            margin-top: 4px;
            display: block;
            text-align: right;
        }
        .qr-link:hover { color: var(--primary-color); }

        /* --- BUTTONS --- */
        .toolbar-buttons {
            display: flex;
            gap: 8px;
        }

        .tool-btn {
            width: 44px; height: 44px;
            border-radius: 12px;
            border: 1px solid var(--border-subtle);
            background: var(--bg-card);
            color: var(--text-secondary);
            display: flex;
            align-items: center; justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .tool-btn:hover {
            background: var(--primary-color);
            color: #fff;
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--accent-glow);
        }

        .mobile-join-btn {
            display: none; /* Desktop default */
            background: var(--primary-color);
            color: #fff;
            padding: 10px 20px;
            border-radius: 99px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            box-shadow: 0 4px 12px var(--accent-glow);
            transition: transform 0.2s;
        }
        
        .mobile-join-btn:hover {
            transform: translateY(-2px);
            background: var(--primary-hover);
        }

        /* --- BOARD GRID --- */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.5rem;
            padding: 0 2rem 3rem 2rem;
            width: 100%;
        }

        .column h2 {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 700;
            margin-bottom: 1.25rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-subtle);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* --- CARDS --- */
        .card-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 200px; /* Drop zone hint */
        }

        .idea-card-wrapper {
            perspective: 1000px;
        }

        .idea-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 1.25rem;
            color: var(--text-primary);
            font-size: 0.95rem;
            line-height: 1.5;
            border: 1px solid transparent;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
        }

        .idea-card:hover:not(.blurred) {
            transform: translateY(-4px) scale(1.01);
            box-shadow: var(--shadow-md);
            border-color: var(--border-hover);
        }

        /* Entry Animation */
        .idea-card.animate-in {
            animation: cardEnter 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes cardEnter {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* BLURRED STATE - Modern "Frosted Skeleton" Look */
        .idea-card.blurred {
            color: transparent;
            cursor: not-allowed;
            background: var(--bg-card);
            opacity: 0.7;
            /* Striped pattern via CSS gradient */
            background-image: repeating-linear-gradient(
                -45deg,
                transparent,
                transparent 10px,
                rgba(0,0,0,0.03) 10px,
                rgba(0,0,0,0.03) 20px
            );
        }
        /* Add a "Skeleton" text block look for blurred cards */
        .idea-card.blurred::after {
            content: "";
            position: absolute;
            top: 1.25rem; left: 1.25rem; right: 20%;
            height: 12px;
            background: var(--border-subtle);
            border-radius: 4px;
            box-shadow: 0 20px 0 var(--border-subtle);
        }

        /* --- OVERLAYS --- */
        .overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .overlay.active { opacity: 1; pointer-events: all; }

        .qr-overlay-content {
            background: white;
            padding: 40px;
            border-radius: 32px;
            box-shadow: var(--shadow-xl);
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .overlay.active .qr-overlay-content { transform: scale(1); }

        .focus-text {
            color: white;
            font-size: clamp(2rem, 5vw, 4rem);
            font-weight: 800;
            text-align: center;
            max-width: 80%;
            line-height: 1.1;
            text-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transform: translateY(30px);
            transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .overlay.active .focus-text { transform: translateY(0); }
        .overlay-instruction { color: rgba(255,255,255,0.7); margin-top: 2rem; font-family: var(--font-mono); font-size: 0.8rem; letter-spacing: 2px; text-transform: uppercase; }

        /* --- CONTEXT MENU (ADMIN) --- */
        .context-menu {
            display: none;
            position: absolute; z-index: 10000;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            min-width: 200px;
            animation: fadeIn 0.1s ease;
        }
        .context-menu-item {
            padding: 12px 16px;
            font-size: 0.9rem;
            color: var(--text-primary);
            cursor: pointer;
            display: flex; align-items: center; gap: 10px;
            transition: background 0.1s;
        }
        .context-menu-item:hover { background: var(--bg-body); color: var(--primary-color); }
        .context-menu-item.danger { color: #ef4444; border-top: 1px solid var(--border-subtle); }
        .context-menu-item.danger:hover { background: #fee2e2; }

        /* --- RESPONSIVE --- */
        @media (max-width: 1600px) { .dashboard-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 1200px) { .dashboard-grid { grid-template-columns: repeat(3, 1fr); padding: 0 1rem 2rem 1rem; } }
        
        @media (max-width: 900px) {
            .header-split {
                flex-direction: column; align-items: stretch; gap: 1rem;
                margin: 0; border-radius: 0; top: 0;
                border: none; border-bottom: 1px solid var(--border-subtle);
            }
            .dashboard-grid { grid-template-columns: repeat(2, 1fr); padding: 1rem; }
            .qr-section, .qr-link, .toolbar-buttons { display: none !important; }
            .mobile-join-btn { display: inline-block; text-align: center; margin-top: 0.5rem; }
            
            /* Mobile Toolbar Floating */
            .toolbar { display: flex !important; position: absolute; top: 1.5rem; right: 1.5rem; gap: 8px; z-index: 100; }
        }
        
        @media (max-width: 600px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            h1 { font-size: 1.5rem; }
        }

        /* Utility for original toolbar fallback */
        .toolbar { display: none; }
        
        /* Floating Badge for Admin */
        .admin-badge {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: var(--primary-color); color: white;
            padding: 8px 16px; border-radius: 99px; font-size: 0.75rem; font-weight: 600;
            box-shadow: var(--shadow-xl); z-index: 90;
            backdrop-filter: blur(4px);
            opacity: 0.9;
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="tool-btn" id="themeToggleMobile" title="Theme">☀︎</button>
    <?php if ($is_own_workshop): ?>
        <a href="admin.php" class="tool-btn" title="Admin">⚙</a>
        <a href="logout.php" class="tool-btn" title="Logout" style="color: #ef4444;">🚪</a>
    <?php else: ?>
        <a href="login.php" class="tool-btn" title="Login">⚙</a>
    <?php endif; ?>
</div>

<?php if ($is_own_workshop && $current_user): ?>
<div class="admin-badge">
    👤 <?= htmlspecialchars($current_user['email']) ?> | Workshop Admin
</div>
<?php endif; ?>

<div class="overlay" id="qrOverlay">
    <div class="qr-overlay-content">
        <div id="qrcodeBig"></div>
    </div>
    <div class="overlay-instruction">Click anywhere to close</div>
</div>

<div class="overlay" id="focusOverlay">
    <div class="focus-text" id="focusContent"></div>
    <div class="overlay-instruction">Live Focus</div>
</div>

<?php if ($isAdmin): ?>
    <div id="customContextMenu" class="context-menu">
        <div class="context-menu-item" id="ctxToggle">👁 Visibility Toggle</div>
        <div class="context-menu-item danger" id="ctxDelete">🗑 Delete Card</div>
    </div>
<?php endif; ?>

<div class="container">
    <header class="header-split">
        <div class="header-content-left">
            <?php if (!empty($logoUrl)): ?>
            <div class="logo-row">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="ep-logo">
            </div>
            <?php endif; ?>

            <div>
                <span class="subtitle">Live Situation Room</span>
                <h1><?= $headerTitle ?></h1>
            </div>
            
            <a href="eingabe.php?u=<?= urlencode($viewing_user_id) ?>" class="mobile-join-btn">
               + New Entry
            </a>
        </div>

        <div class="qr-toolbar-container">
            <div class="qr-toolbar-row">
                <div class="qr-column">
                    <div class="qr-section" id="openQr">
                        <div class="qr-text">SCAN TO<br>CONTRIBUTE</div>
                        <div class="qr-wrapper" id="qrcodeSmall"></div>
                    </div>
                     <a href="eingabe.php?u=<?= urlencode($viewing_user_id) ?>" class="qr-link">or click here →</a>
                </div>
                
                <div class="toolbar-buttons">
                    <button class="tool-btn" id="themeToggle" title="Toggle Theme">☀︎</button>
                    <?php if ($is_own_workshop): ?>
                        <a href="admin.php" class="tool-btn" title="Admin Dashboard">⚙</a>
                        <a href="logout.php" class="tool-btn" title="Logout" style="color: #ef4444;">🚪</a>
                    <?php else: ?>
                        <a href="login.php" class="tool-btn" title="Login as Admin">⚙</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-grid" id="board">
        <?php foreach ($gruppen as $key => $info): ?>
            <div class="column" id="col-<?= $key ?>">
                <h2><?= $info['icon'] ?> <?= $info['title'] ?></h2>
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
        // Icon logic inverted visually because default is now light/modern
        const icon = isLight ? '☾' : '☀︎';
        if (themeBtn) themeBtn.innerText = icon;
        if (themeBtnMobile) themeBtnMobile.innerText = icon;
    }

    // Default is Light. Class 'light-mode' triggers Dark Mode colors in CSS now.
    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('light-mode'); // Triggers dark vars in new CSS
        updateIcon(true);
    } else {
        updateIcon(false);
    }

    function toggleTheme() {
        body.classList.toggle('light-mode');
        // If class is present, we are in Dark Mode (renamed vars)
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
        text: inputUrl, width: 45, height: 45, // Adjusted size for new design
        colorDark : "#111827", colorLight : "#ffffff",
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
                    text: inputUrl, width: 300, height: 300, 
                    colorDark : "#111827", colorLight : "#ffffff", 
                    correctLevel : QRCode.CorrectLevel.H 
                });
                bigQrGenerated = true;
            }
        });
    }

    // --- FOCUS MODE LOGIC (Local + Remote) ---
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

    // --- CONTEXT MENU LOGIC (ADMIN ONLY) ---
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
                ctxToggle.innerHTML = isHidden ? '👁 Reveal Card' : '🚫 Hide Card';

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
            if (currentCardId && confirm('Really delete this card?')) {
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
            // Select via ID preserved in HTML
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
                    // Note: 'animate-in' class provides the new entry animation defined in CSS
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