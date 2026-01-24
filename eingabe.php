<?php
// ============================================
// EINGABE.PHP - Multi-tenant Public Submission Form
// Supports public submissions to user-specific workshops
// ============================================

require_once 'file_handling_robust.php';
require_once 'user_auth.php';
require_once 'security_helpers.php';

// Set security headers
setSecurityHeaders();

// ===== DETERMINE WORKSHOP OWNER =====
// Public submissions via ?u={user_id}

if (!isset($_GET['u'])) {
    die('Workshop ID is required. Please use the correct link.');
}

$workshop_user_id = $_GET['u'];

// Validate that this user/workshop exists
if (!is_dir(getUserDataPath($workshop_user_id))) {
    die('Workshop not found.');
}

// ===== LOAD USER-SPECIFIC CONFIG =====

$config_file = getUserFile($workshop_user_id, 'config.json');
$data_file = getUserFile($workshop_user_id, 'daten.json');

$config = loadConfig($config_file);

// Build gruppen from config
$gruppen = [];
$headerTitle = 'Live Situation Room'; // Default
$logoUrl = ''; // Default (no logo)
$leitfragen_by_key = []; // Store guiding questions per category

if ($config && isset($config['categories'])) {
    foreach ($config['categories'] as $category) {
        $gruppen[$category['key']] = $category['display_name'] ?? $category['name'];
        $leitfragen_by_key[$category['key']] = $category['leitfragen'] ?? [];
    }
    $headerTitle = $config['header_title'] ?? $headerTitle;
    $logoUrl = $config['logo_url'] ?? $logoUrl;
} else {
    // Fallback if config cannot be loaded
    $gruppen = [
        'general' => '💡 General Ideas'
    ];
    $leitfragen_by_key = ['general' => []];
}

$message = '';

// ===== SUBMISSION LOGIC =====

ensureFileExists($data_file);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting: Max 10 submissions per IP per minute
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkPublicRateLimit('public_submit', $user_ip, 10, 60)) {
        $message = '<div class="alert alert-error">⚠️ TOO MANY REQUESTS. Please wait a moment.</div>';
    } else {
        $thema = $_POST['thema'] ?? '';
        $idee = trim($_POST['idee'] ?? '');

        // Validierung
        if (!array_key_exists($thema, $gruppen)) {
            $message = '<div class="alert alert-error">⚠️ INVALID CATEGORY.</div>';
        } elseif (empty($idee)) {
            $message = '<div class="alert alert-error">⚠️ TEXT MISSING.</div>';
        } elseif (strlen($idee) > 500) {
            $message = '<div class="alert alert-error">⚠️ TEXT TOO LONG (Max 500 chars).</div>';
        } else {
            // Neuer Eintrag
            $new_entry = [
                'id' => uniqid(random_int(1000, 9999) . '_', true), // Bessere ID-Generierung
                'thema' => $thema,
                'text' => $idee, // Store raw text - encoding happens during output
                'zeit' => time(),
                'visible' => false,
                'focus' => false
            ];
            
            // 🔒 ATOMIC WRITE - Keine Race Condition möglich!
            $writeSuccess = atomicAddEntry($data_file, $new_entry);

            if ($writeSuccess) {
                $message = '<div class="alert alert-success">✅ SUBMISSION SUCCESSFUL. (KEEP GOING!)</div>';
                $_POST = [];
            } else {
                $message = '<div class="alert alert-error">⚠️ TECHNICAL ERROR. Please try again.</div>';
                logError("Write failed for entry: " . $new_entry['id']);
            }
        }
    } // End rate limit check
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Submit | Live Situation Room</title>
    
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
            
            /* Status Colors */
            --color-green: #27ae60; 
            --color-red: #e74c3c;   
            
            /* Typography */
            --font-head: 'Bebas Neue', sans-serif;
            --font-body: 'Inter', sans-serif;
            
            /* UI */
            --radius-input: 4px;
            --radius-btn: 4px;
            --shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        /* --- GLOBAL RESET --- */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-body);
            margin: 0; padding: 0;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
        }

        /* --- WRAPPER --- */
        .form-wrapper {
            width: 100%;
            max-width: 600px; 
            margin: 3rem auto; 
            padding: 3rem;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            border-top: 4px solid var(--text-main); /* Black top border accent */
        }

        /* --- HEADER --- */
        header {
            text-align: center;
            margin-bottom: 2.5rem;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 1.5rem;
        }
        
        .header-logo {
            max-width: 180px;
            height: auto;
            margin-bottom: 1.5rem;
            filter: grayscale(100%); /* Force B&W logo */
        }

        h1 { 
            font-family: var(--font-head); 
            font-size: 3rem; 
            margin: 0 0 5px 0; 
            line-height: 0.9; 
            color: var(--text-main); 
            font-weight: 400;
            text-transform: uppercase;
        }
        
        .subtitle { 
            color: var(--text-muted); 
            text-transform: uppercase; 
            letter-spacing: 2px; 
            font-size: 0.85rem; 
            font-weight: 600; 
            display: block; 
            margin-bottom: 0.5rem;
            font-family: var(--font-head);
        }

        .instruction {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-top: 10px;
        }

        /* --- FORM ELEMENTS --- */
        .form-group { margin-bottom: 2rem; }
        
        label { 
            display: block; 
            margin-bottom: 8px; 
            color: var(--text-main); 
            font-size: 1.2rem; 
            font-family: var(--font-head);
            letter-spacing: 0.5px;
        }

        select, textarea {
            width: 100%; 
            padding: 14px 16px; 
            background: #fff; 
            border: 1px solid var(--border-color);
            color: var(--text-main); 
            font-family: var(--font-body); 
            font-size: 1rem;
            transition: all 0.2s;
            border-radius: var(--radius-input);
            -webkit-appearance: none;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
        }
        
        select:focus, textarea:focus { 
            outline: none; 
            border-color: var(--text-main); 
            background: #fafafa;
        }
        
        textarea { resize: vertical; min-height: 160px; line-height: 1.6; }
        
        /* Custom Select Arrow (Black SVG) */
        select { 
            cursor: pointer; 
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23111111%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 0.65em auto;
        }
        select option { background: #fff; color: #111; }

        /* --- INFO BOX (Questions) --- */
        .info-box {
            display: none; 
            padding: 1.5rem; 
            background: #fafafa; 
            border-left: 3px solid var(--text-main); 
            margin-bottom: 2rem; 
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .info-label {
            text-transform: uppercase; 
            letter-spacing: 1px; 
            font-size: 1rem; 
            color: var(--text-main); 
            display: block; 
            margin-bottom: 0.75rem;
            font-family: var(--font-head);
        }
        .info-content {
            font-family: var(--font-body); 
            font-size: 0.95rem; 
            line-height: 1.6; 
            color: var(--text-muted);
        }
        .info-content ul { padding-left: 20px; margin: 0; }
        .info-content li { margin-bottom: 8px; }

        /* --- BUTTONS --- */
        .btn-submit {
            width: 100%; 
            padding: 16px; 
            background: var(--text-main); 
            border: 1px solid var(--text-main);
            color: #fff; 
            font-family: var(--font-head);
            font-size: 1.2rem;
            letter-spacing: 1px; 
            cursor: pointer; 
            transition: all 0.2s ease;
            text-transform: uppercase; 
            border-radius: var(--radius-btn);
            -webkit-tap-highlight-color: transparent;
        }
        .btn-submit:hover { 
            background: #333; 
            transform: translateY(-2px);
        }
        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* --- MESSAGES --- */
        .alert { 
            padding: 1rem 1.5rem; 
            margin-bottom: 2rem; 
            border-left: 4px solid; 
            background: #fff; 
            font-size: 0.95rem; 
            box-shadow: var(--shadow);
            font-weight: 500;
        }
        .alert-success { border-color: var(--color-green); color: var(--color-green); }
        .alert-error { border-color: var(--color-red); color: var(--color-red); }
        
        .link-subtle { 
            color: var(--text-muted); 
            text-decoration: none; 
            border-bottom: 1px solid transparent; 
            transition: 0.2s; 
            padding-bottom: 2px; 
            font-size: 0.9rem;
            font-weight: 500;
        }
        .link-subtle:hover { color: var(--text-main); border-color: var(--text-main); }

        /* --- MOBILE ADAPTABILITY --- */
        @media (max-width: 650px) {
            .form-wrapper {
                padding: 1.5rem;
                margin: 0;
                max-width: 100%;
                border: none;
                border-top: none;
                box-shadow: none;
                min-height: 100vh;
            }
            body { background: #fff; } 
            
            h1 { font-size: 2.5rem; }
            .header-logo { max-width: 150px; }
            
            /* Inputs need to be easily tappable */
            select, textarea { font-size: 16px; /* Prevents zoom on iOS */ }
            
            .btn-submit { padding: 18px; /* Taller touch target */ }
        }
    </style>
</head>
<body>

<div class="form-wrapper">
    <header>
        <?php if (!empty($logoUrl)): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="header-logo">
        <?php endif; ?>
        <span class="subtitle">Live Workshop</span>
        <h1><?= $headerTitle ?></h1>
        <p class="instruction">Select a topic below to see guiding questions.</p>
    </header>

    <?= $message ?>

    <form method="POST" action="" id="ideaForm">
        <div class="form-group">
            <label for="thema">1. Choose Topic</label>
            <div style="position: relative;">
                <select name="thema" id="thema" required>
                    <option value="" disabled selected>-- Select --</option>
                    <?php foreach ($gruppen as $key => $label): ?>
                        <option value="<?= $key ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="infoBox" class="info-box">
            <span class="info-label">Guiding Questions</span>
            <div id="infoContent" class="info-content">
            </div>
        </div>

        <div class="form-group">
            <label for="idee">2. Your Input</label>
            <textarea 
                name="idee" 
                id="idee" 
                rows="6" 
                placeholder="Type your idea or measure here..."
                required 
                maxlength="500"></textarea>
            <div style="text-align: right; font-size: 0.8rem; color: #999; margin-top: 5px; font-family: var(--font-body);">
                <span id="charCount">0</span> / 500
            </div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">Submit Entry</button>
    </form>

    <div style="text-align: center; margin-top: 3rem; margin-bottom: 1rem;">
        <a href="index.php?u=<?= urlencode($workshop_user_id) ?>" class="link-subtle">← Back to Live Dashboard</a>
    </div>
</div>

<script>
    // --- LEITFRAGEN CONFIGURATION (Dynamically generated from config) ---
    const leitfragen = {
        <?php
        if ($config && isset($config['categories'])) {
            foreach ($config['categories'] as $category) {
                $key = $category['key'];
                $questions = $category['leitfragen'] ?? [];
                echo "'$key': `\n        <ul>\n";
                foreach ($questions as $question) {
                    $escapedQuestion = htmlspecialchars($question, ENT_QUOTES, 'UTF-8');
                    echo "            <li>$escapedQuestion</li>\n";
                }
                echo "        </ul>\n    `,\n";
            }
        } else {
            // Fallback leitfragen (kept purely for safety, though config usually loads)
            ?>
            'general': `
                <ul>
                    <li>What is your main idea?</li>
                    <li>How can we improve the situation?</li>
                </ul>
            `
        <?php } ?>
    }

    const select = document.getElementById('thema');
    const infoBox = document.getElementById('infoBox');
    const infoContent = document.getElementById('infoContent');
    const textarea = document.getElementById('idee');
    const charCount = document.getElementById('charCount');
    const submitBtn = document.getElementById('submitBtn');

    // Change Handler für Dropdown
    select.addEventListener('change', function() {
        const value = this.value;
        if (leitfragen[value]) {
            infoContent.innerHTML = leitfragen[value];
            infoBox.style.display = 'none';
            infoBox.offsetHeight; // Trigger Reflow
            infoBox.style.display = 'block';
            textarea.placeholder = "Answer the questions above or describe your measure...";
        }
    });
    
    // Character Count Logic
    textarea.addEventListener('input', function() {
        charCount.textContent = this.value.length;
        if (this.value.length > 450) {
            charCount.style.color = '#e74c3c';
        } else {
            charCount.style.color = '#999';
        }
    });

    // Form Submit Handler
    let isSubmitting = false;
    const form = document.getElementById('ideaForm');
    
    form.addEventListener('submit', function(e) {
        if (!document.getElementById('thema').value || !document.getElementById('idee').value.trim()) {
            e.preventDefault();
            alert('⚠️ Please fill out all fields.');
            return;
        }
        
        if (isSubmitting) {
            e.preventDefault();
            return;
        }
        
        isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.6';
        submitBtn.innerHTML = 'SENDING...';
        
        // Timeout protection logic kept from original
        setTimeout(function() {
            if (isSubmitting) {
                submitBtn.style.opacity = '1';
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Submit Entry';
                isSubmitting = false;
            }
        }, 3000);
    });

    // Success Alert Scroll & Reset
    window.addEventListener('load', function() {
        const alert = document.querySelector('.alert');
        if (alert) {
            alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            submitBtn.style.opacity = '1';
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit Entry';
            isSubmitting = false;
            
            if (alert.classList.contains('alert-success')) {
                setTimeout(function() {
                    form.reset();
                    charCount.textContent = '0';
                    infoBox.style.display = 'none';
                    textarea.placeholder = "Please select a topic first...";
                }, 2000);
            }
        }
    });
</script>

</body>
</html>