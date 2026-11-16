<?php

declare(strict_types=1);

if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test') {
    exit(2);
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$file = '/tmp/p003b-queue-evidence.jsonl';
$records = array_map(static fn (string $line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
    file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
$eg = '00000000-0000-4000-8000-000000000091';
$it = '00000000-0000-4000-8000-000000000092';
$expected = [[$eg,'allow'],[$it,'allow'],[$eg,'allow'],[$eg,'deny'],[$it,'allow']];
if (count($records) !== count($expected)) {
    throw new RuntimeException('Expected five executed jobs, got '.count($records));
}
$pids = [];
foreach ($expected as $i => [$company,$status]) {
    $row = $records[$i];
    if ($row['company'] !== $company || $row['result'] !== $status || $row['after'] !== null) {
        throw new RuntimeException('Bad worker result at index '.$i.': '.json_encode($row));
    }
    $pids[] = $row['worker_pid'];
}
if (count(array_unique($pids)) !== 1) {
    throw new RuntimeException('Jobs did not share one reused worker process.');
}
if (DB::table('jobs')->where('queue','p003b')->count() !== 0 || DB::table('failed_jobs')->count() !== 0) {
    throw new RuntimeException('Queue has unfinished or failed jobs.');
}
echo "PASS: real Laravel reused queue worker, five jobs, EG -> IT -> revoked EG denial -> IT, no GUC leak.\n";
