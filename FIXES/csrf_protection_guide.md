# CSRF Protection Implementation Guide

## What is CSRF?
Cross-Site Request Forgery (CSRF) allows attackers to trick authenticated users into performing unwanted actions on your website.

**Example Attack:**
1. User logs into your Workshop Tool
2. User visits attacker's website (while still logged in)
3. Attacker's site submits a form to your customize.php
4. Your server thinks it's the legitimate user and changes their workshop settings!

## How to Fix

### Step 1: Add CSRF Token to Forms

You already have the CSRF functions in `user_auth.php` - you just need to USE them!

#### customize.php - Line 447
```php
<!-- BEFORE (VULNERABLE): -->
<form method="POST" id="customizeForm">
    <div class="form-section">

<!-- AFTER (PROTECTED): -->
<form method="POST" id="customizeForm">
    <?= getCSRFField() ?>  <!-- ADD THIS LINE -->
    <div class="form-section">
```

### Step 2: Validate CSRF Token on Submission

#### customize.php - Line 35 (at the very top of form processing)
```php
<!-- BEFORE (VULNERABLE): -->
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and process header title
    $newHeaderTitle = trim($_POST['header_title'] ?? '');

<!-- AFTER (PROTECTED): -->
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = '⚠️ Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        // Validate and process header title
        $newHeaderTitle = trim($_POST['header_title'] ?? '');
        // ... rest of existing code
    }
}
```

### Complete Example - customize.php

Here's the complete fixed version of the form processing section:

```php
<?php
// Line 35 - Replace this section
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // STEP 1: Validate CSRF token FIRST
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = '⚠️ Invalid security token. Please refresh the page and try again.';
        $messageType = 'error';
    } else {
        // STEP 2: Process form normally (existing code)

        // Validate and process header title
        $newHeaderTitle = trim($_POST['header_title'] ?? '');

        // Validate and process logo URL
        $newLogoUrl = trim($_POST['logo_url'] ?? '');

        // ... rest of your existing form processing code ...
    }
}
?>

<!-- Line 447 - Add CSRF field to form -->
<form method="POST" id="customizeForm">
    <?= getCSRFField() ?>  <!-- ADD THIS LINE -->

    <div class="form-section">
        <!-- rest of form -->
    </div>
</form>
```

## Files That Need CSRF Protection

### ✅ customize.php
- Form at line 447
- Processing at line 35

### ✅ admin.php (already protected via AJAX!)
Admin.php uses AJAX requests which rely on same-origin policy. However, for extra security, you could add CSRF to the AJAX requests:

```javascript
// Optional: Add CSRF to admin.php AJAX requests
async function runCmd(queryParams) {
    const csrfToken = '<?= generateCSRFToken() ?>';
    queryParams += '&csrf_token=' + encodeURIComponent(csrfToken);

    const response = await fetch('admin.php?' + queryParams + '&ajax=1');
    // ... rest of code
}
```

And in admin.php action handler (line 336):
```php
if (isset($_REQUEST['ajax'])) {
    // Validate CSRF for AJAX requests
    if (!isset($_REQUEST['csrf_token']) || !validateCSRFToken($_REQUEST['csrf_token'])) {
        http_response_code(403);
        echo "CSRF validation failed";
        exit;
    }
}
```

### ❌ eingabe.php (PUBLIC FORM - NO CSRF NEEDED)
This is a public form for anonymous submissions. CSRF is NOT needed here because:
1. No authentication required
2. Rate limiting protects against abuse
3. Submissions are hidden by default (admin must approve)

## Testing Your CSRF Protection

### Test 1: Valid Submission
1. Open customize.php
2. Fill out form normally
3. Submit
4. Should work! ✅

### Test 2: Missing Token
1. Open customize.php
2. Open browser DevTools (F12)
3. Find the CSRF token input in the HTML
4. Delete the input element
5. Submit form
6. Should be REJECTED! ✅

### Test 3: Invalid Token
1. Open customize.php
2. Open browser DevTools (F12)
3. Change the CSRF token value to "invalid123"
4. Submit form
5. Should be REJECTED! ✅

### Test 4: CSRF Attack Simulation
Create this file: `test_csrf_attack.html`
```html
<!DOCTYPE html>
<html>
<head><title>CSRF Attack Test</title></head>
<body>
<h1>CSRF Attack Simulation</h1>
<p>If CSRF protection works, this should FAIL:</p>

<form id="attack" method="POST" action="http://localhost/your-workshop-tool/customize.php">
    <input type="text" name="header_title" value="HACKED!">
    <input type="text" name="logo_url" value="">
    <!-- No CSRF token! -->
    <button type="submit">Try Attack</button>
</form>

<script>
// Uncomment to auto-submit:
// document.getElementById('attack').submit();
</script>
</body>
</html>
```

1. Log into your Workshop Tool
2. Open this HTML file in the SAME browser
3. Click "Try Attack"
4. Your workshop should NOT be changed! ✅

## Troubleshooting

### "Invalid security token" on legitimate submissions
**Cause:** Session expired or user opened form in multiple tabs

**Solution:** Add this user message:
```php
if (!validateCSRFToken($_POST['csrf_token'])) {
    $message = '⚠️ Your session has expired. Please <a href="">refresh the page</a> and try again.';
    $messageType = 'error';
}
```

### CSRF token changes on each page load
**Behavior:** This is NORMAL! The token is stored in the session and remains valid until:
- User logs out
- Session expires (2 hours)
- Server restarts

The same token is reused for all forms in the same session.

## Summary

1. ✅ Add `<?= getCSRFField() ?>` to customize.php form (line 447)
2. ✅ Add CSRF validation to customize.php processing (line 35)
3. ✅ Test with the 4 tests above
4. ✅ Optional: Add CSRF to admin.php AJAX requests

**Time to implement:** 15 minutes
**Lines of code added:** ~5 lines

This simple fix prevents a CRITICAL vulnerability!
