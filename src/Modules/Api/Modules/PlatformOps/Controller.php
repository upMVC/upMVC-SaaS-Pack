<?php

namespace App\Modules\Api\Modules\PlatformOps;

use BitsHost\UpmvcSaas\Http\SaasApiController;
use BitsHost\UpmvcSaas\Mail\MailGateway;
use BitsHost\UpmvcSaas\Ops\Attention;
use BitsHost\UpmvcSaas\Ops\PlatformSettings;
use BitsHost\UpmvcSaas\Ops\SchedulerHeartbeat;
/**
 * Generic platform ops API (Harvest).
 * Apps may override this module for domain Attention items / SaaS metrics.
 */
class Controller extends SaasApiController
{
    private function requirePlatformAdmin(): void
    {
        if (($this->user['role'] ?? '') !== 'platform_admin') {
            $this->error('Platform admin access required', 403);
        }
    }

    /** GET /api/admin/ops/attention */
    public function attention(): never
    {
        $this->requirePlatformAdmin();
        $this->success(['attention' => Attention::collect()]);
    }

    /** GET /api/admin/ops/flags */
    public function getFlags(): never
    {
        $this->requirePlatformAdmin();
        $this->success(['flags' => PlatformSettings::flags()]);
    }

    /** POST /api/admin/ops/flags/save */
    public function saveFlags(): never
    {
        $this->requirePlatformAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $this->success(['flags' => PlatformSettings::saveFlags($body)], 'Flags saved');
    }

    /** POST /api/admin/ops/scheduler/beat */
    public function schedulerBeat(): never
    {
        $this->requirePlatformAdmin();
        $this->success(['heartbeat' => SchedulerHeartbeat::beat(), 'health' => SchedulerHeartbeat::health()]);
    }

    /** GET /api/admin/ops/mfa */
    public function mfaStatus(): never
    {
        $this->requirePlatformAdmin();
        $uid = (int) ($this->user['sub'] ?? $this->user['id'] ?? 0);
        $this->success((new Model())->mfaStatus($uid));
    }

    /** POST /api/admin/ops/mfa/setup */
    public function mfaSetup(): never
    {
        $this->requirePlatformAdmin();
        $uid = (int) ($this->user['sub'] ?? $this->user['id'] ?? 0);
        try {
            $this->success((new Model())->mfaSetup($uid));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    /** POST /api/admin/ops/mfa/enable */
    public function mfaEnable(): never
    {
        $this->requirePlatformAdmin();
        $uid = (int) ($this->user['sub'] ?? $this->user['id'] ?? 0);
        $body = $this->requireFields(['code']);
        try {
            (new Model())->mfaEnable($uid, (string) $body['code']);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage(), 400);
        }
        $this->success(['enabled' => true], 'MFA enabled');
    }

    /** POST /api/admin/ops/mfa/disable */
    public function mfaDisable(): never
    {
        $this->requirePlatformAdmin();
        $uid = (int) ($this->user['sub'] ?? $this->user['id'] ?? 0);
        $body = $this->requireFields(['password', 'code']);
        try {
            (new Model())->mfaDisable($uid, (string) $body['password'], (string) $body['code']);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage(), 400);
        }
        $this->success(['enabled' => false], 'MFA disabled');
    }

    /** GET /api/admin/ops/smtp — platform SMTP public view */
    public function getSmtp(): never
    {
        $this->requirePlatformAdmin();
        $gw = new MailGateway();
        $this->success([
            'smtp'  => $gw->getPublicPlatform(),
            'route' => $gw->describeRoute(null),
        ]);
    }

    /** POST /api/admin/ops/smtp/save */
    public function saveSmtp(): never
    {
        $this->requirePlatformAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        try {
            $smtp = (new MailGateway())->savePlatform($body);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage(), 400);
        }
        $this->success(['smtp' => $smtp], 'SMTP saved');
    }
}
