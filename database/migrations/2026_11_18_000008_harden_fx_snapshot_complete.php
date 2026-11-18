<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONSTRAINT = 'p004_fx_snapshot_complete_chk';

    // The entire tuple must be absent, OR every member must be present and
    // both numeric values strictly positive. IS TRUE eliminates SQL UNKNOWN.
    private const VALID_TUPLE = <<<'SQL'
(
    (transaction_currency IS NULL AND amount_currency IS NULL AND fx_rate_snapshot IS NULL)
    OR
    (transaction_currency IS NOT NULL
        AND amount_currency IS NOT NULL AND amount_currency > 0
        AND fx_rate_snapshot IS NOT NULL AND fx_rate_snapshot > 0)
) IS TRUE
SQL;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('P0-04-FX requires PostgreSQL; no fallback financial checks.');
        }

        // This migration runs in Laravel's default PostgreSQL DDL transaction.
        // A complete ADD CHECK scan takes ACCESS EXCLUSIVE until commit.
        // Limit lock waiting; this is NOT a production DDL/latency sign-off.
        DB::statement("SET LOCAL lock_timeout = '3s'");
        DB::statement("SET LOCAL statement_timeout = '30s'");

        $old = DB::selectOne(<<<'SQL'
SELECT convalidated FROM pg_constraint
WHERE conrelid = 'journal_lines'::regclass
  AND conname = 'p003_currency_snapshot_chk' AND contype = 'c'
SQL);
        if ($old === null || ! $old->convalidated) {
            throw new RuntimeException('P0-04-FX requires the validated P0-03 FX constraint.');
        }

        // Fail closed on legacy bad rows. No implicit backfill, rewrite or
        // financial-history remediation. Any error rolls back the DDL attempt.
        $invalid = DB::selectOne('SELECT EXISTS (SELECT 1 FROM journal_lines WHERE ('
            .self::VALID_TUPLE.') IS NOT TRUE) AS present');
        if ($invalid !== null && $invalid->present) {
            throw new RuntimeException(
                'P0-04-FX preflight rejected incomplete historical FX rows; manual review required.'
            );
        }

        // Preserve P0-03 check and its rollback semantics; add one new named
        // guard. PostgreSQL validates all existing rows during this statement.
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT '.self::CONSTRAINT
            .' CHECK ('.self::VALID_TUPLE.')');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('P0-04-FX rollback requires PostgreSQL.');
        }

        DB::statement("SET LOCAL lock_timeout = '3s'");
        DB::statement('ALTER TABLE journal_lines DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
        // DO NOT drop or change p003_currency_snapshot_chk.
    }
};
