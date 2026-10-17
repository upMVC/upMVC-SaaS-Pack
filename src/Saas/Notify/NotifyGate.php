<?php

namespace BitsHost\UpmvcSaas\Notify;

use App\Etc\Database;

/** Global kill switch for registered notification types. */
final class NotifyGate
{
    public static function isPaused(string $type): bool
    {
        if ($type === '' || !NotifyRegistry::isValid($type)) {
            return false;
        }
        try {
            $pdo = (new Database())->getConnection();
            $stmt = $pdo->prepare(
                'SELECT paused FROM notification_global WHERE type = :t LIMIT 1'
            );
            $stmt->execute([':t' => $type]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row && (int) $row['paused'] === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function setPaused(string $type, bool $paused, ?string $reason = null, ?int $by = null): void
    {
        if (!NotifyRegistry::isValid($type)) {
            throw new \InvalidArgumentException('Unknown notification type');
        }
        $pdo = (new Database())->getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO notification_global (type, paused, reason, updated_by)
             VALUES (:t, :p, :r, :by)
             ON DUPLICATE KEY UPDATE paused = VALUES(paused), reason = VALUES(reason), updated_by = VALUES(updated_by)"
        );
        $stmt->execute([
            ':t'  => $type,
            ':p'  => $paused ? 1 : 0,
            ':r'  => $reason,
            ':by' => $by,
        ]);
    }
}
