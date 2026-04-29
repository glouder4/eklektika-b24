# Progress: R8 inbound execution parity

- Updated: 2026-04-30 (delivery-chain **step 7** — Tech Lead documentation close after implementation + external snapshot alignment)
- Initiative: **R8** — inbound transport/security/dedup parity with `bitrix24-external-developers` execution expectations
- Overall status: `in_progress` (implementation + doc/Tier B snapshot alignment **closed this step**; matrix re-adjudication + stand evidence still open)
- Delivery-chain: **step 7 complete** for factual ADR/progress/task/README cross-links and Tier B tree refresh; steps toward full R8 closure = `R8-SUB-02` / `R8-SUB-03` + Team Lead audit

## Completed (this wave — implementation facts)

- **Classes:** `InboundSecurity.php`, `InboundDedupStore.php` under `yomerch.b24.inbound/lib/`; **autoload** in `yomerch.b24.inbound/include.php`.
- **Handler:** `site_requests_handler.php` — secret resolution (`sync_token` + `inbound_secret` alias); token from `X-SYNC-TOKEN` and body aliases; optional header-only token; optional HMAC (`X-Sync-Signature`, hex sha256 of raw body); optional clock skew (`X-Sync-Timestamp` / `sync_ts` and aliases); optional dedup (TTL + store path, `HTTP 409` + `reason_code=dedup_duplicate`); `X-Sync-Request-Id` response header; dev env/settings to allow inbound without secret.
- **Example settings:** `site_sync_settings.local.example.php` — optional inbound keys documented (maintain parity with `inbound_secret` alias in comments).
- **Delivery-chain step 1 (2026-04-30):** ADR `adr-r8-inbound-execution-parity.md`, task `tasks/r8-inbound-execution-parity-task.md`, subtasks `r8-sub-01`–`03`; Tier A `b24-inbound.md` § R8; R7 cross-links (`adr-r7`, `progress-r7` table, `r7` task); `R8-SUB-01` **done**.
- **Delivery-chain step 7 (2026-04-30):** Recorded merged facts end-to-end (HMAC / clock skew / token header+body / dedup + **`X-Sync-Request-Id`** echo or **`trace_id` fallback**); **`InboundSecurity`**, **`InboundDedupStore`**; example keys; **Tier B** handoff bundle under `local/bitrix24-external-developers/` refreshed (handoff, contracts, dedup policy, readme, intake checklist, generated ACTION map, SLI/SLO, UF map); `lib/docs/README.md` lists R8 index entries; **no fabricated** intake tag/hash/signer lines added.

## In progress

- Refresh `R7-SUB-02` / `R7-SUB-03` rows that referred to missing Tier B transport toggles (`r8-sub-02`).
- Stand traces: HMAC valid/invalid, skew expired, dedup 409, header-only token rejection (`r8-sub-03`).

## Explicit remaining work

- Close `R8-SUB-02` / `R8-SUB-03` with matrix verdicts and evidence links (log lines, HTTP captures).
- Team Lead audit: confirm no regression on default path (token + POST only, dedup/HMAC off).
- **`R7-W2`:** ACTION catalog breadth vs dispatcher — **product decision**; track in `r7-sub-02` / `r7-sub-03`, not as hidden R8 transport debt.

## Completion definition for R8

- All `R8-SUB-*` subtasks `done` with DoD met.
- `b24-inbound.md` R8 runtime section matches verified behavior.
- `R7` matrix updated for any rows closed by R8 implementation (verdict + anchor), or waived with owner.

## Next steps for Team Lead (after step 7)

| Priority | Work | Dependencies | DoD / artifacts |
| --- | --- | --- | --- |
| **P1** | Execute **`R8-SUB-02`**: re-adjudicate `R7-B-*-C*` transport rows vs `InboundSecurity` / handler. | `r7-sub-02`, `r7-sub-03`, Tier B files | Each affected row: verdict + file/code anchor; backlog trimmed where code satisfies Tier B. |
| **P1** | Execute **`R8-SUB-03`**: stand HTTP + log evidence pack. | Stand URL, writable dedup path if testing dedup | Curl/log excerpts linked from subtask; default path smoke (POST + token, options off). |
| **P2** | Schedule **ACTION breadth** adjudication with product owner (`R7-W2`). | Dispatcher + `generated_inbound_action_contract_map.md` | Explicit waiver rows or implementation backlog — not mixed into R8 transport closure. |
| **P2** | Audit signoff on **no regression** default inbound path. | R8-SUB-03 partial | Short audit note in task or progress. |
