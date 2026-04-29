# Task: R7 external contract reconciliation

- Task ID: `R7-EXTERNAL-CONTRACT-RECONCILIATION`
- Status: `in_progress`
- Cycle checkpoint: `re_audit_finalized_2026_04_30` ( **`R7-W2` verification still open** )
- Priority: highest
- Owner: Team Lead + contract owner
- ADR: `../adr-r7-external-contract-reconciliation.md`
- Progress: `../progress-r7-external-contract-reconciliation.md`

## Goal

Reconcile local Bitrix24 integration behavior with recently updated external contract docs and produce actionable remediation backlog.

## Inputs

- ADR: `../adr-r7-external-contract-reconciliation.md`
- Local inbound contract doc: `../b24-inbound.md`
- Inbound implementation:
  - `../../../../yomerch.b24.inbound/lib/site_requests_handler.php`
  - `../../../../yomerch.b24.inbound/lib/InboundEndpoint.php`
- Contract services:
  - `../../../../yomerch.b24.contract/lib/`
- Tier B package (available): `../../../../../bitrix24-external-developers/`

## Subtasks tree

- [ ] `R7-SUB-01` Source-of-truth inventory and external package recovery
  - Subtask doc: `../subtasks/r7-sub-01-source-of-truth-inventory.md`
  - Current state: inventory delivered; Tier B folder available; intake/signoff metadata now pending.
  - DoD:
    - all available and missing contract sources listed with owner and freshness mark;
    - recovery path for external package agreed (repo URL/branch/archive);
    - immutable reference for external docs captured.
  - Risks:
    - no single owner for external package;
    - stale mirror copied without revision evidence.

- [ ] `R7-SUB-02` Contract delta matrix (actions, payloads, outcomes, observability)
  - Subtask doc: `../subtasks/r7-sub-02-inbound-contract-delta-matrix.md`
  - Current state: Tier A matrix delivered and audited; `R7-W2` expands matrix with Tier B clause-level adjudication.
  - DoD:
    - matrix includes required sections: auth, endpoint, ACTION/METHOD, schema, response/error, retry, logging;
    - each delta classified (`missing`, `mismatch`, `unknown`, `ok`);
    - each non-`ok` row has target file and owner.
  - Risks:
    - ambiguous language in external docs;
    - hidden assumptions not encoded in schemas/examples.

- [ ] `R7-SUB-03` Remediation plan, execution order, and verification evidence
  - Subtask doc: `../subtasks/r7-sub-03-remediation-and-evidence-plan.md`
  - Current state: playbook/backlog skeleton delivered and audited; file-level execution queue updated for Team Lead implementation wave.
  - DoD:
    - prioritized execution plan with file-level changes and tests;
    - verification artifacts defined (trace IDs, payload fixtures, log snippets);
    - closure criteria agreed by Team Lead and Tech Lead.
  - Risks:
    - broad remediations collide with active waves;
    - evidence collection blocked by stand environment access.

## Related waves

- **R8** (`tasks/r8-inbound-execution-parity-task.md`): inbound transport/security/dedup implementation parity with Tier B; after R8 merge, re-run `R7-SUB-02` transport clause rows against runtime instead of treating those features as absent.

## Current assessment

- **Re-audit 2026-04-30:** Tier B tree is **operative** under `local/bitrix24-external-developers/` when hydrated (path **gitignored** — see parent `README.md`); **`blocked_external_source` removed** for teams with the tree locally.
- **Delivery-chain step 7 (2026-04-30):** Tier B **snapshots** in that folder were updated for handoff/contracts/dedup policy/readme/intake/ACTION map/SLI/UF alignment with merged R8 inbound transport; portal doc index lists R8. Dominant **known** residual mismatch class: **ACTION catalog breadth vs inbound dispatcher** (explicit product/`R7-W2` track).
- **Not ready (`R7`):** immutable intake checklist fields where required by process, open `R7-B-*-C*` and Tier A remediation rows — see `progress-r7-external-contract-reconciliation.md` § «Post re-audit finalization».
- Next verification wave scope remains clause-level comparison of Tier B docs vs runtime/docs (`R7-W2`).
- Compliance still cannot be asserted until `R7-W2` deltas are closed or waived with evidence.
- Task closure condition: all `R7-SUB-*` subtasks completed with evidence and explicit resolution/waiver of all non-`ok` deltas.
