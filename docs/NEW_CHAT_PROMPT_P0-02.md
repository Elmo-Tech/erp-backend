# Current continuation prompt — P0-02 Auth/MFA/RBAC security review

Suggested chat title: `ERP V2 | P0-02 | Auth & RBAC`

> This file preserves the useful cross-chat task instructions originally prepared in the **stale docs-only PR #5**, but updates the now-outdated `NOT STARTED` statements and PR refs. The latest handoff and project status in backend Draft PR #6 are canonical and MUST NOT be overwritten by merging PR #5 as-is.

```text
كمّل P0-02 بس: Authentication, MFA & Company-Scoped RBAC — ما تبدأش P0-03.
Backend: https://github.com/Elmo-Tech/erp-backend/pull/6
Frontend: https://github.com/Elmo-Tech/erp-frontend/pull/2
Issue: https://github.com/Elmo-Tech/erp-backend/issues/4

اقرأ أولًا أحدث Review Comments في PR #6/#2 وdocs PR #5، وبعدها docs/START_HERE.md وPROJECT_STATUS.md وWORK_PROTOCOL.md وMODULE_ROADMAP.md وDECISIONS.md وAUTH_SECURITY_CONTRACT.md وdocs/handoffs/P0-02.md وكمان AGENTS.md في المشروعين.

اشتغل على نفس task/p0-02-auth-rbac في الريبوين، بدون إعادة الـ Scaffold أو إنشاء Branch جديد. الـ P0-01 baseline مدموجة وCI ناجح. استخدم فقط SHAs حقيقية وثابتة للـ browser E2E، ووثّق الـ PASS/FAIL/NOT VERIFIED بدقة.

الأمان: secrets encrypted/hidden، trusted first enrollment، TOTP/recovery replay + brute-force، مصر/إيطاليا RBAC، central overview بدون finance detail، consolidation grant منفصل ومقترح. لا تفرض سياسة إنتاجية غير معتمدة: سجّل PROPOSED لمراجعة مالك المشروع.

حدّث كود/اختبارات والتوثيق وحالة Issue #4. ممنوع Merge إلى rebuild/erp-v2 أو main أو oberation، ممنوع Production deployment أو بيانات حقيقية، وممنوع تحويل Draft PRs إلى Ready بدون الموافقة الصريحة.
```

Current immutable backend E2E source pin at time of writing: `0d2843ccb2df9c5df3629ce3f4ad0b56dfd44cea`. Check latest [handoff](handoffs/P0-02.md) for current CI and next atomic action; do not assume any CI pending at this text's commit has passed.
