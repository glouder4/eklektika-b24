# ADR: R5 cutover slice closure

- Status: accepted
- Date: 2026-04-29
- Scope: `local/modules/yomerch.b24.*` delivery-chain initiative slice
- Related task: `tasks/r5-cutover-slice-task.md`

## Context

Implementation and audit for the current delivery-chain slice are completed on code/documentation level for inbound/outbound contract parity, deterministic outcome handling, and fallback observability.

Operational closure still requires stand execution evidence package run (R5 gate) by operator on target stand.

## Decision

1. Mark code-level implementation and audit artifacts as completed.
2. Keep initiative slice in "conditionally done" state until R5 stand evidence is executed and attached.
3. Treat R5 stand evidence execution as the only blocking operational subtask.

## Consequences

- Engineering stream can be considered finished for this slice.
- Release/cutover approval remains blocked until stand evidence artifacts are attached.
- Team Lead proceeds with operator coordination only; no additional code work is required unless stand reveals defects.

## Risks

- Delay risk: stand slot/operator availability postpones closure.
- Discovery risk: stand run can reveal environment-specific regressions requiring hotfix.

## Governance note (re-audit 2026-04-30)

Re-audit confirms: decision **accepted**; slice stays **conditionally done** until `R5-SUB-03` evidence attaches (cross-ref `progress-r5-cutover-slice.md` and `progress-r7` not-ready table).

## Rollback

If stand evidence fails:
- reopen top-level task status to `in_progress`;
- create follow-up fix subtask(s) under `docs/subtasks/`;
- rerun checklist and evidence capture after fix deployment.
