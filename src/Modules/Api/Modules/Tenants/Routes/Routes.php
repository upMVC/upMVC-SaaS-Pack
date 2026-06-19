<?php

namespace App\Modules\Api\Modules\Tenants\Routes;

use App\Modules\Api\Modules\Tenants\Controller;

class Routes
{
    public function routes($router): void
    {
        $router->addRoute('/api/tenants/register',              Controller::class, 'register', ['cors'], ['POST']);
        $router->addParamRoute('/api/tenants/{id:int}',         Controller::class, 'show',     ['cors', 'jwt'], [], ['GET']);
        $router->addParamRoute('/api/tenants/{id:int}/users',   Controller::class, 'users',    ['cors', 'jwt'], [], ['GET']);
        $router->addParamRoute('/api/tenants/{id:int}/update',  Controller::class, 'update',   ['cors', 'jwt'], [], ['PATCH']);
        $router->addParamRoute('/api/public/tenants/{slug}',    Controller::class, 'bySlug',   ['cors'], [], ['GET']);
    }
}
