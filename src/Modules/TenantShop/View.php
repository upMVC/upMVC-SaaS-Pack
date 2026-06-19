<?php

namespace App\Modules\TenantShop;

class View
{
    public function storefront(string $slug): void
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
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; color: #1e293b; }
        header { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
        header h1 { font-size: 1.5rem; font-weight: 700; }
        header a { color: #6366f1; text-decoration: none; font-weight: 500; font-size: .9rem; }
        .main { max-width: 900px; margin: 48px auto; padding: 0 24px; }
        .placeholder { background: #fff; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 60px 24px; text-align: center; color: #94a3b8; }
        .placeholder h2 { font-size: 1.25rem; margin-bottom: 8px; color: #64748b; }
        .placeholder p { font-size: .9rem; }
        .suspended { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; border-radius: 8px; padding: 16px 24px; margin-bottom: 24px; text-align: center; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: .85rem; }
    </style>
</head>
<body>

<header>
    <h1 id="shop-name">…</h1>
    <a href="<?php echo $base; ?>/app/<?php echo $safeSlug; ?>">Tenant Login &rarr;</a>
</header>

<div class="main">
    <div id="suspended-notice" style="display:none" class="suspended">
        ⚠️ This shop is currently <strong>suspended</strong>.
    </div>
    <div class="placeholder" id="storefront-body">
        <h2>Loading…</h2>
    </div>
</div>

<script>
const BASE = <?php echo json_encode($base); ?>;
const SLUG = <?php echo json_encode($safeSlug); ?>;

async function init() {
    const res  = await fetch(BASE + '/api/public/tenants/' + SLUG);
    const data = await res.json();

    if (!data.success || !data.data) {
        document.getElementById('shop-name').textContent = 'Shop not found';
        document.getElementById('storefront-body').innerHTML =
            '<h2>404 — Not found</h2><p>No active storefront for <strong>' + SLUG + '</strong>.</p>';
        return;
    }

    const t = data.data;
    document.title = t.name;
    document.getElementById('page-title').textContent  = t.name;
    document.getElementById('shop-name').textContent   = t.name;

    if (t.status === 'suspended') {
        document.getElementById('suspended-notice').style.display = 'block';
    }

    document.getElementById('storefront-body').innerHTML = `
        <h2>Storefront — ${t.name}</h2>
        <p style="margin-top:10px">Plan: <strong>${t.plan_name ?? 'Free'}</strong> &nbsp;|&nbsp; Status: <strong>${t.status}</strong></p>
        <p style="margin-top:20px">Add your product listings, categories, and checkout logic here.</p>`;
}

init();
</script>
</body>
</html>
<?php
    }
}
