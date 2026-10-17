<?php

namespace App\Modules\Api\Modules\I18n;

use BitsHost\UpmvcSaas\Http\SaasApiController;
use BitsHost\UpmvcSaas\I18n\Catalog;
use BitsHost\UpmvcSaas\Ops\PlatformSettings;

class Controller extends SaasApiController
{
    /** @var list<string> */
    private const LOCALES = ['en', 'ro'];

    /** GET /api/i18n */
    public function index(): never
    {
        $this->success([
            'locales' => self::LOCALES,
            'default' => PlatformSettings::get('default_locale', 'en') ?: 'en',
            'source'  => 'file+overlays',
        ]);
    }

    /** GET /api/i18n/{locale} */
    public function show(): never
    {
        $locale = strtolower(trim((string) ($_GET['locale'] ?? '')));
        try {
            $loaded = null;
            $last = null;
            foreach ($this->langDirs() as $dir) {
                try {
                    $loaded = Catalog::load($locale, $dir, self::LOCALES);
                    break;
                } catch (\Throwable $e) {
                    $last = $e;
                }
            }
            if ($loaded === null) {
                throw $last ?? new \RuntimeException('Language file not found');
            }
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 404);
        }

        $this->success(array_merge($loaded, [
            'locales' => self::LOCALES,
            'default' => PlatformSettings::get('default_locale', 'en') ?: 'en',
        ]));
    }

    /** @return list<string> */
    private function langDirs(): array
    {
        $dirs = [];
        if (defined('SITEPATH')) {
            $dirs[] = rtrim((string) SITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'lang';
        }
        // Pack root: …/src/Modules/Api/Modules/I18n → 5 levels up
        $packRoot = dirname(__DIR__, 5);
        $dirs[] = $packRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'lang';
        // When pack lives under vendor/, prefer the consuming app's public/lang
        $vendorParent = dirname($packRoot, 3); // …/vendor/bitshost/upmvc-saas-pack → project
        if (is_dir($vendorParent . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'lang')) {
            array_unshift($dirs, $vendorParent . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'lang');
        }
        return array_values(array_unique(array_filter($dirs, 'is_dir')));
    }
}
