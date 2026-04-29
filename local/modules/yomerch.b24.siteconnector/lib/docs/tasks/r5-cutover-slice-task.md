# Task: Delivery-chain R5 cutover slice closure

- Task ID: `R5-CUTOVER-SLICE`
- Status: `in_progress`
- Priority: high
- Owner: Team Lead + operator
- ADR: `../adr-r5-cutover-slice.md`
- Progress: `../progress-r5-cutover-slice.md`

## Goal

Close current initiative slice with final documentation and operational evidence gate.

## Inputs

- Completed implementation + audit + process rework docs.
- Smoke checklist and ready-to-run evidence template.

## Subtasks tree

- [x] `R5-SUB-01` Finalize code-level implementation artifacts
  - DoD: required code changes merged in working tree and documented.
  - Risks: residual environment-specific behavior.
- [x] `R5-SUB-02` Finalize audit/process docs alignment
  - DoD: contract/outcome semantics and runbook-level guidance are consistent.
  - Risks: low, documentation drift over time.
- [ ] `R5-SUB-03` Execute stand evidence package (blocking)
  - Subtask doc: `../subtasks/r5-stand-evidence-execution.md`
  - DoD:
    - run scenarios from `../smoke-checklist-cutover.md`;
    - capture evidence using `../subtasks/r5-stand-evidence-template.md`;
    - attach trace IDs + response snippets + log fragments.
  - Risks:
    - stand availability delay;
    - potential environment regressions discovered during run.

## Current assessment

- **Re-audit 2026-04-30:** unchanged — initiative **not** closable until stand gate clears.
- Code-level completion: **done**.
- Remaining blocker (**R5**): **`R5-SUB-03`** stand evidence execution on target stand (`r5-stand-evidence-execution`).
- Task closure condition: `R5-SUB-03` completed and evidence attached (see `progress-r5-cutover-slice.md`, `progress-r7` blocker table).
