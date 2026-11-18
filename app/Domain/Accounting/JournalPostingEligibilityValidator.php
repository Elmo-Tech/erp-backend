<?php

namespace App\Domain\Accounting;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Internal, read-only, transient eligibility check for synthetic/development use.
 *
 * SECURITY: companyId is ONLY a lookup scope, NOT a trusted authorization grant.
 * NO actor authorization, posting transition, source idempotency or audit/outbox.
 * A passing result MUST NOT be used to post money. P0-04A guards are unmerged
 * on this independent base branch, so concurrent child writes can still race.
 */
final class JournalPostingEligibilityValidator
{
    public function assertEligible(string $companyId, string $journalId): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Posting eligibility validation requires PostgreSQL NUMERIC.');
        }

        DB::transaction(function () use ($companyId, $journalId): void {
            // Stable protocol: header -> company -> fiscal year -> period ->
            // currency codes -> account IDs -> READ-ONLY lines aggregate.
            // Never SELECT journal_lines FOR UPDATE: a BEFORE ROW edit trigger
            // may already own a child tuple before it requests the header lock.
            $header = DB::table('journal_entries')
                ->where('company_id', $companyId)->where('id', $journalId)
                ->lockForUpdate()->first();
            if ($header === null || $header->status !== 'draft' || $header->posted_at !== null) {
                throw new InvalidArgumentException('Own-company draft journal required.');
            }

            $company = DB::table('companies')
                ->where('id', $companyId)->sharedLock()->first();
            if ($company === null || $company->status !== 'active') {
                throw new InvalidArgumentException('Active legal company required.');
            }

            // Initial nonlocking discovery is safe only because this is an
            // advisory READ-ONLY check. Re-verify all keys under the locks.
            $periodRef = DB::table('accounting_periods')
                ->where('company_id', $companyId)->where('id', $header->period_id)
                ->first();
            if ($periodRef === null) {
                throw new InvalidArgumentException('Own-company accounting period required.');
            }

            $fiscal = DB::table('fiscal_years')
                ->where('company_id', $companyId)->where('id', $periodRef->fiscal_year_id)
                ->sharedLock()->first();
            $period = DB::table('accounting_periods')
                ->where('company_id', $companyId)->where('id', $header->period_id)
                ->sharedLock()->first();

            if ($fiscal === null || $fiscal->state !== 'open'
                || $period === null || $period->fiscal_year_id !== $fiscal->id
                || $period->state !== 'open' || $period->locked_at !== null
                || $period->starts_on < $fiscal->starts_on
                || $period->ends_on > $fiscal->ends_on
                || $header->entry_date < $period->starts_on
                || $header->entry_date > $period->ends_on
                || $header->entry_date < $fiscal->starts_on
                || $header->entry_date > $fiscal->ends_on) {
                throw new InvalidArgumentException('Fiscal year and accounting period must be open and contain the journal date.');
            }

            $currencyCodes = DB::table('journal_lines')
                ->where('company_id', $companyId)->where('entry_id', $journalId)
                ->whereNotNull('transaction_currency')
                ->distinct()->pluck('transaction_currency')->all();
            $currencyCodes[] = $company->base_currency_code;
            $currencyCodes = array_values(array_unique($currencyCodes));
            sort($currencyCodes, SORT_STRING);
            $currencies = DB::table('currencies')->whereIn('code', $currencyCodes)
                ->orderBy('code')->sharedLock()->get(['code', 'active']);
            if ($currencies->count() !== count($currencyCodes)) {
                throw new InvalidArgumentException('Unknown ledger currency.');
            }
            foreach ($currencies as $currency) {
                if (! $this->isTrue($currency->active)) {
                    throw new InvalidArgumentException('Inactive ledger currency.');
                }
            }

            $accounts = DB::table('ledger_accounts')
                ->where('company_id', $companyId)
                ->whereIn('id', function ($query) use ($companyId, $journalId): void {
                    $query->select('account_id')->from('journal_lines')
                        ->where('company_id', $companyId)->where('entry_id', $journalId);
                })
                ->orderBy('id')->sharedLock()->get(['id', 'active']);
            foreach ($accounts as $account) {
                if (! $this->isTrue($account->active)) {
                    throw new InvalidArgumentException('Inactive journal account.');
                }
            }

            // All monetary comparison and addition occurs INSIDE PostgreSQL,
            // where SUM(DECIMAL(20,4)) yields arbitrary-precision NUMERIC.
            // No conversion of financial amounts to PHP int/float.
            $result = DB::selectOne(<<<'SQL'
SELECT
    COUNT(DISTINCT l.account_id) AS account_count,
    CASE WHEN
        COUNT(*) >= 2
        AND COALESCE(SUM(l.debit_base), 0::numeric) > 0::numeric
        AND COALESCE(SUM(l.debit_base), 0::numeric) = COALESCE(SUM(l.credit_base), 0::numeric)
        AND COUNT(*) FILTER (WHERE
            l.company_id <> ?
            OR NOT ((l.debit_base > 0 AND l.credit_base = 0)
                    OR (l.credit_base > 0 AND l.debit_base = 0))
            OR a.id IS NULL OR a.company_id <> l.company_id
            OR a.active IS DISTINCT FROM TRUE
            OR NOT (
                (l.transaction_currency IS NULL
                    AND l.amount_currency IS NULL AND l.fx_rate_snapshot IS NULL)
                OR (l.transaction_currency IS NOT NULL
                    AND l.amount_currency > 0 AND l.fx_rate_snapshot > 0)
            )
        ) = 0
    THEN 1 ELSE 0 END AS eligible
FROM journal_lines l
LEFT JOIN ledger_accounts a ON a.id = l.account_id AND a.company_id = l.company_id
WHERE l.entry_id = ?
SQL, [$companyId, $journalId]);

            if ($result === null || (int) $result->eligible !== 1
                || (int) $result->account_count !== $accounts->count()) {
                throw new InvalidArgumentException('Journal lines must be exact, balanced, one-sided, currency-complete and own-company active.');
            }
        });
    }

    private function isTrue(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't'], true);
    }
}
