<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/auth.php';

logoutUser();

header('Location: login.php', true, 303);
exit;
