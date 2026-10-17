<?php

namespace BitsHost\UpmvcSaas\Security;

use App\Etc\Config\Environment;

/** AES-256-GCM seal for SMTP passwords (and similar) at rest. */
final class SecretBox
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
        $pt  = openssl_decrypt($ct, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $pt === false ? null : $pt;
    }

    private static function key(): string
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
        return hash('sha256', $src . '|saas-secret-v1', true);
    }
}
