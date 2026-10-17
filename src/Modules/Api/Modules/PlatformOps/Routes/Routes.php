<?php

namespace App\Modules\Api\Modules\PlatformOps\Routes;

use App\Modules\Api\Modules\PlatformOps\Controller;

class Routes
{
    public function routes($router): void
    {
        $auth = ['cors', 'jwt'];
        $router->addRoute('/api/admin/ops/attention', Controller::class, 'attention', $auth, ['GET']);
        $router->addRoute('/api/admin/ops/flags', Controller::class, 'getFlags', $auth, ['GET']);
        $router->addRoute('/api/admin/ops/flags/save', Controller::class, 'saveFlags', $auth, ['POST']);
        $router->addRoute('/api/admin/ops/scheduler/beat', Controller::class, 'schedulerBeat', $auth, ['POST']);
        $router->addRoute('/api/admin/ops/mfa', Controller::class, 'mfaStatus', $auth, ['GET']);
        $router->addRoute('/api/admin/ops/mfa/setup', Controller::class, 'mfaSetup', $auth, ['POST']);
        $router->addRoute('/api/admin/ops/mfa/enable', Controller::class, 'mfaEnable', $auth, ['POST']);
        $router->addRoute('/api/admin/ops/mfa/disable', Controller::class, 'mfaDisable', $auth, ['POST']);
        $router->addRoute('/api/admin/ops/smtp', Controller::class, 'getSmtp', $auth, ['GET']);
        $router->addRoute('/api/admin/ops/smtp/save', Controller::class, 'saveSmtp', $auth, ['POST']);
    }
}
