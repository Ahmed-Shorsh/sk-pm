<?php

ini_set('display_errors', 0);                                   // never show errors to Telegram
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

define('WEBHOOK_ROOT', __DIR__);
define('LOG_FILE',      WEBHOOK_ROOT . '/tele_debug.log');


function tlog(string $label, $data = ''): void
{
    $line = sprintf(
        "%s %s: %s\n",
        date('c'),
        $label,
        is_scalar($data) ? $data : json_encode($data, JSON_UNESCAPED_SLASHES)
    );
    @file_put_contents(LOG_FILE, $line, FILE_APPEND);
}


require_once __DIR__ . '/../backend/db.php';   // provides $pdo (PDO instance)


$rootPath = dirname(__DIR__);                  // project root
$envPath  = $rootPath . '/.env';

if (getenv('TG_BOT_TOKEN') === false || getenv('TG_BOT_USERNAME') === false) {
    if (is_readable($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (!str_contains($line, '='))        continue;
            [$k, $v] = explode('=', $line, 2);
            if (getenv($k) === false) putenv(trim($k) . '=' . trim($v));
        }
    }
}

$botToken    = getenv('TG_BOT_TOKEN')    ?: '';
$botUsername = getenv('TG_BOT_USERNAME') ?: '';

tlog('BOOT', [
    'token_set' => $botToken ? 'yes' : 'no',
    'username'  => $botUsername ?: 'N/A',
]);

if ($botToken === '' || $botUsername === '') {
    tlog('ERROR', 'Bot token or username missing – abort');
    http_response_code(500);
    exit('Configuration error');
}


$raw = file_get_contents('php://input');
tlog('RAW_UPDATE', $raw);

$update = json_decode($raw, true);
if (!$update || !isset($update['message'])) {
    tlog('IGNORED', 'No message field');
    echo 'OK';
    exit;
}

$message = $update['message'];
$chatId  = $message['chat']['id'] ?? null;
$text    = trim($message['text'] ?? '');

tlog('PARSED', ['chat' => $chatId, 'text' => $text]);


if (!$chatId || strpos($text, '/start') !== 0) {
    tlog('IGNORED', 'Not a /start command');
    echo 'OK';
    exit;
}

$parts = explode(' ', $text, 2);
$token = $parts[1] ?? '';
tlog('TOKEN', $token ?: 'EMPTY');

if ($token === '') {
    $reply = '⚠️ No verification token found. Please click the sign-up link again.';
    goto send_reply;
}


try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare('SELECT user_id, verified FROM user_telegram WHERE token = :tok');
    $stmt->execute([':tok' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    tlog('DB_LOOKUP', $row ?: 'not_found');

    if (!$row) {
        $reply = '⚠️ Verification token invalid or expired.';
    } elseif ((int)$row['verified'] === 1) {
        $reply = 'ℹ️ This Telegram account is already linked to your SK-PM account.';
    } else {
        // Transaction: update both tables
        $pdo->beginTransaction();

        $upd1 = $pdo->prepare(
            'UPDATE user_telegram SET telegram_chat_id = :cid, verified = 1 WHERE user_id = :uid'
        );
        $upd1->execute([':cid' => $chatId, ':uid' => $row['user_id']]);

        $upd2 = $pdo->prepare(
            'UPDATE users SET telegram_chat_id = :cid WHERE user_id = :uid'
        );
        $upd2->execute([':cid' => $chatId, ':uid' => $row['user_id']]);

        $pdo->commit();
        tlog('DB_UPDATE', "linked user_id={$row['user_id']} to chat_id={$chatId}");

        $reply = '✅ You have successfully linked your SK-PM account to Telegram. Thank you!';
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    tlog('ERROR', $e->getMessage());
    $reply = '⚠️ An internal error occurred. Please try again later.';
}


send_reply:
try {
    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $payload = [
        'chat_id'    => $chatId,
        'text'       => $reply,
        'parse_mode' => 'Markdown',
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);

    tlog('SEND_RESULT', $curlErr ?: $response);
} catch (Exception $e) {
    tlog('SEND_ERROR', $e->getMessage());
}

echo 'OK';
?>
