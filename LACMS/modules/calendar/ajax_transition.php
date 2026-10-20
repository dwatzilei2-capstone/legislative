<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.calendar.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['calendar_event_id']??0);$action=clean($_POST['action']??'');
$notes=trim((string)($_POST['notes']??''));$override=!empty($_POST['override_conflict']);

try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT * FROM lacms_calendar_events WHERE id=:id FOR UPDATE');
    $q->execute([':id'=>$id]);$event=$q->fetch();
    if(!$event){$pdo->rollBack();jsonResponse(false,'Calendar event not found.');}

    $old=$event['status'];$new=$old;

    if($action==='confirm'){
        if(!in_array($old,['Tentative','Postponed'],true)){$pdo->rollBack();jsonResponse(false,'Only Tentative/Postponed events can be confirmed.');}
        $conflicts=lacmsRefreshEventConflicts($pdo,$id);
        $critical=array_filter($conflicts,fn($c)=>$c['severity']==='Critical');
        if($critical&&!$override){$pdo->rollBack();jsonResponse(false,'Critical schedule conflicts must be resolved or explicitly overridden by an Administrator.');}
        if($critical&&$override&&normalizeRole(currentRole())!==normalizeRole(ROLE_ADMIN)){$pdo->rollBack();jsonResponse(false,'Only an Administrator can override critical conflicts.');}
        if($critical&&$override&&$notes===''){$pdo->rollBack();jsonResponse(false,'Conflict override reason is required.');}
        $new='Confirmed';
        $pdo->prepare(
            'UPDATE lacms_calendar_events
             SET status="Confirmed",confirmed_by=:user,confirmed_at=NOW(),
                 conflict_status=:conflict_status,
                 conflict_notes=CASE WHEN :note_check<>"" THEN CONCAT(COALESCE(conflict_notes,""),"\nOverride: ",:note_value) ELSE conflict_notes END,
                 updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':user'=>currentUserId(),':conflict_status'=>$critical?'Overridden':'Clear',
            ':note_check'=>$notes,':note_value'=>$notes,':id'=>$id
        ]);
        if($critical&&$override&&lacmsTableExists('lacms_calendar_conflicts')){
            $pdo->prepare(
                'UPDATE lacms_calendar_conflicts
                 SET status="Resolved",resolved_by=:user,resolved_at=NOW(),resolution_notes=:notes,updated_at=NOW()
                 WHERE calendar_event_id=:event AND status="Open"'
            )->execute([':user'=>currentUserId(),':notes'=>$notes,':event'=>$id]);
        }
    }elseif($action==='start'){
        if($old!=='Confirmed'){$pdo->rollBack();jsonResponse(false,'Only Confirmed events can start.');}
        $new='In Progress';
        $pdo->prepare('UPDATE lacms_calendar_events SET status=:status,updated_at=NOW() WHERE id=:id')
            ->execute([':status'=>$new,':id'=>$id]);
    }elseif($action==='complete'){
        if(!in_array($old,['Confirmed','In Progress'],true)){$pdo->rollBack();jsonResponse(false,'Event cannot be completed from its current status.');}
        $new='Completed';
        $pdo->prepare('UPDATE lacms_calendar_events SET status=:status,updated_at=NOW() WHERE id=:id')
            ->execute([':status'=>$new,':id'=>$id]);
    }elseif($action==='postpone'){
        if(in_array($old,['Completed','Cancelled'],true)){$pdo->rollBack();jsonResponse(false,'Closed event cannot be postponed.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Postponement reason is required.');}
        $new='Postponed';
        $pdo->prepare('UPDATE lacms_calendar_events SET status=:status,conflict_notes=:notes,updated_at=NOW() WHERE id=:id')
            ->execute([':status'=>$new,':notes'=>$notes,':id'=>$id]);
    }elseif($action==='cancel'){
        if(in_array($old,['Completed','Cancelled'],true)){$pdo->rollBack();jsonResponse(false,'Event is already closed.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Cancellation reason is required.');}
        $new='Cancelled';
        $pdo->prepare(
            'UPDATE lacms_calendar_events
             SET status="Cancelled",cancelled_at=NOW(),cancellation_reason=:notes,updated_at=NOW()
             WHERE id=:id'
        )->execute([':notes'=>$notes,':id'=>$id]);
    }else{
        $pdo->rollBack();jsonResponse(false,'Invalid calendar action.');
    }

    lacmsCalendarHistory($pdo,$id,ucfirst($action),$old,$new,$notes?:ucfirst($action).' event.');
    lacmsLogActivity(currentUserId(),'LACMS Calendar '.ucfirst($action),
        "{$event['event_reference']} · {$old} -> {$new}.");
    $pdo->commit();
    jsonResponse(true,"Calendar event is now {$new}.",['status'=>$new]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update calendar event.');
}
