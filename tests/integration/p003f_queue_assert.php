<?php

declare(strict_types=1);
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || getenv('DB_USERNAME') !== 'p003e_web' || getenv('DB_QUEUE_CONNECTION') !== 'pgsql_queue') exit(2);
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$file='/tmp/p003f-queue-evidence.jsonl';
$lines=file($file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [];
if (count($lines)!==5) throw new RuntimeException('Expected five jobs, got '.count($lines));
$expect=['c1','c2','c1','c2','c1']; $pids=[];
foreach ($lines as $i=>$line) {
    $row=json_decode($line,true,512,JSON_THROW_ON_ERROR);
    if ($row['result']!=='allow' || $row['after']!==null
        || ! str_ends_with($row['company'],$expect[$i])) {
        throw new RuntimeException('Job scope or GUC leak at position '.$i);
    }
    $pids[]=$row['worker_pid'];
}
if (count(array_unique($pids))!==1) throw new RuntimeException('Queue jobs did not reuse one worker.');
if (DB::connection('pgsql_queue')->table('jobs')->where('queue','p003f')->count() !== 0
    || DB::connection('pgsql_queue')->table('failed_jobs')->count() !== 0) {
    throw new RuntimeException('Residual or failed Queue jobs.');
}
echo "PASS: isolated Queue SQL principal, 5 reused-worker jobs, EG/IT context and no GUC leak.\n";
