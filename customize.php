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

    // 🔒 SECURITY: Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = '⚠️ Invalid security token. Please refresh the page and try again.';
        $messageType = 'error';
    } else {
        // Validate and process header title
        $newHeaderTitle = trim($_POST['header_title'] ?? '');
        // Convert newlines to <br> tags for natural input
        $newHeaderTitle = str_replace(["\r\n", "\n", "\r"], '<br>', $newHeaderTitle);
        // 🔒 SECURITY: Only allow <br> tags to prevent XSS
        $newHeaderTitle = strip_tags($newHeaderTitle, '<br>');

    // Validate and process logo URL
    $newLogoUrl = trim($_POST['logo_url'] ?? '');

    // Validate and process logo size
    $newLogoSize = intval($_POST['logo_size'] ?? 100);
    // Ensure logo size is within reasonable bounds (20px - 500px)
    if ($newLogoSize < 20) $newLogoSize = 20;
    if ($newLogoSize > 500) $newLogoSize = 500;

    // Validate logo URL is safe (no javascript:, data:, etc.)
    if (!empty($newLogoUrl) && !isUrlSafe($newLogoUrl)) {
        $message = '⚠️ INVALID LOGO URL. Only HTTP/HTTPS URLs are allowed.';
        $messageType = 'error';
        $newLogoUrl = ''; // Clear invalid URL
    }

    // Validate and process categories
    $newCategories = [];
    $categoryNames = $_POST['category_name'] ?? [];
    $categoryIcons = $_POST['category_icon'] ?? [];
    $categoryCustomFormNames = $_POST['category_custom_form_name'] ?? [];
    $categoryUseCustomFormName = $_POST['category_use_custom_form_name'] ?? [];

    $errors = [];

    // Process each category
    for ($i = 0; $i < count($categoryNames); $i++) {
        $name = trim($categoryNames[$i] ?? '');
        $icon = trim($categoryIcons[$i] ?? '');

        if (empty($name)) {
            $errors[] = "Category " . ($i + 1) . ": Name is required.";
            continue;
        }

        // Auto-generate key from name (lowercase, replace spaces with underscores)
        $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
        $key = trim($key, '_'); // Remove leading/trailing underscores

        // Auto-generate abbreviation (first 3 letters, uppercase)
        $abbrev = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3));
        if (empty($abbrev)) {
            $abbrev = 'CAT'; // Fallback if no letters in name
        }

        // Determine display name for form
        $useCustomFormName = isset($categoryUseCustomFormName[$i]) && $categoryUseCustomFormName[$i] === 'yes';
        $customFormName = trim($categoryCustomFormNames[$i] ?? '');

        // If custom form name is enabled and provided, use it; otherwise use the main name with icon
        if ($useCustomFormName && !empty($customFormName)) {
            $displayName = $customFormName;
        } else {
            // Use icon + name as default
            $displayName = !empty($icon) ? $icon . ' ' . $name : $name;
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
            'use_custom_form_name' => $useCustomFormName,
            'custom_form_name' => $customFormName,
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
                'logo_size' => $newLogoSize,
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
    } // End CSRF validation
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
        /* --- DESIGN SYSTEM --- */
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

        /* RANGE SLIDER STYLING */
        input[type="range"] {
            -webkit-appearance: none;
            appearance: none;
            background: var(--border-color);
            outline: none;
            border-radius: 10px;
            padding: 0;
        }

        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            background: var(--color-green);
            cursor: pointer;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        input[type="range"]::-moz-range-thumb {
            width: 20px;
            height: 20px;
            background: var(--color-green);
            cursor: pointer;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

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
        /* ALIGNMENT FIX APPLIED HERE */
        .form-row {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 1.5rem;
            align-items: stretch; /* Ensures columns are same height */
        }

        .form-row .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 0; /* Override standard margin */
            height: 100%; /* Fill the grid cell */
        }

        .form-row label {
            /* Label stays at top */
            margin-bottom: 10px;
        }

        .form-row input {
            /* Input pushes to bottom */
            margin-top: auto;
        }

        /* SIMPLIFIED 2-COLUMN LAYOUT FOR CATEGORIES */
        .form-row-simple {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 1.5rem;
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
            
            /* Reset grid on mobile */
            .form-row { grid-template-columns: 1fr; gap: 20px; }
            .form-row .form-group { display: block; height: auto; }
            .form-row input { margin-top: 0; }
            .form-row-simple { grid-template-columns: 1fr; gap: 20px; }
            
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
        <?= getCSRFField() ?>

        <div class="form-section">
            <h2>Header Settings</h2>
            <div class="form-group">
                <label for="header_title">
                    Dashboard Title
                    <span class="help-text">
                        The main title displayed at the top of the Situation Room screen.<br>
                        Tip: Press <strong>Enter</strong> to add a line break naturally.
                    </span>
                </label>
                <textarea id="header_title" name="header_title" rows="3" required><?php
                    // Convert <br> tags back to newlines for display in textarea
                    echo htmlspecialchars(str_replace('<br>', "\n", $config['header_title'] ?? ''));
                ?></textarea>
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

            <div class="form-group">
                <label for="logo_size">
                    Logo Size: <span id="logo_size_display"><?= htmlspecialchars($config['logo_size'] ?? 100) ?>px</span>
                    <span class="help-text">
                        Adjust the height of your logo. Default is 100px.
                    </span>
                </label>
                <input type="range" id="logo_size" name="logo_size" min="20" max="500" step="5" value="<?= htmlspecialchars($config['logo_size'] ?? 100) ?>" oninput="document.getElementById('logo_size_display').textContent = this.value + 'px'" style="width: 100%; height: 40px; cursor: pointer;">
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

                        <div class="form-row-simple">
                            <div class="form-group">
                                <label>
                                    Name
                                    <span class="help-text">The display name for this category. Used everywhere by default. <strong>Key and Abbreviation are auto-generated.</strong></span>
                                </label>
                                <input type="text" name="category_name[]" value="<?= htmlspecialchars($category['name'] ?? '') ?>" required>
                            </div>

                            <div class="form-group">
                                <label>
                                    Icon
                                    <span class="help-text">Optional emoji (e.g. 💡)</span>
                                </label>
                                <input type="text" name="category_icon[]" value="<?= htmlspecialchars($category['icon'] ?? '') ?>" placeholder="💡">
                            </div>
                        </div>

                        <div class="form-group custom-form-name-wrapper">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="category_use_custom_form_name[<?= $index ?>]" value="yes" onchange="toggleCustomFormName(this)" <?= !empty($category['use_custom_form_name']) ? 'checked' : '' ?> style="width: auto; cursor: pointer;">
                                <span style="font-family: var(--font-body); font-size: 0.95rem; font-weight: 500;">Use different name for input form</span>
                            </label>
                            <span class="help-text">By default, participants see: <strong><?= htmlspecialchars(($category['icon'] ?? '') . ' ' . ($category['name'] ?? '')) ?></strong>. Check this to customize it.</span>

                            <div class="custom-form-name-input" style="<?= empty($category['use_custom_form_name']) ? 'display: none;' : '' ?> margin-top: 12px;">
                                <input type="text" name="category_custom_form_name[<?= $index ?>]" value="<?= htmlspecialchars($category['custom_form_name'] ?? '') ?>" placeholder="e.g. 💰 Finance & Budgeting Team">
                            </div>
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

        <div class="form-row-simple">
            <div class="form-group">
                <label>
                    Name
                    <span class="help-text">The display name for this category. Used everywhere by default. <strong>Key and Abbreviation are auto-generated.</strong></span>
                </label>
                <input type="text" name="category_name[]" value="" required>
            </div>

            <div class="form-group">
                <label>
                    Icon
                    <span class="help-text">Optional emoji (e.g. 💡)</span>
                </label>
                <input type="text" name="category_icon[]" value="" placeholder="💡">
            </div>
        </div>

        <div class="form-group custom-form-name-wrapper">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="category_use_custom_form_name[${categoryIndex}]" value="yes" onchange="toggleCustomFormName(this)" style="width: auto; cursor: pointer;">
                <span style="font-family: var(--font-body); font-size: 0.95rem; font-weight: 500;">Use different name for input form</span>
            </label>
            <span class="help-text">By default, participants see the category name with icon. Check this to customize it.</span>

            <div class="custom-form-name-input" style="display: none; margin-top: 12px;">
                <input type="text" name="category_custom_form_name[${categoryIndex}]" value="" placeholder="e.g. 💰 Finance & Budgeting Team">
            </div>
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

function toggleCustomFormName(checkbox) {
    const wrapper = checkbox.closest('.custom-form-name-wrapper');
    const input = wrapper.querySelector('.custom-form-name-input');

    if (checkbox.checked) {
        input.style.display = 'block';
    } else {
        input.style.display = 'none';
    }
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