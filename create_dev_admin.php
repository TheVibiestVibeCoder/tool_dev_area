#!/usr/bin/env php
<?php
/**
 * CLI Script to Create Dev Admin Accounts
 *
 * Usage: php create_dev_admin.php
 *
 * This script provides a secure, interactive way to create developer admin accounts.
 * It should only be run by authorized developers with server access.
 *
 * Security: This script can only be run from the command line (CLI), not via web browser.
 */

// Ensure this is run from CLI only
if (php_sapi_name() !== 'cli') {
    die("ERROR: This script can only be run from the command line.\n");
}

require_once __DIR__ . '/dev_admin_auth.php';

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║                                                               ║\n";
echo "║          🛠️  DEV ADMIN ACCOUNT CREATION TOOL  🛠️             ║\n";
echo "║                                                               ║\n";
echo "║                 Live Situation Room SaaS                      ║\n";
echo "║                                                               ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Show existing dev admins
$existingAdmins = loadDevAdmins();
if (!empty($existingAdmins)) {
    echo "📋 Existing Dev Admin Accounts:\n";
    echo "─────────────────────────────────────────────────────────────\n";
    foreach ($existingAdmins as $admin) {
        echo "  • " . $admin['username'] . " (" . $admin['email'] . ") - ";
        echo $admin['active'] ? "✅ Active" : "❌ Inactive";
        echo "\n";
    }
    echo "\n";
} else {
    echo "ℹ️  No dev admin accounts exist yet. This will be your first admin!\n\n";
}

// Interactive account creation
echo "Let's create a new dev admin account.\n";
echo "─────────────────────────────────────────────────────────────\n\n";

// Get username
echo "👤 Enter username (alphanumeric + underscore, 3-30 chars): ";
$username = trim(fgets(STDIN));

if (empty($username)) {
    die("❌ Username cannot be empty.\n");
}

if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
    die("❌ Invalid username format. Use 3-30 alphanumeric characters or underscore.\n");
}

// Get full name
echo "📝 Enter full name: ";
$fullName = trim(fgets(STDIN));

if (empty($fullName)) {
    die("❌ Full name cannot be empty.\n");
}

// Get email
echo "📧 Enter email address: ";
$email = trim(fgets(STDIN));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("❌ Invalid email address.\n");
}

// Get password
echo "🔐 Enter password (min 8 characters): ";
$password = trim(fgets(STDIN));

if (strlen($password) < 8) {
    die("❌ Password must be at least 8 characters.\n");
}

// Confirm password
echo "🔐 Confirm password: ";
$passwordConfirm = trim(fgets(STDIN));

if ($password !== $passwordConfirm) {
    die("❌ Passwords do not match.\n");
}

echo "\n";
echo "─────────────────────────────────────────────────────────────\n";
echo "📝 Account Details:\n";
echo "   Username:  $username\n";
echo "   Full Name: $fullName\n";
echo "   Email:     $email\n";
echo "─────────────────────────────────────────────────────────────\n";
echo "\n";

// Confirm creation
echo "✅ Create this dev admin account? (yes/no): ";
$confirm = trim(strtolower(fgets(STDIN)));

if ($confirm !== 'yes' && $confirm !== 'y') {
    die("❌ Account creation cancelled.\n");
}

// Create the account
echo "\n🔄 Creating dev admin account...\n";

$result = createDevAdmin($username, $password, $email, $fullName);

if ($result['success']) {
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║                                                               ║\n";
    echo "║                   ✅ SUCCESS! ✅                              ║\n";
    echo "║                                                               ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "🎉 Dev admin account created successfully!\n";
    echo "\n";
    echo "🔐 Login Details:\n";
    echo "   • Username: $username\n";
    echo "   • Password: [saved securely]\n";
    echo "\n";
    echo "🌐 You can now login at:\n";
    echo "   • dev_login.php\n";
    echo "\n";
    echo "⚠️  IMPORTANT: Store these credentials securely!\n";
    echo "    This is your only chance to see this confirmation.\n";
    echo "\n";
} else {
    echo "\n";
    echo "❌ ERROR: " . $result['message'] . "\n";
    exit(1);
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";
