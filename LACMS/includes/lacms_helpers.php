<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function lacmsTableExists(string $table): bool
{
    static $cache=[];

    if(isset($cache[$table]))return $cache[$table];

    try{
        $q=db()->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name=:table'
        );
        $q->execute([':table'=>$table]);
        return $cache[$table]=(int)$q->fetchColumn()>0;
    }catch(Throwable $e){
        error_log('[LACMS table check] '.$e->getMessage());
        return $cache[$table]=false;
    }
}

function lacmsSystemId(): ?int
{
    static $cached=false;
    if($cached!==false)return $cached;

    try{
        $q=db()->prepare("SELECT id FROM systems WHERE code='agenda' LIMIT 1");
        $q->execute();
        $id=$q->fetchColumn();
        return $cached=$id!==false?(int)$id:null;
    }catch(Throwable $e){
        error_log('[LACMS system id] '.$e->getMessage());
        return $cached=null;
    }
}

function lacmsExplicitAccessRequired(): bool
{
    static $cached=null;
    if($cached!==null)return $cached;
    try{
        if(!lacmsTableExists('lacms_schema_migrations'))return $cached=false;
        $q=db()->prepare('SELECT COUNT(*) FROM lacms_schema_migrations WHERE migration_key=:key');
        $q->execute([':key'=>'004_sync_admin_rbac_security']);
        return $cached=(int)$q->fetchColumn()>0;
    }catch(Throwable $e){
        error_log('[LACMS explicit access mode] '.$e->getMessage());
        return $cached=false;
    }
}

function lacmsHasSystemAccess(int $userId): bool
{
    if($userId<=0)return false;

    $systemId=lacmsSystemId();
    if(!$systemId)return !lacmsExplicitAccessRequired();

    try{
        $q=db()->prepare(
            'SELECT status
             FROM user_system_access
             WHERE user_id=:user
               AND system_id=:system
             LIMIT 1'
        );
        $q->execute([':user'=>$userId,':system'=>$systemId]);
        $status=$q->fetchColumn();

        if($status===false)return !lacmsExplicitAccessRequired();

        return strcasecmp((string)$status,'Active')===0;
    }catch(Throwable $e){
        error_log('[LACMS access check] '.$e->getMessage());
        return !lacmsExplicitAccessRequired();
    }
}

function lacmsLogActivity(?int $userId,string $action,string $details=''): void
{
    try{
        if(!lacmsTableExists('activity_logs'))return;

        db()->prepare(
            'INSERT INTO activity_logs
             (user_id,system_id,action,details,ip_address,user_agent,created_at)
             VALUES(:user,:system,:action,:details,:ip,:agent,NOW())'
        )->execute([
            ':user'=>$userId?:null,
            ':system'=>lacmsSystemId(),
            ':action'=>$action,
            ':details'=>$details?:null,
            ':ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)?:null,
            ':agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)?:null,
        ]);
    }catch(Throwable $e){
        error_log('[LACMS activity log] '.$e->getMessage());
    }
}

function lacmsGenerateReference(
    PDO $pdo,
    string $table,
    string $column,
    string $prefix,
    ?string $dateValue=null
): string {
    $allowed=[
        'lacms_agendas'=>'agenda_reference',
        'lacms_calendar_events'=>'event_reference',
        'lacms_meetings'=>'meeting_reference',
        'lacms_deadlines'=>'deadline_reference',
        'lacms_sync_records'=>'sync_reference',
        'lacms_notifications'=>'notification_reference',
    ];

    if(!isset($allowed[$table]) || $allowed[$table]!==$column){
        throw new InvalidArgumentException('Unsupported LACMS reference target.');
    }

    $year=$dateValue && strtotime($dateValue)!==false
        ? date('Y',strtotime($dateValue))
        : date('Y');

    $base=$prefix.'-'.$year.'-';

    $q=$pdo->prepare(
        "SELECT `$column`
         FROM `$table`
         WHERE `$column` LIKE :pattern
         ORDER BY id DESC
         LIMIT 1"
    );
    $q->execute([':pattern'=>$base.'%']);
    $last=(string)($q->fetchColumn()?:'');

    $number=1;
    if($last!=='' && preg_match('/(\d+)$/',$last,$m)){
        $number=(int)$m[1]+1;
    }

    return $base.str_pad((string)$number,4,'0',STR_PAD_LEFT);
}

function lacmsAgendaStatuses(): array
{
    return ['Draft','Under Review','Finalized','Archived','Cancelled'];
}

function lacmsCalendarStatuses(): array
{
    return ['Tentative','Confirmed','In Progress','Completed','Postponed','Cancelled'];
}

function lacmsMeetingStatuses(): array
{
    return ['Planned','Confirmed','In Progress','Completed','Postponed','Cancelled'];
}

function lacmsDeadlineStatuses(): array
{
    return ['Pending','In Progress','Completed','Overdue','Cancelled'];
}

function lacmsFoundationTables(): array
{
    return [
        'lacms_schema_migrations',
        'lacms_agendas',
        'lacms_agenda_items',
        'lacms_agenda_documents',
        'lacms_agenda_history',
        'lacms_calendar_events',
        'lacms_calendar_event_participants',
        'lacms_calendar_event_documents',
        'lacms_calendar_history',
        'lacms_meetings',
        'lacms_meeting_participants',
        'lacms_meeting_documents',
        'lacms_meeting_history',
        'lacms_deadlines',
        'lacms_deadline_assignments',
        'lacms_deadline_reminders',
        'lacms_deadline_documents',
        'lacms_deadline_history',
        'lacms_sync_records',
        'lacms_sync_history',
        'lacms_notifications',
        'lacms_notification_recipients',
        'lacms_notification_history',
    ];
}

function lacmsFoundationReady(): bool
{
    foreach(lacmsFoundationTables() as $table){
        if(!lacmsTableExists($table))return false;
    }
    return true;
}
