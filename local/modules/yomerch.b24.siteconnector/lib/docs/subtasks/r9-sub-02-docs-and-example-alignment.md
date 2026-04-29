# R9-SUB-02: Docs and example alignment (no getenv policy)

- Parent task: `../tasks/r9-portal-runtime-local-settings-policy-task.md`
- Status: `done` (2026-04-30; delivery-chain step 7 audit — no doc gaps vs implementation)

## Goal

Ensure operators and external readers see a single policy: toggles live in `site_sync_settings.local.php`, not process environment or `.env` on the portal.

## Inputs

- `../adr-r9-portal-runtime-local-settings-policy.md`
- `../../../../yomerch.b24.siteconnector/site_sync_settings.local.example.php`
- `../b24-inbound.md`, `../../../../../bitrix24-external-developers/` (site contract markdown as applicable)
- `../README.md`

## Steps

1. Confirm example documents: `allow_inbound_without_secret`, `deals_fallback_on_mismatch` (and any other keys moved off getenv).
2. Cross-link R9 from README and, where helpful, from R8 ADR.
3. Tier B / external doc mentions “no getenv” where operators configure the portal.

## DoD

- [x] ADR R9 exists and states policy.
- [x] Example + inbound / contract docs updated in same initiative (verified step 7 audit).
- [x] README lists R9 task/ADR/progress.

## Risks

- Future toggles added without updating example — extend DoD in code review checklist.
