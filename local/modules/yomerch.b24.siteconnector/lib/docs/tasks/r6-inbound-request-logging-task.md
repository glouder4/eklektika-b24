# Task: R6 inbound request logging completeness

- Task ID: `R6-INBOUND-REQUEST-LOGGING`
- Status: `in_progress`
- Priority: highest
- Owner: Team Lead + inbound developer
- ADR: `../adr-r6-inbound-request-logging.md`
- Progress: `../progress-r6-inbound-request-logging.md`

## Goal

Ensure inbound endpoint logs always show which `ACTION` arrived, full sanitized request body, and payload field-level keys with deterministic truncation metadata.

## Inputs

- Current handler implementation: `../../../../yomerch.b24.inbound/lib/site_requests_handler.php`
- Dispatch logic: `../../../../yomerch.b24.inbound/lib/InboundEndpoint.php`
- Existing inbound contract notes: `../b24-inbound.md`
- Architecture decision: `../adr-r6-inbound-request-logging.md`

## Subtasks tree

- [ ] `R6-SUB-01` Logging contract + sanitizer/truncation helper
  - Subtask doc: `../subtasks/r6-sub-01-logging-contract-and-sanitizer.md`
  - DoD:
    - reusable sanitize+truncate helper introduced in inbound module;
    - contract-required fields generated from one source-of-truth payload extractor;
    - redaction key list and binary masking covered by tests or deterministic fixtures.
  - Risks:
    - missing sensitive key patterns in nested payloads;
    - helper over-coupling with specific actions.

- [ ] `R6-SUB-02` Endpoint instrumentation and reject-path parity
  - Subtask doc: `../subtasks/r6-sub-02-endpoint-instrumentation.md`
  - DoD:
    - event `site_requests_handler.payload.received` emitted in all accepted payload parsing paths;
    - reject/invalid payload paths still include action/method/payload diagnostics where available;
    - logs written to `local/logs/inbound-b24.log` with fallback preserved.
  - Risks:
    - increased log volume;
    - accidental body logging before sanitizer call.

- [ ] `R6-SUB-03` Stand validation and leakage audit
  - Subtask doc: `../subtasks/r6-sub-03-stand-validation-and-leakage-audit.md`
  - DoD:
    - representative requests executed (`JSON`, `form-data`, oversized body, binary-like file payload);
    - evidence confirms truncation markers and redaction markers;
    - operator/Team Lead sign-off with no sensitive leakage.
  - Risks:
    - stand data variability;
    - false confidence without negative-case payloads.

## Current assessment

- **Re-audit 2026-04-30:** ADR/policy prepared; **`R6` remains not-ready** until all subs complete.
- Engineering implementation (**`R6-SUB-01`**, **`R6-SUB-02`**) and stand leakage audit (**`R6-SUB-03`**) remain open — see blocker row **R6** in `progress-r7-external-contract-reconciliation.md`.
- Task closure condition: all `R6-SUB-*` subtasks marked completed with attached evidence.
