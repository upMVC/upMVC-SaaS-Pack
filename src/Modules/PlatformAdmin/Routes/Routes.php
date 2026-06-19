<?php

namespace App\Modules\PlatformAdmin\Routes;

use App\Modules\PlatformAdmin\Controller;

class Routes
{
    public function routes($router): void
    {
        $router->addRoute('/platform-admin',        Controller::class, 'display');
        $router->addRoute('/platform-admin/assume', Controller::class, 'assume', ['csrf']);
        $router->addRoute('/platform-admin/resume', Controller::class, 'resume');
    }
}
