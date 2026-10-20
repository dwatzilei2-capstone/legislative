<?php
declare(strict_types=1);

require_once __DIR__.'/lacms_notification_helpers.php';
require_once __DIR__.'/lacms_security.php';

function lacmsSyncHistory(PDO $pdo,int $syncId,string $action,?string $old,?string $new,string $details=''): void
{
    $pdo->prepare(
        'INSERT INTO lacms_sync_history
         (sync_record_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:sync,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':sync'=>$syncId,':action'=>$action,':old'=>$old,':new'=>$new,
        ':details'=>$details?:null,':user'=>currentUserId()?:null,
    ]);
}

function lacmsSyncStatuses(): array
{
    return ['Draft','Coordinating','Awaiting Executive','Awaiting Legislative','Aligned','Resolved','Closed','Cancelled'];
}

function lacmsSyncActionStatuses(): array
{
    return ['Pending','In Progress','Completed','Cancelled'];
}

function lacmsSyncContext(PDO $pdo,int $id): ?array
{
    $q=$pdo->prepare(
        'SELECT s.*,li.reference_number legislative_reference,li.title legislative_title,
                a.agenda_reference,a.title agenda_title,e.event_reference,e.title event_title,
                o.name source_office_name,c.name target_committee_name,
                u.full_name created_name,ru.full_name resolved_name
         FROM lacms_sync_records s
         LEFT JOIN legislative_items li ON li.id=s.legislative_item_id
         LEFT JOIN lacms_agendas a ON a.id=s.agenda_id
         LEFT JOIN lacms_calendar_events e ON e.id=s.calendar_event_id
         LEFT JOIN offices o ON o.id=s.source_office_id
         LEFT JOIN committees c ON c.id=s.target_committee_id
         LEFT JOIN users u ON u.id=s.created_by
         LEFT JOIN users ru ON ru.id=s.resolved_by
         WHERE s.id=:id'
    );
    $q->execute([':id'=>$id]);
    $row=$q->fetch();
    return $row?:null;
}
