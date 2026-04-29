# R8-SUB-01: Tier A baseline + example settings

- Parent task: `../tasks/r8-inbound-execution-parity-task.md`
- Status: `done` (2026-04-30 — Tier A + example aligned in repo)

## Goal

Align Tier A documentation and `site_sync_settings.local.example.php` with post-R8 inbound runtime so operators and developers do not rely on stale “header-only token” wording.

## Inputs

- `../adr-r8-inbound-execution-parity.md`
- `../b24-inbound.md`
- `../../../../yomerch.b24.siteconnector/site_sync_settings.local.example.php`
- `../../../../yomerch.b24.inbound/lib/site_requests_handler.php`
- `../../../../yomerch.b24.inbound/lib/InboundSecurity.php`

## Steps

1. Verify `b24-inbound.md` § R8 bullets against handler + `InboundSecurity` (token candidates, HMAC, skew, dedup, headers, overrides).
2. Ensure example file comments list: `sync_token`, optional `inbound_secret` (alias for same secret), `allow_inbound_without_secret`, `inbound_hmac_secret`, `inbound_max_skew_seconds`, `inbound_require_header_token`, `inbound_dedup_ttl_seconds`, `inbound_dedup_store_path`.

## DoD

- [x] `b24-inbound.md` contains accurate R8 runtime bullets (no contradiction with code).
- [x] `site_sync_settings.local.example.php` documents optional inbound keys including `inbound_secret` alias semantics.

## Risks

- Example file drift if new settings keys are added without updating this subtask.
