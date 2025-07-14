<?php

declare(strict_types=1);


function logoutUser(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION = [];

    $p = session_get_cookie_params();


    setcookie(session_name(), '', [
        'expires'  => time() - 42000,    
        'path'     => $p['path']     ?? '/',
        'domain'   => $p['domain']   ?? '',   
        'secure'   => $p['secure']   ?? false,  
        'httponly' => $p['httponly'] ?? true,
        'samesite' => $p['samesite'] ?? 'Lax', 
    ]);


    session_destroy();
}


logoutUser();

header('Location: login.php', true, 303);
exit;
