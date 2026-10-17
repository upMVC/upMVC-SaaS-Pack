# Agent context — composed upMVC SaaS host

This app uses **Composer libraries**, not a forked kernel:

- `bitshost/upmvc` — kernel
- `bitshost/upmvc-saas-pack` — tenancy + Harvest
- optional: `bitshost/upmvc-payments-pack` (and other packs with `saas-kit.json`)

## Before coding

```bash
php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php doctor
php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php agent --goal "…"
```

Paste `docs/agent/generated/saas-last-prompt.md` into the chat.

Human setup guide (kernel repo): `docs/BUILD_SAAS_APP.md` on upMVC.

## Must

1. Scope tenant data with `tenant_id`
2. Domain modules under `src/Modules/Api/Modules/{Domain}/`
3. Prefer pack libraries (MailGateway, NotifyRegistry, …) — do not copy them into the app
4. Register product notify types in a host provider — pack registry stays empty of domain keys

## Never

- Commit `.env`, vault secrets, or live payment keys
- Put vertical-only logic into saas-pack / payments-pack
