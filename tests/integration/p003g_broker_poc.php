<?php
/**
 * P0-03G disposable, separately executable MFA command PoC.
 * NOT an HTTP route, NOT a production broker, and NOT a deployable security boundary.
 * The calling Web PHP process must never receive the PDO or encryption environment.
 * This executable's seed/revoke/inspect commands are TEST-ONLY admin operations.
 */
declare(strict_types=1);

function reply(array $value): never {
    echo json_encode($value, JSON_THROW_ON_ERROR)."\n";
    exit(0);
}
function denied(string $reason): never { reply(['ok'=>false,'reason'=>$reason]); }
function audit(PDO $db, string $event, string $email, int $now): void {
    $q=$db->prepare('INSERT INTO public.p003g_audit(event,account_digest,created_epoch) VALUES(?,?,?)');
    $q->execute([$event, hash('sha256', $email),$now]);
}
function factorKey(): string {
    $key=base64_decode((string)getenv('P003G_FACTOR_KEY'),true);
    if ($key===false || strlen($key)!==32) throw new RuntimeException('Missing broker-only encryption key');
    return $key;
}
function seal(string $plain): string {
    $iv=random_bytes(12);
    $tag='';
    $encrypted=openssl_encrypt($plain,'aes-256-gcm',factorKey(),OPENSSL_RAW_DATA,$iv,$tag);
    if($encrypted===false) throw new RuntimeException('Encryption failed');
    return base64_encode($iv.$tag.$encrypted);
}
function unseal(string $sealed): string {
    $data=base64_decode($sealed,true);
    if($data===false || strlen($data)<29) throw new RuntimeException('Invalid factor envelope');
    $plain=openssl_decrypt(substr($data,28),'aes-256-gcm',factorKey(),OPENSSL_RAW_DATA,substr($data,0,12),substr($data,12,16));
    if($plain===false) throw new RuntimeException('Invalid factor envelope');
    return $plain;
}
function totp(string $factor, int $step): string {
    $key=hex2bin($factor);
    if($key===false || strlen($key)!==20) throw new RuntimeException('Invalid factor key');
    $digest=hash_hmac('sha1',pack('N2',($step>>32)&0xffffffff,$step&0xffffffff),$key,true);
    $offset=ord($digest[19])&15;
    $number=(unpack('N',substr($digest,$offset,4))[1]&0x7fffffff)%1000000;
    return str_pad((string)$number,6,'0',STR_PAD_LEFT);
}
if(getenv('APP_ENV')!=='testing' || getenv('DB_DATABASE')!=='elmo_erp_test'
    || getenv('P003G_BROKER_USERNAME')!=='p003g_broker') {
    fwrite(STDERR,"REFUSED: disposable broker test environment required\n"); exit(3);
}
try {
    $raw=stream_get_contents(STDIN);
    $request=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($request)) denied('bad_request');
    $op=$request['op']??'';
    $email=$request['email']??'';
    if(!is_string($email) || strlen($email)>254) denied('bad_request');
    $db=new PDO(
       'pgsql:host='.getenv('P003G_BROKER_HOST').';port='.getenv('P003G_BROKER_PORT').';dbname=elmo_erp_test',
       'p003g_broker',(string)getenv('P003G_BROKER_PASSWORD'),
       [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>true]
    );
    $now=time();
    $admin=in_array($op,['seed','revoke','inspect'],true);
    if($admin && getenv('P003G_TEST_ADMIN')!=='1') denied('admin_disabled');
    if($op==='seed') {
        $factor=bin2hex(random_bytes(20));
        $q=$db->prepare('INSERT INTO public.p003g_accounts(email,user_id,password_hash,otp_cipher) VALUES(?,?,?,?)');
        $q->execute([$email, (int)($request['id']??0),
            password_hash((string)($request['password']??''),PASSWORD_BCRYPT),seal($factor)]);
        reply(['ok'=>true,'test_factor'=>$factor]);
    }
    if($op==='inspect') {
        $q=$db->prepare('SELECT event,account_digest FROM public.p003g_audit ORDER BY id');
        $q->execute();
        reply(['ok'=>true,'audit'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if(!in_array($op,['begin','verify','revoke'],true)) denied('unsupported_command');
    $db->beginTransaction();
    if($op==='revoke') {
        $q=$db->prepare('UPDATE public.p003g_accounts SET revocation_epoch=revocation_epoch+1 WHERE email=?');
        $q->execute([$email]);
        audit($db,'revoked',$email,$now); $db->commit(); reply(['ok'=>$q->rowCount()===1]);
    }
    if($op==='begin') {
        $q=$db->prepare('SELECT * FROM public.p003g_accounts WHERE email=? FOR UPDATE');
        $q->execute([$email]); $account=$q->fetch(PDO::FETCH_ASSOC);
        if(!$account || !$account['active'] || (int)$account['locked_until_epoch']>$now) {
            audit($db,'begin_denied',$email,$now);$db->commit();denied('denied');
        }
        if(!password_verify((string)($request['password']??''),$account['password_hash'])) {
            $failed=(int)$account['failed_passwords']+1;
            $q=$db->prepare('UPDATE public.p003g_accounts SET failed_passwords=?, locked_until_epoch=? WHERE email=?');
            $q->execute([$failed,$failed>=5?$now+300:0,$email]);
            audit($db,'password_denied',$email,$now);$db->commit();denied('denied');
        }
        $q=$db->prepare('UPDATE public.p003g_accounts SET failed_passwords=0, locked_until_epoch=0 WHERE email=?');
        $q->execute([$email]);
        $token=bin2hex(random_bytes(32));
        $q=$db->prepare('INSERT INTO public.p003g_challenges(token_hash,email,created_epoch,expires_epoch,revocation_epoch) VALUES(?,?,?,?,?)');
        $q->execute([hash('sha256',$token),$email,$now,$now+300,$account['revocation_epoch']]);
        audit($db,'challenge_created',$email,$now);$db->commit();
        reply(['ok'=>true,'challenge'=>$token,'expires_in'=>300]);
    }
    $token=$request['challenge']??'';
    $code=$request['code']??'';
    if(!is_string($token) || !preg_match('/^[0-9a-f]{64}$/D',$token)
       || !is_string($code) || !preg_match('/^[0-9]{6}$/D',$code)) {
       $db->rollBack();denied('invalid_format');
    }
    $q=$db->prepare('SELECT * FROM public.p003g_challenges WHERE token_hash=? FOR UPDATE');
    $q->execute([hash('sha256',$token)]);$challenge=$q->fetch(PDO::FETCH_ASSOC);
    if(!$challenge || $challenge['email']!==$email) {
       audit($db,'challenge_denied',$email,$now);$db->commit();denied('denied');
    }
    $q=$db->prepare('SELECT * FROM public.p003g_accounts WHERE email=? FOR UPDATE');
    $q->execute([$email]);$account=$q->fetch(PDO::FETCH_ASSOC);
    if(!$account || !$account['active'] || $challenge['consumed_epoch']!==null ||
       (int)$challenge['expires_epoch']<=$now || (int)$challenge['attempts']>=5 ||
       (int)$challenge['revocation_epoch']!==(int)$account['revocation_epoch']) {
       audit($db,'challenge_denied',$email,$now);$db->commit();denied('denied');
    }
    $step=intdiv($now,30);
    $factor=unseal($account['otp_cipher']);
    $match=null;
    foreach([$step-1,$step,$step+1] as $candidate) {
        if($candidate>(int)$account['last_totp_step'] && hash_equals(totp($factor,$candidate),$code)) {
            $match=$candidate;break;
        }
    }
    if($match===null) {
        $q=$db->prepare('UPDATE public.p003g_challenges SET attempts=attempts+1 WHERE token_hash=?');
        $q->execute([hash('sha256',$token)]);
        audit($db,'factor_denied',$email,$now);$db->commit();denied('denied');
    }
    $q=$db->prepare('UPDATE public.p003g_accounts SET last_totp_step=? WHERE email=?');
    $q->execute([$match,$email]);
    $q=$db->prepare('UPDATE public.p003g_challenges SET consumed_epoch=? WHERE token_hash=?');
    $q->execute([$now,hash('sha256',$token)]);
    audit($db,'factor_verified',$email,$now);$db->commit();
    // This is a disposable proof, NOT a trusted Laravel session/identity grant.
    reply(['ok'=>true,'user_id'=>(int)$account['user_id'],'mfa_verified'=>true]);
} catch(Throwable $e) {
    if(isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR,"Broker command failed (details redacted)\n");
    exit(1);
}
