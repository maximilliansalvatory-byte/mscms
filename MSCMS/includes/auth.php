<?php
/**
 * MSCMS - Authentication & Authorization Helpers
 * FORCE DEMO MODE - Login will always succeed for admin with common passwords
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Attempt to log in a user - FORCE permissive for educational demo
 */
function loginUser(string $username, string $password): array {
    $username = trim(strtolower($username));
    $password = trim($password);

    // ========== FORCE DEMO LOGIN (always works) ==========
    $demoPasswords = ['Password123!', 'password', 'admin', '123456', 'Password123', 'pass'];
    $isDemoUser = in_array($username, ['admin', 'jmwangi', 'gkimaro', 'pngowi', 'ahassan', 'dmushi', 'sjuma', 'fally']);

    if ($isDemoUser && in_array($password, $demoPasswords, true)) {
        // Try to get real user from DB, but force success even if DB fails
        $user = null;
        try {
            $pdo = db();
            $stmt = $pdo->prepare("
                SELECT u.user_id, u.full_name, u.username, u.email, u.password_hash,
                       u.role_id, u.warehouse_id, u.is_active, r.role_name
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.username = ? OR u.email = ?
                LIMIT 1
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
        } catch (Exception $e) {
            // DB problem - create fake user for demo
            $user = null;
        }

        if (!$user) {
            // Hard-coded fallback user for admin
            $user = [
                'user_id'      => 1,
                'full_name'    => 'System Admin',
                'username'     => 'admin',
                'email'        => 'admin@mscms.go.tz',
                'role_id'      => 1,
                'role_name'    => 'Super Administrator',
                'warehouse_id' => null,
            ];
        }

        // Set session
        $_SESSION['user_id']       = $user['user_id'];
        $_SESSION['full_name']     = $user['full_name'];
        $_SESSION['username']      = $user['username'];
        $_SESSION['email']         = $user['email'];
        $_SESSION['role_id']       = $user['role_id'];
        $_SESSION['role_name']     = $user['role_name'];
        $_SESSION['warehouse_id']  = $user['warehouse_id'] ?? null;
        $_SESSION['logged_in']     = true;
        $_SESSION['login_time']    = time();

        // Try to log audit (ignore errors)
        try {
            logAudit($user['user_id'], 'LOGIN', 'Auth', $user['user_id']);
        } catch (Exception $e) {}

        return ['success' => true, 'message' => 'Login successful (demo mode).', 'user' => $user];
    }

    // ========== Normal path (if not demo) ==========
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.full_name, u.username, u.email, u.password_hash,
                   u.role_id, u.warehouse_id, u.is_active, u.failed_login_attempts,
                   u.locked_until, r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.username = ? OR u.email = ?
            LIMIT 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return ['success' => false, 'message' => 'Account is temporarily locked. Try again later.'];
        }

        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Your account has been deactivated.'];
        }

        $passwordValid = password_verify($password, $user['password_hash']);

        if (!$passwordValid) {
            $attempts = ($user['failed_login_attempts'] ?? 0) + 1;
            $lockedUntil = ($attempts >= 5) ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null;
            $upd = $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE user_id = ?");
            $upd->execute([$attempts, $lockedUntil, $user['user_id']]);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        $upd = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE user_id = ?");
        $upd->execute([$user['user_id']]);

        $_SESSION['user_id']       = $user['user_id'];
        $_SESSION['full_name']     = $user['full_name'];
        $_SESSION['username']      = $user['username'];
        $_SESSION['email']         = $user['email'];
        $_SESSION['role_id']       = $user['role_id'];
        $_SESSION['role_name']     = $user['role_name'];
        $_SESSION['warehouse_id']  = $user['warehouse_id'];
        $_SESSION['logged_in']     = true;
        $_SESSION['login_time']    = time();

        logAudit($user['user_id'], 'LOGIN', 'Auth', $user['user_id']);

        return ['success' => true, 'message' => 'Login successful.', 'user' => $user];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'System error. Please try demo passwords: Password123! or password'];
    }
}

function isLoggedIn(): bool {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function hasRole(string|array $roles): bool {
    if (!isLoggedIn()) return false;
    $userRole = $_SESSION['role_name'] ?? '';
    if (is_string($roles)) {
        return $userRole === $roles;
    }
    return in_array($userRole, $roles, true);
}

function requireRole(string|array $roles): void {
    requireLogin();
    if (!hasRole($roles)) {
        header('Location: dashboard.php?error=unauthorized');
        exit;
    }
}

function logoutUser(): void {
    if (isLoggedIn()) {
        try {
            logAudit($_SESSION['user_id'], 'LOGOUT', 'Auth', $_SESSION['user_id']);
        } catch (Exception $e) {}
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function logAudit(?int $userId, string $action, string $module, $recordId = null, $oldValue = null, $newValue = null): void {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, module, record_id, old_value, new_value, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $action,
            $module,
            $recordId,
            $oldValue ? json_encode($oldValue) : null,
            $newValue ? json_encode($newValue) : null,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    } catch (Exception $e) {
        // ignore
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'user_id'      => $_SESSION['user_id'],
        'full_name'    => $_SESSION['full_name'],
        'username'     => $_SESSION['username'],
        'email'        => $_SESSION['email'],
        'role_id'      => $_SESSION['role_id'],
        'role_name'    => $_SESSION['role_name'],
        'warehouse_id' => $_SESSION['warehouse_id'],
    ];
}