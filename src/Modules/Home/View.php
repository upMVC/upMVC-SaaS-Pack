<?php

namespace App\Modules\Home;

class View
{
    public function landing(): void
    {
        $baseUrl = BASE_URL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>upMVC SaaS</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; color: #1e293b; }
        .hero { max-width: 700px; margin: 80px auto; padding: 40px 24px; text-align: center; }
        h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 12px; }
        h1 span { color: #6366f1; }
        p { font-size: 1.1rem; color: #64748b; margin-bottom: 40px; line-height: 1.6; }
        .actions { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
        .btn { padding: 12px 28px; border-radius: 8px; font-size: 1rem; font-weight: 600; text-decoration: none; transition: opacity .2s; }
        .btn-primary { background: #6366f1; color: #fff; }
        .btn-secondary { background: #fff; color: #6366f1; border: 2px solid #6366f1; }
        .btn:hover { opacity: .85; }
        .sections { max-width: 700px; margin: 0 auto 80px; padding: 0 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
        .card h3 { font-size: 1rem; font-weight: 600; margin-bottom: 6px; }
        .card p { font-size: .875rem; color: #64748b; margin-bottom: 12px; }
        .card a { font-size: .875rem; color: #6366f1; text-decoration: none; font-weight: 500; }
    </style>
</head>
<body>
    <div class="hero">
        <h1>Welcome to <span>upMVC</span> SaaS</h1>
        <p>A multi-tenant SaaS starter built on the upMVC PHP framework.<br>
           Login to your account or sign up to create a new tenant.</p>
        <div class="actions">
            <a href="<?= $baseUrl ?>/auth" class="btn btn-primary">Login</a>
            <a href="<?= $baseUrl ?>/signup" class="btn btn-secondary">Sign Up</a>
        </div>
    </div>
    <div class="sections">
        <div class="card">
            <h3>Tenant App</h3>
            <p>Access your tenant workspace and manage your data.</p>
            <a href="<?= $baseUrl ?>/app">Open App &rarr;</a>
        </div>
        <div class="card">
            <h3>Platform Admin</h3>
            <p>Manage tenants, plans and platform-wide settings.</p>
            <a href="<?= $baseUrl ?>/platform-admin">Admin Panel &rarr;</a>
        </div>
        <div class="card">
            <h3>API</h3>
            <p>All API endpoints live under <code>/api/*</code>. JWT-protected.</p>
            <a href="<?= $baseUrl ?>/api/tenants/register">Register endpoint &rarr;</a>
        </div>
    </div>
</body>
</html>
<?php
    }
}
