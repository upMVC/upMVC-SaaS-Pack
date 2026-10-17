<?php

namespace BitsHost\UpmvcSaas\Notify;

/**
 * App-owned notification type catalog.
 * Pack owns gate / mute / dispatch; products register types at boot.
 *
 * Example type shape:
 * [
 *   'key' => 'invoice_paid', 'zone' => 'tenant', 'kind' => 'instant',
 *   'audience' => 'roles', 'roles' => ['tenant_owner'], 'enabled' => true,
 *   'label' => '…', 'help' => '…',  // prefer i18n keys in UI
 * ]
 */
final class NotifyRegistry
{
    /** @var array<string,array<string,mixed>> */
    private static array $types = [];

    /** @param array<string,mixed> $type */
    public static function register(array $type): void
    {
        $key = (string) ($type['key'] ?? '');
        if ($key === '' || !preg_match('/^[a-z][a-z0-9_]{1,62}$/', $key)) {
            throw new \InvalidArgumentException('Invalid notification type key');
        }
        $type['zone'] = $type['zone'] ?? 'tenant';
        $type['kind'] = $type['kind'] ?? 'instant';
        self::$types[$key] = $type;
    }

    /** @param list<array<string,mixed>> $types */
    public static function registerMany(array $types): void
    {
        foreach ($types as $t) {
            self::register($t);
        }
    }

    public static function clear(): void
    {
        self::$types = [];
    }

    public static function isValid(string $type): bool
    {
        return isset(self::$types[$type]);
    }

    /** @return array<string,mixed>|null */
    public static function get(string $type): ?array
    {
        return self::$types[$type] ?? null;
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return array_values(self::$types);
    }

    /** @return list<array<string,mixed>> */
    public static function forZone(string $zone): array
    {
        return array_values(array_filter(
            self::$types,
            static fn(array $t): bool => ($t['zone'] ?? '') === $zone
        ));
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::$types);
    }
}
