# Next chat prompt — P0-02 Auth & RBAC

Suggested chat title: `ERP V2 | P0-02 | Auth & RBAC`

```text
اشتغل على Elmo Tech ERP V2 في مهمة P0-02 فقط: Authentication, MFA & Company-Scoped RBAC.

Backend: https://github.com/Elmo-Tech/erp-backend/tree/rebuild/erp-v2
Frontend: https://github.com/Elmo-Tech/erp-frontend/tree/rebuild/erp-v2
Issue: https://github.com/Elmo-Tech/erp-backend/issues/4

اقرأ من Backend على أحدث rebuild/erp-v2: docs/START_HERE.md, docs/PROJECT_STATUS.md, docs/WORK_PROTOCOL.md, docs/MODULE_ROADMAP.md, docs/DECISIONS.md, docs/BUSINESS_RULES.md, docs/SCHEMA_FOUNDATION.md و docs/handoffs/P0-02.md، وكمان AGENTS.md في المشروعين وحالة GitHub PRs/CI.

الـ PRs الخاصة بـ P0-01 اتدمجت بالفعل والـ CI ناجح؛ ممنوع ترجع للـ scaffold القديم أو تعيد نفس الشغل. Issue #1 ما زالت مفتوحة لأن اختبار اتصال المتصفح بالـ API الحقيقي لم يتم إثباته، فاختبر الـ SPA مع الـ Backend فعليًا في بيئة آمنة واربط دليل النجاح بالمهمة #1.

اشتغل على topic branch task/p0-02-auth-rbac من rebuild/erp-v2 لكل repo مطلوب، وافتح PR على rebuild/erp-v2، وماتعدّلش main أو oberation أو Production أو بيانات حقيقية، وماتعملش Merge من غير تصريح.

ابدأ بتصميم Auth/MFA/Permission Matrix و API Contract، وبعدها نفّذ Sanctum SPA cookies + CSRF + Login/Me/Logout، ثم MFA للحسابات الحساسة، Company-scoped RBAC + Switcher، UI وتستات منع وصول مصر لإيطاليا والعكس، مع اختبار real browser/CORS/cookies.

قبل ما تخلص وثّق كل حاجة والـ CI evidence وPRs في docs/PROJECT_STATUS.md و docs/handoffs/P0-02.md و MODULE_ROADMAP.md و DECISIONS.md عند وجود قرار جديد، وحدّث Issue #4. أي حاجة غير متختبرة اكتب NOT VERIFIED. خلّص قدر الإمكان في الشات ده، وسلّم Next Atomic Action.
```
