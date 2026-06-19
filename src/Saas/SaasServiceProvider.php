<?php

namespace BitsHost\UpmvcSaas;

use App\Etc\Application;
use App\Etc\Router;
use BitsHost\UpmvcSaas\Middleware\PlanGateMiddleware;
use BitsHost\UpmvcSaas\Middleware\TenantMiddleware;

class SaasServiceProvider
{
    public function register(Application $app): void
    {
        $packRoot = dirname(__DIR__, 2);

        $app->addModulePath($packRoot . '/src/Modules');
        $app->addMigrationPath($packRoot . '/database/migrations');
        $app->addProtectedRoutes([
            '/platform-admin',
            '/platform-admin/*',
            '/app',
        ]);
    }

    public function boot(Application $app, Router $router): void
    {
        $router->addMiddleware('tenant', new TenantMiddleware());
        $router->registerMiddlewareFactory('feature', fn(string $feature) => new PlanGateMiddleware($feature));
    }
}
