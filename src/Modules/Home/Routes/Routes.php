<?php

namespace App\Modules\Home\Routes;

use App\Modules\Home\Controller;

class Routes
{
    public function routes($router): void
    {
        $router->addRoute('/', Controller::class, 'display');
        $router->addRoute('/home', Controller::class, 'display');
    }
}
