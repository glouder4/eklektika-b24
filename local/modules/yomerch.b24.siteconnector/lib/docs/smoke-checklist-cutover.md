# Smoke checklist: inbound cutover

- [ ] `UPDATE_GROUP`: send `ACTION=UPDATE_GROUP` with `ID`, `ACTIVE`, `C_SORT`, `NAME`; verify success and group fields updated.
- [ ] `UPDATE_COMPANY`: send canonical payload with token and trace headers; verify `success=1` and company match by site id.
- [ ] `GET_CONTACT_ID`: run positive (`EMAIL`/`PHONE` matched) and negative (`not found`) cases; verify deterministic response.
- [ ] `CRM_METHOD`: run `crm.company.get` and `crm.requisite.list`; verify transport success and expected result shape.
- [ ] Negative inbound contract: send unknown `ACTION`; verify HTTP `400` + body `error_code=unknown_action`.
- [ ] Negative inbound contract: send malformed JSON payload or broken `PARAMS`; verify HTTP `400` + body `error_code=invalid_payload`.
- [ ] Domain failure contract (`UPDATE_CONTACT user not found`): run provided sample and verify HTTP `200` + `success=0` + `reason_code=update_contact_user_not_found`.
- [ ] Transport parity (`outbound` vs `statussync`): verify both send `X-Sync-Token` + `X-Sync-Trace-ID` and retry on `429/503` with backoff.
- [ ] Trace propagation: verify one correlation id is present in status outbound log and inbound dispatch log for the same call.
- [ ] Deals fallback: when rules service mismatches/errors (with fallback toggles), verify log has `cutover_source=legacy_fallback`; otherwise `new_rules`.

## Evidence notes
- R5 status: **execution pending on target stand**.
- Tracking subtask: `subtasks/r5-stand-evidence-execution.md` (`R5-SUB-03`, status `blocked`).
- This package contains ready-to-run commands/payloads and pass criteria to close gate operationally after stand run.

## R5 evidence package (ready to run)

### Preconditions
- Inbound endpoint: `https://<B24_HOST>/local/modules/yomerch.b24.inbound/endpoint.php`
- Secret source-of-truth: `local/modules/yomerch.b24.siteconnector/site_sync_settings.local.php` key `sync_token`
- Logs:
  - inbound: `local/logs/inbound-b24.log`
  - outbound transport: `local/logs/b24-to-site-sync.log`
  - deals status flow: `local/logs/deals-status.log` (or stand-equivalent app log configured for `yomerch.b24.deals`)

### 1) Inbound positive: UPDATE_GROUP
```bash
curl -i -X POST "https://<B24_HOST>/local/modules/yomerch.b24.inbound/endpoint.php" \
  -H "Content-Type: application/json" \
  -H "X-Sync-Token: <SYNC_TOKEN>" \
  -H "X-Sync-Trace-ID: R5-INB-UPDATE-GROUP-001" \
  --data '{"ACTION":"UPDATE_GROUP","PARAMS":{"ID":123,"ACTIVE":"Y","C_SORT":100,"NAME":"R5 Smoke Group"}}'
```
Pass criteria:
- HTTP 200
- JSON `success=1`
- `trace_id` present in response and in `local/logs/inbound-b24.log`
- Group fields match sent payload

### 2) Inbound positive: UPDATE_COMPANY
```bash
curl -i -X POST "https://<B24_HOST>/local/modules/yomerch.b24.inbound/endpoint.php" \
  -H "Content-Type: application/json" \
  -H "X-Sync-Token: <SYNC_TOKEN>" \
  -H "X-Sync-Trace-ID: R5-INB-UPDATE-COMPANY-001" \
  --data '{"ACTION":"UPDATE_COMPANY","PARAMS":{"ID":1001,"fields":{"TITLE":"R5 Smoke Company"}}}'
```
Pass criteria:
- HTTP 200
- JSON `success=1`
- inbound log contains `site_requests_handler.dispatch.done` for `UPDATE_COMPANY` with same trace id

### 3) Inbound positive: CRM_METHOD
```bash
curl -i -X POST "https://<B24_HOST>/local/modules/yomerch.b24.inbound/endpoint.php" \
  -H "Content-Type: application/json" \
  -H "X-Sync-Token: <SYNC_TOKEN>" \
  -H "X-Sync-Trace-ID: R5-INB-CRM-METHOD-001" \
  --data '{"ACTION":"CRM_METHOD","METHOD":"crm.company.get","PARAMS":{"id":1001}}'
```
Pass criteria:
- HTTP 200
- JSON `success=1`
- `result` has expected shape for requested CRM method

### 3.1) Inbound positive: CRM_METHOD `crm.contact.add` (registration minimal fields)
```bash
curl -i -X POST "https://<B24_HOST>/local/modules/yomerch.b24.inbound/endpoint.php" \
  -H "Content-Type: application/json" \
  -H "X-Sync-Token: <SYNC_TOKEN>" \
  -H "X-Sync-Trace-ID: R5-INB-CRM-CONTACT-ADD-001" \
  --data '{"ACTION":"CRM_METHOD","METHOD":"crm.contact.add","PARAMS":{"fields":{"NAME":"R5","LAST_NAME":"Smoke Contact","PHONE":[{"VALUE":"+70000000001","VALUE_TYPE":"WORK"}],"EMAIL":[{"VALUE":"r5-smoke-contact@example.test","VALUE_TYPE":"WORK"}]}}}'
```
Pass criteria:
- HTTP 200
- JSON `success=1`, `result` is positive integer (new contact CRM ID)
- `local/logs/inbound-b24.log` contains `site_requests_handler.dispatch.done` with `crm_method=crm.contact.add`, `result_int`, `response_body_bytes`, same trace id

### 3.2) Inbound positive: CRM_METHOD `crm.company.add` (registration minimal fields)
```bash
curl -i -X POST "https://<B24_HOST>/local/modules/yomerch.b24.inbound/endpoint.php" \
  -H "Content-Type: application/json" \
  -H "X-Sync-Token: <SYNC_TOKEN>" \
  -H "X-Sync-Trace-ID: R5-INB-CRM-COMPANY-ADD-001" \
  --data '{"ACTION":"CRM_METHOD","METHOD":"crm.company.add","PARAMS":{"fields":{"TITLE":"R5 Smoke Company","UF_CRM_1756112106093":"876","UF_CRM_1774915439581":999001,"UF_CRM_3804624439373":999001}}}'
```
Pass criteria:
- HTTP 200
- JSON `success=1`, `result` is positive integer (new or dedup-matched company CRM ID)
- При dedup по ИНН допустимы `reason_code` `company_add_use_existing_for_contact` / `company_add_child_under_head_inn` — **`result` всё равно int**
- `local/logs/inbound-b24.log`: `site_requests_handler.dispatch.done` with `crm_method=crm.company.add`, `result_int`, `reason_code` (если есть), same trace id

**Примечание:** полный smoke регистрации юрлица = `createCompanyElement` на сайте + inbound chain (`crm.company.add` → contact chain) — **`createCompanyElement` вне этого репо**. Негатив: `site_element_id=0` → `success=0`, `reason_code=company_add_invalid_site_element_id`.

### 4) Inbound negative contract checks
Unknown action:
```bash
curl -i -X POST "https://<B24_HOST>/local/modules/yomerch.b24.inbound/endpoint.php" \
  -H "Content-Type: application/json" \
  -H "X-Sync-Token: <SYNC_TOKEN>" \
  --data '{"ACTION":"UNKNOWN_ACTION","PARAMS":{}}'
```
Expected:
- HTTP 400
- JSON `error_code=unknown_action`

Invalid payload:
```bash
curl -i -X POST "https://<B24_HOST>/local/modules/yomerch.b24.inbound/endpoint.php" \
  -H "Content-Type: application/json" \
  -H "X-Sync-Token: <SYNC_TOKEN>" \
  --data '{"ACTION":"UPDATE_COMPANY","PARAMS":'
```
Expected:
- HTTP 400
- JSON `error_code=invalid_payload`

### 4.1) Inbound domain failure: UPDATE_CONTACT user not found (operator run)
```bash
curl -i -X POST "https://<B24_HOST>/local/modules/yomerch.b24.inbound/endpoint.php" \
  -H "Content-Type: application/json" \
  -H "X-Sync-Token: <SYNC_TOKEN>" \
  -H "X-Sync-Trace-ID: R5-INB-UPDATE-CONTACT-NOT-FOUND-001" \
  --data '{"ACTION":"UPDATE_CONTACT","PARAMS":{"ID":900001,"fields":{"NAME":"R5 Missing","LAST_NAME":"Contact","PHONE":[{"VALUE":"+70000000000","VALUE_TYPE":"WORK"}],"EMAIL":[{"VALUE":"missing-r5@example.test","VALUE_TYPE":"WORK"}]}}}'
```
Expected response (body fragment):
```json
{"success":0,"reason_code":"update_contact_user_not_found"}
```
Pass criteria:
- HTTP 200
- JSON `success=0`
- JSON `reason_code=update_contact_user_not_found`
- Same `trace_id` is present in response and `local/logs/inbound-b24.log`

### 5) Outbound/statussync transport parity
Run one outbound and one statussync trigger for same entity/update window, then compare logs:
```bash
rg -n "X-Sync-Token|X-Sync-Trace-ID|retry|429|503" "local/logs/b24-to-site-sync.log"
```
Pass criteria:
- Both paths emit `X-Sync-Token` and `X-Sync-Trace-ID`
- Retries visible on `429/503` with backoff evidence
- No silent JSON parse failures (must be explicit `success=0` + error)
- Business reject (`HTTP 200`, `success=0`) is logged as `outcome=domain_failure` with `reason_code`

### 6) Deals fallback evidence
Trigger a deals status recalculation:
```bash
php "local/cron/check_deals_status.php"
```
Pass criteria:
- Log contains either `cutover_source=legacy_fallback` (when fallback path triggered) or `cutover_source=new_rules`
- Scenario/result is linked to trace/correlation id in stand logs

### Evidence collection template
- Command/request sample used: `<paste exact curl/php command>`
- Timestamp (UTC): `<YYYY-MM-DDTHH:MM:SSZ>`
- Trace/request id: `<id>`
- Response snippet: `<json>`
- Log references:
  - `local/logs/inbound-b24.log`: `<line/time fragment>`
  - `local/logs/b24-to-site-sync.log`: `<line/time fragment>`
  - deals log: `<line/time fragment>`
