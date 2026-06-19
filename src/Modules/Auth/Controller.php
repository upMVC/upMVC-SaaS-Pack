<?php

/*
 *   Created on Tue Oct 31 2023
 
 *   Copyright (c) 2023 BitsHost
 *   All rights reserved.

 *   Permission is hereby granted, free of charge, to any person obtaining a copy
 *   of this software and associated documentation files (the "Software"), to deal
 *   in the Software without restriction, including without limitation the rights
 *   to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *   copies of the Software, and to permit persons to whom the Software is
 *   furnished to do so, subject to the following conditions:

 *   The above copyright notice and this permission notice shall be included in all
 *   copies or substantial portions of the Software.

 *   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *   IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *   FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *   AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *   LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *   OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *   SOFTWARE.
 *   Here you may host your app for free:
 *   https://bitshost.biz/
 */


namespace App\Modules\Auth;

use App\Etc\JwtService;
use App\Etc\Security;
use App\Modules\Api\Modules\Auth\Model as ApiAuthModel;
use App\Modules\Api\Modules\Tenants\Model as TenantModel;
use PDO;

class Controller
{
    public $title = "Authetnication Page";
    public $username;
    public $url = BASE_URL;
    public $html;
    public $name;

    public function display($reqRoute, $reqMet)

    {
        switch ($reqRoute) {
            case "/auth":
            case "/login":
                $this->auth();
                break;
            case "/logout":
                $this->logout();
                break;
            case "/signup":
                $this->signUp();
                break;
            case "/activation":
                $this->accountActivation();
                break;
            default:
                $this->login();
                echo $reqMet;
        }
    }

    private function getRoleRedirect(string $role, mixed $tenantId = null): string
    {
        return match($role) {
            'platform_admin' => BASE_URL . '/platform-admin',
            'tenant_owner',
            'tenant_user'    => $tenantId ? BASE_URL . '/app' : BASE_URL,
            default          => BASE_URL,
        };
    }

    private function auth()
    {
        if (isset($_SESSION["logged"]) && $_SESSION["logged"] === true) {
            $intendedUrl = $_SESSION['intended_url'] ?? null;
            unset($_SESSION['intended_url']);
            if ($intendedUrl && str_starts_with($intendedUrl, BASE_URL)) {
                header("Location: $intendedUrl");
                exit;
            }
            header('Location: ' . $this->getRoleRedirect($_SESSION['role'] ?? '', $_SESSION['tenant_id'] ?? null));
            exit;
        } else {
            $this->login();
        }
    }

    private function login()
    {
        $loginError = null;

        // Process POST before any HTML output so session cookie can be set
        if ($_POST) {
            // Brute-force guard: 10 attempts per IP per 15 minutes
            if (!Security::rateLimit('login:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 10, 900)) {
                (new View())->renderLogin('Too many login attempts. Please wait 15 minutes before trying again.');
                return;
            }

            $users           = new Model();
            $users->username = $_POST['username'] ?? '';
            $inputPassword   = $_POST['password'] ?? '';
            $stmt            = $users->readUserLogin();
            $row             = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

            if ($row && password_verify($inputPassword, $row['password'])) {
                if (intval($row['state']) === 1) {
                    session_regenerate_id(true);
                    $_SESSION['username']      = $row['username'];
                    $_SESSION['iduser']        = $row['id'];
                    $_SESSION['role']          = $row['role'];
                    $_SESSION['tenant_id']     = $row['tenant_id'];
                    $_SESSION['tenant_slug']   = $row['tenant_slug'] ?? '';
                    $_SESSION['tenant_name']   = $row['tenant_name'] ?? '';
                    $_SESSION['logged']        = true;
                    $_SESSION['authenticated'] = true;

                    // Issue a JWT so web shells (PlatformAdmin, TenantApp) can call the API via JS
                    $jwtSvc      = new JwtService();
                    $jwt         = $jwtSvc->issueAccessToken([
                        'sub'       => (int) $row['id'],
                        'username'  => $row['username'],
                        'tenant_id' => $row['tenant_id'],
                        'role'      => $row['role'],
                    ]);
                    $_SESSION['jwt_token'] = $jwt;

                    $rawRefresh = $jwtSvc->issueRefreshToken();
                    $expiresAt  = date('Y-m-d H:i:s', time() + $jwtSvc->getRefreshTtl());
                    (new ApiAuthModel())->storeRefreshToken((int) $row['id'], hash('sha256', $rawRefresh), $expiresAt);
                    $_SESSION['refresh_token'] = $rawRefresh;

                    $intendedUrl = $_SESSION['intended_url'] ?? null;
                    unset($_SESSION['intended_url']);
                    $redirectUrl = ($intendedUrl && str_starts_with($intendedUrl, BASE_URL))
                        ? $intendedUrl
                        : $this->getRoleRedirect($row['role'], $row['tenant_id']);

                    header('Location: ' . $redirectUrl);
                    exit;
                } else {
                    $loginError = 'Account not activated. Check your email!';
                }
            } else {
                $loginError = 'Invalid username or password.';
            }
        }

        (new View())->renderLogin($loginError);
    }

    private function logout(): void
    {
        session_unset();
        session_destroy();
        header('Location: ' . $this->url);
        exit;
    }

    private function signUp(): void
    {
        $error = null;

        if (isset($_POST['signup'])) {
            $companyName = trim($_POST['company_name'] ?? '');
            $name        = trim($_POST['name']         ?? '');
            $username    = trim($_POST['username']     ?? '');
            $email       = trim($_POST['email']        ?? '');
            $password    = $_POST['password']          ?? '';

            // Build a URL-safe slug from the company name
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $companyName));
            $slug = trim($slug, '-');
            if ($slug === '') {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $username));
                $slug = trim($slug, '-');
            }

            $tenantModel = new TenantModel();

            // Ensure slug is unique — append random suffix if taken
            if ($tenantModel->findBySlug($slug)) {
                $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
            }

            $result = $tenantModel->createWithOwner(
                ['slug' => $slug, 'name' => $companyName],
                ['name' => $name, 'username' => $username, 'email' => $email, 'password' => $password]
            );

            if ($result['success']) {
                $tenantId = $result['tenant_id'];
                $userId   = $result['user_id'];

                session_regenerate_id(true);
                $_SESSION['username']      = $username;
                $_SESSION['iduser']        = $userId;
                $_SESSION['role']          = 'tenant_owner';
                $_SESSION['tenant_id']     = $tenantId;
                $_SESSION['tenant_slug']   = $slug;
                $_SESSION['tenant_name']   = $companyName;
                $_SESSION['logged']        = true;
                $_SESSION['authenticated'] = true;

                $jwt         = new JwtService();
                $accessToken = $jwt->issueAccessToken([
                    'sub'       => $userId,
                    'username'  => $username,
                    'tenant_id' => $tenantId,
                    'role'      => 'tenant_owner',
                ]);
                $_SESSION['jwt_token'] = $accessToken;

                $rawRefresh = $jwt->issueRefreshToken();
                $expiresAt  = date('Y-m-d H:i:s', time() + $jwt->getRefreshTtl());
                (new ApiAuthModel())->storeRefreshToken($userId, hash('sha256', $rawRefresh), $expiresAt);
                $_SESSION['refresh_token'] = $rawRefresh;

                header('Location: ' . BASE_URL . '/app/' . $slug . '/admin');
                exit;
            }

            $error = $result['error'] ?? 'Registration failed. Try a different username or email.';
        }

        (new View())->renderSignup(false, $error);
    }

    public function sessionRefresh(): void
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['logged']) || $_SESSION['logged'] !== true) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            exit;
        }

        $rawRefresh = $_SESSION['refresh_token'] ?? '';
        if ($rawRefresh === '') {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No refresh token in session']);
            exit;
        }

        $tokenHash = hash('sha256', $rawRefresh);
        $model     = new ApiAuthModel();
        $record    = $model->findRefreshToken($tokenHash);

        if (!$record || $record['revoked_at'] !== null || strtotime($record['expires_at']) < time()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Refresh token invalid or expired — please log in again']);
            exit;
        }

        $model->revokeRefreshToken($tokenHash);
        $user = $model->findUserById((int) $record['user_id']);

        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false]);
            exit;
        }

        $jwtSvc     = new JwtService();
        $newAccess  = $jwtSvc->issueAccessToken([
            'sub'       => (int) $user['id'],
            'username'  => $user['username'],
            'tenant_id' => (int) $user['tenant_id'],
            'role'      => $user['role'],
        ]);
        $newRaw    = $jwtSvc->issueRefreshToken();
        $expiresAt = date('Y-m-d H:i:s', time() + $jwtSvc->getRefreshTtl());
        $model->storeRefreshToken((int) $user['id'], hash('sha256', $newRaw), $expiresAt);

        $_SESSION['jwt_token']     = $newAccess;
        $_SESSION['refresh_token'] = $newRaw;

        echo json_encode(['success' => true, 'access_token' => $newAccess]);
        exit;
    }

    private function accountActivation()
    {
        $this->html = new View();
        $user = new Model();
        if (!empty($_GET['token'])) {
            $token       = $_GET['token'];
            $user->token = $token;
            $stmt     = $user->readUserToken();
            $result = $stmt->rowCount();
            if ($result === 0) {
                $this->html->tokenInvalid();
            } else {
                $user->state = 1;
                $user->setActiveUser();
                $this->html->tokenValid();
            }
        } else {
            $this->html->tokenNull();
        }
    }
}











