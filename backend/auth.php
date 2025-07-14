<?php

$ttl = 60 * 60 * 24 * 30;

// Keep the session file that long on the server
ini_set('session.gc_maxlifetime', (string)$ttl);

// Configure the cookie that stores the session ID
session_set_cookie_params([
    'lifetime' => $ttl,              
    'path'     => '/',              
    'domain'   => '',                
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Start (or resume) the session after the parameters above are in place
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ──────────────────────────────────────────────────────────────
// Helper functions
// ──────────────────────────────────────────────────────────────

/**
 * Regenerates the session ID once per session to defeat fixation.
 */
function secureSessionStart(): void
{
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}

/**
 * Ensures the user is logged in; otherwise redirects to login page.
 */
function checkLogin(): void
{
    secureSessionStart();

    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Gatekeeper for role-based pages.
 *
 * @param int $roleId  required role ID
 */
function requireRole(int $roleId): void
{
    checkLogin();

    if (($_SESSION['role_id'] ?? 0) !== $roleId) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied.';
        exit();
    }
}


function loginUser(array $user): void
{
    secureSessionStart();

    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['name']      = $user['name'];
    $_SESSION['role_id']   = (int)$user['role_id'];
    $_SESSION['role_name'] = $user['role_name'];

    // Extra defence: issue a fresh session ID at login
    session_regenerate_id(true);
}


function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
