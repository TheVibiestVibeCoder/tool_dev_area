<?php
// ============================================
// USER MANAGEMENT LIBRARY
// ============================================
// Provides secure multi-user authentication with role-based access control
// Compatible with existing atomic file handling system

require_once 'file_handling_robust.php';

// ===== CONFIGURATION =====
define('USERS_FILE', 'users.json');
define('SESSION_TIMEOUT', 3600 * 8); // 8 hours
define('PASSWORD_MIN_LENGTH', 8);

// ===== USER ROLES & PERMISSIONS =====
const ROLES = [
    'admin' => [
        'name' => 'Administrator',
        'permissions' => ['all'] // Full access to everything
    ],
    'moderator' => [
        'name' => 'Moderator',
        'permissions' => ['view', 'toggle', 'edit', 'focus', 'delete_single', 'export']
        // Cannot: delete_all, manage_users, edit_config
    ]
];

// ===== INITIALIZATION =====

/**
 * Ensures users.json exists and contains at least the default admin
 */
function initializeUserSystem() {
    if (!file_exists(USERS_FILE)) {
        // Create default admin user (password: "admin123" - MUST BE CHANGED!)
        $defaultAdmin = [
            'id' => uniqid('user_', true),
            'username' => 'admin',
            'password_hash' => password_hash('admin123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'created_at' => time(),
            'last_login' => null
        ];

        atomicWrite(USERS_FILE, json_encode([$defaultAdmin], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        logError("User system initialized with default admin (username: admin, password: admin123)");
    }
}

// ===== AUTHENTICATION =====

/**
 * Authenticate user with username and password
 * @return array|false User data on success, false on failure
 */
function authenticateUser($username, $password) {
    $users = loadUsers();

    foreach ($users as $user) {
        if ($user['username'] === $username) {
            if (password_verify($password, $user['password_hash'])) {
                // Update last login time
                updateUserLastLogin($user['id']);

                // Return user data (without password hash)
                return [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ];
            }
            return false; // Wrong password
        }
    }

    return false; // User not found
}

/**
 * Start authenticated session for user
 */
function loginUser($userData) {
    session_regenerate_id(true); // Prevent session fixation
    $_SESSION['user_id'] = $userData['id'];
    $_SESSION['username'] = $userData['username'];
    $_SESSION['role'] = $userData['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
}

/**
 * Check if current session is valid and authenticated
 * @return bool
 */
function isAuthenticated() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
        return false;
    }

    // Check session timeout
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        logoutUser();
        return false;
    }

    // Update last activity
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Get current authenticated user data
 * @return array|null
 */
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role']
    ];
}

/**
 * Logout current user
 */
function logoutUser() {
    session_unset();
    session_destroy();
}

// ===== AUTHORIZATION =====

/**
 * Check if current user has permission for an action
 * @param string $action Permission to check (e.g., 'delete_all', 'edit', 'view')
 * @return bool
 */
function hasPermission($action) {
    if (!isAuthenticated()) {
        return false;
    }

    $role = $_SESSION['role'];

    if (!isset(ROLES[$role])) {
        return false;
    }

    $permissions = ROLES[$role]['permissions'];

    // 'all' permission grants everything
    if (in_array('all', $permissions)) {
        return true;
    }

    return in_array($action, $permissions);
}

/**
 * Require authentication - redirects to login if not authenticated
 */
function requireAuth($redirectUrl = 'admin.php') {
    if (!isAuthenticated()) {
        header("Location: $redirectUrl");
        exit;
    }
}

/**
 * Require specific permission - returns 403 if not authorized
 */
function requirePermission($action) {
    if (!hasPermission($action)) {
        http_response_code(403);
        die("403 Forbidden - Insufficient permissions for action: $action");
    }
}

// ===== USER CRUD OPERATIONS =====

/**
 * Load all users from users.json
 * @return array
 */
function loadUsers() {
    initializeUserSystem();
    $content = atomicRead(USERS_FILE);
    $users = json_decode($content, true);
    return is_array($users) ? $users : [];
}

/**
 * Create new user
 * @return bool|string User ID on success, false on failure
 */
function createUser($username, $password, $role = 'moderator') {
    // Validation
    if (strlen($username) < 3) {
        logError("Username too short: $username");
        return false;
    }

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        logError("Password too short for user: $username");
        return false;
    }

    if (!isset(ROLES[$role])) {
        logError("Invalid role: $role");
        return false;
    }

    // Check if username exists
    $users = loadUsers();
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            logError("Username already exists: $username");
            return false;
        }
    }

    // Create new user
    $newUser = [
        'id' => uniqid('user_', true),
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        'role' => $role,
        'created_at' => time(),
        'last_login' => null
    ];

    // Add to users file atomically
    $success = atomicUpdate(USERS_FILE, function($users) use ($newUser) {
        $users[] = $newUser;
        return $users;
    });

    return $success ? $newUser['id'] : false;
}

/**
 * Update user password
 * @return bool
 */
function updateUserPassword($userId, $newPassword) {
    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        return false;
    }

    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

    return atomicUpdate(USERS_FILE, function($users) use ($userId, $passwordHash) {
        foreach ($users as &$user) {
            if ($user['id'] === $userId) {
                $user['password_hash'] = $passwordHash;
                break;
            }
        }
        return $users;
    });
}

/**
 * Update user role
 * @return bool
 */
function updateUserRole($userId, $newRole) {
    if (!isset(ROLES[$newRole])) {
        return false;
    }

    return atomicUpdate(USERS_FILE, function($users) use ($userId, $newRole) {
        foreach ($users as &$user) {
            if ($user['id'] === $userId) {
                $user['role'] = $newRole;
                break;
            }
        }
        return $users;
    });
}

/**
 * Delete user by ID
 * @return bool
 */
function deleteUser($userId) {
    // Cannot delete yourself
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $userId) {
        logError("User tried to delete themselves: $userId");
        return false;
    }

    return atomicUpdate(USERS_FILE, function($users) use ($userId) {
        // Ensure at least one admin remains
        $admins = array_filter($users, fn($u) => $u['role'] === 'admin' && $u['id'] !== $userId);
        if (empty($admins)) {
            logError("Cannot delete last admin user");
            return $users; // No change - keep the user
        }

        return array_values(array_filter($users, fn($u) => $u['id'] !== $userId));
    });
}

/**
 * Update last login timestamp
 */
function updateUserLastLogin($userId) {
    atomicUpdate(USERS_FILE, function($users) use ($userId) {
        foreach ($users as &$user) {
            if ($user['id'] === $userId) {
                $user['last_login'] = time();
                break;
            }
        }
        return $users;
    });
}

/**
 * Get user by ID
 * @return array|null
 */
function getUserById($userId) {
    $users = loadUsers();
    foreach ($users as $user) {
        if ($user['id'] === $userId) {
            unset($user['password_hash']); // Don't expose password hash
            return $user;
        }
    }
    return null;
}

// ===== CSRF PROTECTION =====

/**
 * Generate CSRF token for current session
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from request
 * @return bool
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token - dies with 403 if invalid
 */
function requireCSRFToken() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        http_response_code(403);
        die("403 Forbidden - Invalid CSRF token");
    }
}

/**
 * Output hidden CSRF input field for forms
 */
function csrfField() {
    $token = generateCSRFToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Get CSRF token as URL parameter
 * @return string
 */
function csrfParam() {
    return 'csrf_token=' . urlencode(generateCSRFToken());
}

// ===== AUDIT TRAIL =====

/**
 * Get current username for audit logging
 * @return string
 */
function getAuditUsername() {
    return $_SESSION['username'] ?? 'system';
}

/**
 * Add audit info to entry data
 * @param array $entry Entry data
 * @param string $action Action performed (e.g., 'created', 'modified', 'deleted')
 * @return array Entry with audit info
 */
function addAuditInfo($entry, $action = 'modified') {
    if (!isset($entry['audit_log'])) {
        $entry['audit_log'] = [];
    }

    $entry['audit_log'][] = [
        'action' => $action,
        'username' => getAuditUsername(),
        'timestamp' => time()
    ];

    $entry['last_modified_by'] = getAuditUsername();
    $entry['last_modified_at'] = time();

    return $entry;
}

// Initialize on load
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
initializeUserSystem();
