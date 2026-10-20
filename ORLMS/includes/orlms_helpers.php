<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function orlmsTableExists(string $table): bool
{
    static $cache=[];
    if(array_key_exists($table,$cache)) return $cache[$table];

    try{
        $stmt=db()->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name=:table'
        );
        $stmt->execute([':table'=>$table]);
        return $cache[$table]=(int)$stmt->fetchColumn()>0;
    }catch(Throwable $e){
        error_log('[ORLMS tableExists] '.$e->getMessage());
        return $cache[$table]=false;
    }
}

function orlmsSystemId(): ?int
{
    static $cached=false;
    if($cached!==false) return $cached;

    try{
        $stmt=db()->prepare("SELECT id FROM systems WHERE code='ordinance' LIMIT 1");
        $stmt->execute();
        $id=$stmt->fetchColumn();
        return $cached=$id!==false?(int)$id:null;
    }catch(Throwable $e){
        error_log('[ORLMS system id] '.$e->getMessage());
        return $cached=null;
    }
}

function orlmsExplicitAccessRequired(): bool
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
        error_log('[ORLMS explicit access mode] '.$e->getMessage());
        return $cached=false;
    }
}

function orlmsHasSystemAccess(int $userId): bool
{
    $systemId=orlmsSystemId();
    if(!$systemId) return !orlmsExplicitAccessRequired();

    try{
        $stmt=db()->prepare(
            'SELECT status
             FROM user_system_access
             WHERE user_id=:user
               AND system_id=:system
             LIMIT 1'
        );
        $stmt->execute([':user'=>$userId,':system'=>$systemId]);
        $status=$stmt->fetchColumn();

        if($status===false){
            return !orlmsExplicitAccessRequired();
        }

        return strcasecmp((string)$status,'Active')===0;
    }catch(Throwable $e){
        error_log('[ORLMS system access] '.$e->getMessage());
        return !orlmsExplicitAccessRequired();
    }
}

function orlmsItemTypeId(PDO $pdo,string $code): ?int
{
    $stmt=$pdo->prepare('SELECT id FROM legislative_item_types WHERE code=:code LIMIT 1');
    $stmt->execute([':code'=>$code]);
    $id=$stmt->fetchColumn();
    return $id!==false?(int)$id:null;
}

function orlmsGenerateReference(
    PDO $pdo,
    string $table,
    string $column,
    string $prefix,
    ?string $date=null
): string {
    $year=date('Y',strtotime($date?:'now'));
    $base=strtoupper($prefix).'-'.$year.'-';

    $stmt=$pdo->prepare(
        "SELECT {$column}
         FROM {$table}
         WHERE {$column} LIKE :pattern
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([':pattern'=>$base.'%']);
    $last=(string)($stmt->fetchColumn()?:'');

    $next=1;
    if($last!==''&&preg_match('/(\d+)$/',$last,$m)){
        $next=(int)$m[1]+1;
    }

    for($i=0;$i<1000;$i++){
        $candidate=$base.str_pad((string)($next+$i),4,'0',STR_PAD_LEFT);
        $check=$pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column}=:ref");
        $check->execute([':ref'=>$candidate]);
        if((int)$check->fetchColumn()===0) return $candidate;
    }

    throw new RuntimeException('Unable to generate a unique ORLMS reference number.');
}

function orlmsAddStatusHistory(
    PDO $pdo,
    int $itemId,
    ?string $previousStatus,
    string $newStatus,
    string $remarks=''
): void {
    $pdo->prepare(
        'INSERT INTO legislative_item_status_history
         (legislative_item_id,previous_status,new_status,remarks,changed_by,created_at)
         VALUES(:item,:old,:new,:remarks,:user,NOW())'
    )->execute([
        ':item'=>$itemId,
        ':old'=>$previousStatus,
        ':new'=>$newStatus,
        ':remarks'=>$remarks?:null,
        ':user'=>function_exists('currentUserId')?currentUserId():null,
    ]);
}

function orlmsAddWorkflowHistory(
    PDO $pdo,
    int $itemId,
    string $stage,
    string $action,
    ?string $previousStatus,
    ?string $newStatus,
    string $remarks='',
    ?int $toCommitteeId=null,
    ?int $toOfficeId=null,
    ?int $assignedUserId=null
): void {
    if(!orlmsTableExists('orlms_workflow_history')) return;

    $pdo->prepare(
        'INSERT INTO orlms_workflow_history
         (legislative_item_id,stage_code,action,previous_status,new_status,
          to_committee_id,to_office_id,assigned_user_id,remarks,acted_by,created_at)
         VALUES(:item,:stage,:action,:old,:new,:committee,:office,:assigned,
          :remarks,:actor,NOW())'
    )->execute([
        ':item'=>$itemId,':stage'=>$stage,':action'=>$action,
        ':old'=>$previousStatus,':new'=>$newStatus,
        ':committee'=>$toCommitteeId,':office'=>$toOfficeId,
        ':assigned'=>$assignedUserId,':remarks'=>$remarks?:null,
        ':actor'=>function_exists('currentUserId')?currentUserId():null,
    ]);
}

function orlmsCreateDraftSnapshot(
    PDO $pdo,
    int $itemId,
    string $changeSummary=''
): int {
    $q=$pdo->prepare(
        'SELECT li.title,li.summary,d.body_text,d.explanatory_note
         FROM legislative_items li
         LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
         WHERE li.id=:id'
    );
    $q->execute([':id'=>$itemId]);
    $item=$q->fetch();

    if(!$item) throw new RuntimeException('Legislative item not found.');

    $n=$pdo->prepare(
        'SELECT COALESCE(MAX(version_number),0)+1
         FROM orlms_draft_versions
         WHERE legislative_item_id=:id'
    );
    $n->execute([':id'=>$itemId]);
    $version=(int)$n->fetchColumn();

    $pdo->prepare(
        'INSERT INTO orlms_draft_versions
         (legislative_item_id,version_number,title_snapshot,summary_snapshot,
          body_snapshot,explanatory_note_snapshot,change_summary,created_by,created_at)
         VALUES(:item,:version,:title,:summary,:body,:note,:changes,:user,NOW())'
    )->execute([
        ':item'=>$itemId,':version'=>$version,':title'=>$item['title'],
        ':summary'=>$item['summary'],':body'=>$item['body_text'],
        ':note'=>$item['explanatory_note'],':changes'=>$changeSummary?:null,
        ':user'=>function_exists('currentUserId')?currentUserId():null,
    ]);

    return (int)$pdo->lastInsertId();
}
