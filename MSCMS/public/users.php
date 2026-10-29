<?php
/**
 * MSCMS - Users Management (Full Control)
 * View + Add + Edit + Activate/Deactivate
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$currentUser = currentUser();

// Only Super Administrator or ICT Administrator
if (!hasRole(['Super Administrator', 'ICT Administrator'])) {
    header('Location: dashboard.php?error=unauthorized');
    exit;
}

$pdo = null;
$message = '';
$error = '';
$editUser = null;

try {
    $pdo = db();
} catch (Exception $e) {
    $error = "Database connection failed.";
}

// ========== Handle Actions ==========
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ----- ADD USER -----
    if ($action === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $role_id   = (int)($_POST['role_id'] ?? 0);
        $password  = $_POST['password'] ?? '';

        if (!$full_name || !$username || !$email || !$role_id || !$password) {
            $error = "Please fill all required fields.";
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (full_name, username, email, phone, password_hash, role_id, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$full_name, $username, $email, $phone, $hash, $role_id]);
                $message = "User <strong>$username</strong> created successfully.";
            } catch (Exception $e) {
                $error = "Failed to create user. Username or email may already exist.";
            }
        }
    }

    // ----- UPDATE USER -----
    if ($action === 'edit') {
        $user_id   = (int)($_POST['user_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $role_id   = (int)($_POST['role_id'] ?? 0);

        if ($user_id && $full_name && $email && $role_id) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users SET full_name = ?, email = ?, phone = ?, role_id = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$full_name, $email, $phone, $role_id, $user_id]);
                $message = "User updated successfully.";
            } catch (Exception $e) {
                $error = "Failed to update user.";
            }
        }
    }

    // ----- TOGGLE STATUS -----
    if ($action === 'toggle') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id && $user_id != $currentUser['user_id']) {
            $stmt = $pdo->prepare("UPDATE users SET is_active = IF(is_active = 1, 0, 1) WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $message = "User status updated.";
        } else {
            $error = "You cannot deactivate your own account.";
        }
    }
  

/// ----- DELETE USER -----
if ($action === 'delete') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id && $user_id != $currentUser['user_id']) {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $message = "User deleted successfully.";
        } catch (Exception $e) {
            $error = "Cannot delete this user. They may have related records.";
        }
    } else {
        $error = "You cannot delete your own account.";
    }
}
}

// Load data for display
$users = [];
$roles = [];
$search = trim($_GET['search'] ?? '');

if ($pdo) {
    try {
        $roles = $pdo->query("SELECT role_id, role_name FROM roles ORDER BY role_name")->fetchAll();

        $sql = "
            SELECT u.user_id, u.full_name, u.username, u.email, u.phone, 
                   u.is_active, u.last_login, r.role_name, r.role_id
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE 1=1
        ";
        $params = [];
        if ($search) {
            $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%"];
        }
        $sql .= " ORDER BY u.full_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = "Could not load users.";
    }
}

// Load user for editing
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($users as $u) {
        if ($u['user_id'] == $editId) {
            $editUser = $u;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - MSCMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

    <nav class="bg-teal-800 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-6">
                    <a href="dashboard.php" class="flex items-center gap-2 font-bold text-xl">
                        <i class="fas fa-pills"></i> MSCMS
                    </a>
                    <div class="hidden md:flex gap-4 text-sm">
                        <a href="dashboard.php" class="text-teal-200 hover:text-white">Dashboard</a>
                        <a href="users.php" class="text-white font-medium border-b-2 border-white">Users</a>
                        <a href="reports.php" class="text-teal-200 hover:text-white">Reports</a>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-teal-100"><?= e($currentUser['full_name']) ?></span>
                    <a href="logout.php" class="bg-teal-700 hover:bg-teal-600 px-3 py-1.5 rounded-lg text-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Users Management</h1>
                <p class="text-gray-500 text-sm mt-1">Add, edit and manage system users</p>
            </div>
            <button onclick="document.getElementById('addModal').classList.remove('hidden')" 
                    class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-user-plus mr-1"></i> Add New User
            </button>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
                <?= $message ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- Search -->
        <form method="GET" class="bg-white rounded-xl shadow-sm border p-4 mb-6">
            <div class="flex gap-3">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name, username or email..."
                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="users.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">Reset</a>
            </div>
        </form>

        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium">Full Name</th>
                            <th class="text-left px-4 py-3 font-medium">Username</th>
                            <th class="text-left px-4 py-3 font-medium">Email</th>
                            <th class="text-left px-4 py-3 font-medium">Role</th>
                            <th class="text-left px-4 py-3 font-medium">Last Login</th>
                            <th class="text-center px-4 py-3 font-medium">Status</th>
                            <th class="text-center px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($users)): ?>
                            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No users found</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium"><?= e($u['full_name']) ?></td>
                                <td class="px-4 py-3 font-mono text-gray-600"><?= e($u['username']) ?></td>
                                <td class="px-4 py-3 text-gray-600"><?= e($u['email']) ?></td>
                                <td class="px-4 py-3">
                                    <span class="bg-teal-50 text-teal-700 text-xs px-2 py-0.5 rounded-full"><?= e($u['role_name']) ?></span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    <?= $u['last_login'] ? formatDate($u['last_login'], 'd M Y H:i') : 'Never' ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($u['is_active']): ?>
                                        <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">Active</span>
                                    <?php else: ?>
                                        <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
    <div class="flex justify-center gap-2">
        <!-- VIEW -->
        <a href="?view=<?= $u['user_id'] ?>" class="text-teal-600 hover:text-teal-800" title="View">
            <i class="fas fa-eye"></i>
        </a>

        <!-- EDIT -->
        <a href="?edit=<?= $u['user_id'] ?>" class="text-blue-600 hover:text-blue-800" title="Edit">
            <i class="fas fa-edit"></i>
        </a>

        <?php if ($u['user_id'] != $currentUser['user_id']): ?>
        <!-- ACTIVATE / DEACTIVATE -->
        <form method="POST" class="inline" onsubmit="return confirm('Change status of this user?')">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
            <button type="submit" class="text-orange-600 hover:text-orange-800" title="Activate/Deactivate">
                <i class="fas fa-power-off"></i>
            </button>
        </form>

        <!-- DELETE -->
        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to DELETE this user permanently?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
            <button type="submit" class="text-red-600 hover:text-red-800" title="Delete">
                <i class="fas fa-trash"></i>
            </button>
        </form>
        <?php endif; ?>
    </div>
</td>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-sm text-gray-500 mt-4">Showing <?= count($users) ?> user(s)</p>
    </div>

    <!-- ========== ADD USER MODAL ========== -->
    <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div class="flex justify-between items-center border-b px-6 py-4">
                <h2 class="text-lg font-semibold">Add New User</h2>
                <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="add">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="full_name" required class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                    <input type="text" name="username" required class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" required class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                    <select name="role_id" required class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['role_id'] ?>"><?= e($r['role_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                    <input type="password" name="password" required minlength="6" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" 
                            class="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========== EDIT USER MODAL ========== -->
    <?php if ($editUser): ?>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div class="flex justify-between items-center border-b px-6 py-4">
                <h2 class="text-lg font-semibold">Edit User</h2>
                <a href="users.php" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></a>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" value="<?= $editUser['user_id'] ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="full_name" value="<?= e($editUser['full_name']) ?>" required
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" value="<?= e($editUser['username']) ?>" disabled
                           class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-100 text-gray-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" value="<?= e($editUser['email']) ?>" required
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" value="<?= e($editUser['phone'] ?? '') ?>"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                    <select name="role_id" required class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['role_id'] ?>" <?= $r['role_id'] == $editUser['role_id'] ? 'selected' : '' ?>>
                                <?= e($r['role_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <a href="users.php" class="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
<?php if (isset($_GET['view'])): 
    $viewId = (int)$_GET['view'];
    $viewUser = null;
    foreach ($users as $u) {
        if ($u['user_id'] == $viewId) {
            $viewUser = $u;
            break;
        }
    }
    if ($viewUser):
?>
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="flex justify-between items-center border-b px-6 py-4">
            <h2 class="text-lg font-semibold">User Details</h2>
            <a href="users.php" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></a>
        </div>
        <div class="p-6 space-y-3 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">Full Name</span>
                <span class="font-medium"><?= e($viewUser['full_name']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Username</span>
                <span class="font-mono"><?= e($viewUser['username']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Email</span>
                <span><?= e($viewUser['email']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Phone</span>
                <span><?= e($viewUser['phone'] ?? '-') ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Role</span>
                <span class="bg-teal-50 text-teal-700 text-xs px-2 py-0.5 rounded-full"><?= e($viewUser['role_name']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Status</span>
                <span><?= $viewUser['is_active'] ? 'Active' : 'Inactive' ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Last Login</span>
                <span><?= $viewUser['last_login'] ? formatDate($viewUser['last_login'], 'd M Y H:i') : 'Never' ?></span>
            </div>
        </div>
        <div class="border-t px-6 py-4 flex justify-end">
            <a href="users.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm">Close</a>
        </div>
    </div>
</div>
<?php endif; endif; ?>
</body>
</html>