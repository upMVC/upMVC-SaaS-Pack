<?php

namespace App\Modules\Api\Modules\Auth;

use App\Etc\JwtService;
use BitsHost\UpmvcSaas\Http\SaasApiController;

class Controller extends SaasApiController
{
    public function login(): never
    {
        $body  = $this->requireFields(['username', 'password']);
        $model = new Model();

        $user = $model->findUserByUsername($body['username']);

        if (!$user || !password_verify($body['password'], $user['password'])) {
            $this->error('Invalid credentials', 401);
        }

        if ((int) $user['state'] !== 1) {
            $this->error('Account is not activated', 403);
        }

        [$accessToken, $rawRefresh] = $this->buildTokenPair($model, $user);

        $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $rawRefresh,
            'token_type'    => 'Bearer',
            'expires_in'    => (new JwtService())->getAccessTtl(),
            'user'          => [
                'id'        => (int) $user['id'],
                'username'  => $user['username'],
                'name'      => $user['name'],
                'tenant_id' => (int) $user['tenant_id'],
                'role'      => $user['role'] ?? 'tenant_user',
            ],
        ]);
    }

    public function refresh(): never
    {
        $body      = $this->requireFields(['refresh_token']);
        $tokenHash = hash('sha256', $body['refresh_token']);
        $model     = new Model();

        $record = $model->findRefreshToken($tokenHash);

        if (!$record) {
            $this->error('Invalid refresh token', 401);
        }
        if ($record['revoked_at'] !== null) {
            $model->revokeAllUserTokens((int) $record['user_id']);
            $this->error('Refresh token has already been used (possible theft detected)', 401);
        }
        if (strtotime($record['expires_at']) < time()) {
            $this->error('Refresh token has expired — please log in again', 401);
        }

        $model->revokeRefreshToken($tokenHash);

        $user = $model->findUserById((int) $record['user_id']);
        if (!$user) {
            $this->error('User not found', 401);
        }

        [$accessToken, $rawRefresh] = $this->buildTokenPair($model, $user);

        $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $rawRefresh,
            'token_type'    => 'Bearer',
            'expires_in'    => (new JwtService())->getAccessTtl(),
        ]);
    }

    public function logout(): never
    {
        $body  = $this->body();
        $model = new Model();

        if (!empty($body['refresh_token'])) {
            $model->revokeRefreshToken(hash('sha256', $body['refresh_token']));
        } else {
            $userId = (int) ($this->user['sub'] ?? 0);
            if ($userId > 0) {
                $model->revokeAllUserTokens($userId);
            }
        }

        $this->success(null, 'Logged out successfully');
    }

    private function buildTokenPair(Model $model, array $user): array
    {
        $jwt = new JwtService();

        $accessToken = $jwt->issueAccessToken([
            'sub'       => (int) $user['id'],
            'username'  => $user['username'],
            'tenant_id' => (int) $user['tenant_id'],
            'role'      => $user['role'] ?? 'tenant_user',
        ]);

        $rawRefresh = $jwt->issueRefreshToken();
        $expiresAt  = date('Y-m-d H:i:s', time() + $jwt->getRefreshTtl());

        $model->storeRefreshToken(
            (int) $user['id'],
            hash('sha256', $rawRefresh),
            $expiresAt
        );

        return [$accessToken, $rawRefresh];
    }
}
