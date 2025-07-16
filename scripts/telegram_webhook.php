<?php

require_once __DIR__ . '/../backend/db.php';
$root = dirname(__DIR__);

if (getenv('TG_BOT_TOKEN') === false) {
    $envPath = $root . '/.env';
    if (is_readable($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line[0] === '#') continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k !== '' && getenv($k) === false) putenv("$k=$v");
        }
    }
}
$botToken = getenv('TG_BOT_TOKEN');

// Read Telegram update
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) {
    echo "OK";  // no data
    exit;
}
if (isset($update['message'])) {
    $message = $update['message'];
    $text   = $message['text'] ?? '';
    $chatId = $message['chat']['id'] ?? null;
    if ($chatId && isset($text) && strpos($text, '/start') === 0) {
        // Extract token after "/start"
        $parts = explode(' ', $text, 2);
        $token = $parts[1] ?? '';
        if ($token !== '') {
            // Look up the token in user_telegram
            $stmt = $pdo->prepare("SELECT user_id, verified FROM user_telegram WHERE token = ?");
            $stmt->execute([$token]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['verified'] == 0) {
                    // Mark as verified: store chat_id
                    $pdo->prepare("UPDATE user_telegram SET telegram_chat_id = ?, verified = 1 WHERE user_id = ?")
                        ->execute([$chatId, $row['user_id']]);
                    $pdo->prepare("UPDATE users SET telegram_chat_id = ? WHERE user_id = ?")
                        ->execute([$chatId, $row['user_id']]);
                    // Send confirmation message to user
                    $msg = "✅ You have successfully linked your SK-PM account to Telegram. Thank you!";
                } else {
                    // Already verified (user clicked /start again)
                    $msg = "ℹ️ This Telegram account is already linked to your SK-PM account.";
                }
            } else {
                // Token not found (possibly expired or invalid)
                $msg = "⚠️ Verification token invalid or expired.";
            }
            // Reply to the user (confirmation or error)
            if (isset($msg)) {
                $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
                $params = ['chat_id' => $chatId, 'text' => $msg];
                // Using curl to POST to Telegram API
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_exec($ch);
                curl_close($ch);
            }
        }
    }
}
echo "OK";
