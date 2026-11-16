# Copy this into the FIRST new ChatGPT Project chat

Suggested chat name: **ERP V2 | P0-01 | CI & Builds**

```text
اشتغل معايا على Elmo Tech ERP V2 — مهمة P0-01 فقط: إصلاح والتحقق من CI & Builds.

المشروعين على GitHub:
Backend: https://github.com/Elmo-Tech/erp-backend/tree/rebuild/erp-v2
Frontend: https://github.com/Elmo-Tech/erp-frontend/tree/rebuild/erp-v2
Issue: https://github.com/Elmo-Tech/erp-backend/issues/1

مهم جدًا: قبل أي تعديل اقرأ من Backend على rebuild/erp-v2:
docs/START_HERE.md
docs/PROJECT_STATUS.md
docs/WORK_PROTOCOL.md
docs/MODULE_ROADMAP.md
docs/DECISIONS.md
docs/BUSINESS_RULES.md
docs/handoffs/P0-01.md
وكمان AGENTS.md في المشروعين، وافحص حالة آخر Commits و CI و PRs فعليًا.

التزم بـ Task واحد لكل شات، وابدأ من آخر حالة موثقة، وماتعيدش كتابة حاجة اتعملت.
أنشئ task branch مستقل من rebuild/erp-v2 لكل repo هتعدل فيه، وافتح PR ناحية rebuild/erp-v2. 
ممنوع تعديل main أو oberation أو Production أو بيانات عملاء حقيقية.
هدفك P0-01: تحديد سبب فشل CI، تثبيت dependency lockfiles حقيقية، نجاح الاختبارات والـ migrations والـ build، وتوثيق الأدلة.
قبل نهاية الشات لازم تحدّث وتعمل Commit لملفات docs/PROJECT_STATUS.md و docs/handoffs/P0-01.md،
وتحدّث MODULE_ROADMAP/DECISIONS لو اتغيرت حالة أو قرار، وتربط الـ Issue والـ PR.
لو حاجة فشلت أو مش متاحة قول صراحة NOT VERIFIED؛ ممنوع تدعي إن الاختبارات نجحت بدون دليل.

اشتغل بأقصى قدر تقدر عليه في الشات ده، وفي النهاية اديني روابط GitHub والـ CI واسم المهمة اللي بعدها.
```

## Reusable version for all future chats

Replace `P0-01`, `docs/handoffs/P0-01.md` and Issue #1 with the new task. Always read current docs from GitHub, rather than pasting an old blueprint and treating it as current. User renames the chat to `ERP V2 | TASK-ID | short title`.

## Optional ChatGPT Project Instructions (paste once manually)

```text
Elmo ERP Project: GitHub is the canonical source of truth. For each new chat always read the latest docs/START_HERE.md, docs/PROJECT_STATUS.md, docs/WORK_PROTOCOL.md, docs/MODULE_ROADMAP.md, docs/DECISIONS.md and task-specific handoff from Elmo-Tech/erp-backend branch rebuild/erp-v2, plus AGENTS.md in both repositories and latest GitHub issue/PR/CI status. One chat = one task. Do not duplicate existing work. Use topic branches and PRs to rebuild/erp-v2; never modify main, oberation or production without explicit approval. At session end commit documented status, decision changes, tests and precise next step; distinguish verified from unverified. Backend holds canonical cross-repo specs; frontend points to it. Follow legal/company isolation and accounting invariants.
```
