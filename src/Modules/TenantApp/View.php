<?php

namespace App\Modules\TenantApp;

class View
{
    // ── Admin shell (authenticated) ─────────────────────────────────────────

    public function renderAdmin(array $s): void
    {
        $base         = BASE_URL;
        $slug         = htmlspecialchars($s['slug']);
        $name         = htmlspecialchars($s['name']);
        $username     = htmlspecialchars($s['username']);
        $impersonating = $_SESSION['impersonating'] ?? null;
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $name; ?> — Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; color: #1e293b; display: flex; flex-direction: column; min-height: 100vh; }

        /* ── Impersonation banner ── */
        .impersonate-bar {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #fff;
            text-align: center;
            padding: 8px 16px;
            font-size: 0.82rem;
            font-weight: 500;
        }
        .impersonate-bar a { color: #fff; font-weight: 700; text-decoration: underline; }

        /* ── Layout ── */
        .app-body { display: flex; flex: 1; }

        /* ── Sidebar ── */
        .sidebar {
            width: 240px;
            min-height: 100%;
            background: #0f172a;
            color: #e2e8f0;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
        }
        .sidebar-brand {
            padding: 20px 18px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar-brand-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .sidebar-avatar {
            width: 34px; height: 34px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; font-weight: 800; color: #fff; flex-shrink: 0;
        }
        .sidebar-org { font-size: 0.9rem; font-weight: 700; color: #f1f5f9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .plan-badge {
            display: inline-block;
            padding: 2px 9px;
            background: rgba(99,102,241,0.2);
            color: #a5b4fc;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            border: 1px solid rgba(99,102,241,0.25);
        }
        .sidebar-nav { padding: 10px 0; flex: 1; overflow-y: auto; }
        .nav-label {
            padding: 10px 18px 4px;
            font-size: 0.65rem;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 18px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: background 0.12s, color 0.12s;
        }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.04); color: #e2e8f0; }
        .sidebar-nav a.active { background: rgba(99,102,241,0.12); color: #a5b4fc; border-left-color: #6366f1; font-weight: 600; }
        .sidebar-nav a.nav-external { font-size: 0.78rem; color: #334155; padding: 7px 18px; }
        .sidebar-nav a.nav-external:hover { color: #64748b; }
        .nav-divider { border-top: 1px solid rgba(255,255,255,0.05); margin: 6px 0; }
        .sidebar-footer {
            padding: 14px 18px;
            border-top: 1px solid rgba(255,255,255,0.06);
            font-size: 0.78rem;
            color: #334155;
        }
        .sidebar-footer strong { color: #64748b; }
        .sidebar-footer a { color: #475569; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; margin-top: 6px; font-size: 0.78rem; }
        .sidebar-footer a:hover { color: #94a3b8; }

        /* ── Main area ── */
        .main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 24px;
            height: 54px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 10;
        }
        .topbar-title { font-size: 0.95rem; font-weight: 700; color: #0f172a; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .topbar-user { font-size: 0.8rem; color: #94a3b8; }
        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .status-badge::before { content:''; width:6px; height:6px; border-radius:50%; background: currentColor; opacity: 0.6; }
        .content { padding: 28px; flex: 1; }

        /* ── Stat cards ── */
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 18px 20px;
            border-left: 4px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card--org   { border-color: #6366f1; }
        .card--plan  { border-color: #8b5cf6; }
        .card--users { border-color: #10b981; }
        .card--since { border-color: #f59e0b; }
        .card-label { font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; margin-bottom: 6px; }
        .card-value { font-size: 1.6rem; font-weight: 700; color: #0f172a; line-height: 1; }
        .card-value--sm { font-size: 1.1rem; margin-top: 2px; }
        .card-sub { font-size: 0.78rem; color: #94a3b8; margin-top: 5px; }
        .card-link { font-size: 0.78rem; color: #6366f1; text-decoration: none; font-weight: 600; }
        .card-link:hover { text-decoration: underline; }

        /* ── Section card ── */
        .section {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .section-header {
            padding: 13px 18px;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 600;
            font-size: 0.875rem;
            color: #0f172a;
            display: flex; align-items: center; justify-content: space-between;
        }
        .section-header a { font-size: 0.78rem; font-weight: 500; color: #6366f1; text-decoration: none; }
        .section-header a:hover { text-decoration: underline; }

        /* ── Table ── */
        .tbl { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .tbl thead th {
            padding: 10px 16px;
            text-align: left;
            background: #f8fafc;
            font-size: 0.7rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e2e8f0;
        }
        .tbl tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.1s; }
        .tbl tbody tr:last-child { border-bottom: none; }
        .tbl tbody tr:hover { background: #fafbfc; }
        .tbl td { padding: 11px 16px; color: #374151; vertical-align: middle; }
        .td-mono { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.8rem; color: #6366f1; }
        .td-muted { color: #94a3b8; font-size: 0.78rem; }

        /* ── Badges ── */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 9px; border-radius: 20px;
            font-size: 0.72rem; font-weight: 600;
        }
        .badge::before { content:''; width:5px; height:5px; border-radius:50%; }
        .badge-owner    { background: #e0e7ff; color: #3730a3; }
        .badge-owner::before  { background: #6366f1; }
        .badge-user     { background: #f1f5f9; color: #475569; }
        .badge-user::before   { background: #94a3b8; }
        .badge-active   { background: #d1fae5; color: #065f46; }
        .badge-active::before { background: #10b981; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .badge-inactive::before { background: #ef4444; }

        /* ── Plan features ── */
        .features { display: flex; gap: 14px; flex-wrap: wrap; padding: 18px 20px; }
        .feat-item { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: #374151; }
        .feat-on  { color: #10b981; font-weight: 700; }
        .feat-off { color: #cbd5e1; }
        .plan-limits { padding: 0 20px 16px; font-size: 0.78rem; color: #64748b; display: flex; gap: 18px; flex-wrap: wrap; }
        .plan-limits strong { color: #0f172a; }

        /* ── Alerts ── */
        .alert-suspended {
            padding: 13px 18px;
            background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px;
            color: #991b1b; font-size: 0.875rem; font-weight: 500;
            margin-bottom: 20px; display: flex; align-items: center; gap: 8px;
        }

        /* ── Loading / empty ── */
        .loading-state { padding: 48px; text-align: center; color: #94a3b8; font-size: 0.875rem; }
        .empty-state { padding: 28px; color: #94a3b8; font-size: 0.875rem; text-align: center; }

        @media (max-width: 700px) {
            .sidebar { width: 200px; }
            .content { padding: 16px; }
        }
        @media (max-width: 480px) {
            .app-body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; min-height: auto; position: static; }
        }
    </style>
</head>
<body>

<?php if ($impersonating): ?>
<div class="impersonate-bar">
    Viewing as tenant <strong><?php echo $name; ?></strong> —
    <a href="<?php echo $base; ?>/platform-admin/resume">Return to Platform Admin</a>
</div>
<?php endif; ?>

<div class="app-body">

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-row">
            <div class="sidebar-avatar" id="sidebar-avatar"><?php echo mb_strtoupper(mb_substr($name, 0, 1)); ?></div>
            <div class="sidebar-org"><?php echo $name; ?></div>
        </div>
        <span class="plan-badge" id="plan-badge">Loading…</span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Menu</div>
        <a href="<?php echo $base; ?>/app/<?php echo $slug; ?>/admin" id="nav-dashboard">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a href="<?php echo $base; ?>/app/<?php echo $slug; ?>/admin/users" id="nav-users">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Users
        </a>
        <div class="nav-divider"></div>
        <a href="<?php echo $base; ?>/app/<?php echo $slug; ?>" class="nav-external">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            View public site
        </a>
    </nav>

    <div class="sidebar-footer">
        Signed in as <strong><?php echo $username; ?></strong>
        <br>
        <a href="<?php echo $base; ?>/logout">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign out
        </a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <span class="topbar-title" id="page-title">Loading…</span>
        <div class="topbar-right">
            <span class="topbar-user"><?php echo $username; ?></span>
            <span class="status-badge" id="status-badge"></span>
        </div>
    </div>
    <div class="content" id="content-area">
        <div class="loading-state">Loading…</div>
    </div>
</div>

</div><!-- /.app-body -->

<script>
const BASE      = <?php echo json_encode($base); ?>;
let   TOKEN     = <?php echo json_encode($s['token']); ?>;
const TENANT_ID = <?php echo (int) $s['tenant_id']; ?>;
const SLUG      = <?php echo json_encode($slug); ?>;
const PATH      = window.location.pathname;

const statusColors = {
    active:    { bg: '#d1fae5', fg: '#065f46' },
    trial:     { bg: '#fef9c3', fg: '#92400e' },
    suspended: { bg: '#fee2e2', fg: '#991b1b' },
};

async function refreshToken() {
    try {
        const r = await fetch(BASE + '/auth/session-refresh', { method: 'POST' });
        const d = await r.json();
        if (d.success) { TOKEN = d.access_token; return true; }
    } catch (_) {}
    return false;
}

async function api(path) {
    const res = await fetch(BASE + path, {
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Content-Type': 'application/json' }
    });
    if (res.status === 401) {
        const ok = await refreshToken();
        if (!ok) { window.location.href = BASE + '/auth'; return null; }
        return (await fetch(BASE + path, {
            headers: { 'Authorization': 'Bearer ' + TOKEN, 'Content-Type': 'application/json' }
        })).json();
    }
    return res.json();
}

function isUsersPage() {
    return PATH.includes('/admin/users');
}

// ── nav active state ──────────────────────────────────────────────────────────
document.getElementById(isUsersPage() ? 'nav-users' : 'nav-dashboard').classList.add('active');

// ── render helpers ────────────────────────────────────────────────────────────

function renderDashboard(tenant, users) {
    document.getElementById('page-title').textContent = 'Dashboard';

    const features = tenant.plan_features ?? {};
    const limits   = tenant.plan_limits   ?? {};

    const featHtml = Object.keys(features).length ? `
        <div class="section">
            <div class="section-header">Plan Features</div>
            <div class="features">
                ${Object.entries(features).map(([k, v]) => `
                    <div class="feat-item">
                        <span class="${v ? 'feat-on' : 'feat-off'}">${v ? '✓' : '✗'}</span>
                        ${k.replace(/_/g, ' ')}
                    </div>`).join('')}
            </div>
            ${Object.keys(limits).length ? `
            <div class="plan-limits">
                ${Object.entries(limits).map(([k, v]) =>
                    `<span>${k.replace(/_/g, ' ')}: <strong>${v == 0 ? '∞' : v}</strong></span>`
                ).join('')}
            </div>` : ''}
        </div>` : '';

    const previewRows = users.slice(0, 5).map(u => `
        <tr>
            <td>${u.name ?? '—'}</td>
            <td class="td-mono">${u.username}</td>
            <td><span class="badge ${u.role === 'tenant_owner' ? 'badge-owner' : 'badge-user'}">${u.role === 'tenant_owner' ? 'Owner' : 'User'}</span></td>
            <td><span class="badge ${u.state ? 'badge-active' : 'badge-inactive'}">${u.state ? 'Active' : 'Inactive'}</span></td>
        </tr>`).join('');

    document.getElementById('content-area').innerHTML = `
        ${tenant.status === 'suspended' ? '<div class="alert-suspended"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Your account is <strong>suspended</strong>. Contact support.</div>' : ''}
        <div class="cards">
            <div class="card card--org">
                <div class="card-label">Organisation</div>
                <div class="card-value card-value--sm">${tenant.name}</div>
                <div class="card-sub">/${tenant.slug}</div>
            </div>
            <div class="card card--plan">
                <div class="card-label">Plan</div>
                <div class="card-value">${tenant.plan_name ?? '—'}</div>
                <div class="card-sub">$${parseFloat(tenant.plan_price ?? 0).toFixed(2)}/mo</div>
            </div>
            <div class="card card--users">
                <div class="card-label">Users</div>
                <div class="card-value">${users.length}</div>
                <a class="card-link" href="${BASE}/app/${SLUG}/admin/users">Manage →</a>
            </div>
            <div class="card card--since">
                <div class="card-label">Member since</div>
                <div class="card-value card-value--sm">${(tenant.created_at ?? '').slice(0, 10)}</div>
            </div>
        </div>
        ${featHtml}
        ${previewRows ? `
        <div class="section">
            <div class="section-header">
                Team Members
                <a href="${BASE}/app/${SLUG}/admin/users">View all →</a>
            </div>
            <table class="tbl">
                <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th></tr></thead>
                <tbody>${previewRows}</tbody>
            </table>
        </div>` : ''}`;
}

function renderUsers(users) {
    document.getElementById('page-title').textContent = 'Users';

    const rows = users.map(u => `
        <tr>
            <td>${u.name ?? '—'}</td>
            <td class="td-mono">${u.username}</td>
            <td class="td-muted">${u.email ?? '—'}</td>
            <td><span class="badge ${u.role === 'tenant_owner' ? 'badge-owner' : 'badge-user'}">${u.role === 'tenant_owner' ? 'Owner' : 'User'}</span></td>
            <td><span class="badge ${u.state ? 'badge-active' : 'badge-inactive'}">${u.state ? 'Active' : 'Inactive'}</span></td>
            <td class="td-muted">${(u.stamp ?? '').slice(0, 10)}</td>
        </tr>`).join('');

    document.getElementById('content-area').innerHTML = `
        <div class="section">
            <div class="section-header">Team Members — ${users.length} total</div>
            ${rows ? `
            <table class="tbl">
                <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Since</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>` : '<div class="empty-state">No users yet.</div>'}
        </div>`;
}

// ── init ──────────────────────────────────────────────────────────────────────

async function init() {
    const [tenantRes, usersRes] = await Promise.all([
        api(`/api/tenants/${TENANT_ID}`),
        api(`/api/tenants/${TENANT_ID}/users`),
    ]);

    if (!tenantRes.success) {
        document.getElementById('content-area').innerHTML = '<div class="loading-state" style="color:#ef4444">Failed to load tenant data.</div>';
        return;
    }

    const tenant = tenantRes.data;
    const users  = usersRes.success ? usersRes.data : [];

    // Sidebar plan badge
    document.getElementById('plan-badge').textContent = tenant.plan_name ?? 'Free';

    // Topbar status badge
    const sc = statusColors[tenant.status] ?? { bg: '#f1f5f9', fg: '#374151' };
    const sb = document.getElementById('status-badge');
    sb.textContent = tenant.status;
    sb.style.background = sc.bg;
    sb.style.color = sc.fg;

    isUsersPage() ? renderUsers(users) : renderDashboard(tenant, users);
}

init();
</script>
</body>
</html>
<?php
    }

    // ── Public frontend (no auth) ────────────────────────────────────────────

    public function renderPublic(string $slug): void
    {
        $base     = BASE_URL;
        $safeSlug = htmlspecialchars($slug);
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="page-title">Loading…</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #fff; color: #1e293b; }

        /* ── Nav ── */
        .pub-nav {
            position: sticky; top: 0; z-index: 10;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid #e2e8f0;
            padding: 0 32px; height: 60px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .pub-nav-brand { font-size: 1rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px; }
        .pub-nav-brand-initial {
            width: 30px; height: 30px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; font-weight: 800; color: #fff;
        }
        .btn-signin {
            background: #0f172a; color: #fff;
            padding: 7px 18px; border-radius: 7px;
            font-size: 0.85rem; font-weight: 600;
            text-decoration: none;
            transition: background 0.15s;
        }
        .btn-signin:hover { background: #1e293b; }

        /* ── Suspended banner ── */
        .suspended-banner {
            background: #fee2e2; border-bottom: 1px solid #fca5a5;
            padding: 12px 32px; text-align: center;
            color: #991b1b; font-size: 0.875rem; font-weight: 500;
            display: none;
        }

        /* ── Hero ── */
        .hero {
            background: linear-gradient(145deg, #0f172a 0%, #1e293b 50%, #0f3460 100%);
            color: #fff; padding: 80px 32px 72px; text-align: center;
        }
        .hero-avatar {
            width: 72px; height: 72px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; font-weight: 800; color: #fff;
            margin: 0 auto 24px;
        }
        .hero h1 { font-size: 2.2rem; font-weight: 800; margin-bottom: 12px; letter-spacing: -0.5px; }
        .hero-sub { font-size: 1rem; color: #94a3b8; margin-bottom: 28px; }
        .status-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 14px; border-radius: 20px;
            font-size: 0.82rem; font-weight: 600; margin-bottom: 28px;
        }
        .status-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: 0.7; }
        .hero-cta {
            display: inline-block;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff; padding: 13px 32px; border-radius: 8px;
            font-size: 0.95rem; font-weight: 600; text-decoration: none;
            box-shadow: 0 4px 16px rgba(99,102,241,0.35);
            transition: opacity 0.15s;
        }
        .hero-cta:hover { opacity: 0.88; }

        /* ── Features ── */
        .features-section { padding: 72px 32px; background: #f8fafc; }
        .features-section h2 { text-align: center; font-size: 1.5rem; font-weight: 700; margin-bottom: 48px; color: #0f172a; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; max-width: 900px; margin: 0 auto; }
        .feat-card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
            padding: 28px 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: box-shadow 0.15s;
        }
        .feat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
        .feat-icon {
            width: 42px; height: 42px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
        }
        .feat-icon--purple { background: #ede9fe; color: #7c3aed; }
        .feat-icon--green  { background: #d1fae5; color: #059669; }
        .feat-icon--blue   { background: #dbeafe; color: #2563eb; }
        .feat-card h3 { font-size: 0.95rem; font-weight: 700; margin-bottom: 8px; color: #0f172a; }
        .feat-card p  { font-size: 0.85rem; color: #64748b; line-height: 1.65; }

        /* ── Footer ── */
        .pub-footer {
            padding: 24px 32px;
            border-top: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 0.78rem; color: #94a3b8;
        }
        .pub-footer-brand { font-weight: 600; color: #64748b; }

        @media (max-width: 640px) {
            .hero h1 { font-size: 1.6rem; }
            .pub-nav, .pub-footer { padding: 0 16px; }
            .features-section { padding: 48px 16px; }
        }
    </style>
</head>
<body>

<div id="suspended-banner" class="suspended-banner">
    This account is currently <strong>suspended</strong>. Please contact support.
</div>

<header class="pub-nav">
    <div class="pub-nav-brand">
        <div class="pub-nav-brand-initial" id="nav-initial">…</div>
        <span id="nav-brand">…</span>
    </div>
    <a href="<?php echo $base; ?>/auth" class="btn-signin">Sign in</a>
</header>

<section class="hero">
    <div class="hero-avatar" id="hero-avatar">…</div>
    <h1 id="hero-name">…</h1>
    <p class="hero-sub">Sign in to access your account and dashboard.</p>
    <div><span class="status-pill" id="status-pill"></span></div>
    <br>
    <a href="<?php echo $base; ?>/auth" class="hero-cta" id="hero-cta">Sign in to your account &rarr;</a>
</section>

<section class="features-section">
    <h2>Everything you need</h2>
    <div class="features-grid">
        <div class="feat-card">
            <div class="feat-icon feat-icon--purple">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            </div>
            <h3>Analytics Dashboard</h3>
            <p>A clear overview of your activity, users, and key metrics in one place.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon feat-icon--green">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <h3>Team Management</h3>
            <p>Invite team members, assign roles, and manage access across your organisation.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon feat-icon--blue">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <h3>Secure &amp; Reliable</h3>
            <p>Role-based access control, secure sessions, and isolated multi-tenant data.</p>
        </div>
    </div>
</section>

<footer class="pub-footer">
    <span class="pub-footer-brand" id="footer-name">…</span>
    <span>Powered by upMVC SaaS</span>
</footer>

<script>
const BASE = <?php echo json_encode($base); ?>;
const SLUG = <?php echo json_encode($safeSlug); ?>;

const statusColorMap = {
    active:    { bg: '#d1fae5', fg: '#065f46' },
    trial:     { bg: '#fef9c3', fg: '#92400e' },
    suspended: { bg: '#fee2e2', fg: '#991b1b' },
};

async function init() {
    const res  = await fetch(BASE + '/api/public/tenants/' + SLUG);
    const data = await res.json();

    if (!data.success || !data.data) {
        document.getElementById('hero-name').textContent = 'Tenant not found';
        document.getElementById('hero-cta').style.display = 'none';
        document.getElementById('hero-avatar').textContent = '?';
        return;
    }

    const t       = data.data;
    const initial = t.name.charAt(0).toUpperCase();
    const sc      = statusColorMap[t.status] ?? { bg: '#f1f5f9', fg: '#374151' };

    document.title                                        = t.name;
    document.getElementById('nav-initial').textContent   = initial;
    document.getElementById('nav-brand').textContent     = t.name;
    document.getElementById('hero-avatar').textContent   = initial;
    document.getElementById('hero-name').textContent     = t.name;
    document.getElementById('footer-name').textContent   = t.name;

    const pill = document.getElementById('status-pill');
    pill.textContent       = t.status;
    pill.style.background  = sc.bg;
    pill.style.color       = sc.fg;

    if (t.status === 'suspended') {
        document.getElementById('suspended-banner').style.display = 'block';
        document.getElementById('hero-cta').style.display = 'none';
    }
}

init();
</script>
</body>
</html>
<?php
    }
}
