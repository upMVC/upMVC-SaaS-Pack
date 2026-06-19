<?php

namespace App\Modules\Api\Modules\Admin\Routes;

use App\Modules\Api\Modules\Admin\Controller;

class Routes
{
    public function routes($router): void
    {
        // All routes require JWT — role check (platform_admin) in Controller constructor
        $router->addRoute('/api/admin/tenants',     Controller::class, 'listTenants', ['cors', 'jwt'], ['GET']);
        $router->addRoute('/api/admin/dashboard',   Controller::class, 'dashboard',   ['cors', 'jwt'], ['GET']);
        $router->addRoute('/api/admin/metrics',     Controller::class, 'metrics',     ['cors', 'jwt'], ['GET']);
        $router->addRoute('/api/admin/impersonate', Controller::class, 'impersonate', ['cors', 'jwt'], ['POST']);

        $router->addParamRoute('/api/admin/tenants/{id:int}',        Controller::class, 'updateTenant',      ['cors', 'jwt'], [], ['PATCH']);
        $router->addParamRoute('/api/admin/tenants/{id:int}/status', Controller::class, 'updateStatus',      ['cors', 'jwt'], [], ['PATCH']);
        $router->addParamRoute('/api/admin/tenants/{id:int}/plan',   Controller::class, 'updatePlan',        ['cors', 'jwt'], [], ['PATCH']);
        $router->addParamRoute('/api/admin/plans/{id:int}',          Controller::class, 'updatePlanDetails', ['cors', 'jwt'], [], ['PUT']);
    }
}
