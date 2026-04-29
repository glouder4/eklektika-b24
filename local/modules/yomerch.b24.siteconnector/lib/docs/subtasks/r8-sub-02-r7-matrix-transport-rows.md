# R8-SUB-02: R7 matrix refresh (Tier B transport rows)

- Parent task: `../tasks/r8-inbound-execution-parity-task.md`
- Status: `pending` (delivery-chain **step 7** closed **documentation** alignment; this subtask still owns **matrix row** edits + verdicts)

## Step 7 note (2026-04-30)

Facts are recorded in `adr-r8`, `progress-r8`, Tier B tree, and `b24-inbound.md` § R8. **`R8-SUB-02` remains the execution queue** for changing `r7-sub-02` / `r7-sub-03` transport-related rows from pre-R8 “missing” to post-R8 `ok` / `partial` / `mismatch` with anchors. **ACTION breadth** rows stay in **`R7-W2`** product scope, not folded into transport closure here.

## Goal

Update `R7-SUB-02` / `R7-SUB-03` clause rows that claimed missing HMAC, clock skew, dedup, or body token support so `R7-W2` reflects post-R8 reality.

## Inputs

- `r7-sub-02-inbound-contract-delta-matrix.md`
- `r7-sub-03-remediation-and-evidence-plan.md`
- Tier B: `b24_site_contracts_yomerch.md`, `inbound_dedup_storage_policy.md` (when hydrated)
- Code: `site_requests_handler.php`, `InboundSecurity.php`, `InboundDedupStore.php`

## Steps

1. List `R7-B-*-C*` rows tagged transport/auth/dedup/timestamp.
2. For each row, map to implementation branch and HTTP/error payload.
3. Set verdict (`ok`, `partial`, `mismatch`) and evidence pointer (`R8-SUB-03` artifact id).
4. Trim backlog rows in `r7-sub-03` that are fully satisfied by R8 code.

## DoD

- [ ] Every pre-R8 “missing transport” row has a post-R8 verdict and anchor.
- [ ] No orphan backlog item claims implementation still absent for features delivered in R8.

## Risks

- Tier B specifies error strings or status codes that still differ slightly from runtime.
