<?php
declare(strict_types=1);

namespace Backend;

use PDO;
use PDOException;

/* bring in $pdo from backend/db.php (creates the PDO handle) */
require_once __DIR__ . '/db.php';

final class SettingsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;   // assign once, inside the constructor
    }

    /** Return value or null if key missing */
    public function getSetting(string $key): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT setting_value FROM settings WHERE setting_key = :k'
        );
        $stmt->execute([':k' => $key]);
        $v = $stmt->fetchColumn();
        return ($v === false) ? null : $v;
    }

    /** All settings as [key => value] */
    public function getAllSettings(): array
    {
        $stmt = $this->pdo->query(
            'SELECT setting_key, setting_value FROM settings'
        );
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    }

    /** Insert or update one key */
    public function updateSetting(string $key, string $value): bool
    {
        $sql = 'INSERT INTO settings (setting_key, setting_value)
                VALUES (:k, :v)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
        return $this->pdo->prepare($sql)->execute([':k' => $key, ':v' => $value]);
    }

    /** Insert / update many keys atomically */
    public function updateSettings(array $settings): bool
    {
        try {
            $this->pdo->beginTransaction();
            $sql  = 'INSERT INTO settings (setting_key, setting_value)
                     VALUES (:k, :v)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
            $stmt = $this->pdo->prepare($sql);
            foreach ($settings as $k => $v) {
                $stmt->execute([':k' => $k, ':v' => $v]);
            }
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}
