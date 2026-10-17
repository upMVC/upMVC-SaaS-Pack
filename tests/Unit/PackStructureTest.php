<?php

declare(strict_types=1);

namespace BitsHost\UpmvcSaas\Tests\Unit;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/**
 * Structural guards.
 *
 * Module discovery in upMVC is filesystem-driven: InitModsImproved globs three
 * fixed patterns, and a module at the wrong depth is skipped with no error at
 * all — the routes simply never exist. Nothing else in the suite would notice,
 * so these tests assert the layout the kernel actually scans.
 */
final class PackStructureTest extends TestCase
{
    private const KERNEL_GLOBS = [
        '/*/Routes/Routes.php',
        '/*/Modules/*/Routes/Routes.php',
        '/*/Modules/*/Modules/*/Routes/Routes.php',
    ];

    private static function packRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /** Every Routes.php that ships in the pack. */
    private static function shippedRouteFiles(): array
    {
        $found = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::packRoot() . '/src/Modules', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if ($file->getFilename() === 'Routes.php') {
                $found[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        sort($found);
        return $found;
    }

    /** What the kernel would actually discover. */
    private static function discoverableRouteFiles(): array
    {
        $base  = self::packRoot() . '/src/Modules';
        $found = [];

        foreach (self::KERNEL_GLOBS as $pattern) {
            foreach (glob($base . $pattern) ?: [] as $file) {
                $found[] = str_replace('\\', '/', $file);
            }
        }

        sort($found);
        return $found;
    }

    public function testPackShipsModules(): void
    {
        $this->assertNotEmpty(self::shippedRouteFiles(), 'The pack must ship at least one module');
    }

    public function testEveryShippedModuleIsDiscoverableByTheKernel(): void
    {
        $undiscoverable = array_diff(self::shippedRouteFiles(), self::discoverableRouteFiles());

        $this->assertSame(
            [],
            array_values($undiscoverable),
            "These modules sit at a depth the kernel does not scan, so their routes "
            . "would silently never register. Nested modules need the literal "
            . "'Modules' segment: src/Modules/Api/Modules/{Name}/Routes/Routes.php"
        );
    }

    #[Depends('testPackShipsModules')]
    public function testEveryRoutesClassMatchesItsPsr4NamespaceAndIsCallable(): void
    {
        foreach (self::shippedRouteFiles() as $file) {
            $relative = ltrim(str_replace(
                str_replace('\\', '/', self::packRoot() . '/src/Modules'),
                '',
                $file
            ), '/');

            $class = 'App\\Modules\\' . str_replace('/', '\\', substr($relative, 0, -4));

            $this->assertTrue(
                class_exists($class),
                "PSR-4 expects $class in $file — namespace and directory must agree"
            );
            $this->assertTrue(
                method_exists($class, 'routes'),
                "$class must expose routes(\$router) for the kernel to call"
            );
        }
    }

    public function testMigrationsAreShippedAndNonEmpty(): void
    {
        $files = glob(self::packRoot() . '/database/migrations/*.sql') ?: [];

        $this->assertNotEmpty($files, 'The pack must ship its own migrations');

        foreach ($files as $file) {
            $this->assertNotEmpty(
                trim((string) file_get_contents($file)),
                basename($file) . ' is empty — it would be recorded as applied while doing nothing'
            );
        }
    }

    public function testMigrationsCreateTheSaasTables(): void
    {
        $sql = '';
        foreach (glob(self::packRoot() . '/database/migrations/*.sql') ?: [] as $file) {
            $sql .= (string) file_get_contents($file);
        }

        foreach (['plans', 'tenants', 'refresh_tokens'] as $table) {
            $this->assertMatchesRegularExpression(
                '/CREATE TABLE (IF NOT EXISTS )?`?' . $table . '`?/i',
                $sql,
                "Migrations must create the $table table"
            );
        }

        // Harvest ops layer (002)
        foreach (['platform_settings', 'smtp_settings', 'email_log', 'audit_log', 'i18n_overlays'] as $table) {
            $this->assertMatchesRegularExpression(
                '/CREATE TABLE (IF NOT EXISTS )?`?' . $table . '`?/i',
                $sql,
                "Ops migrations must create the $table table"
            );
        }
    }

    public function testSchemaAndSeedShipForNonMigrationInstalls(): void
    {
        $this->assertFileExists(self::packRoot() . '/database/schema.sql');
        $this->assertFileExists(self::packRoot() . '/database/seed.sql');
    }

    public function testSeedDoesNotWipeTablesWholesale(): void
    {
        $seed = (string) file_get_contents(self::packRoot() . '/database/seed.sql');

        // An unscoped DELETE would destroy real tenants and users on any
        // database where the seed is re-imported.
        $this->assertDoesNotMatchRegularExpression(
            '/DELETE\s+FROM\s+`?(users|tenants|plans|refresh_tokens)`?\s*;/i',
            $seed,
            'seed.sql must scope its deletes to its own demo rows'
        );
    }
}
