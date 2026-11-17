<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /** @var array<string, array<string, string>> */
    private const CHECKS = [
        'companies' => [
            'p003_company_status_chk' => "status IN ('active', 'inactive', 'suspended')",
            'p003_country_code_chk' => "country_code ~ '^[A-Z]{2}$'",
        ],
        'company_memberships' => [
            'p003_membership_status_chk' => "status IN ('active', 'inactive', 'pending', 'revoked')",
        ],
        'global_role_assignments' => [
            'p003_global_role_status_chk' => "status IN ('active', 'inactive', 'revoked')",
        ],
        'fiscal_years' => [
            'p003_fiscal_dates_chk' => 'starts_on <= ends_on',
            'p003_fiscal_state_chk' => "state IN ('draft', 'open', 'closed')",
        ],
        'accounting_periods' => [
            'p003_period_dates_chk' => 'starts_on <= ends_on',
            'p003_period_state_chk' => "state IN ('open', 'locked', 'closed')",
            'p003_period_lock_chk' => "(state = 'open' AND locked_at IS NULL) OR (state IN ('locked', 'closed') AND locked_at IS NOT NULL)",
        ],
        'ledger_accounts' => [
            'p003_account_type_chk' => "account_type IN ('asset', 'liability', 'equity', 'income', 'expense')",
            'p003_account_balance_chk' => "normal_balance IN ('debit', 'credit')",
        ],
        'journal_entries' => [
            'p003_journal_status_chk' => "status IN ('draft', 'posted', 'reversed')",
            'p003_posted_at_chk' => "(status = 'draft' AND posted_at IS NULL) OR (status IN ('posted', 'reversed') AND posted_at IS NOT NULL)",
        ],
        'journal_lines' => [
            'p003_line_no_chk' => 'line_no > 0',
            'p003_one_side_chk' => '(debit_base > 0 AND credit_base = 0) OR (debit_base = 0 AND credit_base > 0)',
            'p003_currency_snapshot_chk' => "(transaction_currency IS NULL AND amount_currency IS NULL AND fx_rate_snapshot IS NULL) OR (transaction_currency IS NOT NULL AND amount_currency > 0 AND fx_rate_snapshot > 0)",
        ],
        'outbox_events' => [
            'p003_event_version_chk' => 'event_version > 0',
        ],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('P0-03 accounting security migration requires PostgreSQL 18.');
        }

        // btree_gist allows a company UUID equality key alongside a date-range overlap test.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        // Existing tables already carry NOT NULL company_id and same-company composite FKs.
        // Add parent candidate keys needed by future company-owned child relationships.
        DB::statement('ALTER TABLE company_role_assignments ADD CONSTRAINT p003_role_company_id_uniq UNIQUE (company_id, id)');
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT p003_line_company_id_uniq UNIQUE (company_id, id)');
        DB::statement('ALTER TABLE idempotency_requests ADD CONSTRAINT p003_idempotency_company_id_uniq UNIQUE (company_id, id)');

        foreach (self::CHECKS as $table => $checks) {
            foreach ($checks as $name => $condition) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$condition})");
            }
        }

        // Boundaries are inclusive: touching at one calendar day is an overlap.
        DB::statement("ALTER TABLE fiscal_years ADD CONSTRAINT p003_fiscal_no_overlap EXCLUDE USING gist (company_id WITH =, daterange(starts_on, ends_on, '[]') WITH &&)");
        DB::statement("ALTER TABLE accounting_periods ADD CONSTRAINT p003_period_no_overlap EXCLUDE USING gist (company_id WITH =, daterange(starts_on, ends_on, '[]') WITH &&)");

        // PostgreSQL CHECK cannot query a parent row. Triggers protect cross-row date
        // containment, including updates to the parent after children already exist.
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION p003_validate_period_dates() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE fiscal_start date; fiscal_end date;
BEGIN
    SELECT starts_on, ends_on INTO fiscal_start, fiscal_end
      FROM fiscal_years WHERE id = NEW.fiscal_year_id AND company_id = NEW.company_id FOR SHARE;
    IF NOT FOUND OR NEW.starts_on < fiscal_start OR NEW.ends_on > fiscal_end THEN
        RAISE EXCEPTION 'accounting period outside its own company fiscal year' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END
$$;
SQL);
        DB::statement('CREATE TRIGGER p003_period_dates_trg BEFORE INSERT OR UPDATE OF company_id, fiscal_year_id, starts_on, ends_on ON accounting_periods FOR EACH ROW EXECUTE FUNCTION p003_validate_period_dates()');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION p003_validate_journal_date() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE period_start date; period_end date;
BEGIN
    SELECT starts_on, ends_on INTO period_start, period_end
      FROM accounting_periods WHERE id = NEW.period_id AND company_id = NEW.company_id FOR SHARE;
    IF NOT FOUND OR NEW.entry_date < period_start OR NEW.entry_date > period_end THEN
        RAISE EXCEPTION 'journal entry outside its own company accounting period' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END
$$;
SQL);
        DB::statement('CREATE TRIGGER p003_journal_date_trg BEFORE INSERT OR UPDATE OF company_id, period_id, entry_date ON journal_entries FOR EACH ROW EXECUTE FUNCTION p003_validate_journal_date()');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION p003_guard_fiscal_dates() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM accounting_periods p WHERE p.fiscal_year_id = OLD.id
          AND (p.company_id <> NEW.company_id OR p.starts_on < NEW.starts_on OR p.ends_on > NEW.ends_on)
    ) THEN
        RAISE EXCEPTION 'fiscal year change would orphan an accounting period' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END
$$;
SQL);
        DB::statement('CREATE TRIGGER p003_guard_fiscal_trg BEFORE UPDATE OF company_id, starts_on, ends_on ON fiscal_years FOR EACH ROW EXECUTE FUNCTION p003_guard_fiscal_dates()');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION p003_guard_period_dates() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM journal_entries j WHERE j.period_id = OLD.id
          AND (j.company_id <> NEW.company_id OR j.entry_date < NEW.starts_on OR j.entry_date > NEW.ends_on)
    ) THEN
        RAISE EXCEPTION 'accounting period change would orphan a journal entry' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END
$$;
SQL);
        DB::statement('CREATE TRIGGER p003_guard_period_trg BEFORE UPDATE OF company_id, starts_on, ends_on ON accounting_periods FOR EACH ROW EXECUTE FUNCTION p003_guard_period_dates()');

        // PostgreSQL does not index referencing FK columns automatically.
        DB::statement('CREATE INDEX p003_period_fiscal_fk_idx ON accounting_periods (company_id, fiscal_year_id)');
        DB::statement('CREATE INDEX p003_journal_period_fk_idx ON journal_entries (company_id, period_id)');
        DB::statement('CREATE INDEX p003_line_entry_fk_idx ON journal_lines (company_id, entry_id)');
        DB::statement('CREATE INDEX p003_line_account_fk_idx ON journal_lines (company_id, account_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS p003_guard_period_trg ON accounting_periods');
        DB::statement('DROP TRIGGER IF EXISTS p003_guard_fiscal_trg ON fiscal_years');
        DB::statement('DROP TRIGGER IF EXISTS p003_journal_date_trg ON journal_entries');
        DB::statement('DROP TRIGGER IF EXISTS p003_period_dates_trg ON accounting_periods');
        foreach (['p003_guard_period_dates', 'p003_guard_fiscal_dates', 'p003_validate_journal_date', 'p003_validate_period_dates'] as $function) {
            DB::statement("DROP FUNCTION IF EXISTS {$function}()");
        }
        foreach (['p003_line_account_fk_idx', 'p003_line_entry_fk_idx', 'p003_journal_period_fk_idx', 'p003_period_fiscal_fk_idx'] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
        DB::statement('ALTER TABLE accounting_periods DROP CONSTRAINT IF EXISTS p003_period_no_overlap');
        DB::statement('ALTER TABLE fiscal_years DROP CONSTRAINT IF EXISTS p003_fiscal_no_overlap');
        foreach (self::CHECKS as $table => $checks) {
            foreach (array_keys($checks) as $name) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
            }
        }
        DB::statement('ALTER TABLE idempotency_requests DROP CONSTRAINT IF EXISTS p003_idempotency_company_id_uniq');
        DB::statement('ALTER TABLE journal_lines DROP CONSTRAINT IF EXISTS p003_line_company_id_uniq');
        DB::statement('ALTER TABLE company_role_assignments DROP CONSTRAINT IF EXISTS p003_role_company_id_uniq');
        // Deliberately retain a pre-existing/shared btree_gist extension.
    }
};
