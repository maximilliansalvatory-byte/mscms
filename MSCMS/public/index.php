<?php
/**
 * MSCMS - Login Page
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Provide a safe fallback for redirect if not defined in included files
if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        // Ensure no prior output before sending header
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }
        echo "<script>location.href='" . addslashes($url) . "'</script>";
        exit;
    }
}

// Ensure auth helper exists before calling to avoid undefined function error
if (function_exists('isLoggedIn') && isLoggedIn()) {
    if (function_exists('redirect')) {
        redirect('dashboard.php');
    }
}

// Provide a simple escaping helper if not defined in included files
if (!function_exists('e')) {
    function e(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Provide a safe fallback if loginUser is not defined in included files
if (!function_exists('loginUser')) {
    /**
     * Fallback loginUser implementation to prevent undefined function error.
     * Returns a failure result indicating the real implementation is missing.
     */
    function loginUser($username, $password)
    {
        return [
            'success' => false,
            'message' => 'Login service unavailable. Please contact the administrator.'
        ];
    }
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $result = loginUser($username, $password);
        if ($result['success']) {
            redirect('dashboard.php');
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MSCMS | Medical Supply Chain Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f766e 0%, #134e4a 50%, #042f2e 100%);
            min-height: 100vh;
        }
        .login-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-white shadow-lg mb-4">
                <i class="fas fa-pills text-4xl text-teal-700"></i>
            </div>
            <h1 class="text-3xl font-bold text-white tracking-tight">MSCMS</h1>
            <p class="text-teal-100 mt-1">Medical Supply Chain Management System</p>
        </div>

        <div class="login-card rounded-2xl shadow-2xl p-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6 text-center">Sign In</h2>

            <?php if ($error): ?>
                <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-start gap-2">
                    <i class="fas fa-exclamation-circle mt-0.5"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                        Username or Email
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" id="username" name="username" value="<?= e($username) ?>"
                               class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition"
                               placeholder="Enter username" required autofocus>
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        Password
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password"
                               class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition"
                               placeholder="Enter password" required>
                    </div>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 text-gray-600">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        Remember me
                    </label>
                    <a href="#" class="text-teal-600 hover:text-teal-800 font-medium">Forgot password?</a>
                </div>

                <button type="submit"
                        class="w-full bg-teal-700 hover:bg-teal-800 text-white font-semibold py-2.5 rounded-lg transition duration-200 flex items-center justify-center gap-2 shadow-md">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-gray-200 text-center text-xs text-gray-500">
                <p>GateWay to MSCMS</p>
                <p class="mt-1 font-mono text-gray-700">Secure Login</p>
            </div>
        </div>

        <p class="text-center text-teal-100 text-sm mt-6">
            &copy; <?= date('Y') ?> Medical Supply Chain Management System
        </p>
    </div>
</body>
</html>