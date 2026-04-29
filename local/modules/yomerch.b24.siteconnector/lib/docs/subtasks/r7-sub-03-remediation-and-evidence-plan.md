# Subtask: R7 remediation and evidence plan

- Subtask ID: `R7-SUB-03`
- Parent task: `../tasks/r7-external-contract-reconciliation-task.md`
- Status: `in_progress`
- Cycle checkpoint: `step_1_completed_wave_w2_open`
- Priority: high

## Purpose

Tier A: define **verification procedures** ("evidence playbook") and a **minimal backlog skeleton** keyed **only** to matrix **`Row ID`**s from `r7-sub-02-inbound-contract-delta-matrix.md`.

Tier B: backlog entries are now executable from available package and must include file-level code/doc targets.

---

## Evidence playbook (Tier A applies)

Use this template for **every** remediation item before code/docs merge moves a row from **`mismatch` / `gap` / `doc_gap_local`** to **`ok`** (definitions per matrix):

| Step | What to capture | Acceptance signal |
| --- | --- | --- |
| 1 — **Locate** | Note `trace_id` or timestamp window for the inbound request on a validated stand (`local/logs/inbound-b24.log` or fallback path per `b24-inbound.md`). | At least two log lines tying `site_requests_handler.entry` ↔ `dispatch.done` ↔ relevant `payload.received`. |
| 2 — **Reproduce fixture** | Store **non-secret** minimized JSON body representing the clause (redact tokens; never commit secrets). Fixture path tracked in backlog row or PR description. | Same `error_code` / `reason_code` / `http_status` as matrix expectation after fix **or** doc-only alignment with explicit rationale. |
| 3 — **Contrast** | Side-by-side: **doc sentence** (`b24-inbound.md` § anchor) vs **PHP line reference** (`file:approximate-symbol`). | Anchors pasted as markdown links relative to repo in PR/Task. |
| 4 — **Closure** | For doc-only deltas: citation of amended paragraph. For behavioral deltas: list touched files (`site_requests_handler.php`, `InboundPayloadNormalizer.php`, etc.). | Team Lead checklist item unchecked → checked in `progress-r7-external-contract-reconciliation.md`. |

**Explicit non-goals:** This playbook **does not** specify production credentials, client secrets, or stand URLs — those belong to ops-owned runbooks Tier B (`R7-B-RUN-*`).

---

## Remediation backlog skeleton (Tier A — keyed by Row ID)

| Matrix Row ID | Suggested remediation class | Candidate target(s) (non-binding until approved) | Backlog priority |
| --- | --- | --- | --- |
| `R7-A-LOG-001` | Align **`fields_keys`** vs **`field_keys`** naming (`doc XOR code`). | `b24-inbound.md` and/or `site_requests_handler.php` (`payload.received` uses `field_keys`; `payload.snapshot` uses `fields_keys`) | medium |
| `R7-A-REQ-001` | Clarify или implement idempotent handling at inbound boundary vs document caller guarantees. | `b24-inbound.md`; possibly future handler/DEDUP artifact | high (product decision) |
| `R7-A-BIN-001` | Align binary redaction story with sanitizer implementation. | `site_requests_handler.php` (`$sanitize*` chain) vs `b24-inbound.md` | medium |
| `R7-A-HTTP-001` | Extend local HTTP transport appendix (`503`/`403`/`405` rationale + trace event names). | `b24-inbound.md` | low |
| `R7-A-METH-001` | Cross-link `405` semantics with evidence playbook step 2 (method probe fixture). | `b24-inbound.md`; optional standalone testing notes | low |
| `R7-A-DIS-001` | Document explicit `ACTION` list + dispatcher alias(es) in **`b24-inbound.md`** or ADR appendix. | `b24-inbound.md` | medium |
| `R7-A-CRM-001` | Document `crm.*` METHOD list matching `InboundEndpoint::handleCrmMethod`. | `b24-inbound.md` | medium |

_rows already labeled **`ok`** in the Tier A matrix have **no backlog line** pending regression guard choice only._

---

## Remediation backlog (Tier B clause rows — `R7-W2`)

| Clause Row ID | Status | Execution target(s) | Evidence to attach before close |
| --- | --- | --- | --- |
| `R7-B-HOF-C01` | `done` | None (baseline confirmed) | Existing code/doc anchors in `r7-sub-02` |
| `R7-B-HOF-C02` | `todo` | Doc: `../../../../../bitrix24-external-developers/BITRIX24_EXTERNAL_TEAM_HANDOFF.md` or code: `../../../../yomerch.b24.inbound/lib/site_requests_handler.php` | Final decision record: `header-only` vs `header+body` token policy |
| `R7-B-HOF-C03` | `todo` | Code: `../../../../yomerch.b24.inbound/lib/` security layer and/or doc downgrade in handoff | Explicit owner waiver or merged implementation proof |
| `R7-B-HOF-C04` | `todo` | Doc: `../../../../../bitrix24-external-developers/BITRIX24_EXTERNAL_TEAM_HANDOFF.md` + `inbound_sli_slo.md` | Error-code table after rename/normalization |
| `R7-B-CON-C01` | `todo` | Docs: `b24_site_contracts_yomerch.md`, `BITRIX24_EXTERNAL_TEAM_HANDOFF.md`; optional code expansion in `InboundEndpoint.php` | Signed ACTION list parity note |
| `R7-B-CON-C02` | `done` | None | Existing dispatcher/normalizer anchors in `r7-sub-02` |
| `R7-B-CON-C03` | `done` | None | Existing `CRM_METHOD` allow-list anchor in `r7-sub-02` |
| `R7-B-MAP-C01` | `todo` | `generated_inbound_action_contract_map.md` regeneration process + runtime dispatcher | Regeneration provenance (command + commit) |
| `R7-B-MAP-C02` | `todo` | Map contract row for `UPDATE_GROUP` and exporter assumptions | Before/after map row + runtime response shape snippet |
| `R7-B-UF-C01` | `todo` | UF doc source pointers (`b24_site_to_crm_uf_field_map.md`) | Updated source-of-truth file list accepted by contract owner |
| `R7-B-UF-C02` | `in_progress` | Verify remaining UF keys against `../../../../yomerch.b24.contract/lib/config/uf_mapping.php` | Field-by-field parity checklist signed by owner |
| `R7-B-OPS-C01` | `todo` | Dedup policy doc vs runtime implementation decision | Product waiver or merged dedup gate evidence |
| `R7-B-OPS-C02` | `todo` | `inbound_sli_slo.md` reason-code alignment | Updated SLI legend with runtime labels |

---

## Inputs

- Tier A matrix: **`r7-sub-02-inbound-contract-delta-matrix.md`**
- Tier A inventory: **`r7-sub-01-source-of-truth-inventory.md`**
- Runtime anchors (verification): `../../../../yomerch.b24.inbound/lib/*.php` paths already stated in sibling deliverables.

## Steps

1. For each **`mismatch` / `gap` / `doc_gap_local`** row in Tier A matrix, populate **Remediation class** acceptance in a PR/Task (not duplicated here verbatim).
2. For each non-`done` Tier B clause row, bind one code owner + one docs owner with due date in progress tracker.
3. Update **`../progress-r7-external-contract-reconciliation.md`** when playbook items receive first evidence attaches.
4. Close wave only after intake checklist metadata/signoff is attached and linked from progress/task.

## DoD (progress)

- Playbook defines evidence shape before remediation PRs (**met** skeleton).
- Backlog skeleton exists **only by Row ID** (**met** this revision).
- Tier B backlog includes file-level code/docs targets (**met** this revision).

## Risks

- Cross-module refactors (**ContactSync** suspension, outbound, contract lib) inferred from filenames in original subtask inputs can expand scope — keep Tier A remediation **inbound-visible** unless **`R7-B-*`** unblocks outbound semantics.
