<?php

namespace Tests\Support;

use App\Domain\Companies\AuthenticatedDatabaseContext;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class P003ContextQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $actorId,
        public string $companyId,
        public bool $revokeMembershipAfter = false,
    ) {}

    public function handle(AuthenticatedDatabaseContext $context): void
    {
        if (! app()->environment('testing') || getenv('DB_DATABASE') !== 'elmo_erp_test') {
            throw new RuntimeException('P0-03B synthetic queue probe refuses a non-test database.');
        }
        $actor = User::query()->select(['id', 'is_active'])->findOrFail($this->actorId);
        try {
            $seen = $context->run($actor, $this->companyId, 'company.view',
                fn (string $id) => DB::selectOne(
                    "SELECT nullif(current_setting('app.company_id', true), '') AS tenant"
                )->tenant
            );
            $status = $seen === $this->companyId ? 'allow' : 'bad-context';
        } catch (AuthorizationException) {
            $status = 'deny';
        }
        $after = DB::selectOne("SELECT nullif(current_setting('app.company_id', true), '') AS tenant")->tenant;
        if ($after !== null || $status === 'bad-context') {
            throw new RuntimeException('Queue worker leaked context across jobs.');
        }
        if ($this->revokeMembershipAfter) {
            DB::table('company_memberships')
                ->where('user_id', $this->actorId)
                ->where('company_id', $this->companyId)->update(['status' => 'revoked']);
        }
        $file = getenv('P003_EVIDENCE_FILE');
        if (! is_string($file) || ! str_starts_with($file, '/tmp/')) {
            throw new RuntimeException('P0-03B test evidence path must be in /tmp.');
        }
        file_put_contents($file, json_encode([
            'company' => $this->companyId, 'result' => $status, 'after' => $after,
            'worker_pid' => getmypid(),
        ], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
    }
}
