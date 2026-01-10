<?php
// ============================================
// USER MANAGEMENT PANEL
// ============================================
// Admin-only interface for managing users

require_once 'user_management.php';
require_once 'file_handling_robust.php';

// Require admin authentication
requireAuth('admin.php');
requirePermission('all'); // Only admins can manage users

$message = '';
$messageType = '';

// ===== HANDLE USER ACTIONS =====

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();

    // CREATE NEW USER
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'moderator';

        if (empty($username) || empty($password)) {
            $message = 'Username and password are required';
            $messageType = 'error';
        } else {
            $userId = createUser($username, $password, $role);
            if ($userId) {
                $message = "User '$username' created successfully";
                $messageType = 'success';
            } else {
                $message = 'Failed to create user - username may already exist or password too short';
                $messageType = 'error';
            }
        }
    }

    // UPDATE PASSWORD
    if (isset($_POST['action']) && $_POST['action'] === 'update_password') {
        $userId = $_POST['user_id'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';

        if (empty($newPassword)) {
            $message = 'Password cannot be empty';
            $messageType = 'error';
        } else {
            if (updateUserPassword($userId, $newPassword)) {
                $message = 'Password updated successfully';
                $messageType = 'success';
            } else {
                $message = 'Failed to update password - may be too short';
                $messageType = 'error';
            }
        }
    }

    // UPDATE ROLE
    if (isset($_POST['action']) && $_POST['action'] === 'update_role') {
        $userId = $_POST['user_id'] ?? '';
        $newRole = $_POST['new_role'] ?? '';

        if (updateUserRole($userId, $newRole)) {
            $message = 'Role updated successfully';
            $messageType = 'success';
        } else {
            $message = 'Failed to update role';
            $messageType = 'error';
        }
    }

    // DELETE USER
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $userId = $_POST['user_id'] ?? '';
        $user = getUserById($userId);

        if (deleteUser($userId)) {
            $message = "User '{$user['username']}' deleted successfully";
            $messageType = 'success';
        } else {
            $message = 'Failed to delete user - cannot delete yourself or last admin';
            $messageType = 'error';
        }
    }
}

// Load all users for display
$users = loadUsers();
$currentUser = getCurrentUser();

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Infraprotect</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        /* --- INFRAPROTECT DESIGN SYSTEM --- */
        :root {
            --ip-blue: #00658b;
            --ip-dark: #32373c;
            --ip-light: #ffffff;
            --ip-grey-bg: #f4f4f4;
            --ip-border: #e0e0e0;
            --accent-success: #00d084;
            --accent-danger: #cf2e2e;
            --accent-warning: #ff6900;
            --font-heading: 'Montserrat', sans-serif;
            --font-body: 'Roboto', sans-serif;
            --radius-pill: 9999px;
            --radius-card: 4px;
        }

        body {
            background-color: var(--ip-grey-bg);
            color: var(--ip-dark);
            font-family: var(--font-body);
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* HEADER */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--ip-light);
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            border-radius: var(--radius-card);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header h1 {
            font-family: var(--font-heading);
            font-size: 2rem;
            margin: 0;
            color: var(--ip-blue);
            font-weight: 700;
        }

        .subtitle {
            color: var(--ip-dark);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.75rem;
            font-weight: 600;
            display: block;
            margin-bottom: 0.2rem;
            opacity: 0.6;
        }

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

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn-primary { background: var(--ip-blue); }
        .btn-success { background: var(--accent-success); }
        .btn-danger { background: var(--accent-danger); }
        .btn-neutral { background: #e0e0e0; color: #555; }
        .btn-sm { padding: 6px 16px; font-size: 0.75rem; }

        /* ALERT MESSAGES */
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-radius: var(--radius-card);
            border-left: 4px solid;
        }

        .alert-success {
            background: rgba(0, 208, 132, 0.1);
            border-color: var(--accent-success);
            color: #006644;
        }

        .alert-error {
            background: rgba(207, 46, 46, 0.1);
            border-color: var(--accent-danger);
            color: var(--accent-danger);
        }

        /* PANELS */
        .panel {
            background: var(--ip-light);
            border: 1px solid var(--ip-border);
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: var(--radius-card);
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }

        .panel h2 {
            margin: 0 0 1.5rem 0;
            font-family: var(--font-heading);
            font-weight: 600;
            color: var(--ip-blue);
            font-size: 1.3rem;
            border-bottom: 2px solid var(--ip-border);
            padding-bottom: 10px;
        }

        /* FORMS */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--ip-dark);
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--ip-border);
            border-radius: 4px;
            font-size: 1rem;
            font-family: var(--font-body);
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--ip-blue);
            box-shadow: 0 0 0 3px rgba(0, 101, 139, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        /* USER TABLE */
        .user-table {
            width: 100%;
            border-collapse: collapse;
        }

        .user-table th {
            background: var(--ip-grey-bg);
            padding: 12px;
            text-align: left;
            font-family: var(--font-heading);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--ip-dark);
            border-bottom: 2px solid var(--ip-border);
        }

        .user-table td {
            padding: 12px;
            border-bottom: 1px solid var(--ip-border);
        }

        .user-table tr:hover {
            background: var(--ip-grey-bg);
        }

        .user-table tr.current-user {
            background: rgba(0, 101, 139, 0.05);
        }

        .user-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-admin {
            background: var(--ip-blue);
            color: white;
        }

        .badge-moderator {
            background: #e0e0e0;
            color: #555;
        }

        .user-actions {
            display: flex;
            gap: 8px;
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--ip-light);
            padding: 2rem;
            border-radius: var(--radius-card);
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            font-family: var(--font-heading);
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--ip-blue);
            margin-bottom: 1.5rem;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 1.5rem;
        }

        .modal-actions button {
            flex: 1;
        }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .form-row { grid-template-columns: 1fr; }
            .user-table { font-size: 0.85rem; }
            .user-actions { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="container">
    <header class="page-header">
        <div>
            <span class="subtitle">User Management</span>
            <h1>Manage Users</h1>
        </div>
        <div>
            <a href="admin.php" class="btn btn-neutral">← Back to Admin</a>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- CREATE USER PANEL -->
    <div class="panel">
        <h2>Create New User</h2>
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="create">

            <div class="form-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required minlength="3" placeholder="johndoe">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required minlength="8" placeholder="Min 8 characters">
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="moderator">Moderator</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-success">Create User</button>
        </form>
    </div>

    <!-- USER LIST PANEL -->
    <div class="panel">
        <h2>Existing Users (<?= count($users) ?>)</h2>

        <table class="user-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user):
                    $isCurrentUser = ($user['id'] === $currentUser['id']);
                ?>
                <tr class="<?= $isCurrentUser ? 'current-user' : '' ?>">
                    <td>
                        <strong><?= htmlspecialchars($user['username']) ?></strong>
                        <?php if ($isCurrentUser): ?>
                            <span style="color: #00658b; font-size: 0.75rem;"> (You)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="user-badge badge-<?= $user['role'] ?>">
                            <?= htmlspecialchars($user['role']) ?>
                        </span>
                    </td>
                    <td><?= date('Y-m-d H:i', $user['created_at']) ?></td>
                    <td>
                        <?= $user['last_login'] ? date('Y-m-d H:i', $user['last_login']) : 'Never' ?>
                    </td>
                    <td>
                        <div class="user-actions">
                            <button onclick="openPasswordModal('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username']) ?>')"
                                    class="btn btn-sm btn-neutral">
                                Change Password
                            </button>

                            <?php if (!$isCurrentUser): ?>
                                <button onclick="openRoleModal('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username']) ?>', '<?= $user['role'] ?>')"
                                        class="btn btn-sm btn-primary">
                                    Change Role
                                </button>

                                <button onclick="confirmDelete('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username']) ?>')"
                                        class="btn btn-sm btn-danger">
                                    Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CHANGE PASSWORD MODAL -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">Change Password</div>
        <form method="POST" id="passwordForm">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="update_password">
            <input type="hidden" name="user_id" id="password_user_id">

            <div class="form-group">
                <label>New Password for <strong id="password_username"></strong></label>
                <input type="password" name="new_password" required minlength="8" placeholder="Min 8 characters">
            </div>

            <div class="modal-actions">
                <button type="button" onclick="closeModal('passwordModal')" class="btn btn-neutral">Cancel</button>
                <button type="submit" class="btn btn-success">Update Password</button>
            </div>
        </form>
    </div>
</div>

<!-- CHANGE ROLE MODAL -->
<div id="roleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">Change Role</div>
        <form method="POST" id="roleForm">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="user_id" id="role_user_id">

            <div class="form-group">
                <label>New Role for <strong id="role_username"></strong></label>
                <select name="new_role" id="role_select">
                    <option value="moderator">Moderator</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" onclick="closeModal('roleModal')" class="btn btn-neutral">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Role</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE CONFIRMATION FORM (hidden) -->
<form method="POST" id="deleteForm" style="display: none;">
    <?php csrfField(); ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="delete_user_id">
</form>

<script>
    function openPasswordModal(userId, username) {
        document.getElementById('password_user_id').value = userId;
        document.getElementById('password_username').textContent = username;
        document.getElementById('passwordModal').classList.add('active');
    }

    function openRoleModal(userId, username, currentRole) {
        document.getElementById('role_user_id').value = userId;
        document.getElementById('role_username').textContent = username;
        document.getElementById('role_select').value = currentRole;
        document.getElementById('roleModal').classList.add('active');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    function confirmDelete(userId, username) {
        if (confirm(`⚠️ Delete user '${username}'?\n\nThis action cannot be undone.`)) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteForm').submit();
        }
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    }
</script>

</body>
</html>
