<?php
/**
 * Dev Admin Logout
 * Terminates dev admin session and redirects to login page
 */

require_once __DIR__ . '/dev_admin_auth.php';
require_once __DIR__ . '/security_helpers.php';

// Initialize security
setSecurityHeaders();

// Logout
devAdminLogout();

// Redirect to login page with success message
header('Location: dev_login.php?logged_out=1');
exit;
