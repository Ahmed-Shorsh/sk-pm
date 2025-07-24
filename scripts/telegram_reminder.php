<?php


declare(strict_types=1);

$root = dirname(__DIR__);

/* ───── Autoload + DB ───── */
require_once $root . '/vendor/autoload.php';
require_once $root . '/backend/db.php';          

use Backend\SettingsRepository;

/* ───── Load .env (if TG_BOT_TOKEN not already set) ───── */
if (getenv('TG_BOT_TOKEN') === false) {
    $env = $root . '/.env';
    if (is_readable($env)) {
        foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line[0] === '#' || !str_contains($line, '=')) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k !== '' && getenv($k) === false) putenv("$k=$v");
        }
    }
}

$botToken = getenv('TG_BOT_TOKEN')
    ?: throw new RuntimeException('TG_BOT_TOKEN missing in env/.env');
define('TG_SEND_API', "https://api.telegram.org/bot{$botToken}/sendMessage");

/* ───── Low-level Telegram call ───── */
function tgSend(string $chatId, string $text): array
{
    $ch = curl_init(TG_SEND_API);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]),
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException("Network error: {$err}");
    }

    $resp = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (empty($resp['ok'])) {
        $code = $resp['error_code'] ?? 'n/a';
        $desc = $resp['description'] ?? 'no description';
        throw new RuntimeException("Telegram API error {$code}: {$desc}");
    }

    return $resp;
}

/* ───── Default message helper (fallback only) ───── */
function buildDefaultMessage(array $user, int $globalDays): string
{
    $days = $user['rating_window_days'] ?? $globalDays;

    if ((int)$user['role_id'] === 3) {          // Employee
        return "Reminder from SK-PM:\nYou have exactly the last {$days} "
             . "day" . ($days > 1 ? 's' : '')
             . " of each month to submit your individual ratings.";
    }

    /* Manager / Admin */
    return "Reminder from SK-PM:\n• Month-start — enter KPI plan.\n"
         . "• Month-end — submit actuals & rate your team "
         . "(window: {$days} day" . ($days > 1 ? 's' : '') . ").";
}

/* ───── Public helper: used by controllers ───── */
function sendTelegramReminder(
    PDO $pdo,
    int $userId,
    ?string $message = null
): bool
{
    /* Get global default */
    $settingsRepo = new SettingsRepository($pdo);
    $globalDays   = (int)($settingsRepo->getSetting('evaluation_deadline_days') ?? 2);

    /* Fetch user (includes chat-id & role) */
    $stmt = $pdo->prepare("
        SELECT user_id, name, telegram_chat_id, role_id, rating_window_days
        FROM   users
        WHERE  user_id = :id AND active = 1
        LIMIT  1
    ");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC)
        ?: throw new RuntimeException('User not found or inactive');

    if (empty($user['telegram_chat_id'])) {
        throw new RuntimeException('User has not linked Telegram');
    }

    $text = $message ?: buildDefaultMessage($user, $globalDays);

    /* Send */
    tgSend((string)$user['telegram_chat_id'], $text);

    /* Log (optional) */
    $pdo->prepare('INSERT INTO reminders_log (user_id, type) VALUES (?, ?)')
        ->execute([$userId, 'telegram']);

    return true;                      // success, no output
}

/* ───── CLI automation: php … --auto ───── */
if (PHP_SAPI === 'cli' && in_array('--auto', $argv, true)) {
    /* Example policy: run on the first of every month */
    if (date('j') !== '1') exit(0);

    $ids = $pdo->query("
        SELECT user_id
        FROM   users
        WHERE  active = 1
        AND    telegram_chat_id IS NOT NULL
    ")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $id) {
        try {
            sendTelegramReminder($pdo, (int)$id);
        } catch (Throwable $e) {
            /* Log to PHP error log, but stay silent on STDOUT */
            error_log("Auto-reminder failed for user {$id}: {$e->getMessage()}");
        }
    }
}
