# R8-SUB-03: Stand evidence pack

- Parent task: `../tasks/r8-inbound-execution-parity-task.md`
- Status: `pending`

## Goal

Attach reproducible stand evidence (HTTP status + JSON body + log correlation) for inbound optional security paths and a default-path regression check.

## Inputs

- Stand `site_sync_settings.local.php` with controlled toggles
- Inbound endpoint URL (`yomerch.b24.inbound/endpoint.php` or deployed path)
- Log sink: `local/logs/inbound-b24.log` (per R6)

## Steps

1. Default: POST + valid token (header or body) — expect `200` domain path or valid transport rejection before dispatcher.
2. Enable `inbound_hmac_secret`: valid signature / missing signature / wrong signature.
3. Enable `inbound_max_skew_seconds`: valid ts / invalid / expired skew.
4. Enable dedup: two requests same `REQUEST_ID` or `X-Sync-Request-Id` — expect second `409`, `reason_code=dedup_duplicate`.
5. Enable `inbound_require_header_token` with token only in body — expect `403`.

## DoD

- [ ] Evidence table or linked transcripts stored (path or paste block in this file).
- [ ] `X-Sync-Request-Id` response header observed on success path.
- [ ] Default-path smoke documented after optional tests.

## Risks

- Dedup path not writable on stand → fail-open hides 409 path; document environment limitation.
