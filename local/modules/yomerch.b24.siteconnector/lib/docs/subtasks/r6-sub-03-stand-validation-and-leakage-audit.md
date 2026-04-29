# Subtask: R6 stand validation and leakage audit

- Subtask ID: `R6-SUB-03`
- Parent task: `../tasks/r6-inbound-request-logging-task.md`
- Status: `pending`
- Priority: high

## Purpose

Prove on stand that the new logging contract is present, complete, and safe (no sensitive leakage).

## Inputs

- Contract ADR: `../adr-r6-inbound-request-logging.md`
- Task: `../tasks/r6-inbound-request-logging-task.md`
- Log sink: `local/logs/inbound-b24.log`

## Steps

1. Run inbound requests for:
   - valid action payload;
   - unsupported/invalid payload;
   - oversized body (>16 KiB serialized);
   - payload containing token/password/contact fields and fileData-like blob.
2. Collect log fragments for each request by `trace_id`.
3. Verify:
   - required fields present;
   - masking markers applied;
   - truncation markers and lengths consistent.
4. Attach evidence package to Team Lead audit report.

## DoD

- Evidence proves contract compliance for positive and negative paths.
- No unmasked secrets or raw binary/base64 blobs remain in inspected logs.
- Team Lead approved closure with explicit references to trace IDs.

## Risks

- False negatives if test payloads do not contain realistic sensitive patterns.
- Stand access delays can postpone initiative closure.
