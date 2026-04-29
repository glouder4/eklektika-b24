# Subtask: R7 source-of-truth inventory

- Subtask ID: `R7-SUB-01`
- Parent task: `../tasks/r7-external-contract-reconciliation-task.md`
- Status: `in_progress`
- Cycle checkpoint: `step_1_completed_wave_w2_open`
- Priority: highest

## Purpose

Create a reliable source map for contract verification and close the intake/signoff gap for newly available external docs package.

## Trust tiers (classification)

| Tier | Meaning | Typical scope |
| --- | --- | --- |
| **A** | Verifiable inside this workspace: committed PHP under `local/modules/` and curated docs under `yomerch.b24.siteconnector/lib/docs/`. Used for deterministic delta matrices without importing text from outside repos. |
| **B** | External or cross-repo normative sources (site contract tree, outbound sync package README, unreplicated paths). Rows that depend exclusively on Tier B remain `blocked_external_source` until a revision-attached artifact is supplied. |

## Source inventory — Tier A (available)

| Artifact | Path (from repo root) | Role | Status |
| --- | --- | --- | --- |
| Inbound endpoint script | `local/modules/yomerch.b24.inbound/endpoint.php` | Public URL entry forwards to handler | **available** |
| Inbound request handler | `local/modules/yomerch.b24.inbound/lib/site_requests_handler.php` | POST gate, auth, JSON parse, tracing, ACTION dispatch | **available** |
| ACTION router & CRM_METHOD surface | `local/modules/yomerch.b24.inbound/lib/InboundEndpoint.php` | Implemented `ACTION`s and `CRM_METHOD` values | **available** |
| Dispatcher & aliases | `local/modules/yomerch.b24.inbound/lib/InboundActionDispatcher.php` | `UNKNOWN_ACTION`/validation HTTP shaping | **available** |
| Response shaping | `local/modules/yomerch.b24.inbound/lib/InboundPayloadNormalizer.php` | Default `http_status`, trace fields on responses | **available** |
| Local inbound narrative | `local/modules/yomerch.b24.siteconnector/lib/docs/b24-inbound.md` | Canonical *local* contract notes for inbound | **available** |
| R6 inbound logging ADR | `local/modules/yomerch.b24.siteconnector/lib/docs/adr-r6-inbound-request-logging.md` | Architectural decision referenced from `b24-inbound.md` | **available** |

## Source inventory — Tier B (available in workspace, pending intake signoff)

| Intended artifact | Logical path cited in docs | Expected normative owner | Workspace status |
| --- | --- | --- | --- |
| Tier B entrypoint | `local/bitrix24-external-developers/README.md` | Team Lead + integration owner | **available** |
| External handoff contract | `local/bitrix24-external-developers/BITRIX24_EXTERNAL_TEAM_HANDOFF.md` | Team Lead + integration owner | **available** |
| Full site contract | `local/bitrix24-external-developers/b24_site_contracts_yomerch.md` | Team Lead + contract owner | **available** |
| Generated ACTION map | `local/bitrix24-external-developers/generated_inbound_action_contract_map.md` | Team Lead + inbound owner | **available** |
| Field mapping | `local/bitrix24-external-developers/b24_site_to_crm_uf_field_map.md` | Team Lead + CRM mapping owner | **available** |
| Intake signoff form | `local/bitrix24-external-developers/TIER_B_DELIVERY_INTAKE_CHECKLIST.md` | Tech Lead + Team Lead | **available (not filled)** |
| Dedup policy support doc | `local/bitrix24-external-developers/inbound_dedup_storage_policy.md` | Inbound owner | **available** |
| SLI/SLO support doc | `local/bitrix24-external-developers/inbound_sli_slo.md` | SRE/ops owner | **available** |

External revision placeholders (immutable reference handshake — **not invented**):

| Package | Desired reference type | Assigned owner | SLA / due | Actual reference |
| --- | --- | --- | --- | --- |
| `bitrix24-external-developers/` intake bundle | `commit`, `tag`, or signed archive checksum | **Team Lead + Tech Lead** | **current wave (R7-W2)** | commit fixed: `e87aef936dfc4c1b3135e992b5955ed23115c2a9`; tag/checksum/signoff still pending |
| Generated ACTION map provenance | command run + commit reference | **Inbound owner** | **current wave (R7-W2)** | _pending export evidence_ |

## Inputs

- `../README.md` (2026-04-30: aligned — Tier B path described as gitignored/hydrated, no longer «пакет отсутствует» unconditional)
- `../b24-inbound.md`
- `../../../../yomerch.b24.inbound/lib/site_requests_handler.php`
- `../../../../yomerch.b24.inbound/lib/InboundEndpoint.php`
- Expected external roots (not present locally): logical paths indexed above only.

## Steps

1. Inventory all contract-relevant artifacts in workspace and classify by trust tier (`A/B`) — completed in this revision.
2. Record available Tier B artifacts and bind each one to an owner for clause-level verification.
3. Attach **retrieval path + immutable reference** (`commit`, `tag`, checksum) in `TIER_B_DELIVERY_INTAKE_CHECKLIST.md` before closure.
4. Update progress/task status with confirmed source map when references exist.
5. Open `R7-W2` verification wave for Tier B clause adjudication (handoff to `R7-SUB-02` and `R7-SUB-03`).

## DoD

- Source map exists with `available/missing/unknown` statuses.
- Tier B artifacts are listed as available with explicit owners.
- Immutable reference fields are prepared and pending checklist completion/signatures.

## Risks

- Tier B docs can be consumed without immutable revision metadata if checklist is skipped; mitigation is mandatory checklist completion.
- Generated ACTION map can diverge from runtime if export provenance is not attached for current commit.
