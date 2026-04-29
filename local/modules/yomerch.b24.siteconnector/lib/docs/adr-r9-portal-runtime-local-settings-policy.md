# ADR: R9 portal runtime configuration — no getenv / no .env

- Status: accepted (closed — delivery-chain step 7, 2026-04-30: code + docs + audit)
- Date: 2026-04-30
- Short id: **R9**
- Scope: Bitrix portal tree `local/` runtime for `yomerch.b24.*` integration — how deploy-specific toggles and secrets are supplied
- Related task: `tasks/r9-portal-runtime-local-settings-policy-task.md`
- Related ADRs: `adr-r8-inbound-execution-parity.md` (inbound dev override and secret resolution align with this policy)

## Context

Bitrix24 portal PHP often runs under restrictions where process environment variables are unreliable, opaque to operators, and easy to diverge from documented integration contracts. Historically some code paths used `getenv()` for toggles (for example inbound “allow without secret” and deals legacy fallback). That couples behavior to web server / FPM env configuration instead of the integration’s documented settings surface.

Operators already maintain a non-committed PHP return-array file: `local/modules/yomerch.b24.siteconnector/site_sync_settings.local.php`, documented by `site_sync_settings.local.example.php`.

## Decision

1. **Do not** use `getenv()`, `$_ENV` for integration toggles, or PHP dotenv loaders (e.g. `vlucas/phpdotenv`) anywhere under portal `local/` for `yomerch.b24.*` runtime behavior.
2. **Canonical source** for deploy-local toggles and non-secret configuration that must vary by environment: `site_sync_settings.local.php`, loaded in a controlled way (e.g. `OnlineService\Sync\SiteConnectorLocalSettings::load()` from `yomerch.b24.base`).
3. **Optional PHP constants** defined in `php_interface` or other bootstrap that is explicitly deployed (e.g. `define('…', true)`) may precede or override file-based defaults only where documented — constants are for build-time / deploy-time wiring that must not live in git-tracked defaults; the file remains the operator-facing checklist.
4. **Secrets** stay in the same local settings file (or other established secret channels documented per module), not in `.env` files consumed at runtime on the portal.

**Audited implementation (R9):** inbound empty-secret dev path is gated solely by truthy `allow_inbound_without_secret` in `site_sync_settings.local.php` (no env). Deals legacy fallback-on-mismatch reads `deals_fallback_on_mismatch` from the same local settings via `SiteConnectorLocalSettings::load()` only after an optional documented `define('YOMERRCH24_DEALS_FALLBACK_ON_MISMATCH', …)` is absent — cron mirror in `local/cron/check_deals_status.php` matches.

## Consequences

- All new feature flags and integration toggles must extend `site_sync_settings.local.example.php` and Tier A/B docs; code reads the array loader, not the environment.
- CI or audit scripts can enforce the policy with a repository grep for `getenv(` / dotenv under `local/` (allow-list only in documentation that quotes the policy).
- Slight migration cost: any host that relied on env vars must move values into `site_sync_settings.local.php`.

## Risks

- Operators who relied on `getenv` must migrate settings once; misconfiguration shows as “default off” rather than silent env inheritance.
- Grep-based checks must exclude third-party `local/` subtrees if vendored code appears in the future (prefer scoping to `local/modules/yomerch.b24.*` and `local/cron` owned paths).

## Rollback

- Re-introducing `getenv` would be an explicit ADR amendment; rollback of *this* ADR is not a code revert — it would require documenting a new exception and threat model. Prefer fixing deployment to use the local settings file.
