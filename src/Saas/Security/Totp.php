<?php

namespace BitsHost\UpmvcSaas\Security;

use App\Etc\Config\Environment;

/**
 * RFC 6238 TOTP (SHA-1, 6 digits, 30s) — Google / Microsoft Authenticator compatible.
 * Secrets are sealed with JWT_SECRET so they are not stored in plaintext.
 */
class Totp
{
    private const PERIOD = 30;
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const SEAL_PREFIX = 'v1:';

    public static function newSecret(): string
    {
        $bytes = random_bytes(20);
        $bits = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        for ($i = 0; $i + 5 <= strlen($bits); $i += 5) {
            $out .= self::B32[bindec(substr($bits, $i, 5))];
        }
        return $out;
    }

    public static function otpauthUri(string $secret, string $account, string $issuer = 'App'): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        $q = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => 6,
            'period'    => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
        return "otpauth://totp/{$label}?{$q}";
    }

    public static function stepAt(?int $ms = null): int
    {
        $ms ??= (int) floor(microtime(true) * 1000);
        return (int) floor($ms / 1000 / self::PERIOD);
    }

    public static function codeAt(string $secret, ?int $ms = null): string
    {
        return self::hotp($secret, self::stepAt($ms));
    }

    /**
     * @return int|null matched time-step, or null if invalid
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null): ?int
    {
        $c = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $c)) {
            return null;
        }
        $now = self::stepAt();
        foreach ([$now - 1, $now, $now + 1] as $s) {
            if ($lastStep !== null && $s <= $lastStep) {
                continue;
            }
            $expect = self::hotp($secret, $s);
            if (hash_equals($expect, $c)) {
                return $s;
            }
        }
        return null;
    }

    public static function seal(string $secret): string
    {
        $key = self::sealKey();
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('Failed to seal TOTP secret');
        }
        return self::SEAL_PREFIX . base64_encode($iv . $cipher);
    }

    public static function unseal(string $sealed): ?string
    {
        if (!str_starts_with($sealed, self::SEAL_PREFIX)) {
            return null;
        }
        $raw = base64_decode(substr($sealed, strlen(self::SEAL_PREFIX)), true);
        if ($raw === false || strlen($raw) < 17) {
            return null;
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        foreach (self::sealKeyCandidates() as $key) {
            $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($plain !== false && $plain !== '') {
                return $plain;
            }
        }
        return null;
    }

    private static function sealKey(): string
    {
        return self::sealKeyCandidates()[0];
    }

    /** @return list<string> */
    private static function sealKeyCandidates(): array
    {
        // Prefer process env so unit tests / CLI do not force-load .env
        $secret = (string) (getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? '') ?: Environment::get('JWT_SECRET', ''));
        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET is required to seal TOTP secrets');
        }
        return [
            hash('sha256', 'upmvc-saas-totp|' . $secret, true),
            hash('sha256', 'mycmms-totp|' . $secret, true), // myCMMS-PHP legacy
        ];
    }

    private static function hotp(string $secret, int $counter): string
    {
        $key = self::decodeBase32($secret);
        // 8-byte big-endian counter (high 32 bits always 0 for practical steps)
        $msg = pack('NN', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
        $h = hash_hmac('sha1', $msg, $key, true);
        $o = ord($h[strlen($h) - 1]) & 0x0f;
        $bin = unpack('N', substr($h, $o, 4));
        $code = ($bin[1] & 0x7fffffff) % 1000000;
        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    private static function decodeBase32(string $secret): string
    {
        $secret = strtoupper(rtrim($secret, '='));
        $bits = '';
        for ($i = 0, $n = strlen($secret); $i < $n; $i++) {
            $idx = strpos(self::B32, $secret[$i]);
            if ($idx === false) {
                continue;
            }
            $bits .= str_pad(decbin($idx), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $bytes .= chr(bindec(substr($bits, $i, 8)));
        }
        return $bytes;
    }
}
