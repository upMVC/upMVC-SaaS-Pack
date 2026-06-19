<?php

namespace App\Modules\Auth;

class View
{
    public function renderLogin(?string $error = null): void
    {
        $base = BASE_URL;
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; min-height: 100vh; }
        .auth-wrap { display: flex; min-height: 100vh; }

        .auth-hero {
            flex: 1;
            background: linear-gradient(145deg, #4f46e5 0%, #7c3aed 60%, #6d28d9 100%);
            display: flex; align-items: center; justify-content: center;
            padding: 60px 48px; color: #fff; position: relative; overflow: hidden;
        }
        .auth-hero::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(ellipse at 30% 20%, rgba(255,255,255,0.08) 0%, transparent 60%);
        }
        .hero-inner { max-width: 400px; position: relative; }
        .hero-logo {
            font-size: 1.25rem; font-weight: 800; letter-spacing: -0.3px;
            margin-bottom: 48px; display: inline-flex; align-items: center; gap: 8px;
        }
        .hero-logo-badge {
            background: rgba(255,255,255,0.15); border-radius: 6px;
            padding: 3px 10px; border: 1px solid rgba(255,255,255,0.2);
        }
        .hero-inner h1 {
            font-size: 2rem; font-weight: 700; line-height: 1.25;
            margin-bottom: 16px; letter-spacing: -0.5px;
        }
        .hero-inner > p {
            font-size: 0.95rem; color: rgba(255,255,255,0.7);
            line-height: 1.65; margin-bottom: 40px;
        }
        .hero-features { list-style: none; display: flex; flex-direction: column; gap: 14px; }
        .hero-features li {
            display: flex; align-items: center; gap: 12px;
            font-size: 0.9rem; color: rgba(255,255,255,0.88);
        }
        .hero-features li::before {
            content: ''; flex-shrink: 0; width: 20px; height: 20px; border-radius: 50%;
            background: rgba(255,255,255,0.18)
            url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='white'%3E%3Cpath fill-rule='evenodd' d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z' clip-rule='evenodd'/%3E%3C/svg%3E")
            center / 11px no-repeat;
        }

        .auth-panel {
            width: 500px; display: flex; align-items: center; justify-content: center;
            background: #fff; padding: 48px 48px;
        }
        .auth-card { width: 100%; max-width: 360px; }
        .auth-card h2 {
            font-size: 1.75rem; font-weight: 700; color: #0f172a;
            margin-bottom: 6px; letter-spacing: -0.5px;
        }
        .auth-sub { color: #64748b; font-size: 0.9rem; margin-bottom: 32px; }

        .auth-error {
            background: #fef2f2; border: 1px solid #fecaca; color: #dc2626;
            border-radius: 8px; padding: 11px 14px; font-size: 0.875rem; margin-bottom: 22px;
        }
        .field { margin-bottom: 20px; }
        .field label {
            display: block; font-size: 0.82rem; font-weight: 600;
            color: #374151; margin-bottom: 7px; letter-spacing: 0.01em;
            text-transform: uppercase;
        }
        .field input {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid #e2e8f0; border-radius: 8px;
            font-size: 0.95rem; color: #0f172a; background: #f8fafc;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
            outline: none;
        }
        .field input:focus {
            border-color: #6366f1; background: #fff;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }
        .field input::placeholder { color: #94a3b8; }
        .field-err {
            display: block; font-size: 0.78rem; color: #dc2626;
            margin-top: 5px; min-height: 1.1em;
        }
        .btn-primary {
            width: 100%; padding: 11px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff; border: none; border-radius: 8px;
            font-size: 0.95rem; font-weight: 600; cursor: pointer;
            margin-top: 8px; letter-spacing: 0.01em;
            transition: opacity 0.15s, transform 0.1s, box-shadow 0.15s;
            box-shadow: 0 2px 8px rgba(99,102,241,0.35);
        }
        .btn-primary:hover { opacity: 0.88; box-shadow: 0 4px 16px rgba(99,102,241,0.4); }
        .btn-primary:active { transform: scale(0.99); }
        .auth-switch {
            text-align: center; margin-top: 28px;
            font-size: 0.875rem; color: #64748b;
        }
        .auth-switch a { color: #6366f1; font-weight: 600; text-decoration: none; }
        .auth-switch a:hover { text-decoration: underline; }

        @media (max-width: 800px) {
            .auth-hero { display: none; }
            .auth-panel { width: 100%; padding: 40px 24px; }
        }
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-hero">
        <div class="hero-inner">
            <div class="hero-logo"><span class="hero-logo-badge">upMVC</span> SaaS</div>
            <h1>Your SaaS platform, ready to ship.</h1>
            <p>Multi-tenant architecture with role-based access, JWT auth, and an API-first design — all wired up out of the box.</p>
            <ul class="hero-features">
                <li>Multi-tenant architecture</li>
                <li>Role-based access control</li>
                <li>API-first design</li>
                <li>Platform admin dashboard</li>
                <li>JWT + session dual auth</li>
            </ul>
        </div>
    </div>
    <div class="auth-panel">
        <div class="auth-card">
            <h2>Welcome back</h2>
            <p class="auth-sub">Sign in to your account to continue</p>
            <?php if ($error !== null): ?>
            <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="" onsubmit="return validate()">
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           autocomplete="username" placeholder="your.username">
                    <span class="field-err" id="user_info"></span>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           autocomplete="current-password" placeholder="••••••••">
                    <span class="field-err" id="password_info"></span>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo \App\Etc\Security::csrfToken(); ?>">
                <button type="submit" name="login" class="btn-primary">Sign in</button>
            </form>
            <p class="auth-switch">Don't have an account? <a href="<?php echo $base; ?>/signup">Create one</a></p>
        </div>
    </div>
</div>
<script>
function validate() {
    var ok = true;
    document.getElementById('user_info').textContent = '';
    document.getElementById('password_info').textContent = '';
    if (!document.getElementById('username').value.trim()) {
        document.getElementById('user_info').textContent = 'Required';
        ok = false;
    }
    if (!document.getElementById('password').value) {
        document.getElementById('password_info').textContent = 'Required';
        ok = false;
    }
    return ok;
}
</script>
</body>
</html>
        <?php
    }

    public function renderSignup(bool $sent = false, ?string $error = null): void
    {
        $base = BASE_URL;

        if ($sent) {
            ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check your email</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: #fff; border-radius: 16px; padding: 48px 40px; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 8px 24px rgba(0,0,0,0.06); }
        .icon { width: 56px; height: 56px; background: #ede9fe; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
        h2 { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 12px; }
        p { color: #64748b; font-size: 0.95rem; line-height: 1.65; }
        a { color: #6366f1; font-weight: 600; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <polyline points="22,6 12,13 2,6"/>
        </svg>
    </div>
    <h2>Check your email</h2>
    <p>We sent an activation link to your address. Click it to activate your account, then <a href="<?php echo $base; ?>/auth">sign in</a>.</p>
</div>
</body>
</html>
            <?php
            return;
        }
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; min-height: 100vh; }
        .auth-wrap { display: flex; min-height: 100vh; }

        .auth-hero {
            flex: 1;
            background: linear-gradient(145deg, #4f46e5 0%, #7c3aed 60%, #6d28d9 100%);
            display: flex; align-items: center; justify-content: center;
            padding: 60px 48px; color: #fff; position: relative; overflow: hidden;
        }
        .auth-hero::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(ellipse at 30% 20%, rgba(255,255,255,0.08) 0%, transparent 60%);
        }
        .hero-inner { max-width: 400px; position: relative; }
        .hero-logo {
            font-size: 1.25rem; font-weight: 800; letter-spacing: -0.3px;
            margin-bottom: 48px; display: inline-flex; align-items: center; gap: 8px;
        }
        .hero-logo-badge {
            background: rgba(255,255,255,0.15); border-radius: 6px;
            padding: 3px 10px; border: 1px solid rgba(255,255,255,0.2);
        }
        .hero-inner h1 { font-size: 2rem; font-weight: 700; line-height: 1.25; margin-bottom: 16px; letter-spacing: -0.5px; }
        .hero-inner > p { font-size: 0.95rem; color: rgba(255,255,255,0.7); line-height: 1.65; margin-bottom: 40px; }
        .hero-steps { list-style: none; display: flex; flex-direction: column; gap: 20px; }
        .hero-steps li { display: flex; align-items: flex-start; gap: 14px; font-size: 0.9rem; color: rgba(255,255,255,0.88); }
        .step-num {
            flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%;
            background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: 700; margin-top: 1px;
        }
        .step-body strong { display: block; font-weight: 600; margin-bottom: 2px; }
        .step-body span { color: rgba(255,255,255,0.65); font-size: 0.82rem; }

        .auth-panel {
            width: 520px; display: flex; align-items: center; justify-content: center;
            background: #fff; padding: 48px;
        }
        .auth-card { width: 100%; max-width: 380px; }
        .auth-card h2 { font-size: 1.75rem; font-weight: 700; color: #0f172a; margin-bottom: 6px; letter-spacing: -0.5px; }
        .auth-sub { color: #64748b; font-size: 0.9rem; margin-bottom: 28px; }
        .auth-error {
            background: #fef2f2; border: 1px solid #fecaca; color: #dc2626;
            border-radius: 8px; padding: 11px 14px; font-size: 0.875rem; margin-bottom: 20px;
        }
        .field { margin-bottom: 18px; }
        .field label {
            display: block; font-size: 0.82rem; font-weight: 600; color: #374151;
            margin-bottom: 7px; letter-spacing: 0.01em; text-transform: uppercase;
        }
        .field input {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid #e2e8f0; border-radius: 8px;
            font-size: 0.95rem; color: #0f172a; background: #f8fafc;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
            outline: none;
        }
        .field input:focus {
            border-color: #6366f1; background: #fff;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }
        .field input::placeholder { color: #94a3b8; }
        .field-err { display: block; font-size: 0.78rem; color: #dc2626; margin-top: 5px; min-height: 1.1em; }
        .btn-primary {
            width: 100%; padding: 11px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff; border: none; border-radius: 8px;
            font-size: 0.95rem; font-weight: 600; cursor: pointer; margin-top: 8px;
            letter-spacing: 0.01em;
            transition: opacity 0.15s, transform 0.1s, box-shadow 0.15s;
            box-shadow: 0 2px 8px rgba(99,102,241,0.35);
        }
        .btn-primary:hover { opacity: 0.88; box-shadow: 0 4px 16px rgba(99,102,241,0.4); }
        .btn-primary:active { transform: scale(0.99); }
        .auth-switch { text-align: center; margin-top: 24px; font-size: 0.875rem; color: #64748b; }
        .auth-switch a { color: #6366f1; font-weight: 600; text-decoration: none; }
        .auth-switch a:hover { text-decoration: underline; }

        @media (max-width: 800px) {
            .auth-hero { display: none; }
            .auth-panel { width: 100%; padding: 40px 24px; }
        }
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-hero">
        <div class="hero-inner">
            <div class="hero-logo"><span class="hero-logo-badge">upMVC</span> SaaS</div>
            <h1>Get started in minutes.</h1>
            <p>Create your account and get access to your tenant dashboard instantly.</p>
            <ol class="hero-steps">
                <li>
                    <span class="step-num">1</span>
                    <div class="step-body">
                        <strong>Create your account</strong>
                        <span>Fill in the form — takes 30 seconds.</span>
                    </div>
                </li>
                <li>
                    <span class="step-num">2</span>
                    <div class="step-body">
                        <strong>Activate via email</strong>
                        <span>Click the link we send to your inbox.</span>
                    </div>
                </li>
                <li>
                    <span class="step-num">3</span>
                    <div class="step-body">
                        <strong>Start building</strong>
                        <span>Access your dashboard and API immediately.</span>
                    </div>
                </li>
            </ol>
        </div>
    </div>
    <div class="auth-panel">
        <div class="auth-card">
            <h2>Create account</h2>
            <p class="auth-sub">Set up your workspace in seconds</p>
            <?php if ($error !== null): ?>
            <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="" onsubmit="return validate()">
                <div class="field">
                    <label for="company_name">Company / workspace name</label>
                    <input type="text" id="company_name" name="company_name" autocomplete="organization" placeholder="Acme Corp">
                    <span class="field-err" id="company_info"></span>
                </div>
                <div class="field">
                    <label for="name">Your full name</label>
                    <input type="text" id="name" name="name" autocomplete="name" placeholder="Jane Smith">
                    <span class="field-err" id="name_info"></span>
                </div>
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" autocomplete="username" placeholder="jane.smith">
                    <span class="field-err" id="user_info"></span>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" autocomplete="email" placeholder="jane@example.com">
                    <span class="field-err" id="email_info"></span>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" placeholder="••••••••">
                    <span class="field-err" id="password_info"></span>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo \App\Etc\Security::csrfToken(); ?>">
                <button type="submit" name="signup" class="btn-primary">Create account</button>
            </form>
            <p class="auth-switch">Already have an account? <a href="<?php echo $base; ?>/auth">Sign in</a></p>
        </div>
    </div>
</div>
<script>
function validate() {
    var ok = true;
    ['company_info','name_info','user_info','email_info','password_info'].forEach(function(id) {
        document.getElementById(id).textContent = '';
    });
    if (!document.getElementById('company_name').value.trim()) {
        document.getElementById('company_info').textContent = 'Required'; ok = false;
    }
    if (!document.getElementById('name').value.trim()) {
        document.getElementById('name_info').textContent = 'Required'; ok = false;
    }
    if (!document.getElementById('username').value.trim()) {
        document.getElementById('user_info').textContent = 'Required'; ok = false;
    }
    var email = document.getElementById('email').value.trim();
    if (!email || !email.includes('@')) {
        document.getElementById('email_info').textContent = 'Valid email required'; ok = false;
    }
    if (!document.getElementById('password').value) {
        document.getElementById('password_info').textContent = 'Required'; ok = false;
    }
    return ok;
}
</script>
</body>
</html>
        <?php
    }

    public function tokenInvalid(): void
    {
        ?>
        <p style="text-align:center; font-size: 2rem; padding: 60px">Invalid or expired token.</p>
        <script>setTimeout(function(){ location.href = '<?php echo BASE_URL; ?>'; }, 3000);</script>
        <?php
    }

    public function tokenValid(): void
    {
        ?>
        <p style="text-align:center; font-size: 2rem; padding: 60px">Account activated! Redirecting to login…</p>
        <script>setTimeout(function(){ location.href = '<?php echo BASE_URL; ?>/auth'; }, 2500);</script>
        <?php
    }

    public function tokenNull(): void
    {
        ?>
        <p style="text-align:center; font-size: 2rem; padding: 60px">No token provided.</p>
        <script>setTimeout(function(){ location.href = '<?php echo BASE_URL; ?>'; }, 3000);</script>
        <?php
    }
}
