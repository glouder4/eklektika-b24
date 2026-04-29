# Progress: R9 portal runtime local settings policy

- Initiative: eliminate `getenv` usage from portal `local/` runtime; document policy
- Last updated: 2026-04-30 (delivery-chain **step 7** — Tech Lead finalize after code + docs + audit)

## Done

- ADR **R9** accepted: no `getenv` / `.env` on portal; `site_sync_settings.local.php` canonical for toggles; optional documented PHP constants where applicable.
- Implementation facts (merged in working tree): `SiteConnectorLocalSettings`, inbound override via `allow_inbound_without_secret`, deals/cron `deals_fallback_on_mismatch` from local settings; docs/example updated per initiative notes.
- Static scan (2026-04-30): `getenv(` in `local/**/*.php` — **0 matches** (workspace indexer search).
- **R9-SUB-01** evidence recorded in `subtasks/r9-sub-01-static-verify-no-getenv.md` (includes commit hash + search method note).
- Task **R9-PORTAL-RUNTIME-LOCAL-SETTINGS-POLICY** marked `done`; subtasks R9-SUB-01 / R9-SUB-02 closed including Team Lead scope signoff.

## Open

- None required for R9 closure.

## Optional backlog

- CI grep gate for `getenv(` scoped to owned paths (see ADR risks).

## Blockers

- None.
