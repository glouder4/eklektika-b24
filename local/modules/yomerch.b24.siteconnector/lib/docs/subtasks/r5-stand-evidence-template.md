# R5 stand evidence template (operator-run)

Use this template only with artifacts captured on stand. Do not prefill execution results.

## Scenario
- Name: `<e.g. UPDATE_CONTACT user not found>`
- Operator: `<name>`
- Timestamp (UTC): `<YYYY-MM-DDTHH:MM:SSZ>`
- Trace ID: `<trace_id>`

## Request
- Endpoint: `<url>`
- Method: `POST`
- Payload keys: `<ACTION, PARAMS.ID, PARAMS.fields.*>`
- Command used: `<paste exact curl/php command>`

## Outcomes (captured from real run)
- Inbound outcome: `<success=0|1, reason_code/error_code if any, HTTP code>`
- Outbound outcome: `<transport status/retry/outcome or N/A>`
- Response snippet: `<json fragment>`

## Log references
- `local/logs/inbound-b24.log`: `<time + fragment>`
- `local/logs/b24-to-site-sync.log`: `<time + fragment or N/A>`
- Other stand log: `<path + fragment or N/A>`
