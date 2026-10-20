<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/lacms_security.php';
requireLacmsPermission('lacms.system_health.view');

$pdo=db();
$pageTitle='LACMS System Health';
$activeMenu='system_health';
$extraCss=[
    appUrl('assets/css/lacms-operational.css'),
    appUrl('assets/css/lacms-final-health.css')
];

$checks=[];
$add=function(string $group,string $name,string $status,string $details) use (&$checks): void {
    $checks[]=[
        'group'=>$group,
        'name'=>$name,
        'status'=>$status,
        'details'=>$details
    ];
};

$requiredTables=[
    'systems','users','roles','user_roles','user_system_access',
    'permissions','role_permissions','activity_logs','audit_logs','notifications',
    'committees','committee_members','offices','departments',
    'legislative_items','legislative_item_types',

    'lacms_schema_migrations',
    'lacms_agendas','lacms_agenda_items','lacms_agenda_documents',
    'lacms_agenda_history','lacms_agenda_item_history',
    'lacms_calendar_events','lacms_calendar_event_participants',
    'lacms_calendar_event_documents','lacms_calendar_history','lacms_calendar_conflicts',
    'lacms_meetings','lacms_meeting_participants','lacms_meeting_documents',
    'lacms_meeting_history',
    'lacms_deadlines','lacms_deadline_assignments','lacms_deadline_reminders',
    'lacms_deadline_documents','lacms_deadline_history','lacms_deadline_escalations',
    'lacms_reminder_templates',
    'lacms_notifications','lacms_notification_recipients',
    'lacms_notification_history','lacms_notification_delivery_attempts',
    'lacms_ai_request_logs',
    'lacms_sync_records','lacms_sync_history','lacms_sync_actions',
    'lacms_sync_documents',
    'lacms_login_attempts'
];

foreach($requiredTables as $table){
    $ok=lacmsTableExists($table);
    $add(
        'Database Objects',
        $table,
        $ok?'PASS':'FAIL',
        $ok?'Available.':'Missing required table.'
    );
}

$requiredColumns=[
    ['lacms_agendas','finalized_at'],
    ['lacms_calendar_events','conflict_status'],
    ['lacms_calendar_events','confirmed_at'],
    ['lacms_meetings','calendar_event_id'],
    ['lacms_meetings','completed_at'],
    ['lacms_deadlines','reminder_policy'],
    ['lacms_deadlines','completed_at'],
    ['lacms_notifications','generated_by_ai'],
    ['lacms_sync_records','resolved_at'],
    ['lacms_sync_actions','completed_at'],
];

foreach($requiredColumns as [$table,$column]){
    try{
        $q=$pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema=DATABASE()
               AND table_name=:table
               AND column_name=:column'
        );
        $q->execute([':table'=>$table,':column'=>$column]);
        $ok=(int)$q->fetchColumn()>0;
        $add(
            'Required Columns',
            $table.'.'.$column,
            $ok?'PASS':'FAIL',
            $ok?'Available.':'Missing required column.'
        );
    }catch(Throwable $e){
        $add('Required Columns',$table.'.'.$column,'FAIL',$e->getMessage());
    }
}

$expectedMigrations=[
    '001_lacms_foundation',
    '002_agenda_calendar_meetings',
    '003_deadlines_notifications_reports',
    '004_sync_admin_rbac_security',
];

$applied=[];
if(lacmsTableExists('lacms_schema_migrations')){
    try{
        $applied=$pdo->query(
            'SELECT migration_key,description,applied_at
             FROM lacms_schema_migrations
             ORDER BY applied_at,migration_key'
        )->fetchAll();
    }catch(Throwable $e){}
}
$appliedKeys=array_column($applied,'migration_key');

foreach($expectedMigrations as $key){
    $ok=in_array($key,$appliedKeys,true);
    $add(
        'Migrations',
        $key,
        $ok?'PASS':'FAIL',
        $ok?'Recorded as applied.':'Migration is not recorded.'
    );
}

$system=null;
try{
    $q=$pdo->query(
        "SELECT id,code,name,base_url,status
         FROM systems
         WHERE code='agenda'
         LIMIT 1"
    );
    $system=$q->fetch()?:null;
}catch(Throwable $e){}

$systemOk=$system
    && strcasecmp((string)$system['status'],'Active')===0
    && rtrim((string)$system['base_url'],'/')==='http://localhost/lacms';

$add(
    'Configuration',
    'Shared LACMS system registration',
    $systemOk?'PASS':'FAIL',
    $system
        ? 'ID '.(int)$system['id'].' · '.$system['status'].' · '.($system['base_url']?:'no base URL')
        : 'systems.code=agenda is missing.'
);

$permissionCount=0;
$roleRows=[];
try{
    $permissionCount=(int)$pdo->query(
        "SELECT COUNT(*)
         FROM permissions p
         JOIN systems s ON s.id=p.system_id
         WHERE s.code='agenda'
           AND p.code LIKE 'lacms.%'"
    )->fetchColumn();

    $roleRows=$pdo->query(
        "SELECT r.name,COUNT(p.id) permission_count
         FROM roles r
         LEFT JOIN role_permissions rp ON rp.role_id=r.id
         LEFT JOIN permissions p
           ON p.id=rp.permission_id
          AND p.system_id=(SELECT id FROM systems WHERE code='agenda' LIMIT 1)
          AND p.code LIKE 'lacms.%'
         GROUP BY r.id,r.name
         ORDER BY r.id"
    )->fetchAll();
}catch(Throwable $e){}

$add(
    'RBAC',
    'Fine-grained LACMS permissions',
    $permissionCount>=17?'PASS':'FAIL',
    $permissionCount.' LACMS permission code(s) found; expected at least 17.'
);

$expectedRolePermissions=[
    'Administrator'=>17,
    'Legislative Staff'=>15,
    'Committee Member'=>8,
];
foreach($expectedRolePermissions as $role=>$expected){
    $actual=0;
    foreach($roleRows as $row){
        if($row['name']===$role){
            $actual=(int)$row['permission_count'];
            break;
        }
    }
    $add(
        'RBAC',
        $role.' permission mapping',
        $actual>=$expected?'PASS':'FAIL',
        "{$actual} effective LACMS role-permission mapping(s); expected at least {$expected}."
    );
}

$nonDeletedUsers=0;$explicitAccess=0;$activeAdmins=0;
try{
    $nonDeletedUsers=(int)$pdo->query(
        'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'
    )->fetchColumn();

    $explicitAccess=(int)$pdo->query(
        "SELECT COUNT(DISTINCT u.id)
         FROM users u
         JOIN user_system_access usa
           ON usa.user_id=u.id
          AND usa.system_id=(SELECT id FROM systems WHERE code='agenda' LIMIT 1)
         WHERE u.deleted_at IS NULL"
    )->fetchColumn();

    $activeAdmins=(int)$pdo->query(
        "SELECT COUNT(*)
         FROM users u
         JOIN roles r ON r.id=u.role_id
         JOIN user_system_access usa
           ON usa.user_id=u.id
          AND usa.system_id=(SELECT id FROM systems WHERE code='agenda' LIMIT 1)
         WHERE r.name='Administrator'
           AND u.status='Active'
           AND u.deleted_at IS NULL
           AND usa.status='Active'"
    )->fetchColumn();
}catch(Throwable $e){}

$add(
    'RBAC',
    'Explicit LACMS access rows',
    $explicitAccess===$nonDeletedUsers?'PASS':'WARN',
    "{$explicitAccess} of {$nonDeletedUsers} non-deleted shared users have an explicit LACMS access row."
);

$add(
    'RBAC',
    'LACMS-accessible Administrator',
    $activeAdmins>=1?'PASS':'FAIL',
    $activeAdmins.' active Administrator account(s) currently have LACMS access.'
);

$uploadWritable=is_dir(UPLOAD_DIR)&&is_writable(UPLOAD_DIR);
$add(
    'Filesystem',
    'Uploads directory',
    $uploadWritable?'PASS':'FAIL',
    UPLOAD_DIR.($uploadWritable?' is writable.':' is missing or not writable.')
);

$logDir=dirname((string)ini_get('error_log'));
$logWritable=is_dir($logDir)&&is_writable($logDir);
$add(
    'Filesystem',
    'PHP log directory',
    $logWritable?'PASS':'WARN',
    $logDir.($logWritable?' is writable.':' is missing or not writable by PHP.')
);

$add(
    'Configuration',
    'APP_DEBUG',
    APP_DEBUG?'WARN':'PASS',
    APP_DEBUG
        ? 'APP_DEBUG is enabled. Keep this only for local XAMPP development.'
        : 'APP_DEBUG is disabled.'
);

$sessionOk=SESSION_NAME==='lph_session' && SESSION_IDLE_TIMEOUT===(8*60*60);
$add(
    'Configuration',
    'Shared session',
    $sessionOk?'PASS':'WARN',
    'Session name: '.SESSION_NAME.
    ' · idle timeout: '.round(SESSION_IDLE_TIMEOUT/3600,1).
    ' hours · cookie path: /.'
);

$curlAvailable=function_exists('curl_init');
$ollamaEnabled=defined('LACMS_OLLAMA_ENABLED') ? LACMS_OLLAMA_ENABLED : false;
$add(
    'Automation',
    'AI reminder runtime',
    (!$ollamaEnabled||$curlAvailable)?'PASS':'WARN',
    $ollamaEnabled
        ? ($curlAvailable
            ? 'Ollama assistance is enabled and PHP cURL is available. Template fallback remains active.'
            : 'Ollama assistance is enabled but PHP cURL is unavailable. Template fallback will still work.')
        : 'Ollama assistance is disabled; deterministic reminder templates remain available.'
);

$add(
    'Runtime',
    'PHP / Database runtime',
    'PASS',
    'PHP '.PHP_VERSION.' · Database server '.$pdo->getAttribute(PDO::ATTR_SERVER_VERSION).'.'
);

$integrityQueries=[
    'Finalized agendas with no active agenda item'=>
        "SELECT COUNT(*)
         FROM lacms_agendas a
         WHERE a.status='Finalized'
           AND NOT EXISTS(
             SELECT 1
             FROM lacms_agenda_items i
             WHERE i.agenda_id=a.id
               AND i.item_status NOT IN ('Removed','Deferred')
           )",

    'Duplicate linked legislative item within one agenda'=>
        "SELECT COUNT(*) FROM (
           SELECT agenda_id,legislative_item_id
           FROM lacms_agenda_items
           WHERE legislative_item_id IS NOT NULL
           GROUP BY agenda_id,legislative_item_id
           HAVING COUNT(*)>1
         ) x",

    'Calendar events with invalid end time'=>
        "SELECT COUNT(*)
         FROM lacms_calendar_events
         WHERE end_datetime IS NOT NULL
           AND end_datetime<=start_datetime",

    'Confirmed calendar events with open Critical conflict'=>
        "SELECT COUNT(*)
         FROM lacms_calendar_events e
         JOIN lacms_calendar_conflicts c ON c.calendar_event_id=e.id
         WHERE e.status='Confirmed'
           AND c.status='Open'
           AND c.severity='Critical'",

    'Meetings without linked calendar event'=>
        "SELECT COUNT(*)
         FROM lacms_meetings
         WHERE calendar_event_id IS NULL",

    'Meeting and linked calendar schedule mismatch'=>
        "SELECT COUNT(*)
         FROM lacms_meetings m
         JOIN lacms_calendar_events e ON e.id=m.calendar_event_id
         WHERE m.start_datetime<>e.start_datetime
            OR NOT (m.end_datetime<=>e.end_datetime)
            OR COALESCE(m.venue,'')<>COALESCE(e.venue,'')",

    'Confirmed meeting with invalid linked calendar status'=>
        "SELECT COUNT(*)
         FROM lacms_meetings m
         JOIN lacms_calendar_events e ON e.id=m.calendar_event_id
         WHERE m.status='Confirmed'
           AND e.status NOT IN ('Confirmed','In Progress','Completed')",

    'Past-due deadline not overdue or closed'=>
        "SELECT COUNT(*)
         FROM lacms_deadlines
         WHERE due_datetime<NOW()
           AND status NOT IN ('Overdue','Completed','Cancelled')",

    'Completed deadline missing completion metadata'=>
        "SELECT COUNT(*)
         FROM lacms_deadlines
         WHERE status='Completed'
           AND (completed_at IS NULL OR completed_by IS NULL)",

    'Completed deadline still has Scheduled reminder'=>
        "SELECT COUNT(*)
         FROM lacms_deadlines d
         JOIN lacms_deadline_reminders r ON r.deadline_id=d.id
         WHERE d.status='Completed'
           AND r.status='Scheduled'",

    'Reminder scheduled at or after deadline'=>
        "SELECT COUNT(*)
         FROM lacms_deadline_reminders r
         JOIN lacms_deadlines d ON d.id=r.deadline_id
         WHERE r.remind_at>=d.due_datetime",

    'Sent notification with Pending recipient'=>
        "SELECT COUNT(*)
         FROM lacms_notifications n
         JOIN lacms_notification_recipients r ON r.notification_id=n.id
         WHERE n.status='Sent'
           AND r.delivery_status='Pending'",

    'Orphan Deadline notification source'=>
        "SELECT COUNT(*)
         FROM lacms_notifications n
         LEFT JOIN lacms_deadlines d
           ON n.source_type='Deadline'
          AND d.id=n.source_id
         WHERE n.source_type='Deadline'
           AND d.id IS NULL",

    'Orphan Meeting notification source'=>
        "SELECT COUNT(*)
         FROM lacms_notifications n
         LEFT JOIN lacms_meetings m
           ON n.source_type='Meeting'
          AND m.id=n.source_id
         WHERE n.source_type='Meeting'
           AND m.id IS NULL",

    'Closed synchronization with unfinished action'=>
        "SELECT COUNT(*)
         FROM lacms_sync_records s
         WHERE s.status='Closed'
           AND EXISTS(
             SELECT 1 FROM lacms_sync_actions a
             WHERE a.sync_record_id=s.id
               AND a.status IN ('Pending','In Progress')
           )",

    'Resolved or Closed synchronization missing resolution metadata'=>
        "SELECT COUNT(*)
         FROM lacms_sync_records
         WHERE status IN ('Resolved','Closed')
           AND (resolved_at IS NULL OR resolved_by IS NULL)",

    'Completed synchronization action missing completion metadata'=>
        "SELECT COUNT(*)
         FROM lacms_sync_actions
         WHERE status='Completed'
           AND (completed_at IS NULL OR completed_by IS NULL)",

    'Non-completed synchronization action retaining completed metadata'=>
        "SELECT COUNT(*)
         FROM lacms_sync_actions
         WHERE status<>'Completed'
           AND (completed_at IS NOT NULL OR completed_by IS NOT NULL)",
];

foreach($integrityQueries as $label=>$sql){
    try{
        $count=(int)$pdo->query($sql)->fetchColumn();
        $add(
            'Data Integrity',
            $label,
            $count===0?'PASS':'FAIL',
            $count===0
                ? 'No integrity problems detected.'
                : $count.' problem row(s) detected.'
        );
    }catch(Throwable $e){
        $add(
            'Data Integrity',
            $label,
            'FAIL',
            'Health query failed: '.$e->getMessage()
        );
    }
}

$statusWeights=['PASS'=>1,'WARN'=>0.5,'FAIL'=>0];
$earned=0.0;
foreach($checks as $check){
    $earned+=$statusWeights[$check['status']]??0;
}
$score=(int)round(($earned/max(1,count($checks)))*100);
$passCount=count(array_filter($checks,fn($c)=>$c['status']==='PASS'));
$warnCount=count(array_filter($checks,fn($c)=>$c['status']==='WARN'));
$failCount=count(array_filter($checks,fn($c)=>$c['status']==='FAIL'));

include __DIR__.'/../layouts/header.php';
?>
<div class="lacms-app-wrapper">
<?php include __DIR__.'/../layouts/sidebar.php'; ?>
<main class="lacms-main-content">

<section class="lh-head">
    <div>
        <div class="lo-eyebrow"><i class="bi bi-heart-pulse"></i> Step 11 · Final Deployment Health</div>
        <h1>LACMS System Health</h1>
        <p>Read-only checks for database migrations, fine-grained RBAC, explicit subsystem access, runtime configuration, automation support, filesystem readiness and end-to-end agenda/calendar/meeting/deadline/notification/synchronization integrity.</p>
    </div>
    <div class="lh-score">
        <strong class="<?= $failCount?'text-danger':($warnCount?'text-warning':'text-success') ?>"><?= $score ?>%</strong>
        <small><?= $failCount ?> failure(s) · <?= $warnCount ?> warning(s)</small>
    </div>
</section>

<div class="row g-3 mb-3">
<?php foreach([
    ['Checks',count($checks),'bi-list-check'],
    ['Passed',$passCount,'bi-check-circle'],
    ['Warnings',$warnCount,'bi-exclamation-triangle'],
    ['Failures',$failCount,'bi-x-circle'],
] as [$label,$value,$icon]): ?>
<div class="col-6 col-xl-3">
    <div class="lh-stat">
        <i class="bi <?= e($icon) ?>"></i>
        <div><strong><?= (int)$value ?></strong><small><?= e($label) ?></small></div>
    </div>
</div>
<?php endforeach; ?>
</div>

<div class="lh-note mb-3">
    <strong>Read-only health page.</strong>
    It does not modify application records. Warnings may be acceptable in local development, especially <code>APP_DEBUG=true</code> and external email/AI environment differences.
</div>

<?php foreach(array_values(array_unique(array_column($checks,'group'))) as $group): ?>
<section class="card lh-card mb-3">
    <div class="card-header"><?= e($group) ?></div>
    <div class="table-responsive">
        <table class="table lh-table mb-0">
            <thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach(array_filter($checks,fn($c)=>$c['group']===$group) as $check): ?>
                <tr>
                    <td><strong><?= e($check['name']) ?></strong></td>
                    <td>
                        <span class="<?= $check['status']==='PASS'?'lh-pass':($check['status']==='WARN'?'lh-warn':'lh-fail') ?>">
                            <i class="bi <?= $check['status']==='PASS'?'bi-check-circle-fill':($check['status']==='WARN'?'bi-exclamation-triangle-fill':'bi-x-circle-fill') ?>"></i>
                            <?= e($check['status']) ?>
                        </span>
                    </td>
                    <td><?= e($check['details']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endforeach; ?>

<section class="card lh-card">
    <div class="card-header">Applied LACMS Migrations</div>
    <div class="table-responsive">
        <table class="table lh-table mb-0">
            <thead><tr><th>Migration</th><th>Description</th><th>Applied</th></tr></thead>
            <tbody>
            <?php if(!$applied): ?><tr><td colspan="3" class="text-center text-muted py-4">No LACMS migration records were found.</td></tr><?php endif; ?>
            <?php foreach($applied as $migration): ?>
            <tr>
                <td><code><?= e($migration['migration_key']) ?></code></td>
                <td><?= e($migration['description']) ?></td>
                <td><?= formatDateTime($migration['applied_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

</main>
</div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
