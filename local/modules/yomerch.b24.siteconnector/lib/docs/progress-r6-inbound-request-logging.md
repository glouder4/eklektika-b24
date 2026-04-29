# Progress: R6 inbound request logging

- Updated: 2026-04-30 (re-audit finalization; engineering + evidence gates open)
- Initiative: inbound endpoint logging completeness (`ACTION`, full body, payload fields)
- Overall status: in_progress

## Completed

- Captured architecture decision and fixed logging contract in `adr-r6-inbound-request-logging.md`.
- Defined deterministic policy for required fields, redaction, and truncation behavior.
- Aligned log sink decision with current production path (`local/logs/inbound-b24.log` + `/tmp` fallback).

## In progress

- Task decomposition for implementation and verification in `tasks/r6-inbound-request-logging-task.md`.
- Technical preparation of endpoint-level file targets and acceptance criteria.

## Explicit remaining work (**R6** — not ready to close initiative)

Re-audit 2026-04-30: ADR/policy artifacts are accepted; **`R6-SUB-*` all remain open.**

- Implement sanitizer + truncation helper in inbound module (`R6-SUB-01`).
- Emit new canonical event with body/action/field details for every inbound request (`R6-SUB-02`).
- Validate logging output on stand with representative payloads, including leakage review (`R6-SUB-03`).

## Completion definition for this initiative

Initiative moves to `done` only after:
1. code writes contract-compliant logs for success and reject paths;
2. masking/truncation behavior is verified with evidence snippets;
3. subtask statuses in `subtasks` are switched to `done`;
4. Team Lead audit confirms no sensitive leakage in sample logs.
