# ADR: R6 inbound request logging contract

- Status: accepted (implementation + stand evidence gates **open** — re-audit 2026-04-30)
- Date: 2026-04-29
- Scope: `local/modules/yomerch.b24.inbound/` inbound endpoint observability
- Related task: `tasks/r6-inbound-request-logging-task.md`

## Context

Operators need deterministic diagnostics for inbound endpoint incidents: what `ACTION` arrived, what full request body looked like, and what payload field-set was provided.

Current implementation in `yomerch.b24.inbound/lib/site_requests_handler.php` already logs trace events but does not have a formally fixed contract for:
- required per-request log fields;
- redaction policy for secrets/PII and binary-like payloads;
- body size limits and truncation marker semantics.

Without a stable contract, support and incident triage quality depends on ad-hoc logging details and can drift across refactors.

## Decision

1. Adopt explicit inbound logging contract for all requests handled by:
   - `local/modules/yomerch.b24.inbound/lib/site_requests_handler.php`
   - `local/modules/yomerch.b24.inbound/lib/InboundEndpoint.php` (dispatch-level context only if needed).
2. Keep primary log sink unchanged for operational continuity:
   - primary: `local/logs/inbound-b24.log`
   - fallback: `/tmp/inbound-b24.log`.
3. Require request-level events to include:
   - action identity (`ACTION`, `METHOD` when present),
   - full request body snapshot (sanitized + truncation metadata),
   - payload keys and extracted field keys for `PARAMS`/`fields`.
4. Enforce sanitization and truncation before writing:
   - redact security credentials and high-risk PII;
   - cap serialized body length; include flags/length metadata for deterministic parsing.

## Logging contract (normative)

### Required fields

Each request must produce at least one structured event `site_requests_handler.payload.received` with:
- `trace_id`, `correlation_id`, `cutover_label`, `event`, `ts`;
- transport: `request_method`, `request_uri`, `content_type`;
- action context: `action`, `method`, `payload_keys`;
- payload introspection: `params_keys`, `fields_keys`;
- body telemetry:
  - `body_raw_len` (original byte length),
  - `body_logged_len` (serialized logged body length),
  - `body_truncated` (`true/false`),
  - `body_truncated_from` (source length when truncated),
  - `body` (sanitized+possibly truncated payload string).

### Redaction policy

Before logging request payload/body:
- fully mask secrets by key name (case-insensitive): `X_SYNC_TOKEN`, `sync_token`, `token`, `authorization`, `password`, `api_key`, `secret`;
- for contacts/PII fields (`EMAIL`, `PHONE`, `LEGAL_ENTITY_EMAIL`, `LEGAL_ENTITY_PHONE`; wire payloads may still use `LEGAN_ENTITY_*` spelling) keep value only in masked form (prefix + `***`);
- for known binary/base64 carriers (`fileData[1]`, large scalar candidates, `CONTENT`) replace with marker:
  - `[REDACTED_BINARY len=<N>]`.

Masking applies recursively to nested arrays/objects.

### Max body length and truncation

- `MAX_LOG_BODY_BYTES = 16384` (16 KiB) for final serialized `body`.
- If body exceeds cap:
  - keep prefix up to limit,
  - append suffix marker `... [TRUNCATED <original_len> bytes]`,
  - set `body_truncated=true` and `body_truncated_from=<original_len>`.
- Never drop the event when truncation occurs.

## Consequences

- Incident response can quickly identify inbound action and payload shape without reproducing requests.
- Logs become safer for broader operator access due to deterministic masking.
- Slight increase in log volume is expected; bounded by truncation cap.

## Risks

- Over-redaction can hide useful debugging details.
- Under-redaction can leak sensitive data if new keys are introduced and not covered.
- Extra serialization cost on very large payloads (mitigated by cap and shallow metadata fallback).

## Rollback

If new logging causes runtime/performance issues:
- temporarily disable full-body field in `site_requests_handler.payload.received`, preserving action + keys only;
- keep existing `site_requests_handler.payload.snapshot` behavior;
- reopen task and adjust cap/redaction list with postmortem notes.
