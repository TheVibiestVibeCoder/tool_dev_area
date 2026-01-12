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
        $message = '⚠️ INVALID LOGO URL. Only HTTP/HTTPS URLs are allowed.';
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
            $errors[] = "Category $i: Key, Name, and Abbreviation are required.";
            continue;
        }

        // Validate key format (lowercase alphanumeric)
        if (!preg_match('/^[a-z0-9_]+$/', $key)) {
            $errors[] = "Category $i: Key must use lowercase letters, numbers, and underscores only.";
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
                $message = '✅ CONFIGURATION SAVED SUCCESSFULLY!';
                $messageType = 'success';
                $config = $newConfig;
            } else {
                $message = '⚠️ ERROR SAVING CONFIGURATION.';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Customize | Live Situation Room</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        /* --- DESIGN SYSTEM (Monochrome + Design Colors) --- */
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
            --radius-card: 4px;
            --shadow: 0 4px 6px rgba(0,0,0,0.03);
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-body);
            margin: 0; padding: 0;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .container { max-width: 1000px; margin: 0 auto; padding: 2rem 2rem 100px 2rem; }

        /* HEADER */
        .admin-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            background: var(--bg-card);
            padding: 2rem 3rem; 
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            border-bottom: 3px solid var(--text-main);
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

        /* BUTTONS */
        .btn {
            padding: 12px 24px;
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
            transition: 0.2s;
            line-height: 1;
        }
        .btn:hover { border-color: var(--text-main); color: var(--text-main); transform: translateY(-1px); }

        .btn-neutral { color: var(--text-muted); }
        
        .btn-primary { 
            background: var(--color-green); 
            color: white; 
            border-color: var(--color-green); 
        }
        .btn-primary:hover { 
            background: #219150; 
            color: white; 
            border-color: #219150; 
        }

        .btn-danger { 
            color: var(--color-red); 
            border-color: rgba(231, 76, 60, 0.3); 
            font-size: 0.9rem; padding: 8px 16px;
        }
        .btn-danger:hover { 
            background: var(--color-red); 
            color: white; 
            border-color: var(--color-red); 
        }

        /* ALERTS */
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid;
            background: #fff;
            font-size: 1rem;
            box-shadow: var(--shadow);
            font-family: var(--font-body);
        }
        .alert-success { border-color: var(--color-green); color: var(--color-green); }
        .alert-error { border-color: var(--color-red); color: var(--color-red); }

        /* FORM SECTIONS */
        .form-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 2.5rem;
            margin-bottom: 2rem;
            border-radius: var(--radius-card);
            box-shadow: var(--shadow);
        }
        
        .form-section h2 {
            margin: 0 0 2rem 0;
            font-family: var(--font-head);
            font-weight: 400;
            color: var(--text-main);
            font-size: 2rem;
            border-bottom: 1px solid var(--text-main);
            padding-bottom: 10px;
        }

        .form-group { margin-bottom: 1.5rem; }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-main);
            font-size: 1.1rem;
            font-family: var(--font-head);
            letter-spacing: 0.5px;
        }

        .help-text {
            display: block;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 6px;
            font-family: var(--font-body);
            font-weight: 400;
            line-height: 1.4;
        }
        
        .help-text strong { font-weight: 600; color: #444; }

        input[type="text"], textarea {
            width: 100%;
            padding: 14px 16px;
            background: #fff;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            font-family: var(--font-body);
            font-size: 1rem;
            transition: 0.2s;
            border-radius: var(--radius-btn);
        }

        input[type="text"]:focus, textarea:focus {
            outline: none;
            border-color: var(--text-main);
            background: #fafafa;
        }

        textarea { resize: vertical; min-height: 120px; }

        /* CATEGORY CARD */
        .category-card {
            background: #fafafa;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius-card);
            transition: 0.2s;
        }
        .category-card:hover { border-color: #999; }

        .category-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1.5rem; padding-bottom: 0.5rem;
            border-bottom: 1px solid #ddd;
        }

        .category-number {
            font-family: var(--font-head);
            color: var(--text-main);
            font-size: 1.4rem;
        }

        /* GRID LAYOUT FOR FORM ROWS */
        .form-row {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 1rem;
        }

        /* ADD CATEGORY BTN */
        .add-category-btn {
            background: white;
            border: 2px dashed var(--border-color);
            color: var(--text-muted);
            padding: 1.5rem;
            width: 100%;
            border-radius: var(--radius-card);
            cursor: pointer;
            font-family: var(--font-head);
            font-size: 1.2rem;
            transition: 0.2s;
            text-align: center;
        }
        .add-category-btn:hover {
            border-color: var(--text-main);
            color: var(--text-main);
            background: #fff;
        }

        /* STICKY SAVE BAR */
        .save-area {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
            z-index: 100;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.05);
        }
        
        .save-area .btn {
            width: 100%;
            max-width: 600px;
            font-size: 1.4rem;
            padding: 16px;
        }

        /* MOBILE OPTIMIZATION */
        @media (max-width: 768px) {
            .container { padding: 1rem 1rem 120px 1rem; }
            
            .admin-header { 
                flex-direction: column; align-items: flex-start; gap: 1rem; padding: 1.5rem; 
            }
            .admin-header h1 { font-size: 2.5rem; }
            .header-actions { width: 100%; }
            .header-actions .btn { width: 100%; justify-content: center; }

            .form-section { padding: 1.5rem; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            
            /* On mobile, stack inputs inside the card */
            .category-card { padding: 1rem; }
            .category-header { margin-bottom: 1rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <header class="admin-header">
        <div>
            <span class="subtitle">Dashboard Configuration</span>
            <h1>Customize</h1>
        </div>
        <div class="header-actions">
            <a href="admin.php" class="btn btn-neutral">← Back to Admin</a>
        </div>
    </header>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="customizeForm">

        <div class="form-section">
            <h2>Header Settings</h2>
            <div class="form-group">
                <label for="header_title">
                    Dashboard Title
                    <span class="help-text">
                        The main title displayed at the top of the Situation Room screen.<br>
                        Tip: Type <strong>&lt;br&gt;</strong> to force a line break.
                    </span>
                </label>
                <input type="text" id="header_title" name="header_title" value="<?= htmlspecialchars($config['header_title'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="logo_url">
                    Logo URL
                    <span class="help-text">
                        Paste a direct web link (https://...) to your logo image here. Leave empty to hide.
                    </span>
                </label>
                <input type="text" id="logo_url" name="logo_url" value="<?= htmlspecialchars($config['logo_url'] ?? '') ?>" placeholder="https://example.com/logo.png">
            </div>
        </div>

        <div class="form-section">
            <h2>Categories</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem; font-family: var(--font-body);">
                Define the columns shown on the dashboard. The order here determines the order on the screen.
            </p>

            <div id="categories-container">
                <?php
                $categories = $config['categories'] ?? [];
                foreach ($categories as $index => $category):
                ?>
                    <div class="category-card" data-index="<?= $index ?>">
                        <div class="category-header">
                            <span class="category-number">Category <?= $index + 1 ?></span>
                            <button type="button" class="btn btn-danger" onclick="removeCategory(this)">Remove</button>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    Key 
                                    <span class="help-text">Internal System ID. Use <strong>lowercase letters only</strong>. No spaces. (e.g. <em>finance_team</em>)</span>
                                </label>
                                <input type="text" name="category_key[]" value="<?= htmlspecialchars($category['key'] ?? '') ?>" required pattern="[a-z0-9_]+">
                            </div>

                            <div class="form-group">
                                <label>
                                    Name 
                                    <span class="help-text">The big headline for this column on the main screen.</span>
                                </label>
                                <input type="text" name="category_name[]" value="<?= htmlspecialchars($category['name'] ?? '') ?>" required>
                            </div>

                            <div class="form-group">
                                <label>
                                    Abbreviation 
                                    <span class="help-text">Short 3-letter code for the Admin buttons (e.g. <em>FIN</em>).</span>
                                </label>
                                <input type="text" name="category_abbrev[]" value="<?= htmlspecialchars($category['abbreviation'] ?? '') ?>" required maxlength="3">
                            </div>

                            <div class="form-group">
                                <label>
                                    Icon 
                                    <span class="help-text">Paste an Emoji here (e.g. 💡).</span>
                                </label>
                                <input type="text" name="category_icon[]" value="<?= htmlspecialchars($category['icon'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                Display Name (Input Form) 
                                <span class="help-text">The label participants see on their phones. Best combined with an icon (e.g. <em>💰 Finance Team</em>).</span>
                            </label>
                            <input type="text" name="category_display_name[]" value="<?= htmlspecialchars($category['display_name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>
                                Guiding Questions 
                                <span class="help-text">Help your participants! Write questions here to guide them. <strong>One question per line.</strong></span>
                            </label>
                            <textarea name="category_leitfragen_<?= $index ?>"><?= htmlspecialchars(implode("\n", $category['leitfragen'] ?? [])) ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="add-category-btn" onclick="addCategory()">+ Add New Category</button>
        </div>

        <div class="save-area">
            <button type="submit" class="btn btn-primary">SAVE CONFIGURATION</button>
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
            <span class="category-number">Category ${categoryIndex + 1}</span>
            <button type="button" class="btn btn-danger" onclick="removeCategory(this)">Remove</button>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>
                    Key 
                    <span class="help-text">Internal System ID. Use <strong>lowercase letters only</strong>. No spaces. (e.g. <em>finance_team</em>)</span>
                </label>
                <input type="text" name="category_key[]" value="" required pattern="[a-z0-9_]+">
            </div>

            <div class="form-group">
                <label>
                    Name 
                    <span class="help-text">The big headline for this column on the main screen.</span>
                </label>
                <input type="text" name="category_name[]" value="" required>
            </div>

            <div class="form-group">
                <label>
                    Abbreviation 
                    <span class="help-text">Short 3-letter code for the Admin buttons (e.g. <em>FIN</em>).</span>
                </label>
                <input type="text" name="category_abbrev[]" value="" required maxlength="3">
            </div>

            <div class="form-group">
                <label>
                    Icon 
                    <span class="help-text">Paste an Emoji here (e.g. 💡).</span>
                </label>
                <input type="text" name="category_icon[]" value="">
            </div>
        </div>

        <div class="form-group">
            <label>
                Display Name (Input Form) 
                <span class="help-text">The label participants see on their phones. Best combined with an icon (e.g. <em>💰 Finance Team</em>).</span>
            </label>
            <input type="text" name="category_display_name[]" value="">
        </div>

        <div class="form-group">
            <label>
                Guiding Questions 
                <span class="help-text">Help your participants! Write questions here to guide them. <strong>One question per line.</strong></span>
            </label>
            <textarea name="category_leitfragen_${categoryIndex}"></textarea>
        </div>
    `;

    container.appendChild(newCard);
    categoryIndex++;
    updateCategoryNumbers();
}

function removeCategory(btn) {
    if (!confirm('Remove this category?')) {
        return;
    }

    const card = btn.closest('.category-card');
    card.remove();
    updateCategoryNumbers();
}

function updateCategoryNumbers() {
    const cards = document.querySelectorAll('.category-card');
    cards.forEach((card, index) => {
        card.querySelector('.category-number').textContent = `Category ${index + 1}`;
    });
}

// Form validation
document.getElementById('customizeForm').addEventListener('submit', function(e) {
    const categories = document.querySelectorAll('.category-card');
    if (categories.length === 0) {
        e.preventDefault();
        alert('⚠️ At least one category is required.');
        return false;
    }

    return true;
});
</script>

</body>
</html>