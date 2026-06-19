<?php

namespace App\Modules\TenantShop\Routes;

use App\Modules\TenantShop\Controller;

class Routes
{
    public function routes($router): void
    {
        // Public storefront — no auth required
        $router->addParamRoute('/shop/{slug}', Controller::class, 'display');
        $router->addParamRoute('/shop/{slug}/{page}', Controller::class, 'display');
    }
}
