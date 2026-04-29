# Task: R8 inbound execution parity

- Task ID: `R8-INBOUND-EXECUTION-PARITY`
- Status: `in_progress`
- Priority: high (unblocks `R7-W2` transport clause closure)
- Owner: Team Lead + inbound owner
- ADR: `../adr-r8-inbound-execution-parity.md`
- Progress: `../progress-r8-inbound-execution-parity.md`

## Goal

Document and verify inbound execution parity with `bitrix24-external-developers` for secret resolution, token transport, optional HMAC/skew/dedup, response correlation header, and safe dev overrides — after code landing in `yomerch.b24.inbound`.

## Inputs

- ADR: `../adr-r8-inbound-execution-parity.md`
- Implementation:
  - `../../../../yomerch.b24.inbound/lib/site_requests_handler.php`
  - `../../../../yomerch.b24.inbound/lib/InboundSecurity.php`
  - `../../../../yomerch.b24.inbound/lib/InboundDedupStore.php`
  - `../../../../yomerch.b24.inbound/include.php`
- Settings example: `../../../../yomerch.b24.siteconnector/site_sync_settings.local.example.php`
- Tier A contract: `../b24-inbound.md`
- Tier B (when hydrated): `../../../../../bitrix24-external-developers/`

## Subtasks tree

- [x] `R8-SUB-01` Tier A baseline + example settings
  - Subtask doc: `../subtasks/r8-sub-01-tier-a-baseline-and-settings-example.md`
  - Goal: keep `b24-inbound.md` and `site_sync_settings.local.example.php` aligned with merged runtime.
  - Inputs: ADR fact list; files above.
  - Steps: diff docs vs code paths; add missing `inbound_secret` alias note in example if absent.
  - DoD: `b24-inbound.md` R8 section reviewed; example lists all optional inbound keys used by handler.
  - Status: `done` (2026-04-30).
  - Risks: example over-specifies vs code.

- [ ] `R8-SUB-02` R7 matrix refresh (Tier B transport rows)
  - Subtask doc: `../subtasks/r8-sub-02-r7-matrix-transport-rows.md`
  - Goal: update `r7-sub-02` / `r7-sub-03` clause rows whose prior verdict was “missing” for HMAC/skew/dedup/token body.
  - Inputs: `r7-sub-02-inbound-contract-delta-matrix.md`, Tier B auth sections.
  - Steps: map each row to `InboundSecurity` / handler branch; set verdict `ok` or `partial` with evidence id.
  - DoD: each affected `R7-B-*-C*` transport row has post-R8 verdict + file anchor.
  - Status: `pending`.
  - Risks: Tier B text still differs on edge cases (header casing, error strings).

- [ ] `R8-SUB-03` Stand evidence pack
  - Subtask doc: `../subtasks/r8-sub-03-stand-evidence-pack.md`
  - Goal: reproducible HTTP/log evidence for optional paths and default path regression.
  - Inputs: stand URL, `site_sync_settings.local.php` fixtures.
  - Steps: capture 403 HMAC, 403 skew, 409 dedup, 403 header-only; default POST+token 200 smoke.
  - DoD: trace ids or curl transcripts attached to subtask; no open sev-1 regression on defaults.
  - Status: `pending`.
  - Risks: stand lacks writable dedup path.

## Current assessment

- **Code:** merged per ADR § Context (classes, handler behavior, example).
- **Docs:** R8 ADR/progress/task/subtasks created; `b24-inbound.md` updated for R8 runtime bullets; `R8-SUB-01` closed.
- **Delivery-chain step 7 (2026-04-30):** Tech Lead documentation close — Tier B tree `local/bitrix24-external-developers/` refreshed in lockstep with landed runtime; `lib/docs/README.md` cross-links R8; ADR R7/R8 record **ACTION breadth vs dispatcher** as remaining **product** gap under `R7-W2` (no fabricated intake tag/hash/signer).
- **Not done:** matrix adjudication (`R8-SUB-02`), evidence (`R8-SUB-03`), Team Lead audit signoff.
- **R9 cross-link:** portal-wide “no getenv” policy and verification task live in `tasks/r9-portal-runtime-local-settings-policy-task.md` (inbound override is one consumer).
