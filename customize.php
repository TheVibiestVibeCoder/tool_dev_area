<?php
// ============================================
// CUSTOMIZE.PHP - Configuration Panel (Multi-tenant)
// ============================================

require_once 'file_handling_robust.php';
require_once 'user_auth.php';
require_once 'subscription_manager.php';
require_once 'security_helpers.php';

// Set security headers
setSecurityHeaders();

// Require authentication
requireAuth();

// Get current user
$current_user = getCurrentUser();
$user_id = $current_user['id'];

// Load user-specific config
$configFile = getUserFile($user_id, 'config.json');
$message = '';
$messageType = '';

// Load config
$config = loadConfig($configFile);
if ($config === null) {
    $message = '⚠️ CONFIGURATION COULD NOT BE LOADED.';
    $messageType = 'error';
    $config = ['header_title' => '', 'logo_url' => '', 'categories' => []];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate and process header title
    $newHeaderTitle = trim($_POST['header_title'] ?? '');

    // Validate and process logo URL
    $newLogoUrl = trim($_POST['logo_url'] ?? '');

    // Validate logo URL is safe (no javascript:, data:, etc.)
    if (!empty($newLogoUrl) && !isUrlSafe($newLogoUrl)) {
        $message = '⚠️ UNGÜLTIGE LOGO URL. Nur HTTP/HTTPS URLs sind erlaubt.';
        $messageType = 'error';
        $newLogoUrl = ''; // Clear invalid URL
    }

    // Validate and process categories
    $newCategories = [];
    $categoryKeys = $_POST['category_key'] ?? [];
    $categoryNames = $_POST['category_name'] ?? [];
    $categoryAbbrevs = $_POST['category_abbrev'] ?? [];
    $categoryIcons = $_POST['category_icon'] ?? [];
    $categoryDisplayNames = $_POST['category_display_name'] ?? [];

    $errors = [];

    // Process each category
    for ($i = 0; $i < count($categoryKeys); $i++) {
        $key = trim($categoryKeys[$i] ?? '');
        $name = trim($categoryNames[$i] ?? '');
        $abbrev = trim($categoryAbbrevs[$i] ?? '');
        $icon = trim($categoryIcons[$i] ?? '');
        $displayName = trim($categoryDisplayNames[$i] ?? '');

        if (empty($key) || empty($name) || empty($abbrev)) {
            $errors[] = "Kategorie $i: Schlüssel, Name und Kürzel sind Pflichtfelder.";
            continue;
        }

        // Validate key format (lowercase alphanumeric)
        if (!preg_match('/^[a-z0-9_]+$/', $key)) {
            $errors[] = "Kategorie $i: Schlüssel muss aus Kleinbuchstaben, Zahlen und Unterstrichen bestehen.";
            continue;
        }

        // Process Leitfragen for this category
        $leitfragenRaw = $_POST['category_leitfragen_' . $i] ?? '';
        $leitfragenArray = array_filter(
            array_map('trim', explode("\n", $leitfragenRaw)),
            function($line) { return !empty($line); }
        );

        $newCategories[] = [
            'key' => $key,
            'name' => $name,
            'abbreviation' => $abbrev,
            'icon' => $icon,
            'display_name' => $displayName,
            'leitfragen' => $leitfragenArray
        ];
    }

    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $messageType = 'error';
    } else {
        // Check subscription limits
        $sub_manager = new SubscriptionManager();
        $limits = $sub_manager->getPlanLimits($user_id);
        $max_columns = $limits['max_columns'];

        // Check if exceeding column limit
        if ($max_columns !== -1 && count($newCategories) > $max_columns) {
            $message = "⚠️ Your plan allows a maximum of {$max_columns} columns. You currently have " . count($newCategories) . " columns. <a href='pricing.php' style='color: inherit; text-decoration: underline;'>Please upgrade your plan</a> to add more columns.";
            $messageType = 'error';
        } else {
            // Save new config
            $newConfig = [
                'header_title' => $newHeaderTitle,
                'logo_url' => $newLogoUrl,
                'categories' => $newCategories
            ];

            $success = saveConfig($configFile, $newConfig);

            if ($success) {
                $message = '✅ KONFIGURATION ERFOLGREICH GESPEICHERT!';
                $messageType = 'success';
                $config = $newConfig;
            } else {
                $message = '⚠️ FEHLER BEIM SPEICHERN DER KONFIGURATION.';
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anpassen | Live Situation Room</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        /* --- DESIGN SYSTEM --- */
        :root {
            /* Colors */
            --ip-blue: #00658b;
            --ip-dark: #32373c;
            --ip-light: #ffffff;
            --ip-grey-bg: #f4f4f4;
            --ip-border: #e0e0e0;

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

        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }

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

        /* BUTTONS */
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
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }

        .btn-danger { background: var(--accent-danger); color: white; }
        .btn-success { background: var(--accent-success); color: white; }
        .btn-neutral { background: #e0e0e0; color: #555; }
        .btn-neutral:hover { background: #d0d0d0; }
        .btn-primary { background: var(--ip-blue); color: white; }

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
        .alert-error { border-color: var(--accent-danger); color: #8a1f1f; background-color: #fff5f5; }

        /* FORM SECTION */
        .form-section {
            background: var(--ip-light);
            border: 1px solid var(--ip-border);
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: var(--radius-card);
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .form-section h2 {
            margin: 0 0 1.5rem 0;
            font-family: var(--font-heading);
            font-weight: 600;
            color: var(--ip-blue);
            font-size: 1.3rem;
            border-bottom: 2px solid var(--ip-border);
            padding-bottom: 10px;
        }
        .form-section h3 {
            margin: 2rem 0 1rem 0;
            font-family: var(--font-heading);
            font-weight: 600;
            color: var(--ip-dark);
            font-size: 1.1rem;
        }

        .form-group { margin-bottom: 1.5rem; }

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

        .help-text {
            display: block;
            font-size: 0.75rem;
            color: #767676;
            margin-top: 5px;
            font-weight: 400;
            text-transform: none;
        }

        input[type="text"], textarea {
            width: 100%;
            padding: 12px 16px;
            background: #fff;
            border: 1px solid var(--ip-border);
            color: var(--ip-dark);
            font-family: var(--font-body);
            font-size: 1rem;
            transition: 0.3s;
            border-radius: var(--radius-card);
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.03);
            box-sizing: border-box;
        }

        input[type="text"]:focus, textarea:focus {
            outline: none;
            border-color: var(--ip-blue);
            box-shadow: 0 0 0 2px rgba(0, 101, 139, 0.1);
        }

        textarea { resize: vertical; min-height: 100px; }

        /* CATEGORY CARD */
        .category-card {
            background: #f9f9f9;
            border: 1px solid var(--ip-border);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius-card);
            position: relative;
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #ddd;
        }

        .category-number {
            font-family: var(--font-heading);
            font-weight: 700;
            color: var(--ip-blue);
            font-size: 1.1rem;
        }

        .remove-category-btn {
            background: var(--accent-danger);
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: var(--radius-pill);
            cursor: pointer;
            font-family: var(--font-heading);
            font-size: 0.75rem;
            font-weight: 600;
            transition: 0.2s;
        }

        .remove-category-btn:hover {
            background: #a82424;
        }

        .form-row {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-col-full {
            grid-column: 1 / -1;
        }

        /* ADD CATEGORY BUTTON */
        .add-category-btn {
            background: white;
            border: 2px dashed var(--ip-border);
            color: var(--ip-blue);
            padding: 1rem;
            width: 100%;
            border-radius: var(--radius-card);
            cursor: pointer;
            font-family: var(--font-heading);
            font-size: 0.9rem;
            font-weight: 600;
            transition: 0.3s;
            text-align: center;
        }

        .add-category-btn:hover {
            border-color: var(--ip-blue);
            background: #f0f7fb;
        }

        /* SAVE BUTTON AREA */
        .save-area {
            position: sticky;
            bottom: 0;
            background: var(--ip-light);
            padding: 1.5rem 2rem;
            border-top: 2px solid var(--ip-border);
            margin: 0 -2rem -2rem -2rem;
            border-radius: 0 0 var(--radius-card) var(--radius-card);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* MOBILE */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .admin-header { flex-direction: column; align-items: flex-start; gap: 1.5rem; padding: 1.5rem; }
            .admin-header h1 { font-size: 1.8rem; }
            .header-actions { width: 100%; }
            .header-actions .btn { flex: 1; }

            .form-row {
                grid-template-columns: 1fr;
            }

            .save-area {
                flex-direction: column;
            }

            .save-area .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <header class="admin-header">
        <div>
            <span class="subtitle">Live Situation Room</span>
            <h1>Anpassung</h1>
        </div>
        <div class="header-actions">
            <a href="admin.php" class="btn btn-neutral">← Zurück zum Admin</a>
        </div>
    </header>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="customizeForm">

        <!-- HEADER SECTION -->
        <div class="form-section">
            <h2>Dashboard Konfiguration</h2>
            <div class="form-group">
                <label for="header_title">
                    Titel auf dem Dashboard
                    <span class="help-text">HTML erlaubt (z.B. &lt;br&gt; für Zeilenumbruch)</span>
                </label>
                <input type="text" id="header_title" name="header_title" value="<?= htmlspecialchars($config['header_title'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="logo_url">
                    Logo URL
                    <span class="help-text">Vollständige URL zu Ihrem Logo (z.B. https://example.com/logo.png)</span>
                </label>
                <input type="text" id="logo_url" name="logo_url" value="<?= htmlspecialchars($config['logo_url'] ?? '') ?>" placeholder="https://example.com/logo.png">
            </div>
        </div>

        <!-- CATEGORIES SECTION -->
        <div class="form-section">
            <h2>Kategorien</h2>
            <p style="color: #767676; font-size: 0.9rem; margin-bottom: 2rem;">
                Definiere die Kategorien, die auf dem Dashboard und im Eingabeformular angezeigt werden.
                Die Reihenfolge hier bestimmt die Anzeigereihenfolge.
            </p>

            <div id="categories-container">
                <?php
                $categories = $config['categories'] ?? [];
                foreach ($categories as $index => $category):
                ?>
                    <div class="category-card" data-index="<?= $index ?>">
                        <div class="category-header">
                            <span class="category-number">Kategorie <?= $index + 1 ?></span>
                            <button type="button" class="remove-category-btn" onclick="removeCategory(this)">✕ Entfernen</button>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    Schlüssel
                                    <span class="help-text">Kleinbuchstaben, keine Leerzeichen (z.B. "bildung")</span>
                                </label>
                                <input type="text" name="category_key[]" value="<?= htmlspecialchars($category['key'] ?? '') ?>" required pattern="[a-z0-9_]+">
                            </div>

                            <div class="form-group">
                                <label>
                                    Name
                                    <span class="help-text">Anzeigename für Dashboard</span>
                                </label>
                                <input type="text" name="category_name[]" value="<?= htmlspecialchars($category['name'] ?? '') ?>" required>
                            </div>

                            <div class="form-group">
                                <label>
                                    Kürzel
                                    <span class="help-text">3 Buchstaben</span>
                                </label>
                                <input type="text" name="category_abbrev[]" value="<?= htmlspecialchars($category['abbreviation'] ?? '') ?>" required maxlength="3">
                            </div>

                            <div class="form-group">
                                <label>
                                    Icon
                                    <span class="help-text">Emoji</span>
                                </label>
                                <input type="text" name="category_icon[]" value="<?= htmlspecialchars($category['icon'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                Anzeigename (Eingabeformular)
                                <span class="help-text">Mit Icon, z.B. "📚 Bildung & Schule"</span>
                            </label>
                            <input type="text" name="category_display_name[]" value="<?= htmlspecialchars($category['display_name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>
                                Leitfragen
                                <span class="help-text">Eine Frage pro Zeile</span>
                            </label>
                            <textarea name="category_leitfragen_<?= $index ?>" rows="5"><?= htmlspecialchars(implode("\n", $category['leitfragen'] ?? [])) ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="add-category-btn" onclick="addCategory()">+ Neue Kategorie hinzufügen</button>

            <div class="save-area">
                <button type="submit" class="btn btn-primary">💾 Änderungen speichern</button>
            </div>
        </div>

    </form>
</div>

<script>
let categoryIndex = <?= count($categories) ?>;

function addCategory() {
    const container = document.getElementById('categories-container');
    const newCard = document.createElement('div');
    newCard.className = 'category-card';
    newCard.setAttribute('data-index', categoryIndex);

    newCard.innerHTML = `
        <div class="category-header">
            <span class="category-number">Kategorie ${categoryIndex + 1}</span>
            <button type="button" class="remove-category-btn" onclick="removeCategory(this)">✕ Entfernen</button>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>
                    Schlüssel
                    <span class="help-text">Kleinbuchstaben, keine Leerzeichen (z.B. "bildung")</span>
                </label>
                <input type="text" name="category_key[]" value="" required pattern="[a-z0-9_]+">
            </div>

            <div class="form-group">
                <label>
                    Name
                    <span class="help-text">Anzeigename für Dashboard</span>
                </label>
                <input type="text" name="category_name[]" value="" required>
            </div>

            <div class="form-group">
                <label>
                    Kürzel
                    <span class="help-text">3 Buchstaben</span>
                </label>
                <input type="text" name="category_abbrev[]" value="" required maxlength="3">
            </div>

            <div class="form-group">
                <label>
                    Icon
                    <span class="help-text">Emoji</span>
                </label>
                <input type="text" name="category_icon[]" value="">
            </div>
        </div>

        <div class="form-group">
            <label>
                Anzeigename (Eingabeformular)
                <span class="help-text">Mit Icon, z.B. "📚 Bildung & Schule"</span>
            </label>
            <input type="text" name="category_display_name[]" value="">
        </div>

        <div class="form-group">
            <label>
                Leitfragen
                <span class="help-text">Eine Frage pro Zeile</span>
            </label>
            <textarea name="category_leitfragen_${categoryIndex}" rows="5"></textarea>
        </div>
    `;

    container.appendChild(newCard);
    categoryIndex++;
    updateCategoryNumbers();
}

function removeCategory(btn) {
    if (!confirm('Diese Kategorie wirklich entfernen?')) {
        return;
    }

    const card = btn.closest('.category-card');
    card.remove();
    updateCategoryNumbers();
}

function updateCategoryNumbers() {
    const cards = document.querySelectorAll('.category-card');
    cards.forEach((card, index) => {
        card.querySelector('.category-number').textContent = `Kategorie ${index + 1}`;
    });
}

// Form validation
document.getElementById('customizeForm').addEventListener('submit', function(e) {
    const categories = document.querySelectorAll('.category-card');
    if (categories.length === 0) {
        e.preventDefault();
        alert('⚠️ Mindestens eine Kategorie muss definiert sein.');
        return false;
    }

    return true;
});
</script>

</body>
</html>
