# saas-next — compose helper for host apps

CLI shipped with **upMVC-SaaS-Pack**. Run it from a **host** project that uses the packs as Composer libraries (not from inside a kernel fork).

```bash
php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php
php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php doctor
php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php init --yes
php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php agent --goal "Tenant invoices API"
php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php scaffold-domain Invoices
```

## Pack discovery

Each BitsHost pack may ship a root **`saas-kit.json`**. `saas-next` scans:

```text
vendor/*/*/saas-kit.json
```

So **payments** (and future packs) show up in `doctor` / `packs` / `agent` without rewriting the CLI — drop a manifest in the new pack.

| Pack | Manifest |
|------|----------|
| upmvc-saas-pack | `saas-kit.json` (required floor) |
| upmvc-payments-pack | `saas-kit.json` (`optional: true`) |

## Host AGENTS.md (minimal)

Copy the idea from `docs/templates/AGENTS.host.md` into your host repo so Cursor loads compose rules.

## Teach to fish

`scaffold-domain` creates an **empty** `Api/Modules/{Domain}` tree only. It never pastes product domain logic.
