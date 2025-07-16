<?php
declare(strict_types=1);

$root = dirname(__DIR__);

// DB & dependencies
require_once __DIR__ . '/../backend/db.php'; 
require_once __DIR__ . '/../vendor/autoload.php'; 

use Backend\SettingsRepository;

/* Load .env if needed */
if (getenv('TG_BOT_TOKEN') === false) {
    $envPath = $root . '/.env';
    if (is_readable($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (($line = trim($line)) === '' || $line[0] === '#') continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k !== '' && getenv($k) === false) {
                putenv("$k=$v");
            }
        }
    }
}

$botToken = getenv('TG_BOT_TOKEN') ?: throw new RuntimeException('TG_BOT_TOKEN not set');
define('TG_SEND_API', "https://api.telegram.org/bot{$botToken}/sendMessage");

// send via Telegram API
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

// build default reminder text
function buildDefaultMessage(array $u, int $globalDays): string
{
    $days = $u['rating_window_days'] ?? $globalDays;
    if ($u['role_id'] === 3) {
        return "Reminder from SK-PM:\nYou have exactly the last {$days} day" . ($days > 1 ? 's' : '') . " of each month to submit your individual ratings.";
    }
    return "Reminder from SK-PM:\n• Month-start — enter your KPI plan.\n• Month-end — submit actuals and rate your team (window: {$days} day" . ($days > 1 ? 's' : '') . ").";
}

// main send function
function sendTelegramReminder(PDO $pdo, int $userId, ?string $message = null): array
{
    // get global default from settings
    $settingsRepo = new SettingsRepository($pdo);
    $globalDays   = (int)($settingsRepo->getSetting('evaluation_deadline_days') ?? 2);

    // fetch the user
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.name, u.telegram_chat_id, u.role_id,
               u.rating_window_days
        FROM users u
        WHERE u.user_id = :uid
          AND u.active  = 1
        LIMIT 1
    ");
    $stmt->execute(['uid' => $userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: throw new RuntimeException('User not found or inactive');

    if (empty($u['telegram_chat_id'])) {
        throw new RuntimeException('User hasn’t linked Telegram (chat_id missing)');
    }

    $text = $message ?: buildDefaultMessage($u, $globalDays);
    $apiResponse = tgSend((string)$u['telegram_chat_id'], $text);

    // log it
    $pdo->prepare('INSERT INTO reminders_log (user_id, type) VALUES (?, ?)')
        ->execute([$userId, 'telegram']);

    return ['ok' => true, 'user' => $u['name'], 'resp' => $apiResponse];
}

// CLI auto-mode
if (PHP_SAPI === 'cli' && in_array('--auto', $argv, true)) {
    if (date('j') !== '1') exit;
    $ids = $pdo->query("SELECT user_id FROM users WHERE active = 1 AND telegram_chat_id IS NOT NULL")
               ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        try {
            sendTelegramReminder($pdo, (int)$id);
        } catch (Throwable $e) {
            error_log("Auto-reminder failed for user {$id}: {$e->getMessage()}");
        }
    }
}
