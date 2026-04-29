# Task: R9 portal runtime — local settings policy (no getenv)

- Task ID: `R9-PORTAL-RUNTIME-LOCAL-SETTINGS-POLICY`
- Status: `done` (closed 2026-04-30 — delivery-chain step 7 after code, docs, audit)
- Priority: medium (cross-cutting hygiene; supports R8 operator model)
- Owner: Team Lead + portal integrators
- ADR: `../adr-r9-portal-runtime-local-settings-policy.md`
- Progress: `../progress-r9-portal-runtime-local-settings-policy.md`

## Goal

Eliminate `getenv` / `.env`-style runtime configuration from portal `local/` integration code; make `site_sync_settings.local.php` the canonical operator-controlled surface; document and verify with repeatable evidence.

## Inputs

- ADR R9
- Code: `yomerch.b24.base` (`SiteConnectorLocalSettings`), `yomerch.b24.inbound` (`InboundSecurity`, `site_requests_handler.php`), `yomerch.b24.deals`, `local/cron/check_deals_status.php`
- Example: `../../../../yomerch.b24.siteconnector/site_sync_settings.local.example.php`
- External contract tree (when hydrated): `../../../../../bitrix24-external-developers/` — policy prose where relevant

## Subtasks tree

- [x] `R9-SUB-01` Static verification — zero getenv in owned paths
  - Subtask doc: `../subtasks/r9-sub-01-static-verify-no-getenv.md`
  - Goal: attach stand/repo evidence that owned PHP under `local/` has no `getenv(` (and no new dotenv usage).
  - DoD: command + zero-match output (or CI log) stored in subtask or progress; scope documented.
  - Status: `done` (2026-04-30 — evidence in subtask; Team Lead signoff completed at step 7 close).
  - Risks: future vendor code under `local/` may need path-scoped rules.

- [x] `R9-SUB-02` ADR + example + Tier A/B alignment
  - Subtask doc: `../subtasks/r9-sub-02-docs-and-example-alignment.md`
  - Goal: R9 ADR linked from README; example file documents keys moved off getenv (`allow_inbound_without_secret`, `deals_fallback_on_mismatch`, etc.).
  - DoD: README lists R9; example and contract docs match implementation.
  - Status: `done` (2026-04-30 — verified at delivery-chain step 7 audit).
  - Risks: doc drift if new toggles added without example updates.

## Current assessment

- Policy is recorded in ADR R9; R8 cross-references this policy for inbound overrides.
- Implementation matches ADR: no `getenv` under scoped `local/` PHP; inbound uses `allow_inbound_without_secret` only from `site_sync_settings.local.php`; deals/cron use optional `YOMERRCH24_DEALS_FALLBACK_ON_MISMATCH` then `deals_fallback_on_mismatch` from local settings.
- **R9-SUB-01** evidence attached; initiative closed at step 7.
- Optional follow-up (non-blocking): CI grep gate scoped to owned paths (`yomerch.b24.*`, `local/cron`).
