# Subtask: R6 logging contract and sanitizer

- Subtask ID: `R6-SUB-01`
- Parent task: `../tasks/r6-inbound-request-logging-task.md`
- Status: `in_progress`
- Priority: highest

## Purpose

Implement reusable normalization/sanitization layer that prepares inbound payload for safe, contract-compliant logging.

## Inputs

- ADR: `../adr-r6-inbound-request-logging.md`
- Target file: `../../../../yomerch.b24.inbound/lib/site_requests_handler.php`
- Optional extraction points: `../../../../yomerch.b24.inbound/lib/InboundPayloadNormalizer.php`

## Steps

1. Create helper(s) for recursive redaction and large-value masking.
2. Add deterministic body serialization + truncation function (`MAX_LOG_BODY_BYTES=16384`).
3. Build payload metadata extractor (`action`, `method`, `payload_keys`, `params_keys`, `fields_keys`).
4. Wire helper output into one canonical logging context builder used by handler events.

## DoD

- Canonical builder returns all contract-required fields.
- Sensitive values are masked by key and by binary-pattern heuristics.
- Truncation metadata is present and consistent with logged body.
- Implementation is readable and has no duplicated masking logic across handler blocks.

## Risks

- New key aliases for secrets may bypass redaction.
- Deep payload recursion can degrade performance without guarded traversal.
