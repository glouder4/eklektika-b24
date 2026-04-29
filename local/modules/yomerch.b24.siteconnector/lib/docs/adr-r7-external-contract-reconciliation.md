# ADR: R7 reconcile with external contract docs

- Status: accepted
- Date: 2026-04-29
- Updated: 2026-04-30 (re-audit finalization: Tier B hydrate model + `R7-W2` backlog remains; initiative not closed)
- Updated: 2026-04-30 (delivery-chain step 7: R8 transport code + Tier B snapshot refresh recorded; dominant open gap = ACTION breadth vs dispatcher — product adjudication)
- Scope: verification wave for Bitrix24 external integration contract alignment
- Related task: `tasks/r7-external-contract-reconciliation-task.md`
- Related initiative (transport execution): `adr-r8-inbound-execution-parity.md` (**R8**)

## Context

Initiative requires reconciliation against external contract docs from `local/bitrix24-external-developers/`.

That directory is **gitignored**; a pristine clone may not contain files until hydrated from the canonical source (see parent `README.md`). When present locally, Tier B markdown contains intake-ready artifacts:
- `local/bitrix24-external-developers/README.md`
- `local/bitrix24-external-developers/BITRIX24_EXTERNAL_TEAM_HANDOFF.md`
- `local/bitrix24-external-developers/b24_site_contracts_yomerch.md`
- `local/bitrix24-external-developers/generated_inbound_action_contract_map.md`
- related support files (`b24_site_to_crm_uf_field_map.md`, `inbound_dedup_storage_policy.md`, `inbound_sli_slo.md`, `TIER_B_DELIVERY_INTAKE_CHECKLIST.md`)

This removes the hard blocker `blocked_external_source` for R7 intake and enables full Tier A vs Tier B verification wave.

## Decision

1. Launch dedicated verification wave `R7-EXTERNAL-CONTRACT-RECONCILIATION`.
2. Use a two-tier source-of-truth model:
   - **Tier A (available, binding for current code):**
     - `local/modules/yomerch.b24.siteconnector/lib/docs/b24-inbound.md`
     - `local/modules/yomerch.b24.inbound/lib/site_requests_handler.php`
     - `local/modules/yomerch.b24.inbound/lib/InboundEndpoint.php`
     - `local/modules/yomerch.b24.contract/lib/*` (UF/contract services)
  - **Tier B (available, normative for external handoff):**
    - `local/bitrix24-external-developers/README.md`
    - `local/bitrix24-external-developers/BITRIX24_EXTERNAL_TEAM_HANDOFF.md`
    - `local/bitrix24-external-developers/b24_site_contracts_yomerch.md`
    - `local/bitrix24-external-developers/generated_inbound_action_contract_map.md`
    - `local/bitrix24-external-developers/b24_site_to_crm_uf_field_map.md`
    - `local/bitrix24-external-developers/TIER_B_DELIVERY_INTAKE_CHECKLIST.md`
3. Start verification wave `R7-W2` to compare Tier B contractual claims against:
   - inbound runtime (`yomerch.b24.inbound/lib/*.php`);
   - local curated docs (`yomerch.b24.siteconnector/lib/docs/*.md`);
   - generated map consistency (`generated_inbound_action_contract_map.md` vs runtime ACTION dispatch paths).
4. Track each delta with explicit target file(s), owner, and closure evidence class (runtime trace/doc update/test harness).
5. Do not claim full contract compliance until `R7-W2` deltas are resolved or explicitly waived with owner and due date.

## Consequences

- External-source blocker is removed; Team Lead can move from placeholder rows to adjudicated Tier B deltas.
- Verification workload increases: every previously blocked row now requires concrete verdict and evidence.
- Compliance status remains conditional until `R7-W2` closure evidence is attached.
- Existing R7 artifacts stay valid as baseline and are extended instead of reset.

## Risks

- Tier B docs may still drift from runtime if intake checklist (`TIER_B_DELIVERY_INTAKE_CHECKLIST.md`) is not completed with immutable revision metadata when teams require it.
- Mixed response semantics (`UPDATE_GROUP` plain text vs JSON flows) can create false mismatches during automated checks.
- **ACTION breadth:** Tier B / `generated_inbound_action_contract_map.md` may list actions not implemented on the inbound dispatcher; treat as explicit **`R7-W2`** row until waived or code catches up.
- `REQUEST_ID`/dedup expectations in Tier B may still differ on edge semantics (error strings, header casing); matrix adjudication remains the gate.

## Rollback

If `R7-W2` reveals unacceptable parity gap:
- freeze outward contract claims for affected ACTIONs;
- keep Tier B docs marked as draft/non-authoritative for those rows;
- roll back to Tier A-only asserted behavior (`b24-inbound.md` + runtime) until remediations are merged and verified.
