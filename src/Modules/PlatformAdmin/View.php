<?php

namespace App\Modules\PlatformAdmin;

class View
{
    public function render(): void
    {
        $base     = BASE_URL;
        $username = htmlspecialchars($_SESSION['username'] ?? 'Admin');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; color: #1e293b; min-height: 100vh; }

        /* ── Header ── */
        .pa-header {
            background: #0f172a;
            padding: 0 32px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .pa-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: -0.2px;
        }
        .pa-logo-badge {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            border-radius: 6px;
            padding: 3px 9px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .pa-logo-sep { color: #334155; }
        .pa-header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .pa-user {
            font-size: 0.82rem;
            color: #94a3b8;
        }
        .pa-user strong { color: #e2e8f0; }
        .pa-logout {
            font-size: 0.82rem;
            color: #94a3b8;
            text-decoration: none;
            padding: 5px 12px;
            border: 1px solid #1e293b;
            border-radius: 6px;
            transition: border-color 0.15s, color 0.15s;
        }
        .pa-logout:hover { border-color: #475569; color: #e2e8f0; }

        /* ── Page shell ── */
        .pa-main { max-width: 1200px; margin: 0 auto; padding: 32px 24px; }

        /* ── Page title row ── */
        .pa-title-row {
            display: flex;
            align-items: baseline;
            gap: 12px;
            margin-bottom: 28px;
        }
        .pa-title-row h1 { font-size: 1.4rem; font-weight: 700; color: #0f172a; letter-spacing: -0.3px; }
        .pa-tenant-count {
            font-size: 0.8rem;
            background: #e0e7ff;
            color: #4338ca;
            border-radius: 20px;
            padding: 2px 10px;
            font-weight: 600;
        }

        /* ── Stat cards ── */
        .pa-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .pa-stat {
            background: #fff;
            border-radius: 10px;
            padding: 18px 20px;
            border-left: 4px solid transparent;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .pa-stat--total  { border-color: #6366f1; }
        .pa-stat--active { border-color: #10b981; }
        .pa-stat--trial  { border-color: #f59e0b; }
        .pa-stat--susp   { border-color: #ef4444; }
        .pa-stat-label { font-size: 0.72rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px; }
        .pa-stat-value { font-size: 1.75rem; font-weight: 700; color: #0f172a; line-height: 1; }

        /* ── Flash toast ── */
        .pa-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            display: none;
            z-index: 9000;
            max-width: 340px;
            animation: slideUp 0.2s ease;
        }
        .pa-toast--success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .pa-toast--error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        @keyframes slideUp { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }

        /* ── Card / table wrapper ── */
        .pa-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .pa-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .pa-card-title { font-size: 0.9rem; font-weight: 600; color: #0f172a; }
        .pa-card-sub   { font-size: 0.78rem; color: #94a3b8; }

        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        thead th {
            text-align: left;
            padding: 11px 16px;
            background: #f8fafc;
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.1s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fafbfc; }
        td { padding: 12px 16px; color: #374151; vertical-align: middle; }
        td.td-id    { color: #94a3b8; font-size: 0.8rem; width: 48px; }
        td.td-slug  { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.82rem; color: #6366f1; }
        td.td-name  { font-weight: 500; color: #0f172a; }
        td.td-date  { color: #94a3b8; font-size: 0.8rem; white-space: nowrap; }

        /* ── Status badge ── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        .badge::before { content:''; width:6px; height:6px; border-radius:50%; }
        .badge--active   { background:#d1fae5; color:#065f46; }
        .badge--active::before { background:#10b981; }
        .badge--trial    { background:#fef9c3; color:#854d0e; }
        .badge--trial::before  { background:#f59e0b; }
        .badge--suspended { background:#fee2e2; color:#991b1b; }
        .badge--suspended::before { background:#ef4444; }

        /* ── Action buttons ── */
        .btn-group { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn {
            padding: 4px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 600;
            transition: opacity 0.15s, transform 0.1s;
        }
        .btn:hover  { opacity: 0.85; }
        .btn:active { transform: scale(0.97); }
        .btn--edit  { background: #e0e7ff; color: #4338ca; }
        .btn--login { background: #ede9fe; color: #6d28d9; }

        /* ── Empty / loading state ── */
        .pa-empty {
            padding: 48px 24px;
            text-align: center;
            color: #94a3b8;
            font-size: 0.9rem;
        }

        /* ── Modal ── */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.5);
            backdrop-filter: blur(2px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-backdrop.is-open { display: flex; }
        .modal {
            background: #fff;
            border-radius: 14px;
            padding: 28px 32px;
            width: 440px;
            max-width: 94vw;
            box-shadow: 0 20px 60px rgba(0,0,0,0.18);
            animation: modalIn 0.18s ease;
        }
        @keyframes modalIn { from { opacity:0; transform:scale(0.96) translateY(-8px); } to { opacity:1; transform:none; } }
        .modal-title { font-size: 1.05rem; font-weight: 700; color: #0f172a; margin-bottom: 22px; }
        .modal-field { margin-bottom: 16px; }
        .modal-field label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }
        .modal-field input,
        .modal-field select {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #0f172a;
            background: #f8fafc;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .modal-field input:focus,
        .modal-field select:focus {
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }
        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        .btn-cancel {
            padding: 8px 20px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            color: #374151;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: border-color 0.15s;
        }
        .btn-cancel:hover { border-color: #94a3b8; }
        .btn-save {
            padding: 8px 20px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(99,102,241,0.3);
            transition: opacity 0.15s;
        }
        .btn-save:hover { opacity: 0.88; }

        @media (max-width: 640px) {
            .pa-stats { grid-template-columns: repeat(2, 1fr); }
            .pa-header { padding: 0 16px; }
            .pa-main { padding: 20px 12px; }
        }
    </style>
</head>
<body>

<header class="pa-header">
    <div class="pa-logo">
        <span class="pa-logo-badge">upMVC</span>
        <span class="pa-logo-sep">/</span>
        Platform Admin
    </div>
    <div class="pa-header-right">
        <span class="pa-user">Signed in as <strong><?php echo $username; ?></strong></span>
        <a href="<?php echo $base; ?>/logout" class="pa-logout">Sign out</a>
    </div>
</header>

<main class="pa-main">

    <div class="pa-title-row">
        <h1>Tenants</h1>
        <span class="pa-tenant-count" id="tenant-count"></span>
    </div>

    <div class="pa-stats">
        <div class="pa-stat pa-stat--total">
            <div class="pa-stat-label">Total</div>
            <div class="pa-stat-value" id="stat-total">—</div>
        </div>
        <div class="pa-stat pa-stat--active">
            <div class="pa-stat-label">Active</div>
            <div class="pa-stat-value" id="stat-active">—</div>
        </div>
        <div class="pa-stat pa-stat--trial">
            <div class="pa-stat-label">Trial</div>
            <div class="pa-stat-value" id="stat-trial">—</div>
        </div>
        <div class="pa-stat pa-stat--susp">
            <div class="pa-stat-label">Suspended</div>
            <div class="pa-stat-value" id="stat-susp">—</div>
        </div>
    </div>

    <div class="pa-card">
        <div class="pa-card-header">
            <span class="pa-card-title">All Tenants</span>
            <span class="pa-card-sub" id="last-updated"></span>
        </div>
        <div id="tenants-table">
            <div class="pa-empty">Loading tenants…</div>
        </div>
    </div>

</main>

<!-- Edit modal -->
<div id="edit-modal" class="modal-backdrop">
    <div class="modal">
        <div class="modal-title">Edit Tenant</div>
        <div class="modal-field">
            <label>Name</label>
            <input id="edit-name" type="text" placeholder="Tenant name">
        </div>
        <div class="modal-field">
            <label>Status</label>
            <select id="edit-status">
                <option value="active">Active</option>
                <option value="trial">Trial</option>
                <option value="suspended">Suspended</option>
            </select>
        </div>
        <div class="modal-field">
            <label>Plan</label>
            <select id="edit-plan"></select>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeEdit()">Cancel</button>
            <button class="btn-save"   onclick="saveEdit()">Save changes</button>
        </div>
    </div>
</div>

<!-- Hidden form: POST impersonation token to PHP session-swap endpoint -->
<form id="assume-form" action="<?php echo $base; ?>/platform-admin/assume" method="POST" style="display:none;">
    <input type="hidden" name="token" id="assume-token">
    <input type="hidden" name="csrf_token" value="<?php echo \App\Etc\Security::csrfToken(); ?>">
</form>

<div id="toast" class="pa-toast"></div>

<script>
const BASE  = <?php echo json_encode($base); ?>;
let   token = <?php echo json_encode($_SESSION['jwt_token'] ?? ''); ?>;

let plans     = [];
let editingId = null;

// ── API helper ───────────────────────────────────────────────────────────────

async function refreshToken() {
    try {
        const r = await fetch(BASE + '/auth/session-refresh', { method: 'POST' });
        const d = await r.json();
        if (d.success) { token = d.access_token; return true; }
    } catch (_) {}
    return false;
}

async function api(path, opts = {}) {
    const headers = { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json', ...(opts.headers || {}) };
    const res = await fetch(BASE + path, { ...opts, headers });
    if (res.status === 401) {
        const ok = await refreshToken();
        if (!ok) { window.location.href = BASE + '/auth'; return null; }
        const headers2 = { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json', ...(opts.headers || {}) };
        return (await fetch(BASE + path, { ...opts, headers: headers2 })).json();
    }
    return res.json();
}

// ── Toast ────────────────────────────────────────────────────────────────────

let toastTimer = null;
function flash(msg, type) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className   = 'pa-toast pa-toast--' + type;
    el.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.style.display = 'none'; }, 4000);
}

// ── Load plans ───────────────────────────────────────────────────────────────

async function loadPlans() {
    const data = await api('/api/plans');
    plans = Array.isArray(data) ? data : (data?.data ?? []);
    const sel = document.getElementById('edit-plan');
    sel.innerHTML = plans.map(p =>
        `<option value="${p.id}">${p.name} — $${p.price}/mo</option>`
    ).join('');
}

// ── Load tenants ─────────────────────────────────────────────────────────────

async function loadTenants() {
    const data = await api('/api/admin/tenants');
    if (!data.success) { flash('Failed to load tenants', 'error'); return; }

    const tenants = data.data.tenants ?? [];
    const total   = data.data.total   ?? tenants.length;

    // Stats
    const counts = { active: 0, trial: 0, suspended: 0 };
    tenants.forEach(t => { if (counts[t.status] !== undefined) counts[t.status]++; });
    document.getElementById('tenant-count').textContent = total + ' tenants';
    document.getElementById('stat-total').textContent  = total;
    document.getElementById('stat-active').textContent = counts.active;
    document.getElementById('stat-trial').textContent  = counts.trial;
    document.getElementById('stat-susp').textContent   = counts.suspended;

    const now = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    document.getElementById('last-updated').textContent = 'Updated ' + now;

    if (!tenants.length) {
        document.getElementById('tenants-table').innerHTML = '<div class="pa-empty">No tenants yet.</div>';
        return;
    }

    const rows = tenants.map(t => {
        const badgeClass = 'badge badge--' + (t.status === 'suspended' ? 'suspended' : t.status);
        const nameEsc    = (t.name ?? '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        return `<tr>
            <td class="td-id">${t.id}</td>
            <td class="td-slug">${t.slug}</td>
            <td class="td-name">${t.name ?? '—'}</td>
            <td><span class="${badgeClass}">${t.status}</span></td>
            <td>${t.plan_name ?? '—'}</td>
            <td class="td-date">${(t.created_at ?? '').slice(0, 10)}</td>
            <td>
                <div class="btn-group">
                    <button class="btn btn--edit"
                        onclick="openEdit(${t.id}, '${nameEsc}', '${t.status}', ${t.plan_id ?? 'null'})">
                        Edit
                    </button>
                    <button class="btn btn--login" onclick="impersonate(${t.id})">
                        Login as
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');

    document.getElementById('tenants-table').innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Slug</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Plan</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>`;
}

// ── Edit modal ───────────────────────────────────────────────────────────────

function openEdit(id, name, status, planId) {
    editingId = id;
    document.getElementById('edit-name').value   = name;
    document.getElementById('edit-status').value = status;
    if (planId) document.getElementById('edit-plan').value = planId;
    document.getElementById('edit-modal').classList.add('is-open');
}

function closeEdit() {
    document.getElementById('edit-modal').classList.remove('is-open');
    editingId = null;
}

async function saveEdit() {
    if (!editingId) return;
    const body = {
        name:    document.getElementById('edit-name').value.trim(),
        status:  document.getElementById('edit-status').value,
        plan_id: parseInt(document.getElementById('edit-plan').value, 10),
    };
    const data = await api(`/api/admin/tenants/${editingId}`, {
        method: 'PATCH',
        body: JSON.stringify(body),
    });
    if (data.success) {
        flash('Tenant updated', 'success');
        closeEdit();
        loadTenants();
    } else {
        flash(data.error ?? 'Update failed', 'error');
    }
}

document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});

// ── Impersonate ──────────────────────────────────────────────────────────────

async function impersonate(tenantId) {
    const data = await api('/api/admin/impersonate', {
        method: 'POST',
        body: JSON.stringify({ tenant_id: tenantId }),
    });
    if (!data.success) { flash(data.error ?? 'Impersonate failed', 'error'); return; }
    document.getElementById('assume-token').value = data.data.access_token;
    document.getElementById('assume-form').submit();
}

// ── Init ─────────────────────────────────────────────────────────────────────

loadPlans();
loadTenants();
</script>
</body>
</html>
        <?php
    }
}
