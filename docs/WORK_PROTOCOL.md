# Development Protocol — One Chat, One Task

**Purpose:** chat history is ephemeral; GitHub holds the product contract and proof of work. This process applies to every contributor / ChatGPT project chat / coding agent.

## 1 — Start of a new chat (mandatory)

1. User creates chat named `ERP V2 | <TASK-ID> | <Short Title>`, e.g. `ERP V2 | P0-01 | CI & Builds`.
2. Read `docs/START_HERE.md`, `docs/PROJECT_STATUS.md`, `docs/MODULE_ROADMAP.md`, `docs/DECISIONS.md`, the task's `docs/handoffs/<TASK-ID>.md`, and both `AGENTS.md`.
3. Read the GitHub Issue, actual current branch trees/commits, open PRs, and latest CI outcomes. Documentation may lag behind reality; reconcile first.
4. State scope (one task), prerequisite/dependencies, acceptance criteria, and out-of-scope operations. Check that no concurrent task already owns the same files or migrations.
5. Create **topic branch per repository touched**, e.g. `task/p0-01-ci-baseline`, from the up-to-date `rebuild/erp-v2`. Never write to `main` or `oberation`. No force-push or merge without explicit approval.

## 2 — During implementation

- Keep changes within the Issue; open a new Issue for unrelated work.
- Use small tested commits with meaningful messages and PR(s) targeting `rebuild/erp-v2`, not `main`.
- Track real test commands and results. Mark inaccessible runners/logs and unverified steps truthfully.
- Update the relevant design doc *as part of the code change*, especially workflow fields, permissions, APIs, schema or migration effects.
- Record any new **approved** design decision in `docs/DECISIONS.md` (unique ADR, date, decision, reason, alternatives, impact). Proposals stay labeled **PROPOSED** until accepted; never silently change agreed business rules.
- For cross-repo changes, keep business/domain spec in backend; frontend links to it, with UI-specific details in its own docs.
- Preserve accounting legal isolation, decimal money, audited edits, idempotent billing, and test-first critical operations.
- No credentials or personal data in any handoff. No unapproved production migrations/deploys.

## 3 — End of session (even unfinished)

**Mandatory durability gate** — update, commit and link:
1. `docs/handoffs/<TASK-ID>.md` with actual outcome, changed files, commit SHA/PR links, tests passed/failed/not-run, blockers, risks and **one precise next action**.
2. `docs/PROJECT_STATUS.md` with overall snapshot and next task, CI evidence and clear `done vs pending`.
3. `docs/MODULE_ROADMAP.md` if a module milestone changed.
4. `docs/DECISIONS.md` when design decisions were approved/changed; business rules/schema document if corresponding contract changed.
5. Update the GitHub Issue with a short progress comment/links and leave it open if incomplete. A closed task requires acceptance criteria evidenced and reviewed PR(s).
6. Final chat answer links issue, PR(s), docs, says precisely what was verified and what wasn't. **No relying on uncommitted chat explanations.**

If write access fails: provide patch content/commands, identify the blocker, and do NOT claim the documentation was saved.

## 4 — Status conventions

- `NOT STARTED`: design only.
- `SCAFFOLD`: files created, execution unverified.
- `IN PROGRESS`: active branch/issue with recorded work.
- `BLOCKED`: identified barrier documented with next step.
- `VERIFIED`: all required automated tests pass, user-approved review gate met. No implicit production release.

A PR may be open while a task remains `IN PROGRESS`. PR merge does not equal production deployment.

## 5 — Task ownership, continuity, and conflict avoidance

- Backend Issue is canonical for cross-repo tasks; link associated frontend PR(s) and frontend-specific issues.
- One active chat/task owns one topic branch at a time. A new chat on the **same task** continues the prior handoff and branch, not a duplicate implementation.
- Multiple chats may work on different modules concurrently **only if** shared schema/API contracts and files do not conflict; agree interfaces first.
- Never edit a branch or reverse decisions another task is actively modifying without coordinating through Issue/PR.
- Before a new chat resumes, read the *latest integrated* code and latest docs, not a copied old ZIP.

## 6 — Required task identifiers

`P0-01` for Phase 0 foundation tasks; `M01-01` for CRM subtask 1; next subtask increments.
Suggested chat name `ERP V2 | M01-01 | Lead Sources`; branch `task/m01-01-lead-sources`; handoff `docs/handoffs/M01-01.md`.
See [HANDOFF_TEMPLATE.md](HANDOFF_TEMPLATE.md).

## 7 — Current task

Start with https://github.com/Elmo-Tech/erp-backend/issues/1; status and tested facts live at [PROJECT_STATUS.md](PROJECT_STATUS.md) and [handoffs/P0-01.md](handoffs/P0-01.md).
