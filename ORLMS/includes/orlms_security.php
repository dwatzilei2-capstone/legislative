<?php
declare(strict_types=1);

require_once __DIR__ . '/orlms_helpers.php';

function orlmsFinalRbacInstalled(): bool
{
    static $cached=null;
    if($cached!==null)return $cached;

    try{
        if(!orlmsTableExists('orlms_schema_migrations'))return $cached=false;
        $q=db()->prepare(
            'SELECT COUNT(*) FROM orlms_schema_migrations
             WHERE migration_key=:key'
        );
        $q->execute([':key'=>'005_final_rbac_security']);
        return $cached=(int)$q->fetchColumn()>0;
    }catch(Throwable $e){
        error_log('[ORLMS RBAC migration check] '.$e->getMessage());
        return $cached=false;
    }
}

function orlmsLegacyPermissionRoles(string $permission): array
{
    $admin=[ROLE_ADMIN];
    $staff=[ROLE_ADMIN,ROLE_STAFF];
    $committee=[ROLE_ADMIN,ROLE_STAFF,ROLE_COMMITTEE];

    return match($permission){
        'orlms.dashboard.view' => $committee,

        'orlms.drafting.view',
        'orlms.drafting.manage' => $staff,

        'orlms.reviews.view',
        'orlms.reviews.manage',
        'orlms.endorsements.manage' => $committee,

        'orlms.approvals.view',
        'orlms.publications.view',
        'orlms.implementation.view',
        'orlms.amendments.view',
        'orlms.workflow.view',
        'orlms.reports.view',
        'orlms.ai.view' => $committee,

        'orlms.approvals.manage',
        'orlms.enactments.manage',
        'orlms.publications.manage',
        'orlms.implementation.manage',
        'orlms.amendments.manage',
        'orlms.ai.manage' => $staff,

        'orlms.activity_logs.view',
        'orlms.users.manage',
        'orlms.system_health.view' => $admin,

        default => $admin,
    };
}

function orlmsUserHasPermission(int $userId,string $permission): bool
{
    if($userId<=0)return false;

    if(!orlmsFinalRbacInstalled()){
        $role=currentRole();
        return $role!==null
            && in_array($role,orlmsLegacyPermissionRoles($permission),true);
    }

    $systemId=orlmsSystemId();
    if(!$systemId)return false;

    try{
        $q=db()->prepare(
            'SELECT COUNT(*)
             FROM users u
             JOIN user_system_access usa
               ON usa.user_id=u.id
              AND usa.system_id=:system_access
              AND usa.status="Active"
             JOIN role_permissions rp ON rp.role_id=u.role_id
             JOIN permissions p
               ON p.id=rp.permission_id
              AND p.system_id=:system_permission
              AND p.code=:permission
             WHERE u.id=:user
               AND u.status="Active"
               AND u.deleted_at IS NULL'
        );
        $q->execute([
            ':system_access'=>$systemId,
            ':system_permission'=>$systemId,
            ':permission'=>$permission,
            ':user'=>$userId,
        ]);
        return (int)$q->fetchColumn()>0;
    }catch(Throwable $e){
        error_log('[ORLMS permission check] '.$e->getMessage());
        return false;
    }
}

function hasPermission(string $permission): bool
{
    return isLoggedIn()
        && orlmsUserHasPermission((int)currentUserId(),$permission);
}

function requirePermission(string $permission): void
{
    requireLogin();

    if(!hasPermission($permission)){
        logActivity(
            currentUserId(),
            'ORLMS Permission Denied',
            'Denied permission '.$permission.' for '.$_SERVER['REQUEST_METHOD'].' '.($_SERVER['REQUEST_URI']??'unknown request').'.'
        );

        http_response_code(403);
        if(isAjaxRequest()){
            jsonResponse(false,'You do not have permission to perform this ORLMS action.');
        }

        $requiredPermission=$permission;
        include __DIR__ . '/../pages/403.php';
        exit;
    }
}

function orlmsPermissionCodesForCurrentUser(): array
{
    if(!isLoggedIn())return [];

    if(!orlmsFinalRbacInstalled()){
        $all=[
            'orlms.dashboard.view','orlms.drafting.view','orlms.drafting.manage',
            'orlms.reviews.view','orlms.reviews.manage','orlms.endorsements.manage',
            'orlms.approvals.view','orlms.approvals.manage','orlms.enactments.manage',
            'orlms.publications.view','orlms.publications.manage',
            'orlms.implementation.view','orlms.implementation.manage',
            'orlms.amendments.view','orlms.amendments.manage',
            'orlms.workflow.view','orlms.reports.view','orlms.ai.view','orlms.ai.manage',
            'orlms.activity_logs.view','orlms.users.manage','orlms.system_health.view'
        ];
        return array_values(array_filter(
            $all,
            fn($p)=>in_array(currentRole(),orlmsLegacyPermissionRoles($p),true)
        ));
    }

    try{
        $q=db()->prepare(
            'SELECT DISTINCT p.code
             FROM users u
             JOIN role_permissions rp ON rp.role_id=u.role_id
             JOIN permissions p ON p.id=rp.permission_id
             WHERE u.id=:user
               AND p.system_id=:system
               AND p.code LIKE "orlms.%"
             ORDER BY p.code'
        );
        $q->execute([
            ':user'=>currentUserId(),
            ':system'=>orlmsSystemId()
        ]);
        return $q->fetchAll(PDO::FETCH_COLUMN);
    }catch(Throwable $e){
        return [];
    }
}

function orlmsLoginHash(string $value): string
{
    return hash('sha256',strtolower(trim($value)));
}

function orlmsLoginIp(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR']??'unknown'),0,45);
}

function orlmsLoginRateLimited(string $email,string $ip,int $limit=5,int $minutes=15): bool
{
    if(!orlmsTableExists('orlms_login_attempts'))return false;

    try{
        $cutoff=date('Y-m-d H:i:s',time()-($minutes*60));
        $q=db()->prepare(
            'SELECT
               SUM(email_hash=:email_hash) email_failures,
               SUM(ip_hash=:ip_hash) ip_failures
             FROM orlms_login_attempts
             WHERE was_successful=0
               AND attempted_at>=:cutoff'
        );
        $q->execute([
            ':email_hash'=>orlmsLoginHash($email),
            ':ip_hash'=>orlmsLoginHash($ip),
            ':cutoff'=>$cutoff
        ]);
        $r=$q->fetch()?:[];
        return (int)($r['email_failures']??0)>=$limit
            || (int)($r['ip_failures']??0)>=$limit;
    }catch(Throwable $e){
        error_log('[ORLMS rate limit check] '.$e->getMessage());
        return false;
    }
}

function orlmsRecordLoginAttempt(string $email,string $ip,bool $success): void
{
    if(!orlmsTableExists('orlms_login_attempts'))return;

    try{
        $pdo=db();
        $pdo->prepare(
            'INSERT INTO orlms_login_attempts
             (email_hash,ip_hash,was_successful,attempted_at)
             VALUES(:email,:ip,:success,NOW())'
        )->execute([
            ':email'=>orlmsLoginHash($email),
            ':ip'=>orlmsLoginHash($ip),
            ':success'=>$success?1:0
        ]);

        if($success){
            $pdo->prepare(
                'DELETE FROM orlms_login_attempts
                 WHERE was_successful=0
                   AND (email_hash=:email OR ip_hash=:ip)'
            )->execute([
                ':email'=>orlmsLoginHash($email),
                ':ip'=>orlmsLoginHash($ip)
            ]);
        }

        // Opportunistic cleanup.
        $pdo->exec(
            "DELETE FROM orlms_login_attempts
             WHERE attempted_at<DATE_SUB(NOW(),INTERVAL 30 DAY)"
        );
    }catch(Throwable $e){
        error_log('[ORLMS login attempt write] '.$e->getMessage());
    }
}
