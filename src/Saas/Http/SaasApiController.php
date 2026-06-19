<?php

namespace BitsHost\UpmvcSaas\Http;

use App\Common\Bmvc\BaseApiController;
use App\Etc\Database;

abstract class SaasApiController extends BaseApiController
{
    protected array $tenant;
    protected array $user;
    protected int $tenantId;
    protected \PDO $db;

    public function __construct()
    {
        $this->tenant = $GLOBALS['current_tenant'] ?? [];
        $this->user = $GLOBALS['current_user'] ?? [];
        $this->tenantId = (int) ($this->tenant['id'] ?? 0);
        $this->db = (new Database())->getConnection();
    }
}
