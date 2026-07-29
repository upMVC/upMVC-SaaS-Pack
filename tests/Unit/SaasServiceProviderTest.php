<?php

declare(strict_types=1);

namespace BitsHost\UpmvcSaas\Tests\Unit;

use App\Etc\Application;
use App\Etc\Router;
use BitsHost\UpmvcSaas\SaasServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The provider is the pack's only wiring into the kernel. If it registers a
 * path that does not exist, or stops registering one at all, everything else
 * fails silently — the routes simply never appear and the tables never get
 * created. These tests assert the wiring reaches real directories.
 */
final class SaasServiceProviderTest extends TestCase
{
    private string $packRoot;

    protected function setUp(): void
    {
        $this->packRoot = dirname(__DIR__, 2);
        $this->resetApplicationSingleton();
    }

    protected function tearDown(): void
    {
        $this->resetApplicationSingleton();
    }

    private function resetApplicationSingleton(): void
    {
        $prop = new ReflectionProperty(Application::class, 'instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    private function registeredApp(): Application
    {
        $app = Application::getInstance($this->packRoot);
        (new SaasServiceProvider())->register($app);

        return $app;
    }

    private function normalise(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    public function testRegistersTheModulePathAndItExistsOnDisk(): void
    {
        $expected = $this->normalise($this->packRoot . '/src/Modules');
        $paths    = array_map([$this, 'normalise'], $this->registeredApp()->getModulePaths());

        $this->assertContains($expected, $paths, 'Provider must register the pack module directory');
        $this->assertDirectoryExists($expected);
    }

    public function testRegistersTheMigrationPathAndItContainsMigrations(): void
    {
        $expected = $this->normalise($this->packRoot . '/database/migrations');
        $paths    = array_map([$this, 'normalise'], $this->registeredApp()->getMigrationPaths());

        $this->assertContains($expected, $paths, 'Provider must register the pack migration directory');
        $this->assertDirectoryExists($expected);

        // Regression guard: the directory was registered but empty, so installing
        // the pack created no tables at all.
        $this->assertNotEmpty(
            glob($expected . '/*.sql') ?: [],
            'The registered migration directory must actually contain migrations'
        );
    }

    public function testRegistersProtectedRoutes(): void
    {
        $routes = $this->registeredApp()->getProtectedRoutes();

        $this->assertContains('/platform-admin', $routes);
        $this->assertContains('/platform-admin/*', $routes);
        $this->assertContains('/app', $routes);
    }

    public function testBootRegistersTenantMiddlewareAndFeatureFactory(): void
    {
        $app    = Application::getInstance($this->packRoot);
        $router = new Router();

        (new SaasServiceProvider())->boot($app, $router);

        $named = new ReflectionProperty(Router::class, 'middleware');
        $named->setAccessible(true);
        $this->assertArrayHasKey('tenant', $named->getValue($router));

        $factories = new ReflectionProperty(Router::class, 'middlewareFactories');
        $factories->setAccessible(true);
        $this->assertArrayHasKey('feature', $factories->getValue($router));
    }

    public function testFeatureFactoryProducesAPlanGateForTheRequestedFeature(): void
    {
        $app    = Application::getInstance($this->packRoot);
        $router = new Router();

        (new SaasServiceProvider())->boot($app, $router);

        $factories = new ReflectionProperty(Router::class, 'middlewareFactories');
        $factories->setAccessible(true);

        $middleware = ($factories->getValue($router)['feature'])('efactura');

        $this->assertInstanceOf(\BitsHost\UpmvcSaas\Middleware\PlanGateMiddleware::class, $middleware);
    }
}
