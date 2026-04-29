# R9-SUB-01: Static verification — no getenv in owned portal paths

- Parent task: `../tasks/r9-portal-runtime-local-settings-policy-task.md`
- Status: `done` (delivery-chain step 7 — audit closed 2026-04-30)

## Goal

Produce auditable evidence that portal integration runtime does not use `getenv()` (and does not introduce dotenv) in owned code paths under `local/`.

## Inputs

- ADR: `../adr-r9-portal-runtime-local-settings-policy.md`
- Scope (recommended for CI/grep):
  - `local/modules/yomerch.b24.*/**/*.php`
  - `local/cron/*.php` (integration cron owned by this initiative)
  - `local/php_interface/**/*.php` (bootstrap / defines)
- Exclude: documentation lines that *mention* `getenv` as forbidden (optional: `glob` exclude `**/lib/docs/**`).

## Steps

1. From `local/` (or repo root with paths adjusted):

   `rg "getenv\\(" --glob "*.php" modules cron`

2. If `rg` is unavailable (Windows): `Get-ChildItem modules,cron -Recurse -Filter *.php | Select-String -Pattern 'getenv\('`

3. Optional secondary: `rg "Dotenv|vlucas/phpdotenv" modules cron` (same PHP scope).

4. Paste stdout (zero lines) into this subtask or `progress-r9-portal-runtime-local-settings-policy.md` with date and commit hash.

## DoD

- [x] Zero matches in scoped PHP for `getenv(`.
- [x] Evidence block (command + output) recorded.
- [x] Team Lead signoff on scope — completed at delivery-chain step 7 (2026-04-30); re-open only if vendored PHP appears under scoped `local/` paths.

## Evidence (2026-04-30)

- Commit (local tree at verification time): `e87aef936dfc4c1b3135e992b5955ed23115c2a9`
- Workspace search (Cursor `grep` tool): pattern `getenv\(` with glob `*.php` under `c:\Git_projects\eklektika-b24\local` → **no matches** (0 hits).
- Note: host shell in this environment does not have `rg` installed; evidence is from the workspace indexer search equivalent to `rg`.

## Risks

- Windows vs Unix path quoting; use `rg` consistently.
- Clones without `bitrix24-external-developers` — not required for this grep.
