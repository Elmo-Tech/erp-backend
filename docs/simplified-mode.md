# Simplified ERP Mode

The Tables ERP runs with simplified mode enabled by default through `config/simplification.php`.

```php
'enabled' => env('ERP_SIMPLIFIED_MODE', true),
```

When enabled, hidden legacy modules must remain in the codebase for compatibility, but their API routes must be blocked with the `simplified.disabled:{module}` middleware. The middleware returns a forbidden response before the legacy controller action is allowed to run.

## Middleware

The middleware alias is registered in `bootstrap/app.php`:

```php
'simplified.disabled' => \App\Http\Middleware\BlockSimplifiedModule::class,
```

Use it on any route or route group that belongs to a hidden module:

```php
Route::middleware('simplified.disabled:billing')->group(function () {
    Route::apiResource('invoices', InvoiceController::class);
});
```

The module key must be present in `config/simplification.php` under `disabled_modules`.

## Hidden Route Groups

The simplified backend blocks these hidden module families:

- Sales CRM: leads, lead activities, meetings, sales dashboard, sales analytics, sales exports.
- Pricing and legal workflow: proposals, quotations, contracts, pricing/markup/margin flows.
- Billing: invoices, milestone invoices, client invoices, client payments, payment status.
- Financial internals: profitability, internal costs, cost reports, salary adjustments.
- Points and rewards: user points, leaderboards, point periods, bonuses, rewards.
- Performance workflows: performance evaluations, employee reviews, quality/performance reports.
- Software-agency review workflows: code reviews, innovation logs, deliverable approval/review, UI approval.
- Advanced HR deductions: deduction types and employee deduction management outside the approved attendance workflow.

## Operational Route Groups

These routes remain part of The Tables ERP simplified scope:

- Auth and own profile.
- Dashboard operational overview.
- Clients and client portal access management.
- Projects, project members, project files/media, project history, and project wiki.
- Tasks, task comments/activity, task timers, and time entries for task work.
- Attendance, working hours, grace-day, and attendance management.
- Employees/users, roles, permissions, settings-style enum data, notifications, audit logs, and operational admin stats.
- Client portal project summaries and client-visible project files.

## Adding Routes Safely

When adding a backend route:

1. Decide whether the route is operational ERP scope or hidden legacy scope.
2. If it is hidden, add `simplified.disabled:{module}` to the route or enclosing route group.
3. If no module key exists, add one to `config/simplification.php`.
4. Add or update a focused route regression test in `tests/Feature/SimplifiedModeRouteGuardTest.php`.
5. Do not delete legacy controllers, models, migrations, services, or tables just because a module is hidden.

The current strategy keeps legacy implementation available for future migration or enterprise variants while preventing simplified roles from reaching hidden modules by default.
