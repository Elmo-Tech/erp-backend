# Elmo ERP V2 — New chat prompt for P0-03 database security

**Suggested chat name:** `ERP V2 | P0-03 | Database Security`.

```text
اشتغل على Elmo Tech ERP V2 — P0-03 Database Security فقط.

ابدأ من آخر كود مدموج في:
Backend: https://github.com/Elmo-Tech/erp-backend/tree/rebuild/erp-v2
Frontend: https://github.com/Elmo-Tech/erp-frontend/tree/rebuild/erp-v2
Issue: https://github.com/Elmo-Tech/erp-backend/issues/8

مهم: Backend PR #6 و Frontend PR #2 تم دمجهم بالفعل على rebuild/erp-v2:
Backend merge 6c2894d46ec5f48fca8f769010698c29631dff20
Frontend merge 661ac4a01ecfb3f55c712575490f75cf002b160e
ما تعيدش Auth/MFA ولا أي Scaffold.

أولاً اقرأ آخر docs/START_HERE.md و docs/PROJECT_STATUS.md و docs/MODULE_ROADMAP.md و docs/DECISIONS.md و docs/WORK_PROTOCOL.md و docs/SCHEMA_FOUNDATION.md و docs/handoffs/P0-02.md، وآخر GitHub CI/Issues/PRs، و AGENTS.md في الريبوين.

ADR-021 سمح بدمج التطوير فقط؛ مش تصريح Production. ملف الأمان Issue #7 لسه مفتوح وفيه SEC-01..08؛ ما تنفذش تشغيل حسابات حقيقية أو تغيير مفاتيح أو نشر.

مهمتك توثق وتنفذ PostgreSQL 18 company isolation / composite same-company FKs / CHECK constraints / explicit least-privilege DB role and optional tested RLS pilot on P0-03 topic branches. اختبر مصر ضد إيطاليا في الاتجاهين وتأكد إن session/worker pooling ما يسربش الـ company context.

ابدأ بفحص DB schema الحقيقي وتحديد عقد الـ Migration وAcceptance Tests ثم نفّذ في PRs صغيرة على rebuild/erp-v2. لا تعدل main أو oberation، لا Merge أو Deploy بدون موافقة مستقلة.

قبل نهاية الشات وثق كل نتائج PASS/FAIL/NOT RUN، وأحدث commits/PR/CI في docs/PROJECT_STATUS.md و docs/handoffs/P0-03.md و MODULE_ROADMAP.md وأي ADR جديد. لازم تسلم Next Atomic Action واضح.
```

**Other security hardening chat (separate task):** `ERP V2 | P0-02-SEC | MFA Operations & Audit`, [Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7). Must be complete before real users or production release, independently of database-security progress.
