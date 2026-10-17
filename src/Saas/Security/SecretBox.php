<?php

namespace BitsHost\UpmvcSaas\Security;

use App\Etc\Config\Environment;

/** AES-256-GCM seal for SMTP passwords (and similar) at rest. */
class SecretBox
{
    private const PREFIX = 'v1:';

    public static function seal(string $plain): string
    {
        $key = self::key();
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            throw new \RuntimeException('Failed to encrypt secret');
        }
        return self::PREFIX . base64_encode($iv . $tag . $ct);
    }

    public static function unseal(?string $blob): ?string
    {
        if ($blob === null || $blob === '') {
            return null;
        }
        if (!str_starts_with($blob, self::PREFIX)) {
            return $blob; // legacy plaintext fallback — migrate on next save
        }
        $raw = base64_decode(substr($blob, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }
        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct  = substr($raw, 28);
        foreach (self::keyCandidates() as $key) {
            $pt = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($pt !== false) {
                return $pt;
            }
        }
        return null;
    }

    /** Current seal key (new installs / re-saves). */
    private static function key(): string
    {
        return self::keyCandidates()[0];
    }

    /**
     * Prefer pack pepper; also accept myCMMS-PHP legacy `|smtp-password-v1`
     * so Harvest path installs can read existing SMTP rows without re-entry.
     *
     * @return list<string>
     */
    private static function keyCandidates(): array
    {
        $src = (string) (
            getenv('APP_KEY')
            ?: ($_ENV['APP_KEY'] ?? '')
            ?: getenv('JWT_SECRET')
            ?: ($_ENV['JWT_SECRET'] ?? '')
            ?: Environment::get('APP_KEY', '')
            ?: Environment::get('JWT_SECRET', '')
            ?: 'upmvc-saas-dev-insecure-key-change-me'
        );
        $keys = [
            hash('sha256', $src . '|saas-secret-v1', true),
            hash('sha256', $src . '|smtp-password-v1', true), // myCMMS-PHP legacy
        ];
        // Also try insecure defaults if APP_KEY differed historically
        $keys[] = hash('sha256', 'upmvc-saas-dev-insecure-key-change-me|saas-secret-v1', true);
        $keys[] = hash('sha256', 'mycmms-dev-insecure-key-change-me|smtp-password-v1', true);
        return array_values(array_unique($keys));
    }
}
