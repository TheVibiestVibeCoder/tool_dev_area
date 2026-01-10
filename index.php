<?php
// index.php
session_start(); // Session starten um Admin-Status zu prüfen

// Load config to get dynamic categories and header
require_once 'file_handling_robust.php';
$config = loadConfig('config.json');

// Build gruppen from config
$gruppen = [];
$headerTitle = 'Strategien<br>Black-Outs'; // Default

if ($config && isset($config['categories'])) {
    foreach ($config['categories'] as $category) {
        $gruppen[$category['key']] = [
            'title' => $category['name'],
            'icon' => $category['icon'] ?? ''
        ];
    }
    $headerTitle = $config['header_title'] ?? $headerTitle;
} else {
    // Fallback if config cannot be loaded
    $gruppen = [
        'bildung' => ['title' => 'BILDUNG & FORSCHUNG', 'icon' => '📚'],
        'social' => ['title' => 'SOZIALE MEDIEN', 'icon' => '📱'],
        'individuell' => ['title' => 'INDIV. VERANTWORTUNG', 'icon' => '🧑'],
        'politik' => ['title' => 'POLITIK & RECHT', 'icon' => '⚖️'],
        'kreativ' => ['title' => 'INNOVATIVE ANSÄTZE', 'icon' => '💡']
    ];
}

// ===== DATA LOADING =====

$file = 'daten.json';
$data = safeReadJson($file);

// API Mode
if (isset($_GET['api']) && $_GET['api'] == '1') {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Prüfen ob Admin eingeloggt ist (für Context Menu Berechtigung)
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Situation Room | Infraprotect</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        /* --- INFRAPROTECT THEME ENGINE --- */
        :root {
            /* Corporate Colors */
            --ip-blue: #00658b;
            --ip-dark: #32373c;
            --ip-grey-bg: #f4f4f4;
            --ip-card-bg: #ffffff;
            --ip-border: #e0e0e0;
            
            /* Text Colors */
            --text-main: #32373c;
            --text-muted: #767676;
            --text-light: #ffffff;
            
            /* Shadows & Effects */
            --card-shadow: 0 2px 5px rgba(0,0,0,0.05);
            --card-shadow-hover: 0 10px 20px rgba(0,0,0,0.1);
            
            /* Typography */
            --font-heading: 'Montserrat', sans-serif;
            --font-body: 'Roboto', sans-serif;
            
            /* Dimensions */
            --radius-pill: 9999px;
            --radius-card: 4px;

            /* Filter for Logo (None needed for original color) */
            --logo-filter: none;
            
            --blur-color: rgba(0,0,0,0.1); 
            --spotlight-opacity: 0;
        }

        /* Dark Mode Override (falls via JS getoggelt, hier als "High Contrast" interpretiert) */
        body.light-mode {
            /* Wir nutzen die Klasse "light-mode" im JS, aber hier kehren wir es um 
               oder passen es an, da das Corporate Design per se "Light" ist. */
            --bg-body: #1a1a1a;
            --ip-card-bg: #2c2c2c;
            --text-main: #f4f4f4;
            --text-muted: #aaaaaa;
            --card-shadow: 0 2px 5px rgba(0,0,0,0.5);
            --logo-filter: brightness(0) invert(1);
        }

        body {
            background-color: var(--ip-grey-bg);
            color: var(--text-main);
            font-family: var(--font-body);
            margin: 0; padding: 0;
            overflow-x: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Noise & Spotlight entfernt für cleanen Look */
        .mono-noise, .spotlight { display: none; }

        /* --- TOOLBAR --- */
        .toolbar {
            position: absolute; top: 1.5rem; right: 2rem;
            display: flex; gap: 10px; z-index: 100;
        }
        .tool-btn {
            background: var(--ip-card-bg);
            border: 1px solid var(--ip-border);
            color: var(--text-muted);
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            font-size: 1.1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .tool-btn:hover {
            background: var(--ip-blue);
            color: #fff;
            border-color: var(--ip-blue);
        }

        .container { max-width: 100%; margin: 0 auto; padding: 0; }

        /* HEADER */
        .header-split {
            display: flex; justify-content: space-between; align-items: center;
            background: #fff;
            padding: 1.5rem 3rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--ip-border);
            position: relative;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        .header-content-left {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .subtitle { 
            color: var(--ip-blue); 
            text-transform: uppercase; 
            letter-spacing: 2px; 
            font-size: 0.8rem; 
            font-weight: 700; 
            display: block; 
            margin-bottom: 0;
            font-family: var(--font-heading);
        }
        
        /* LOGO STYLES */
        .logo-row {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 10px;
        }
        .ep-logo {
            height: 50px;
            width: auto;
            filter: var(--logo-filter);
        }
        /* Hide existing elements we don't need for the new design but keep in DOM for safety */
        .logo-separator, .dc-logo {
            display: none; 
        }

        /* TITLE */
        h1 { 
            font-family: var(--font-heading); 
            font-size: clamp(1.8rem, 4vw, 3rem); 
            margin: 0; 
            line-height: 1.2; 
            color: var(--ip-dark); 
            font-weight: 700;
        }
        
        /* QR SECTION */
        .qr-section { 
            display: flex; align-items: center; gap: 1rem; cursor: pointer; 
            padding: 10px;
            background: var(--ip-grey-bg);
            border-radius: var(--radius-card);
            border: 1px solid var(--ip-border);
            transition: all 0.3s ease;
        }
        .qr-section:hover { transform: translateY(-2px); box-shadow: var(--card-shadow); border-color: var(--ip-blue); }
        .qr-text { text-align: right; color: var(--ip-dark); font-size: 0.75rem; font-weight: 600; letter-spacing: 1px; line-height: 1.3; font-family: var(--font-heading); }
        .qr-wrapper { background: white; padding: 4px; display: inline-block; border-radius: 2px; }

        /* MOBILE JOIN BUTTON */
        .mobile-join-btn {
            display: none; 
            background-color: var(--ip-blue);
            color: #fff;
            font-family: var(--font-heading);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: var(--radius-pill);
            margin-top: 1rem;
            transition: all 0.3s ease;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            width: fit-content;
        }
        
        .mobile-join-btn:hover {
            background-color: #004e6d;
            transform: translateY(-1px);
        }

        /* OVERLAYS */
        .overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 9999; display: flex; align-items: center; justify-content: center; flex-direction: column;
            opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
            cursor: pointer;
        }
        .overlay.active { opacity: 1; pointer-events: all; }
        
        .qr-overlay-content {
            background: white; padding: 30px; border-radius: var(--radius-card);
            box-shadow: 0 20px 60px rgba(0,0,0,0.4); 
            transform: scale(0.95); transition: transform 0.3s ease;
            text-align: center;
        }
        .overlay.active .qr-overlay-content { transform: scale(1); }
        .overlay-instruction { margin-top: 20px; color: #fff; font-family: var(--font-heading); letter-spacing: 1px; text-transform: uppercase; font-size: 0.9rem; }

        /* FOCUS CARD STYLES */
        .focus-text {
            font-family: var(--font-heading);
            color: #fff;
            font-size: clamp(1.8rem, 4vw, 3.5rem);
            line-height: 1.4;
            max-width: 80%;
            text-align: center;
            font-weight: 600;
            transform: translateY(20px);
            transition: transform 0.4s ease;
        }
        .overlay.active .focus-text { transform: translateY(0); }

        /* GRID */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.5rem;
            width: 100%;
            padding: 0 3rem 3rem 3rem;
            box-sizing: border-box;
        }
        
        .column { min-width: 0; }
        
        .column h2 {
            font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; 
            color: var(--ip-blue);
            border-bottom: 2px solid var(--ip-blue); 
            padding-bottom: 10px; margin: 0 0 1.5rem 0;
            font-family: var(--font-heading);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .idea-card-wrapper { margin-bottom: 1rem; perspective: 1000px; }

        /* CARD STYLES */
        .idea-card {
            background: var(--ip-card-bg);
            border: 1px solid var(--ip-border);
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--text-main);
            box-shadow: var(--card-shadow);
            border-radius: var(--radius-card);
        }

        .idea-card.animate-in { 
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .idea-card:hover {
            border-color: var(--ip-blue);
            transform: translateY(-3px);
            box-shadow: var(--card-shadow-hover);
        }

        /* BLURRED STATE (Clean Placeholder Look) */
        .idea-card.blurred {
            color: transparent;
            cursor: default;
            background: #fcfcfc;
            border: 1px dashed #ccc;
            box-shadow: none;
            /* Striped pattern for hidden content */
            background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, #f0f0f0 10px, #f0f0f0 20px);
        }
        .idea-card.blurred:hover {
            transform: none;
            border-color: #ccc;
        }

        /* === ADMIN CONTEXT MENU STYLES === */
        .context-menu {
            position: absolute;
            z-index: 10000;
            background: #fff;
            border: 1px solid var(--ip-border);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            border-radius: var(--radius-card);
            display: none;
            overflow: hidden;
            min-width: 180px;
            padding: 5px 0;
        }

        .context-menu-item {
            padding: 10px 15px;
            cursor: pointer;
            color: var(--ip-dark);
            font-size: 0.9rem;
            font-family: var(--font-body);
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .context-menu-item:hover {
            background: var(--ip-grey-bg);
            color: var(--ip-blue);
            font-weight: 500;
        }

        .context-menu-item.danger {
            color: #cf2e2e;
            border-top: 1px solid #eee;
            margin-top: 5px;
            padding-top: 10px;
        }

        .context-menu-item.danger:hover {
            background: #fff0f0;
        }

        /* RESPONSIVE GRID */
        @media (max-width: 1600px) { .dashboard-grid { grid-template-columns: repeat(4, 1fr); padding: 0 2rem 2rem 2rem; } }
        @media (max-width: 1300px) { .dashboard-grid { grid-template-columns: repeat(3, 1fr); } }
        
        @media (max-width: 900px) { 
            /* GRID Anpassung */
            .dashboard-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; padding: 0 1rem 1rem 1rem; }
            .header-split { flex-direction: column; align-items: flex-start; gap: 1.5rem; padding: 1.5rem; }
            
            /* MOBILE LOGO & QR CHANGES */
            .ep-logo { height: 40px; }
            .logo-row { gap: 15px; }

            /* Hide QR, Show Button */
            .qr-section { display: none !important; }
            .mobile-join-btn { display: inline-flex; }
        }
        
        @media (max-width: 600px) { 
            .dashboard-grid { grid-template-columns: 1fr; }
            .toolbar { top: 1rem; right: 1rem; }
            h1 { font-size: 2rem; }
        }
    </style>
</head>
<body>

<div class="mono-noise"></div>
<div class="spotlight"></div>

<div class="toolbar">
    <button class="tool-btn" id="themeToggle" title="Toggle Theme">☀︎</button>
    <a href="admin.php" class="tool-btn" title="Admin Panel">⚙</a>
</div>

<div class="overlay" id="qrOverlay">
    <div class="qr-overlay-content">
        <div id="qrcodeBig"></div>
    </div>
    <div class="overlay-instruction">Klicken zum Schließen</div>
</div>

<div class="overlay" id="focusOverlay">
    <div class="focus-text" id="focusContent"></div>
</div>

<?php if ($isAdmin): ?>
    <div id="customContextMenu" class="context-menu">
        <div class="context-menu-item" id="ctxToggle">👁 Einblenden/Ausblenden</div>
        <div class="context-menu-item danger" id="ctxDelete">🗑 Löschen</div>
    </div>
<?php endif; ?>

<div class="container">
    <header class="header-split">
        <div class="header-content-left">
            <div class="logo-row">
                <img src="https://infraprotect.com/wp-content/uploads/2019/05/Infraprotect_Logo.png" alt="Infraprotect" class="ep-logo">
                <span class="logo-separator">|</span>
                <img src="" alt="" class="dc-logo"> </div>
            
            <div>
                <span class="subtitle">Live Situation Room</span>
                <h1><?= $headerTitle ?></h1>
            </div>
            
            <a href="eingabe.php" class="mobile-join-btn">
               + Beitrag erstellen
            </a>
        </div>
        
        <div class="qr-section" id="openQr">
            <div class="qr-text">SCAN TO<br>JOIN<br><span style="opacity: 0.6; font-weight: normal;">CLICK TO ZOOM</span></div>
            <div class="qr-wrapper" id="qrcodeSmall"></div>
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
    const body = document.body;
    
    function updateIcon(isLight) {
        themeBtn.innerText = isLight ? '☾' : '☀︎';
    }

    if (localStorage.getItem('theme') === 'light') {
        body.classList.add('light-mode');
        updateIcon(true);
    }

    themeBtn.addEventListener('click', () => {
        body.classList.toggle('light-mode');
        const isLight = body.classList.contains('light-mode');
        localStorage.setItem('theme', isLight ? 'light' : 'dark');
        updateIcon(isLight);
    });

    // --- QR CODE LOGIC ---
    const currentUrl = window.location.href;
    const inputUrl = currentUrl.substring(0, currentUrl.lastIndexOf('/')) + '/eingabe.php';
    
    // 1. Small QR
    new QRCode(document.getElementById("qrcodeSmall"), { 
        text: inputUrl, width: 60, height: 60, 
        colorDark : "#00658b", colorLight : "#ffffff", 
        correctLevel : QRCode.CorrectLevel.H 
    });

    // 2. Big QR
    const qrOverlay = document.getElementById('qrOverlay');
    const openQrBtn = document.getElementById('openQr');
    let bigQrGenerated = false;

    openQrBtn.addEventListener('click', () => {
        qrOverlay.classList.add('active');
        if (!bigQrGenerated) {
            new QRCode(document.getElementById("qrcodeBig"), { 
                text: inputUrl, width: 350, height: 350, 
                colorDark : "#00658b", colorLight : "#ffffff", 
                correctLevel : QRCode.CorrectLevel.H 
            });
            bigQrGenerated = true;
        }
    });

    // --- FOCUS MODE LOGIC (Local + Remote) ---
    const focusOverlay = document.getElementById('focusOverlay');
    const focusContent = document.getElementById('focusContent');
    const board = document.getElementById('board');
    let remoteFocusActiveId = null; // Track if we are currently in a remote session

    // Local Click Event
    board.addEventListener('click', function(e) {
        const wrapper = e.target.closest('.idea-card-wrapper');
        
        if (wrapper) {
            const card = wrapper.querySelector('.idea-card');
            // Only open if the card is NOT blurred
            if (card && !card.classList.contains('blurred')) {
                const text = card.innerText;
                focusContent.innerText = text;
                focusOverlay.classList.add('active');
            }
        }
    });

    // Close overlays on click
    qrOverlay.addEventListener('click', () => qrOverlay.classList.remove('active'));
    
    focusOverlay.addEventListener('click', () => {
        focusOverlay.classList.remove('active');
        // If user manually closes it, we can technically reset our tracker, 
        // but the next polling might open it again if Admin hasn't turned it off.
        // This is acceptable behavior (admin enforces focus).
    });

    // --- CONTEXT MENU LOGIC (ADMIN ONLY) ---
    <?php if ($isAdmin): ?>
    (function() {
        const ctxMenu = document.getElementById('customContextMenu');
        const ctxToggle = document.getElementById('ctxToggle');
        const ctxDelete = document.getElementById('ctxDelete');
        let currentCardId = null;

        // Global click to close menu
        document.addEventListener('click', function(e) {
            if (!ctxMenu.contains(e.target)) {
                ctxMenu.style.display = 'none';
            }
        });

        // Right-click listener on cards
        document.addEventListener('contextmenu', function(e) {
            const wrapper = e.target.closest('.idea-card-wrapper');
            if (wrapper) {
                e.preventDefault();
                currentCardId = wrapper.getAttribute('data-id');
                const card = wrapper.querySelector('.idea-card');
                
                // Update text based on state
                const isHidden = card.classList.contains('blurred');
                ctxToggle.innerHTML = isHidden ? '👁 Einblenden' : '🚫 Ausblenden';

                // Position and show menu
                ctxMenu.style.display = 'block';
                ctxMenu.style.left = e.pageX + 'px';
                ctxMenu.style.top = e.pageY + 'px';
            } else {
                ctxMenu.style.display = 'none';
            }
        });

        // Handle Toggle
        ctxToggle.addEventListener('click', function() {
            if (currentCardId) {
                fetch('admin.php?toggle_id=' + currentCardId + '&ajax=1')
                    .then(() => updateBoard())
                    .catch(err => console.error(err));
                ctxMenu.style.display = 'none';
            }
        });

        // Handle Delete
        ctxDelete.addEventListener('click', function() {
            if (currentCardId && confirm('Diesen Eintrag wirklich löschen?')) {
                fetch('admin.php?delete=' + currentCardId + '&ajax=1')
                    .then(() => updateBoard())
                    .catch(err => console.error(err));
                ctxMenu.style.display = 'none';
            }
        });
    })();
    <?php endif; ?>


    // --- DATA HANDLING ---
    const gruppenConfig = <?= json_encode($gruppen) ?>;
    const initialData = <?= json_encode($data) ?>;
    
    renderData(initialData);

    function renderData(data) {
        const existingIds = new Set();
        document.querySelectorAll('.idea-card-wrapper').forEach(el => existingIds.add(el.getAttribute('data-id')));
        const validIdsInNewData = new Set();

        // 1. Check for Remote Focus first
        checkRemoteFocus(data);

        // 2. Render Cards
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
        // Find if any card has focus: true
        const focusedEntry = data.find(e => e.focus === true || e.focus === "true");

        if (focusedEntry) {
            // Admin wants this card focused
            focusContent.innerText = focusedEntry.text;
            focusOverlay.classList.add('active');
            remoteFocusActiveId = focusedEntry.id;
        } else {
            // No card is focused remotely.
            // If we were previously holding a remote focus open, we should close it now.
            if (remoteFocusActiveId !== null) {
                focusOverlay.classList.remove('active');
                remoteFocusActiveId = null;
            }
        }
    }

    function updateBoard() {
        fetch('index.php?api=1')
            .then(response => response.json())
            .then(data => renderData(data))
            .catch(err => console.error(err));
    }
    
    setInterval(updateBoard, 2000);
</script>
</body>
</html>