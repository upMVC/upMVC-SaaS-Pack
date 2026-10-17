<?php

namespace BitsHost\UpmvcSaas\Ops;

use App\Etc\Database;

final class PlatformSettings
{
    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            $pdo = (new Database())->getConnection();
            $stmt = $pdo->prepare(
                'SELECT setting_value FROM platform_settings WHERE setting_key = :k LIMIT 1'
            );
            $stmt->execute([':k' => $key]);
            $v = $stmt->fetchColumn();
            return $v === false ? $default : (string) $v;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function set(string $key, string $value): void
    {
        $pdo = (new Database())->getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO platform_settings (setting_key, setting_value)
             VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmt->execute([':k' => $key, ':v' => $value]);
    }

    /** @return array{signup_enabled:bool,maintenance_mode:bool} */
    public static function flags(): array
    {
        return [
            'signup_enabled'    => self::get('signup_enabled', '1') !== '0',
            'maintenance_mode'  => self::get('maintenance_mode', '0') === '1',
        ];
    }

    /** @param array{signup_enabled?:bool,maintenance_mode?:bool} $flags */
    public static function saveFlags(array $flags): array
    {
        if (array_key_exists('signup_enabled', $flags)) {
            self::set('signup_enabled', !empty($flags['signup_enabled']) ? '1' : '0');
        }
        if (array_key_exists('maintenance_mode', $flags)) {
            self::set('maintenance_mode', !empty($flags['maintenance_mode']) ? '1' : '0');
        }
        return self::flags();
    }
}
