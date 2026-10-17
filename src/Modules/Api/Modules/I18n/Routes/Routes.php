<?php

namespace App\Modules\Api\Modules\I18n\Routes;

use App\Modules\Api\Modules\I18n\Controller;

class Routes
{
    public function routes($router): void
    {
        $public = ['cors'];
        $router->addRoute('/api/i18n', Controller::class, 'index', $public, ['GET']);
        $router->addParamRoute('/api/i18n/{locale}', Controller::class, 'show', $public, [], ['GET']);
    }
}
