<?php
declare(strict_types=1);

function vqdLegacyPermissionFallback(string $code): bool
{
    $role=currentRole();

    if($role===ROLE_ADMIN) return true;

    $staff=[
        'vqd.dashboard.view','vqd.records.manage',
        'vqd.quorum.view','vqd.quorum.manage','vqd.quorum.attendance','vqd.quorum.certify',
        'vqd.voting.view','vqd.voting.manage','vqd.voting.cast',
        'vqd.tally.view','vqd.tally.manage','vqd.tally.certify',
        'vqd.decisions.view','vqd.decisions.manage','vqd.decisions.certify','vqd.decisions.publish',
        'vqd.validation.view','vqd.validation.manage','vqd.validation.certify',
        'vqd.reports.view','vqd.activity_logs.view'
    ];

    if($role===ROLE_STAFF && in_array($code,$staff,true)) return true;

    $committee=[
        'vqd.dashboard.view',
        'vqd.quorum.view','vqd.quorum.attendance',
        'vqd.voting.view','vqd.voting.cast',
        'vqd.tally.view','vqd.decisions.view','vqd.validation.view','vqd.reports.view'
    ];

    if($role===ROLE_COMMITTEE && in_array($code,$committee,true)) return true;

    return $code==='vqd.dashboard.view' && isLoggedIn();
}

function vqdHasPermission(string $code): bool
{
    if(!isLoggedIn()) return false;
    if(currentRole()===ROLE_ADMIN) return true;

    if(!tableExists('permissions') || !tableExists('role_permissions')){
        return vqdLegacyPermissionFallback($code);
    }

    try{
        $roleId=(int)($_SESSION['role_id']??0);

        if($roleId<=0 && currentUserId()){
            $roleStmt=db()->prepare('SELECT role_id FROM users WHERE id=:id LIMIT 1');
            $roleStmt->execute([':id'=>currentUserId()]);
            $roleId=(int)$roleStmt->fetchColumn();

            if($roleId>0){
                $_SESSION['role_id']=$roleId;
            }
        }

        if($roleId<=0){
            return vqdLegacyPermissionFallback($code);
        }

        $stmt=db()->prepare(
          'SELECT COUNT(*)
           FROM role_permissions rp
           JOIN permissions p ON p.id=rp.permission_id
           WHERE rp.role_id=:role
             AND p.code=:code'
        );
        $stmt->execute([
          ':role'=>$roleId,
          ':code'=>$code
        ]);

        if((int)$stmt->fetchColumn()>0) return true;

        if(in_array($code,['vqd.voting.cast','vqd.voting.view'],true)){
            $isVoter=db()->prepare('SELECT COUNT(*) FROM vqd_voting_event_voters WHERE user_id=:u AND voter_eligibility="Eligible"');
            $isVoter->execute([':u'=>currentUserId()]);
            if((int)$isVoter->fetchColumn()>0) return true;
        }

        $known=db()->prepare('SELECT COUNT(*) FROM permissions WHERE code=:code');
        $known->execute([':code'=>$code]);

        return (int)$known->fetchColumn()===0
            ? vqdLegacyPermissionFallback($code)
            : false;
    }catch(Throwable $e){
        error_log('[VQDSS Permission] '.$e->getMessage());
        return vqdLegacyPermissionFallback($code);
    }
}

function requirePermission(string $code): void
{
    requireLogin();

    if(vqdHasPermission($code)) return;

    if(isAjaxRequest()){
        http_response_code(403);
        jsonResponse(false,'You do not have permission to perform this VQDSS action.');
    }

    http_response_code(403);
    $pageTitle='Access Denied';
    include __DIR__.'/../layouts/header.php';
    echo '<div class="app-wrapper">';
    include __DIR__.'/../layouts/sidebar.php';
    echo '<main class="main-content"><section class="system-message-card">';
    echo '<i class="bi bi-shield-lock"></i><h1>Access Denied</h1>';
    echo '<p>Your current VQDSS role does not include this permission.</p>';
    echo '<a class="btn btn-primary" href="'.e(appUrl('dashboard.php')).'">Return to Dashboard</a>';
    echo '</section>';
    include __DIR__.'/../layouts/footer.php';
    exit;
}

function vqdClientIp(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR']??'unknown'),0,45);
}

function vqdLoginRateLimitStatus(string $email): array
{
    if(!tableExists('vqd_login_attempts')){
        return ['blocked'=>false,'remaining'=>5,'retry_after_seconds'=>0];
    }

    $emailHash=hash('sha256',strtolower(trim($email)));
    $ipHash=hash('sha256',vqdClientIp());

    try{
        $stmt=db()->prepare(
          "SELECT COUNT(*)
           FROM vqd_login_attempts
           WHERE successful=0
             AND attempted_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)
             AND (email_hash=:email OR ip_hash=:ip)"
        );
        $stmt->execute([':email'=>$emailHash,':ip'=>$ipHash]);
        $failed=(int)$stmt->fetchColumn();

        if($failed<5){
            return ['blocked'=>false,'remaining'=>5-$failed,'retry_after_seconds'=>0];
        }

        $last=db()->prepare(
          "SELECT attempted_at
           FROM vqd_login_attempts
           WHERE successful=0
             AND attempted_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)
             AND (email_hash=:email OR ip_hash=:ip)
           ORDER BY attempted_at DESC LIMIT 1"
        );
        $last->execute([':email'=>$emailHash,':ip'=>$ipHash]);
        $lastAt=$last->fetchColumn();
        $retry=$lastAt
            ? max(60,(strtotime((string)$lastAt)+900)-time())
            : 900;

        return ['blocked'=>true,'remaining'=>0,'retry_after_seconds'=>$retry];
    }catch(Throwable $e){
        error_log('[VQDSS Login Limit] '.$e->getMessage());
        return ['blocked'=>false,'remaining'=>5,'retry_after_seconds'=>0];
    }
}

function vqdRecordLoginAttempt(string $email,bool $successful): void
{
    if(!tableExists('vqd_login_attempts')) return;

    try{
        $emailHash=hash('sha256',strtolower(trim($email)));
        $ipHash=hash('sha256',vqdClientIp());

        if($successful){
            db()->prepare(
              'DELETE FROM vqd_login_attempts
               WHERE successful=0
                 AND (email_hash=:email OR ip_hash=:ip)'
            )->execute([':email'=>$emailHash,':ip'=>$ipHash]);
        }

        db()->prepare(
          'INSERT INTO vqd_login_attempts
           (email_hash,ip_hash,successful,attempted_at)
           VALUES(:email,:ip,:success,NOW())'
        )->execute([
          ':email'=>$emailHash,
          ':ip'=>$ipHash,
          ':success'=>$successful?1:0
        ]);

        if(random_int(1,25)===1){
            db()->exec(
              'DELETE FROM vqd_login_attempts
               WHERE attempted_at<DATE_SUB(NOW(),INTERVAL 90 DAY)'
            );
        }
    }catch(Throwable $e){
        error_log('[VQDSS Login Attempt] '.$e->getMessage());
    }
}
