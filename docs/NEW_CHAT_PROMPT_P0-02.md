# Historical continuation prompt — P0-02 Auth/MFA/RBAC security review

**P0-02 was squash-merged to the development integration branches. For new database work use [NEW_CHAT_PROMPT_P0-03.md](NEW_CHAT_PROMPT_P0-03.md), not this historical P0-02 prompt. Security Issue #7 is still open.**

Suggested chat title: `ERP V2 | P0-02 | Auth & RBAC`

> This file preserves the useful cross-chat task instructions originally prepared in the **stale docs-only PR #5**, but updates the now-outdated `NOT STARTED` statements and PR refs. The latest handoff and project status in backend Draft PR #6 are canonical and MUST NOT be overwritten by merging PR #5 as-is.

```text
كمّل P0-02 بس: Authentication, MFA & Company-Scoped RBAC — ما تبدأش P0-03.
Backend: https://github.com/Elmo-Tech/erp-backend/pull/6
Frontend: https://github.com/Elmo-Tech/erp-frontend/pull/2
Issue: https://github.com/Elmo-Tech/erp-backend/issues/4

اقرأ أولًا أحدث Review Comments في PR #6/#2 وdocs PR #5، وبعدها docs/START_HERE.md وPROJECT_STATUS.md وWORK_PROTOCOL.md وMODULE_ROADMAP.md وDECISIONS.md وAUTH_SECURITY_CONTRACT.md وdocs/handoffs/P0-02.md وكمان AGENTS.md في المشروعين.

اشتغل على نفس task/p0-02-auth-rbac في الريبوين، بدون إعادة الـ Scaffold أو إنشاء Branch جديد. الـ P0-01 baseline مدموجة وCI ناجح. استخدم فقط SHAs حقيقية وثابتة للـ browser E2E، ووثّق الـ PASS/FAIL/NOT VERIFIED بدقة.

الأمان: secrets encrypted/hidden، trusted first enrollment، TOTP/recovery replay + brute-force، مصر/إيطاليا RBAC، central overview بدون finance detail، consolidation grant منفصل. **مبادئ سياسة الأمان اعتمدها مالك المشروع في ADR-020**: privileged MFA، trusted activation مع التحقق من الهوية وموافقة مستقلة، Recovery موثّق، company isolation، key lifecycle وHTTPS/session security. **تفاصيل التنفيذ والسياسات التشغيلية** مثل اسم المسؤولين، الهوية وإعادة الربط، exact role matrix، production origins/key rotation لا تزال OPEN وليست معتمدة تلقائيًا. اقرأ ADR-020 ولا ترجع المبادئ لـ PROPOSED.

حدّث كود/اختبارات والتوثيق وحالة Issue #4. ممنوع Merge إلى rebuild/erp-v2 أو main أو oberation، ممنوع Production deployment أو بيانات حقيقية، وممنوع تحويل Draft PRs إلى Ready بدون الموافقة الصريحة.
```

Current immutable tested backend E2E source pin: `ceded61c936009105b0607c022c02967e2645a6b`. [Chromium #43885394390](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43885394390) PASS with pinned backend; [Backend #43881203049](https://github.com/Elmo-Tech/erp-backend/actions/runs/43881203049) PASS 27/233. Next: independent qualified human security review, finalize operator/identity/rebind/key/session runbooks and exact permission matrix, then separately request merge authorization. Check latest [handoff](handoffs/P0-02.md) and live CI in every session.
