<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/backend/db.php';
// scripts/telegram_reminder.php  – top of file
require_once dirname(__DIR__) . '../vendor/autoload.php';  

/*--------------------------------------------------
 | 1.  Configuration
 *--------------------------------------------------*/
if (getenv('TG_BOT_TOKEN') === false) {
    $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';  // project root
    if (is_readable($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k !== '' && getenv($k) === false) putenv("$k=$v");
        }
    }
}

$botToken = getenv('TG_BOT_TOKEN');
if (!$botToken) {
    throw new RuntimeException('Bot token not set in .env (TG_BOT_TOKEN).');
}
define('TG_SEND_API', 'https://api.telegram.org/bot' . $botToken . '/sendMessage');

/*--------------------------------------------------
 | 2.  Low-level API helper with verbose diagnostics
 *--------------------------------------------------*/
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
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Network error while contacting Telegram: ' . $curlErr);
    }

    $resp = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    if (!($resp['ok'] ?? false)) {
        $code = $resp['error_code'] ?? 'n/a';
        $desc = $resp['description'] ?? 'no description';
        throw new RuntimeException("Telegram API error {$code}: {$desc}");
    }

    return $resp;   // full Telegram response, including message_id etc.
}

/*--------------------------------------------------
 | 3.  Build a default reminder body
 *--------------------------------------------------*/
function buildDefaultMessage(array $u): string
{
    $days = $u['rating_window_days'] ?? 2;
    return $u['role_id'] == 3
        ? "Reminder from SK-PM:\nYou have exactly the last {$days} day" . ($days > 1 ? 's' : '') . " of each month to submit your individual ratings."
        : "Reminder from SK-PM:\n• Month-start — enter your KPI plan.\n• Month-end — submit actuals and rate your team (window: {$days} day" . ($days > 1 ? 's' : '') . ").";
}

/*--------------------------------------------------
 | 4.  Public helper – all diagnostics bubbled up
 *--------------------------------------------------*/
function sendTelegramReminder(PDO $pdo, int $userId, ?string $message = null): array
{
    // fetch user with chat-ID
    $q = $pdo->prepare("
        SELECT  u.user_id,
                u.name,
                u.telegram_chat_id,
                u.role_id,
                COALESCE(u.rating_window_days, 2) AS rating_window_days
        FROM    users u
        WHERE   u.user_id = :uid
          AND   u.active  = 1
        LIMIT 1
    ");
    $q->execute(['uid' => $userId]);
    $u = $q->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        throw new RuntimeException('User not found or inactive.');
    }
    if (!$u['telegram_chat_id']) {
        throw new RuntimeException('User has not linked Telegram yet (chat_id missing). Ask them to open the bot and send /start.');
    }

    $text = $message ?: buildDefaultMessage($u);

    // will throw RuntimeException on any failure
    $apiResponse = tgSend((string)$u['telegram_chat_id'], $text);

    // record in reminders_log (optional)
    $stmt = $pdo->prepare('INSERT INTO reminders_log (user_id, type) VALUES (?, ?)');
    $stmt->execute([$userId, 'telegram']);

    return [
        'ok'        => true,
        'user_name' => $u['name'],
        'telegram'  => $apiResponse,
    ];
}

/*--------------------------------------------------
 | 5.  CLI auto-mode (php telegram_reminder.php --auto)
 *--------------------------------------------------*/
if (PHP_SAPI === 'cli' && in_array('--auto', $argv, true)) {
    // example: run nightly on the 1st of each month
    if (date('j') !== '1') exit;

    $ids = $pdo->query("SELECT user_id FROM users WHERE active = 1 AND telegram_chat_id IS NOT NULL")
               ->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $id) {
        try {
            sendTelegramReminder($pdo, (int)$id);
        } catch (Throwable $e) {
            error_log("Auto-reminder for user {$id} failed: {$e->getMessage()}");
        }
    }
}
