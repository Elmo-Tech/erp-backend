<?php

declare(strict_types=1);
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || getenv('DB_USERNAME') !== 'p003e_web'
    || getenv('DB_QUEUE_CONNECTION') !== 'pgsql_queue') {
    fwrite(STDERR, "REFUSED: P0-03F disposable Web/Queue roles required.\n");
    exit(2);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Tests\Support\P003ContextQueueJob;

$eg = DB::table('users')->where('email','p003c-eg@example.invalid')->value('id');
$it = DB::table('users')->where('email','p003c-it@example.invalid')->value('id');
if (! $eg || ! $it) throw new RuntimeException('Missing disposable EG/IT identities.');
foreach ([[$eg,'00000000-0000-4000-8000-0000000000c1'],[$it,'00000000-0000-4000-8000-0000000000c2'],
          [$eg,'00000000-0000-4000-8000-0000000000c1'],[$it,'00000000-0000-4000-8000-0000000000c2'],
          [$eg,'00000000-0000-4000-8000-0000000000c1']] as [$user,$company]) {
    P003ContextQueueJob::dispatch($user,$company,false)->onConnection('database')->onQueue('p003f');
}
echo "PASS: five synthetic jobs enqueued with isolated Queue SQL principal.\n";
