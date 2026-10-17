# upMVC-SaaS-Pack — Agent context

You are in the **installable SaaS pack** for upMVC — not a standalone app and not the kernel.

## Load first (this repo)

- `README.md` — tenancy + Harvest overview
- `docs/09-harvest-ops.md` — mail, notify registry, audit, TOTP, Attention, dual-unseal
- `docs/SAAS_NEXT.md` — compose CLI (`bin/saas-next.php`) + `saas-kit.json` discovery
- `CHANGELOG.md` — what shipped
- `src/Saas/SaasServiceProvider.php` — module/migration/middleware wiring
- `saas-kit.json` — manifest for saas-next / future packs

## Also load (from upMVC, when editing agent behaviour)

Full agent machine knowledge for SaaS lives in the **kernel repo**, not here:

- `upMVC/docs/agent/upmvc-saas-pack.json`
- `upMVC/docs/AGENT_PACK.md`

Do not invent a second AGENT_PACK JSON tree inside this pack. Update `upmvc-saas-pack.json` in upMVC when Harvest / tenancy contracts change; keep human truth in this repo’s `docs/`.

## Must

1. Prefer library code under `src/Saas/*` over stuffing domain into pack modules.
2. Keep notify **types** out of the pack — only registry + gate + fire.
3. After seal / security changes: `php vendor/bin/phpunit` (incl. dual-unseal in `HarvestKernelTest`).
4. Dual-unseal stays until known consumers re-saved secrets — never drop `|smtp-password-v1` / `mycmms-totp|` without a migration plan.
5. Do not re-add `final` on Harvest kernel classes while products thin-extend them.

## Never

- Commit `vault/`, `.env`, or live credentials.
- Put product-only domain (WO/PM/stock, etc.) into this pack.
- Treat starter shells as the only API surface; products override `App\Modules\...`.
