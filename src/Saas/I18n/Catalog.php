<?php

namespace BitsHost\UpmvcSaas\I18n;

use App\Etc\Database;

/** File catalogs + DB overlays (dotted keys). */
final class Catalog
{
    /**
     * @param array<string,mixed> $catalog
     * @param array<string,string> $overlays
     * @return array<string,mixed>
     */
    public static function applyOverlays(array $catalog, array $overlays): array
    {
        foreach ($overlays as $path => $value) {
            $parts = explode('.', (string) $path);
            if ($parts === [] || $parts[0] === '') {
                continue;
            }
            $ref = &$catalog;
            $last = count($parts) - 1;
            foreach ($parts as $i => $p) {
                if ($i === $last) {
                    $ref[$p] = $value;
                } else {
                    if (!isset($ref[$p]) || !is_array($ref[$p])) {
                        $ref[$p] = [];
                    }
                    $ref = &$ref[$p];
                }
            }
            unset($ref);
        }
        return $catalog;
    }

    /** @return array<string,string> */
    public static function overlayMap(string $locale): array
    {
        try {
            $pdo = (new Database())->getConnection();
            $stmt = $pdo->prepare(
                'SELECT msg_key, msg_value FROM i18n_overlays WHERE locale = :l'
            );
            $stmt->execute([':l' => $locale]);
            $map = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $map[(string) $row['msg_key']] = (string) $row['msg_value'];
            }
            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<string> $locales
     * @return array{locale:string,source:string,overlays:int,catalog:array,version:string}
     */
    public static function load(string $locale, string $langDir, array $locales = ['en', 'ro'], bool $mergeOverlays = true): array
    {
        $locale = strtolower(trim($locale));
        if (!in_array($locale, $locales, true)) {
            throw new \InvalidArgumentException('Unsupported locale');
        }
        $path = rtrim($langDir, '/\\') . DIRECTORY_SEPARATOR . $locale . '.json';
        if (!is_readable($path)) {
            throw new \RuntimeException('Language file not found');
        }
        $catalog = json_decode((string) file_get_contents($path), true);
        if (!is_array($catalog)) {
            throw new \RuntimeException('Invalid language catalog');
        }
        $overlays = $mergeOverlays ? self::overlayMap($locale) : [];
        if ($overlays !== []) {
            $catalog = self::applyOverlays($catalog, $overlays);
        }
        return [
            'locale'   => $locale,
            'source'   => $overlays === [] ? 'file' : 'file+overlays',
            'overlays' => count($overlays),
            'catalog'  => $catalog,
            'version'  => (string) filemtime($path) . ':' . count($overlays),
        ];
    }
}
