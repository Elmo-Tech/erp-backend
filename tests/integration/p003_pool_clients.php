<?php

declare(strict_types=1);

// P0-03B: actual two-client / transaction-pooling concurrency smoke.
// No SQL writes, no live DB, no claim of Laravel HTTP concurrency acceptance.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'elmo_erp_test'
    || getenv('DB_PORT') !== '6432') {
    fwrite(STDERR, "REFUSED: actual PgBouncer disposable environment required.\n");
    exit(2);
}
if (! function_exists('pcntl_fork')) {
    throw new RuntimeException('pcntl extension required for concurrent pooled clients.');
}
$eg='00000000-0000-4000-8000-000000000091';
$it='00000000-0000-4000-8000-000000000092';
$children=[];
foreach ([$eg,$it] as $tenant) {
    $pid=pcntl_fork();
    if ($pid===-1) {throw new RuntimeException('Unable to fork pooled client');}
    if ($pid===0) {
        try {
            $pdo=new PDO('pgsql:host=127.0.0.1;port=6432;dbname=elmo_erp_test',
                getenv('DB_USERNAME')?:'elmo',getenv('DB_PASSWORD')?:'',[
                    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES=>true,
                ]);
            for($i=0;$i<12;$i++) {
                $pdo->beginTransaction();
                $stmt=$pdo->prepare("SELECT set_config('app.company_id', ?, true)");
                $stmt->execute([$tenant]);
                usleep(20000);
                $seen=$pdo->query("SELECT nullif(current_setting('app.company_id',true),'')")->fetchColumn();
                if ($seen!==$tenant) {throw new RuntimeException('Concurrent client lost its company context');}
                if($i%2===0){$pdo->rollBack();}else{$pdo->commit();}
                if($pdo->query("SELECT nullif(current_setting('app.company_id',true),'')")->fetchColumn()!==null){
                    throw new RuntimeException('Pool reused a prior transaction company context');
                }
            }
            exit(0);
        }catch(Throwable $e){
            fwrite(STDERR, 'POOL CLIENT FAILURE: '.$e->getMessage()."\n");
            exit(1);
        }
    }
    $children[]=$pid;
}
foreach($children as $pid){
    pcntl_waitpid($pid,$status);
    if(!pcntl_wifexited($status)||pcntl_wexitstatus($status)!==0){
        throw new RuntimeException('Concurrent pooled-client isolation failed');
    }
}
echo "PASS: two parallel PgBouncer clients, 24 transactions, EG/IT transaction-local context cleanup.\n";
