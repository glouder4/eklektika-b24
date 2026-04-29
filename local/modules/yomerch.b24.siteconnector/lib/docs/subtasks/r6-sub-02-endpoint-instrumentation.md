# Subtask: R6 endpoint instrumentation

- Subtask ID: `R6-SUB-02`
- Parent task: `../tasks/r6-inbound-request-logging-task.md`
- Status: `pending`
- Priority: high

## Purpose

Ensure inbound endpoint emits the new canonical request log event for every inbound call path and preserves sink compatibility.

## Inputs

- ADR: `../adr-r6-inbound-request-logging.md`
- Handler: `../../../../yomerch.b24.inbound/lib/site_requests_handler.php`
- Endpoint dispatcher: `../../../../yomerch.b24.inbound/lib/InboundEndpoint.php`

## Steps

1. Add event `site_requests_handler.payload.received` after payload parsing/normalization.
2. Preserve existing `site_requests_handler.payload.snapshot` as supplemental event (optional for transition period).
3. Ensure reject paths (`invalid_json`, `invalid_payload`, `sync_forbidden`, etc.) emit minimal diagnostic contract fields.
4. Validate file writing path remains:
   - primary `local/logs/inbound-b24.log`,
   - fallback `/tmp/inbound-b24.log`.

## DoD

- Logs show `ACTION`/`METHOD`, sanitized `body`, and field-key metadata.
- No direct raw body writes bypassing sanitizer/truncation.
- New event is observable for both success and rejection scenarios.

## Risks

- Contract drift if part of context is still assembled ad-hoc in multiple locations.
- Extra event volume can complicate quick grep-style incident scans.
