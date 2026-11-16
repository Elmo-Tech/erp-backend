<?php

namespace App\Domain\Companies;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

/**
 * P0-03B: authorization must precede SET LOCAL on the SAME Laravel connection.
 *
 * This does not make a custom GUC an authorization token or turn on RLS.
 * Callers must still explicitly scope every legal-data query by company_id.
 * Never invoke from an already-open transaction or with a client-selected ID.
 */
final class AuthenticatedDatabaseContext
{
    public function run(User $actor, string $companyId, string $permission, Closure $work): mixed
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'pgsql') {
            throw new LogicException('Company DB context requires PostgreSQL.');
        }
        if ($connection->transactionLevel() !== 0) {
            throw new LogicException('Company DB context must own the outermost transaction.');
        }
        if (! Str::isUuid($companyId) || $permission === '') {
            throw new AuthorizationException('Company access denied.');
        }

        return $connection->transaction(function () use ($connection, $actor, $companyId, $permission, $work): mixed {
            // Re-query and lock. A deserialized job user or an old HTTP session is not authority.
            $liveActor = User::query()->whereKey($actor->getKey())
                ->where('is_active', true)->lockForUpdate()->first();
            if (! $liveActor) {
                throw new AuthorizationException('Company access denied.');
            }

            $membership = $connection->table('company_memberships as m')
                ->join('companies as c', 'c.id', '=', 'm.company_id')
                ->where('m.user_id', $liveActor->getKey())
                ->where('m.company_id', $companyId)
                ->where('m.status', 'active')
                ->where('c.status', 'active')
                ->lockForUpdate()->first(['m.id']);
            if (! $membership) {
                // Even a global central-management grant is not a legal-ledger membership.
                throw new AuthorizationException('Company access denied.');
            }

            // Lock role rows as well; revocation must serialize with the in-flight work.
            $connection->table('company_role_assignments')
                ->where('membership_id', $membership->id)
                ->where('company_id', $companyId)
                ->lockForUpdate()->get(['id']);

            if (! (new CompanyAuthorization())->has($liveActor, $companyId, $permission)) {
                throw new AuthorizationException('Company access denied.');
            }

            $connection->selectOne("SELECT set_config('app.company_id', ?, true) AS company_id", [$companyId]);
            $current = $connection->selectOne("SELECT nullif(current_setting('app.company_id', true), '') AS company_id");
            if ($current?->company_id !== $companyId) {
                throw new RuntimeException('Transaction-local company context failed to initialize.');
            }

            $result = $work($companyId);

            // Detect accidental/malicious changes before committing writes. Does not undo reads.
            $current = $connection->selectOne("SELECT nullif(current_setting('app.company_id', true), '') AS company_id");
            if ($current?->company_id !== $companyId) {
                throw new RuntimeException('Transaction-local company context changed during work.');
            }

            return $result;
        }, 1);
    }
}
