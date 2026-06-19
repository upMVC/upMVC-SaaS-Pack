<?php

namespace App\Modules\TenantApp\Routes;

use App\Modules\TenantApp\Controller;

class Routes
{
    public function routes($router): void
    {
        // /app  → controller redirects to /app/{slug} using session tenant
        $router->addRoute('/app', Controller::class, 'display');

        // These web shell routes intentionally avoid the JSON TenantMiddleware.
        // The controller separates public pages from authenticated admin pages,
        // while API calls enforce tenant status and access via JSON responses.
        // /app/{slug}                 → public tenant frontend
        // /app/{slug}/admin           → tenant admin dashboard  (page=admin)
        // /app/{slug}/admin/{subpage} → tenant admin sub-pages  (subpage=users|settings…)
        $router->addParamRoute('/app/{slug}',                    Controller::class, 'display');
        $router->addParamRoute('/app/{slug}/{page}',             Controller::class, 'display');
        $router->addParamRoute('/app/{slug}/admin/{subpage}',    Controller::class, 'display');
    }
}
