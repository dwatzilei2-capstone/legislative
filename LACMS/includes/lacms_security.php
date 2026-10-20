<?php
declare(strict_types=1);

require_once __DIR__.'/lacms_helpers.php';

function lacmsFinalRbacInstalled(): bool
{
    static $cached=null;
    if($cached!==null)return $cached;

    try{
        if(!lacmsTableExists('lacms_schema_migrations'))return $cached=false;
        $q=db()->prepare('SELECT COUNT(*) FROM lacms_schema_migrations WHERE migration_key=:key');
        $q->execute([':key'=>'004_sync_admin_rbac_security']);
        return $cached=(int)$q->fetchColumn()>0;
    }catch(Throwable $e){
        error_log('[LACMS RBAC migration check] '.$e->getMessage());
        return $cached=false;
    }
}

function lacmsLegacyPermissionRoles(string $permission): array
{
    $admin=[ROLE_ADMIN];
    $staff=[ROLE_ADMIN,ROLE_STAFF];
    $committee=[ROLE_ADMIN,ROLE_STAFF,ROLE_COMMITTEE];

    return match($permission){
        'lacms.dashboard.view','lacms.agendas.view','lacms.calendar.view','lacms.meetings.view',
        'lacms.deadlines.view','lacms.notifications.view','lacms.sync.view','lacms.reports.view','lacms.ai.view' => $committee,

        'lacms.agendas.manage','lacms.calendar.manage','lacms.meetings.manage','lacms.deadlines.manage',
        'lacms.notifications.manage','lacms.sync.manage','lacms.activity_logs.view','lacms.ai.manage' => $staff,

        'lacms.users.manage','lacms.system_health.view' => $admin,
        default => $admin,
    };
}

function lacmsUserHasPermission(int $userId,string $permission): bool
{
    if($userId<=0)return false;

    if(!lacmsFinalRbacInstalled()){
        $role=currentRole();
        return $role!==null && in_array($role,lacmsLegacyPermissionRoles($permission),true);
    }

    $systemId=lacmsSystemId();
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
        error_log('[LACMS permission check] '.$e->getMessage());
        return false;
    }
}

function lacmsHasPermission(string $permission): bool
{
    return isAuthenticated() && lacmsUserHasPermission((int)currentUserId(),$permission);
}

function requireLacmsPermission(string $permission): void
{
    requireLogin();
    if(lacmsHasPermission($permission))return;

    lacmsLogActivity(
        currentUserId(),
        'LACMS Permission Denied',
        'Denied permission '.$permission.' for '.($_SERVER['REQUEST_METHOD']??'GET').' '.($_SERVER['REQUEST_URI']??'unknown request').'.'
    );

    http_response_code(403);
    if(isAjaxRequest())jsonResponse(false,'You do not have permission to perform this LACMS action.',[],403);

    $requiredPermission=$permission;
    include __DIR__.'/../pages/403.php';
    exit;
}

function lacmsPermissionCodesForCurrentUser(): array
{
    if(!isAuthenticated())return [];

    if(!lacmsFinalRbacInstalled()){
        $known=[
            'lacms.dashboard.view','lacms.agendas.view','lacms.agendas.manage',
            'lacms.calendar.view','lacms.calendar.manage','lacms.meetings.view','lacms.meetings.manage',
            'lacms.deadlines.view','lacms.deadlines.manage','lacms.notifications.view','lacms.notifications.manage',
            'lacms.sync.view','lacms.sync.manage','lacms.reports.view','lacms.activity_logs.view',
            'lacms.users.manage','lacms.system_health.view','lacms.ai.view','lacms.ai.manage'
        ];
        return array_values(array_filter($known,'lacmsHasPermission'));
    }

    try{
        $q=db()->prepare(
            'SELECT DISTINCT p.code
             FROM users u
             JOIN user_system_access usa
               ON usa.user_id=u.id AND usa.system_id=:system_access AND usa.status="Active"
             JOIN role_permissions rp ON rp.role_id=u.role_id
             JOIN permissions p ON p.id=rp.permission_id AND p.system_id=:system_permission
             WHERE u.id=:user AND u.status="Active" AND u.deleted_at IS NULL
             ORDER BY p.code'
        );
        $q->execute([
            ':system_access'=>lacmsSystemId(),
            ':system_permission'=>lacmsSystemId(),
            ':user'=>currentUserId(),
        ]);
        return $q->fetchAll(PDO::FETCH_COLUMN);
    }catch(Throwable $e){
        error_log('[LACMS permission list] '.$e->getMessage());
        return [];
    }
}

function lacmsAudit(string $action,string $entityType,?string $entityId=null,?array $oldValues=null,?array $newValues=null,?int $userId=null): void
{
    if(!lacmsTableExists('audit_logs'))return;
    try{
        db()->prepare(
            'INSERT INTO audit_logs
             (user_id,system_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent,created_at)
             VALUES(:user,:system,:action,:entity,:entity_id,:old_values,:new_values,:ip,:agent,NOW())'
        )->execute([
            ':user'=>$userId??(currentUserId()?:null),
            ':system'=>lacmsSystemId(),
            ':action'=>$action,
            ':entity'=>$entityType,
            ':entity_id'=>$entityId,
            ':old_values'=>$oldValues!==null?json_encode($oldValues,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,
            ':new_values'=>$newValues!==null?json_encode($newValues,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,
            ':ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)?:null,
            ':agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)?:null,
        ]);
    }catch(Throwable $e){
        error_log('[LACMS audit] '.$e->getMessage());
    }
}

function lacmsLoginHash(string $value): string
{
    return hash('sha256',strtolower(trim($value)));
}

function lacmsLoginRateLimited(string $email,string $ip,int $limit=5,int $minutes=15): bool
{
    if(!lacmsTableExists('lacms_login_attempts'))return false;
    try{
        $q=db()->prepare(
            'SELECT SUM(email_hash=:email_hash) email_failures,SUM(ip_hash=:ip_hash) ip_failures
             FROM lacms_login_attempts
             WHERE was_successful=0 AND attempted_at>=:cutoff'
        );
        $q->execute([
            ':email_hash'=>lacmsLoginHash($email),
            ':ip_hash'=>lacmsLoginHash($ip),
            ':cutoff'=>date('Y-m-d H:i:s',time()-($minutes*60)),
        ]);
        $r=$q->fetch()?:[];
        return (int)($r['email_failures']??0)>=$limit || (int)($r['ip_failures']??0)>=$limit;
    }catch(Throwable $e){
        error_log('[LACMS rate limit] '.$e->getMessage());
        return false;
    }
}

function lacmsRecordLoginAttempt(string $email,string $ip,bool $success): void
{
    if(!lacmsTableExists('lacms_login_attempts'))return;
    try{
        $pdo=db();
        $pdo->prepare(
            'INSERT INTO lacms_login_attempts (email_hash,ip_hash,was_successful,attempted_at)
             VALUES(:email,:ip,:success,NOW())'
        )->execute([
            ':email'=>lacmsLoginHash($email),
            ':ip'=>lacmsLoginHash($ip),
            ':success'=>$success?1:0,
        ]);

        if($success){
            $pdo->prepare(
                'DELETE FROM lacms_login_attempts
                 WHERE was_successful=0 AND (email_hash=:email OR ip_hash=:ip)'
            )->execute([
                ':email'=>lacmsLoginHash($email),
                ':ip'=>lacmsLoginHash($ip),
            ]);
        }

        $pdo->exec("DELETE FROM lacms_login_attempts WHERE attempted_at<DATE_SUB(NOW(),INTERVAL 30 DAY)");
    }catch(Throwable $e){
        error_log('[LACMS login attempt write] '.$e->getMessage());
    }
}
