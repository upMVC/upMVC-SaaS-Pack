<?php

namespace App\Modules\Api\Modules\Plans\Routes;

use App\Modules\Api\Modules\Plans\Controller;

class Routes
{
    public function routes($router): void
    {
        $router->addRoute('/api/plans', Controller::class, 'index', ['cors'], ['GET']);
        $router->addParamRoute('/api/plans/{id:int}', Controller::class, 'show', ['cors'], [], ['GET']);
    }
}
