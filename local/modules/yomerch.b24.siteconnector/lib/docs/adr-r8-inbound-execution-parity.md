# ADR: R8 inbound execution parity (Tier B transport/security)

- Status: accepted
- Date: 2026-04-30
- Updated: 2026-04-30 (R9 policy cross-link + **R9 closed** at delivery-chain step 7 finalize; prior: step 7 Tier B snapshot)
- Short id: **R8**
- Scope: align Bitrix24 inbound runtime with `bitrix24-external-developers` contract claims for auth, optional HMAC/skew/dedup, and observability headers
- Related task: `tasks/r8-inbound-execution-parity-task.md`
- Parent initiative link: `adr-r7-external-contract-reconciliation.md` (`R7-W2` transport rows may be closed or re-adjudicated against this wave)
- Portal config policy: `adr-r9-portal-runtime-local-settings-policy.md` (**R9** — no `getenv` / `.env`; `site_sync_settings.local.php` canonical)

## Context

`R7-W2` documented gaps between Tier B markdown (timestamp/HMAC toggles, dedup, extended token surfaces) and Tier A docs that described a narrower runtime (e.g. header-only token). Implementation landed in `yomerch.b24.inbound` to close the execution surface without waiting for full `R7` closure.

**Artifacts merged (facts):**

1. New classes: `modules/yomerch.b24.inbound/lib/InboundSecurity.php`, `modules/yomerch.b24.inbound/lib/InboundDedupStore.php`; autoload registration in `modules/yomerch.b24.inbound/include.php` for `InboundSecurity`, `InboundDedupStore`.
2. `site_requests_handler.php` now supports:
   - `inbound_secret` as alias of `sync_token` in `site_sync_settings.local.php` for contract alignment (`InboundSecurity::resolveSecret`);
   - sync token via header `X-SYNC-TOKEN` and body fields `sync_token` / `SYNC_TOKEN` / `_TOKEN` / `_SYNC_TOKEN` (first non-empty wins in handler order);
   - optional `inbound_require_header_token`: reject if token not also present in header when secret is set;
   - optional `inbound_hmac_secret`: `X-Sync-Signature` must be 64-char hex SHA-256 HMAC of **raw** body (`php://input`);
   - optional `inbound_max_skew_seconds` with `X-Sync-Timestamp` or body keys including `sync_ts` / `SYNC_TS` / aliases per `InboundSecurity::assertInboundClockSkew`;
   - optional `inbound_dedup_ttl_seconds` + `inbound_dedup_store_path`: duplicate `REQUEST_ID` / `X-Sync-Request-Id` → `HTTP 409`, `error=duplicate_request`, `reason_code=dedup_duplicate`;
   - response header `X-Sync-Request-Id`: incoming normalized id or fallback trace;
   - dev override: settings flag `allow_inbound_without_secret` in `site_sync_settings.local.php` to allow empty secret (fail-closed by default; normative policy in **R9**).

3. `site_sync_settings.local.example.php` documents optional inbound-related keys (including `inbound_secret` as alias of `sync_token` where applicable).

4. **Tier B delivery tree** under `local/bitrix24-external-developers/` was refreshed in the same cycle (handoff, contracts, dedup policy, intake checklist wording, generated ACTION map, README, SLI/SLO, UF map) so external-facing prose matches the landed runtime; portal-side verification rows remain in `R7-W2` until matrix and stand evidence close.

## Decision

1. Treat **R8** as the authoritative execution baseline for inbound transport/security options listed above; Tier A doc `b24-inbound.md` is updated to match runtime (minimal delta).
2. Keep **Tier B** as normative for wording and SLAs; `R7-SUB-02` / `R7-SUB-03` must re-adjudicate rows that cited “missing” transport features now implemented.
3. Dedup store failures **fail-open** (log `site_requests_handler.dedup.error`, continue); misconfigured HMAC secret path remains fail-closed per handler mapping.

## Consequences

- External developers’ contract sections on HMAC, clock skew, dedup, and token placement can be verified against live code without doc fiction.
- Operational burden: production must set `inbound_dedup_store_path` outside webroot when dedup is enabled; file locking and JSON store require disk health monitoring.
- `R7` initiative remains open until matrix rows and intake signoff complete; R8 narrows the “transport fiction” class of gaps.
- **Known remaining mismatch (product, not transport):** Tier B / generated maps may still describe a broader **ACTION** surface than `InboundEndpoint` + `InboundActionDispatcher` accept; resolution stays under **`R7-W2`** (matrix + owner waiver or runtime expansion), not under R8 alone.

## Risks

- **Skew/HMAC misconfiguration** in production causes hard 403/503 until clocks and secrets align.
- **Dedup false positives** if clients reuse `REQUEST_ID` incorrectly (409).
- **Dev overrides** left enabled in production would weaken auth (process/guardrail risk).
- **ACTION catalog drift:** external docs or maps list actions the dispatcher rejects until product aligns catalog with code or extends handlers.

## Rollback

- Disable optional features by unsetting `inbound_hmac_secret`, `inbound_max_skew_seconds`, `inbound_dedup_ttl_seconds` / `inbound_dedup_store_path`, `inbound_require_header_token` in `site_sync_settings.local.php`.
- Revert to prior module revision only if critical regression; prefer config rollback first.
