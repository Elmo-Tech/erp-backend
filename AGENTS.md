# ERP V2 Engineering Agreement

## Scope and safety
- Work ONLY on `rebuild/erp-v2`; never merge, force-push, or modify `oberation` or `main` without a separate release decision.
- This is a new Laravel 13 application; don't copy legacy migrations or old business rules blindly.
- Keep domain modules separate within one deployable Laravel app.
- No Client Portal and no automated payments/provider renewals in initial scope.
- Never commit environment secrets, customer financial data or live database dumps.
- Use small, reviewable PRs with tests and conventional commits.

## Non-negotiable multi-company behavior
- Egypt and Italy are distinct legal companies, each with independent GL, bank, invoices, taxes and payroll.
- One global customer master is allowed, but legal customer relationships must belong to a company.
- Cross-company project assignment conveys work access only, not ledger or payroll access.
- All financial child entities require same-company composite FKs and server-side authorization.
- External accountants may export only their own company's data.
- Central consolidated reporting must eliminate intercompany duplications explicitly.

## Non-negotiable accounting behavior
- Server computes money and tax; decimals not floats; rates snapshotted at posting.
- Balanced journals, immutable posting, locked periods, and correction by reversal.
- All recurrence/payment writes idempotent and transactionally recorded with audit + outbox.
- No official tax filing integration initially; accountant verifies local tax and revenue recognition.

## Development gate
- Write tests for company isolation, approvals, idempotency, money, and error handling.
- Do not expose a financial API before its authorization, atomic posting and test suite exist.
- Annotate unimplemented controls honestly; do not call a scaffold production-ready.

