<?php

namespace App\Modules\Api\Modules\Auth\Routes;

use App\Modules\Api\Modules\Auth\Controller;

class Routes
{
    public function routes($router): void
    {
        $router->addRoute('/api/auth/login',   Controller::class, 'login',   ['cors'], ['POST']);
        $router->addRoute('/api/auth/refresh', Controller::class, 'refresh', ['cors'], ['POST']);
        $router->addRoute('/api/auth/logout',  Controller::class, 'logout',  ['cors', 'jwt'], ['POST']);
    }
}
