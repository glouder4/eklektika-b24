# Subtask: R7 inbound contract delta matrix

- Subtask ID: `R7-SUB-02`
- Parent task: `../tasks/r7-external-contract-reconciliation-task.md`
- Status: `in_progress`
- Cycle checkpoint: `step_1_completed_wave_w2_open`
- Status note: Tier A matrix baseline is preserved; this revision opens `R7-W2` for clause-level Tier B adjudication using `local/bitrix24-external-developers/*`.
- Priority: high

## Purpose

Build a deterministic delta matrix:

- **Tier A slice:** `b24-inbound.md` clauses checked against inbound runtime PHP under `yomerch.b24.inbound`.

- **Tier B slice (`R7-W2`):** clauses from `local/bitrix24-external-developers/*` are compared against runtime and local docs with explicit file-level outputs.

---

## Inputs (Tier A extraction)

### Local inbound contract narrative

`../b24-inbound.md`

### Runtime (examined paths)

| File | Responsibility |
| --- | --- |
| `../../../../yomerch.b24.inbound/endpoint.php` | Entry shim |
| `../../../../yomerch.b24.inbound/lib/site_requests_handler.php` | Transport guards, tracing, sanitization limits, POST body handling, dispatch |
| `../../../../yomerch.b24.inbound/lib/InboundEndpoint.php` | ACTION handlers (`UPDATE_GROUP`, `GET_CONTACT_ID`, `UPDATE_COMPANY`, `CRM_METHOD` + METHOD switch) |
| `../../../../yomerch.b24.inbound/lib/InboundActionDispatcher.php` | `ACTION` validation, aliases (`UPDATE_STATUS_GROUP` → `UPDATE_GROUP`), `unknown_action` |
| `../../../../yomerch.b24.inbound/lib/InboundPayloadNormalizer.php` | Default `http_status`, trace envelopes on responses |

---

## Tier A delta matrix (`b24-inbound.md` ↔ inbound runtime)

| Row ID | Topic | Doc reference § | Evidence (runtime anchor) | Verdict | Notes |
| --- | --- | --- | --- | --- | --- |
| `R7-A-ENT-001` | Entry wiring / public module path | `b24-inbound.md` § Статус, § Точка входа — module path cited | `endpoint.php` includes `site_requests_handler.php` | **ok** | Public URL naming matches doc sentence structure. |
| `R7-A-AUTH-001` | Sync token verification | Basics + implied by handler | `site_requests_handler.php` — `site_sync_settings.local.php` reads `sync_token`; compares `HTTP_X_SYNC_TOKEN`; misconfig ⇒ 503 JSON | **ok** | Doc states “secret (если используется общий токен)”; runtime always requires configured token header path for happy path after line 269. |
| `R7-A-METH-001` | HTTP method | Implied REST shape | Handler rejects non-`POST` with `405`, `site_requests_handler.reject.method_not_allowed` | **gap vs doc narrative** (`doc_gap_local`) | `b24-inbound.md` focuses on JSON 400/200 cases; **`405`** is not listed in § HTTP ошибки narrative table (extend local doc vs leave as appendix). Same row semantics: Tier A completeness only. |
| `R7-A-HTTP-001` | Pre-dispatch rejection codes beyond 400 family | Partial narrative | **`503`** `sync_misconfigured`; **`403`** `sync_forbidden`; invalid JSON `400` (`invalid_payload`); malformed body handling | **`doc_gap_local`** | Facts exist in code traces; **`b24-inbound.md`** should eventually enumerate transports or cross-ref R6 appendix. Not external contract text. |
| `R7-A-LOG-001` | Obligatory `site_requests_handler.payload.received` | R6 блок + обязательные поля list | `_inboundTrace('site_requests_handler.payload.received', …)_` merges snapshot keys | **mismatch (minor)** | `site_requests_handler.php` uses **`field_keys`** on the **`payload.received`** trace object and **`fields_keys`** on the separate **`payload.snapshot`** object — same semantics (keys of `PARAMS.fields`), different event payloads. **`b24-inbound.md`** lists **`fields_keys`**. Align naming via doc xor code (`field_keys`/`fields_keys`/labels). |
| `R7-A-LOG-002` | `MAX_LOG_BODY_BYTES = 16384` | Строка ограничения лог-длины | `$maxLogBodyBytes = 16384` in `$buildContractLogBody` | **ok** | |
| `R7-A-LOG-003` | Sanitizer keys (secrets + contacts) overlap | Политика редактирования | `$isSensitiveLogKey`, `$isContactPiiKey` + recursive sanitizer branch | **ok** (*heuristic parity*) | Key lists differ by substring vs enumerated names; qualitative intent matches Tier A checklist. Detailed diff intentionally out of Tier A scope vs ADR verbatim diff. |
| `R7-A-RSP-001` | `invalid_payload` / `unknown_action` ⇒ HTTP alignment | Контрактный минимум HTTP 400 branch | `InboundPayloadNormalizer::normalizeResponse` sets **`http_status`** 400 for both error_codes; dispatcher emits `unknown_action` with `http_status` 400 inline | **ok** | |
| `R7-A-RSP-002` | Domain handlers return HTTP 200 for business failure when no explicit status | Контрактный минимум “HTTP 200 + success” | Dispatch path: `InboundPayloadNormalizer` defaults **`http_status` 200** when `error_code` not in `invalid_payload`/`unknown_action` set | **ok** | Matches doc’s `domain_failure` / `domain_success` split. |
| `R7-A-DIS-001` | ACTION catalog | Не детализировано в `b24-inbound.md` полным списком | `InboundEndpoint::processRequest` map + `InboundActionDispatcher` (`UPDATE_STATUS_GROUP` alias) | **`doc_gap_local`** | Runtime list is source of truth for Tier A until local doc enumerates `ACTION`s (no external clause invented). |
| `R7-A-CRM-001` | `CRM_METHOD` METHOD allow-list | Не детализировано в `b24-inbound.md` | `handleCrmMethod` `switch` — `crm.requisite.list`, `crm.company.*`, `crm.requisite.*`, `crm.contact.*`, `crm.contact.company.add` | **`doc_gap_local`** | Same as above; Tier B **normative** comparison waits on `blocked_external_source` package. |
| `R7-A-REQ-001` | Idempotent обработка повторных доставок | Базовые требования | No `idempot` symbol matches under `yomerch.b24.inbound/` | **gap** | Doc states idempotency as baseline; **no explicit idempotency mechanism** located in inbound module (may be caller-side only — **not asserted** here). |
| `R7-A-BIN-001` | Binary / base64 redaction marker | `[REDACTED_BINARY len=<N>]` policy | Sanitizer uses truncation + key-based redaction; **no dedicated `REDACTED_BINARY` string** observed in `site_requests_handler.php` | **`mismatch` or `gap`** (track as open) | Treat as Tier A follow-up: align `b24-inbound.md` with actual sanitizer or extend sanitizer to match doc literal. |

---

## Tier B clause-level matrix (`R7-W2`)

Source blocker is removed; clause rows are now adjudicated with deterministic anchors.

| Clause Row ID | Parent Row | Clause | Evidence anchors | Verdict | Target + owner |
| --- | --- | --- | --- | --- | --- |
| `R7-B-HOF-C01` | `R7-B-HOF-001` | Endpoint and POST baseline | Doc: `../../../../../bitrix24-external-developers/BITRIX24_EXTERNAL_TEAM_HANDOFF.md` §2; Code: `../../../../yomerch.b24.inbound/lib/site_requests_handler.php` (`REQUEST_METHOD` check + `Allow: POST`) | **ok** | no change |
| `R7-B-HOF-C02` | `R7-B-HOF-001` | Token channel semantics | Doc allows `sync_token` body fallback in §2; Code validates `HTTP_X_SYNC_TOKEN` only in `site_requests_handler.php` | **mismatch** | doc fix in `BITRIX24_EXTERNAL_TEAM_HANDOFF.md` or code extension in `site_requests_handler.php` (owner: Team Lead, due: `R7-W2 close`) |
| `R7-B-HOF-C03` | `R7-B-HOF-001` | Timestamp/HMAC optional security toggles | Doc §2 has `X-Sync-Timestamp` / `X-Sync-Signature`; no runtime symbols found under `yomerch.b24.inbound/lib/*.php` for these checks | **gap** | either implement in inbound runtime or mark as deferred external note (owner: Team Lead + integration owner, due: `R7-W2 close`) |
| `R7-B-HOF-C04` | `R7-B-HOF-001` | Method reject error code parity | Doc §4 lists `sync_method_not_allowed` under 403 family; runtime emits `method_not_allowed` with `HTTP 405` | **mismatch** | align handoff error table with runtime (`BITRIX24_EXTERNAL_TEAM_HANDOFF.md`) (owner: docs owner, due: `R7-W2 close`) |
| `R7-B-CON-C01` | `R7-B-CON-001` | ACTION catalog parity | Doc ACTION list in handoff/contract includes `UPDATE_CONTACT`, `UPDATE_BATCH_USERS`, `DELETE_*`, `SYNC_COMPANY_CONTACTS`, `UPDATE_MANAGER`; runtime dispatcher map in `InboundEndpoint::processRequest` includes `UPDATE_GROUP`, `GET_CONTACT_ID`, `UPDATE_COMPANY`, `CRM_METHOD` (+ alias in `InboundActionDispatcher`) | **mismatch** | reconcile ACTION list in docs or runtime expansion (owner: Team Lead, due: `R7-W2 close`) |
| `R7-B-CON-C02` | `R7-B-CON-001` | Unknown ACTION behavior | `InboundActionDispatcher::dispatch` returns `error_code=unknown_action`, `reason_code=unsupported_action`, `http_status=400`; normalizer keeps 400 | **ok** | no change |
| `R7-B-CON-C03` | `R7-B-CON-001` | CRM_METHOD list parity | Code allow-list in `InboundEndpoint::handleCrmMethod` matches documented core methods (`crm.requisite.list`, `crm.company.*`, `crm.requisite.*`, `crm.contact.*`, `crm.contact.company.add`) | **ok** | no change |
| `R7-B-MAP-C01` | `R7-B-MAP-001` | Action coverage in generated map | `generated_inbound_action_contract_map.md` has rows for actions not present in current dispatcher (`DELETE_*`, `UPDATE_CONTACT`, `UPDATE_MANAGER`, `SYNC_COMPANY_CONTACTS`, `UPDATE_BATCH_USERS`) | **mismatch** | regenerate map from current code or restore handlers (owner: inbound owner, due: `R7-W2 close`) |
| `R7-B-MAP-C02` | `R7-B-MAP-001` | UPDATE_GROUP event/reason semantics | Map row uses `event=update_group_ok`, `failure_reason=invalid_payload`; runtime `handleUpdateGroup` returns scalar result and errors from handler/dispatcher without `event` field | **mismatch** | align map generation contract to runtime response schema (owner: inbound owner, due: `R7-W2 close`) |
| `R7-B-UF-C01` | `R7-B-UF-001` | Provenance anchors for UF source files | UF doc references `lib/from-crm/CrmInboundUfMap.php`, `RegisterUserCompanyConfig.php`, `CompanyB24Config.php`; these paths are absent in current workspace tree | **gap** | update source-of-truth pointers to existing config (`../../../../yomerch.b24.contract/lib/config/uf_mapping.php`) or restore referenced files (owner: contract owner, due: `R7-W2 close`) |
| `R7-B-UF-C02` | `R7-B-UF-001` | UF code parity for key fields | `b24_site_to_crm_uf_field_map.md` key codes (`UF_CRM_1775034008956`, `UF_CRM_1777068292434`, `UF_CRM_1755643990423`) are present in `../../../../yomerch.b24.contract/lib/config/uf_mapping.php` | **ok (partial)** | keep parity note; full field-by-field still pending owner signoff |
| `R7-B-OPS-C01` | `R7-B-OPS-001` | Dedup behavior parity | Support doc claims dedup gate + `409 duplicate`; no dedup runtime path in `site_requests_handler.php`/`InboundEndpoint.php` | **gap** | decide doc-only downgrade vs runtime implementation (owner: Team Lead + product owner, due: `R7-W2 close`) |
| `R7-B-OPS-C02` | `R7-B-OPS-001` | SLI reason-code semantics | `inbound_sli_slo.md` cites `sync_method_not_allowed`; runtime method reject emits `method_not_allowed` | **mismatch** | align SLI doc reason-code labels with runtime traces (owner: ops owner, due: `R7-W2 close`) |

---

## Steps (remaining)

1. Keep clause rows (`R7-B-*-C*`) current for every Tier B document change and runtime merge.
2. Re-run Tier A matrix if `b24-inbound.md` or runtime changes.
3. For each **`mismatch`** / **`gap`** in Tier A table, remediation targets sit in `r7-sub-03-remediation-and-evidence-plan.md` by **`Row ID` only**.
4. Attach immutable source evidence from `R7-SUB-01` before closing any Tier B row.

## DoD (progress)

- Tier A slice covers enumerated topics from **`b24-inbound.md`** with explicit code anchors (**met** this revision).
- Tier B rows converted to clause-level adjudication (`R7-B-*-C*`) with evidence anchors (**met** this revision).
- Full “external vs local parity” remains **in progress** until non-`ok` clause rows are resolved or waived with owner + due date.

## Risks

- Mixed legacy behavior (`UPDATE_GROUP` plain response vs JSON wrappers) can be misread as contract violation; tag this per clause before remediation.
- Without clause granularity (`R7-B-*-C*`), large Tier B rows can hide unresolved deltas.
