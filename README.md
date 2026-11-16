# Elmo Tech ERP V2 — Backend

**Status:** P0-01 Foundation Scaffold, **not production ready**. Clean Laravel 13 rewrite. The original backend remains on `oberation`.

## Approved technology
- Laravel 13 + PHP 8.4, PostgreSQL 18, Redis, Laravel Horizon (queue runner planned).
- React 19 + Vite + TypeScript frontend in `Elmo-Tech/erp-frontend` branch `rebuild/erp-v2`.
- One PostgreSQL database; company-scoped financial records and explicit cross-company operations.
- Modular monolith and a versioned REST API. Internal users only, no client portal.

## Local development (requires PHP 8.4, Composer 2, PostgreSQL 18, Redis)
```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000
```
Health: `GET /api/v1/health`. Never use real production data on a development DB.
Tests: `composer test`. Formatting: `composer lint`.

### Before connecting frontend
Configure first-party hostnames, CORS and Sanctum cookie middleware in P0-02.
No auth, user-management or financial posting endpoints are published in this initial scaffold. Existing migrations are intentionally foundational, not a complete general ledger engine.
Official tax configurations, invoice issuance, payroll and opening balances remain blocked pending accountant sign-off.

## Docs
- `docs/DECISIONS.md`: Approved choices and required gates
- `docs/BUSINESS_RULES.md`: Module/workflow contract
- `docs/SCHEMA_FOUNDATION.md`: Phase 0 tables and invariants
- `docs/EXECUTION_PLAN.md`: PR plan and acceptance tests
- `AGENTS.md`: Code quality/safety rules

## Security
- Never expose or log credentials, bank details, salaries, or secrets.
- All company-owned models will use explicit membership checks and composite foreign keys.
- A business record's `company_id` is authoritative: never accept an arbitrary company selector as authorization.
- Once posted, journals and issued financial records are corrected by reversals/credit notes, not edited.
- Both staging and production require separate databases and isolated secrets.

