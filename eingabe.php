<?php
// ============================================
// ROBUSTE EINGABE.PHP FÜR 50+ GLEICHZEITIGE NUTZER
// ============================================

// ===== ROBUSTE FILE-HANDLING MIT AUTO-BACKUP =====
require_once 'file_handling_robust.php';

// Load config to get dynamic categories and header
$config = loadConfig('config.json');

// Build gruppen from config
$gruppen = [];
$headerTitle = 'Strategien gegen<br>Black-Outs'; // Default

if ($config && isset($config['categories'])) {
    foreach ($config['categories'] as $category) {
        $gruppen[$category['key']] = $category['display_name'] ?? $category['name'];
    }
    $headerTitle = $config['header_title'] ?? $headerTitle;
} else {
    // Fallback if config cannot be loaded
    $gruppen = [
        'bildung' => '📚 Bildung & Schule',
        'social' => '📱 Verantwortung Social Media',
        'individuell' => '🧑 Individuelle Verantwortung',
        'politik' => '⚖️ Politik & Recht',
        'kreativ' => '💡 Kreative & innovative Ansätze'
    ];
}

$message = '';

// ===== HAUPTLOGIK =====

$file = 'daten.json';
ensureFileExists($file);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $thema = $_POST['thema'] ?? '';
    $idee = trim($_POST['idee'] ?? '');
    
    // Validierung
    if (!array_key_exists($thema, $gruppen)) {
        $message = '<div class="alert alert-error">⚠️ UNGÜLTIGE KATEGORIE.</div>';
    } elseif (empty($idee)) {
        $message = '<div class="alert alert-error">⚠️ TEXT FEHLT.</div>';
    } elseif (strlen($idee) > 500) {
        $message = '<div class="alert alert-error">⚠️ TEXT ZU LANG (Max 500 Zeichen).</div>';
    } else {
        // Neuer Eintrag
        $new_entry = [
            'id' => uniqid(random_int(1000, 9999) . '_', true), // Bessere ID-Generierung
            'thema' => $thema,
            'text' => htmlspecialchars($idee, ENT_QUOTES, 'UTF-8'),
            'zeit' => time(),
            'visible' => false,
            'focus' => false
        ];
        
        // 🔒 ATOMIC WRITE - Keine Race Condition möglich!
        $writeSuccess = atomicAddEntry($file, $new_entry);
        
        if ($writeSuccess) {
            $message = '<div class="alert alert-success">✅ ANTWORT ERFOLGREICH ÜBERMITTELT. (KEEP GOING!)</div>';
            $_POST = [];
        } else {
            $message = '<div class="alert alert-error">⚠️ TECHNISCHER FEHLER. Bitte erneut versuchen.</div>';
            logError("Write failed for entry: " . $new_entry['id']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Eingabe | Infraprotect Workshop</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    
    <style>
        /* --- INFRAPROTECT DESIGN SYSTEM --- */
        :root {
            /* Colors */
            --ip-blue: #00658b;
            --ip-dark: #32373c;
            --ip-light: #ffffff;
            --ip-grey-bg: #f4f4f4;
            --ip-border: #cccccc;
            
            /* Action Colors */
            --accent-success: #00d084;
            --accent-error: #cf2e2e;
            
            /* Typography */
            --font-heading: 'Montserrat', sans-serif;
            --font-body: 'Roboto', sans-serif;
            
            /* Radius */
            --radius-pill: 9999px;
            --radius-card: 4px;
            --radius-input: 4px;
        }

        /* --- GLOBAL RESET & BOX SIZING --- */
        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            background-color: var(--ip-grey-bg);
            color: var(--ip-dark);
            font-family: var(--font-body);
            margin: 0; 
            padding: 0;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* Removed noise/spotlight for clean look */
        .mono-noise, .spotlight { display: none; }

        /* --- RESPONSIVE WRAPPER --- */
        .form-wrapper {
            width: 100%;
            max-width: 700px; 
            margin: 2rem auto; 
            padding: 3rem;
            background: var(--ip-light);
            border-radius: var(--radius-card);
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            border-top: 5px solid var(--ip-blue);
        }

        header {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1.5rem;
        }
        
        .header-logo {
            max-width: 250px;
            height: auto;
            margin-bottom: 1.5rem;
        }

        h1 { 
            font-family: var(--font-heading); 
            font-size: 1.8rem; 
            margin: 0 0 10px 0; 
            line-height: 1.2; 
            color: var(--ip-blue); 
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .subtitle { 
            color: var(--ip-dark); 
            text-transform: uppercase; 
            letter-spacing: 2px; 
            font-size: 0.8rem; 
            font-weight: 600; 
            display: block; 
            margin-bottom: 0.5rem;
            opacity: 0.6;
        }

        /* FORM ELEMENTS */
        .form-group { margin-bottom: 2rem; }
        
        label { 
            display: block; 
            margin-bottom: 8px; 
            color: var(--ip-dark); 
            font-weight: 700; 
            letter-spacing: 0.5px; 
            font-size: 0.85rem; 
            text-transform: uppercase; 
            font-family: var(--font-heading);
        }

        select, textarea {
            width: 100%; 
            padding: 14px 16px; 
            background: #fff; 
            border: 1px solid var(--ip-border);
            color: var(--ip-dark); 
            font-family: var(--font-body); 
            font-size: 1rem;
            transition: 0.3s;
            border-radius: var(--radius-input);
            -webkit-appearance: none;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.03);
        }
        
        select:focus, textarea:focus { 
            outline: none; 
            border-color: var(--ip-blue); 
            box-shadow: 0 0 0 2px rgba(0, 101, 139, 0.1);
        }
        
        textarea { resize: vertical; min-height: 150px; }
        
        /* Custom Select Arrow */
        select { 
            cursor: pointer; 
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2300658b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 0.65em auto;
        }
        select option { background: #fff; color: #333; }

        /* INFO BOX */
        .info-box {
            display: none; 
            padding: 1.5rem; 
            background: #eef7fb; /* Very light blue */
            border-left: 4px solid var(--ip-blue); 
            margin-bottom: 2rem; 
            animation: fadeIn 0.4s ease;
            border-radius: 0 4px 4px 0;
        }
        .info-label {
            text-transform: uppercase; 
            letter-spacing: 1px; 
            font-size: 0.75rem; 
            font-weight: 700;
            color: var(--ip-blue); 
            display: block; 
            margin-bottom: 0.75rem;
            font-family: var(--font-heading);
        }
        .info-content {
            font-family: var(--font-body); 
            font-size: 0.95rem; 
            line-height: 1.6; 
            color: var(--ip-dark);
            font-weight: 400;
        }
        .info-content ul { padding-left: 20px; margin: 0; }
        .info-content li { margin-bottom: 8px; }
        .info-content li:last-child { margin-bottom: 0; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* BUTTONS */
        .btn-submit {
            width: 100%; 
            padding: 16px; 
            background: var(--ip-blue); 
            border: none;
            color: #fff; 
            font-weight: 600; 
            font-family: var(--font-heading);
            letter-spacing: 1px; 
            cursor: pointer; 
            transition: all 0.3s ease;
            text-transform: uppercase; 
            font-size: 0.95rem;
            border-radius: var(--radius-pill);
            -webkit-tap-highlight-color: transparent;
            box-shadow: 0 4px 10px rgba(0, 101, 139, 0.2);
        }
        .btn-submit:hover { 
            background: #004e6d; 
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(0, 101, 139, 0.3);
        }
        .btn-submit:disabled {
            opacity: 0.7;
            background: #999;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* MESSAGES */
        .alert { 
            padding: 1rem 1.5rem; 
            margin-bottom: 2rem; 
            border-left: 4px solid; 
            background: #fff; 
            font-size: 0.9rem; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            font-weight: 500;
            border-radius: 4px;
        }
        .alert-success { border-color: var(--accent-success); color: #2e5c46; background-color: #f0fff9; }
        .alert-error { border-color: var(--accent-error); color: #8a1f1f; background-color: #fff5f5; }
        
        .link-subtle { 
            color: var(--text-muted); 
            text-decoration: none; 
            border-bottom: 1px solid transparent; 
            transition: 0.2s; 
            padding-bottom: 2px; 
            font-size: 0.85rem;
            font-weight: 500;
        }
        .link-subtle:hover { color: var(--ip-blue); border-color: var(--ip-blue); }

        /* --- MOBILE TWEAKS --- */
        @media (max-width: 600px) {
            .form-wrapper {
                padding: 1.5rem;
                margin: 0;
                max-width: 100%;
                box-shadow: none;
                border-top: none;
                border-radius: 0;
            }
            body { background: #fff; } 
            h1 { font-size: 1.5rem; }
            .header-logo { max-width: 200px; }
            textarea { min-height: 120px; }
        }
    </style>
</head>
<body>

<div class="form-wrapper">
    <header>
        <img src="https://infraprotect.com/wp-content/uploads/2019/05/Infraprotect_Logo.png" alt="Infraprotect Logo" class="header-logo">
        <span class="subtitle">Strategie Workshop 2025</span>
        <h1><?= $headerTitle ?></h1>
        <p style="color: #767676; margin-top: 10px; font-size: 0.9rem; font-weight: 400;">Wähle einen Bereich, um die Leitfragen zu laden.</p>
    </header>

    <?= $message ?>

    <form method="POST" action="" id="ideaForm">
        <div class="form-group">
            <label for="thema">1. Bereich wählen</label>
            <div style="position: relative;">
                <select name="thema" id="thema" required>
                    <option value="" disabled selected>-- Bitte auswählen --</option>
                    <?php foreach ($gruppen as $key => $label): ?>
                        <option value="<?= $key ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="infoBox" class="info-box">
            <span class="info-label">Leitfragen</span>
            <div id="infoContent" class="info-content">
            </div>
        </div>

        <div class="form-group">
            <label for="idee">2. Maßnahme definieren</label>
            <textarea 
                name="idee" 
                id="idee" 
                rows="6" 
                placeholder="Wähle zuerst eine Gruppe..."
                required 
                maxlength="500"></textarea>
            <div style="text-align: right; font-size: 0.75rem; color: #999; margin-top: 5px;">
                <span id="charCount">0</span> / 500 Zeichen
            </div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">Beitrag absenden</button>
    </form>

    <div style="text-align: center; margin-top: 3rem; margin-bottom: 1rem;">
        <a href="index.php" class="link-subtle">← Zurück zum Live-Dashboard</a>
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
            // Fallback leitfragen
            ?>
            'bildung': `
        <ul>
            <li>Was können Schulen tun, um beim Kampf gegen Desinformation zu helfen?</li>
            <li>Was bräuchtet ihr im Unterricht, um besser damit umgehen zu können?</li>
            <li>Was würdet ihr gern lernen?</li>
        </ul>
    `,
    'social': `
        <ul>
            <li>Was würde euch auf Social Media helfen, Desinformation besser zu erkennen?</li>
            <li>Wie sollten Plattformen mit Desinformation umgehen? Was könnten sie besser machen?</li>
            <li>Wie könnten Plattformen gestaltet sein, damit Fakten mehr Chancen haben als Desinformation?</li>
        </ul>
    `,
    'individuell': `
        <ul>
            <li>Was braucht es, damit Menschen besser mit Desinformation umgehen können?</li>
            <li>Was sollten wir als Gesellschaft tun, um Menschen aufzuklären?</li>
            <li>Wenn ihr an eure Oma denkt: Wie wird sie resilient gegen Desinformation?</li>
        </ul>
    `,
    'politik': `
        <ul>
            <li>Welche Regeln oder Gesetze braucht es, damit wir Desinformation eindämmen können?</li>
            <li>Was sollte es geben, das es noch nicht gibt?</li>
            <li>Was könnten Politiker:innen tun, um beim Kampf gegen Desinformation zu helfen?</li>
        </ul>
    `,
    'kreativ': `
        <ul>
            <li>Welche Out-Of-The-Box-Ideen fallen dir ein, wie man das Thema besser angehen könnte?</li>
            <li>Such dir eine Maßnahme aus, mit der du Desinformation bekämpfen würdest – wer müsste was tun und wieso?</li>
            <li>Du hast unlimitiert viel Geld: Was würdest du bauen / tun, um Desinformation zu bekämpfen?</li>
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
            infoBox.style.borderColor = '#00658b'; 
            textarea.placeholder = "Antworte auf die Fragen oder beschreibe deine eigene Maßnahme...";
        }
    });
    
    // Character Count Logic
    textarea.addEventListener('input', function() {
        charCount.textContent = this.value.length;
        if (this.value.length > 450) {
            charCount.style.color = '#cf2e2e';
        } else {
            charCount.style.color = '#767676';
        }
    });

    // Form Submit Handler
    let isSubmitting = false;
    const form = document.getElementById('ideaForm');
    
    form.addEventListener('submit', function(e) {
        if (!document.getElementById('thema').value || !document.getElementById('idee').value.trim()) {
            e.preventDefault();
            alert('⚠️ Bitte alle Felder ausfüllen.');
            return;
        }
        
        if (isSubmitting) {
            e.preventDefault();
            return;
        }
        
        isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.6';
        submitBtn.innerHTML = 'WIRD GESENDET...';
        
        // Timeout protection logic kept from original
        setTimeout(function() {
            if (isSubmitting) {
                submitBtn.style.opacity = '1';
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Beitrag absenden';
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
            submitBtn.innerHTML = 'Beitrag absenden';
            isSubmitting = false;
            
            if (alert.classList.contains('alert-success')) {
                setTimeout(function() {
                    form.reset();
                    charCount.textContent = '0';
                    infoBox.style.display = 'none';
                    textarea.placeholder = "Bitte zuerst Gruppe wählen...";
                }, 2000);
            }
        }
    });
</script>

</body>
</html>