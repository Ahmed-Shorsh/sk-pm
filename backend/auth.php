<?php


declare(strict_types=1);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function secureSessionStart(): void
{
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}

function checkLogin(): void
{
    secureSessionStart();

    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

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
    $_SESSION['role_id']   = (int) $user['role_id'];
    $_SESSION['role_name'] = $user['role_name'];

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
            $params['path']     ?? '/',
            $params['domain']   ?? '',
            $params['secure']   ?? false,
            $params['httponly'] ?? true
        );
    }

    session_destroy();
}
