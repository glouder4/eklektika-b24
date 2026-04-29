# Subtask: R5 stand evidence execution

- Subtask ID: `R5-SUB-03`
- Parent task: `../tasks/r5-cutover-slice-task.md`
- Status: `blocked`
- Blocker: target stand execution window/operator run pending
- Priority: highest

## Purpose

Produce stand-confirmed evidence that final cutover contract and operational behavior pass on target environment.

## Inputs

- Checklist: `../smoke-checklist-cutover.md`
- Evidence template: `r5-stand-evidence-template.md`

## Steps

1. Execute all required R5 scenarios on stand.
2. Capture exact command/request, UTC timestamp, trace ID, response snippets.
3. Collect inbound/outbound/deals log fragments for each scenario.
4. Store evidence package in agreed operational channel and link it in task notes.

## DoD

- All mandatory checklist scenarios executed on stand.
- Evidence bundle contains traceable artifacts for each mandatory scenario.
- No unresolved contract mismatch in captured results.

## Risks

- Stand access delays.
- Environment mismatch may require reopening development loop.
