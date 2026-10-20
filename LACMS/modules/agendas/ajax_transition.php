<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.agendas.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['agenda_id']??0);$action=clean($_POST['action']??'');
$notes=trim((string)($_POST['notes']??''));

try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT * FROM lacms_agendas WHERE id=:id FOR UPDATE');
    $q->execute([':id'=>$id]);$agenda=$q->fetch();
    if(!$agenda){$pdo->rollBack();jsonResponse(false,'Agenda not found.');}

    $old=$agenda['status'];$new=$old;

    if($action==='review'){
        if($old!=='Draft'){$pdo->rollBack();jsonResponse(false,'Only Draft agendas can be sent for review.');}
        $count=$pdo->prepare('SELECT COUNT(*) FROM lacms_agenda_items WHERE agenda_id=:id AND item_status<>"Removed"');
        $count->execute([':id'=>$id]);
        if((int)$count->fetchColumn()===0){$pdo->rollBack();jsonResponse(false,'Add at least one agenda item first.');}
        $new='Under Review';
    }elseif($action==='finalize'){
        if(!in_array($old,['Draft','Under Review'],true)){$pdo->rollBack();jsonResponse(false,'This agenda cannot be finalized.');}
        if(!$agenda['agenda_date']){$pdo->rollBack();jsonResponse(false,'Agenda date is required before finalization.');}
        $count=$pdo->prepare('SELECT COUNT(*) FROM lacms_agenda_items WHERE agenda_id=:id AND item_status NOT IN ("Removed","Deferred")');
        $count->execute([':id'=>$id]);
        if((int)$count->fetchColumn()===0){$pdo->rollBack();jsonResponse(false,'At least one active agenda item is required.');}
        $new='Finalized';
    }elseif($action==='reopen'){
        if($old!=='Finalized'){$pdo->rollBack();jsonResponse(false,'Only Finalized agendas can be reopened.');}
        if(normalizeRole(currentRole())!==normalizeRole(ROLE_ADMIN)){$pdo->rollBack();jsonResponse(false,'Only an Administrator can reopen a finalized agenda.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Reopen reason is required.');}
        $new='Under Review';
    }elseif($action==='archive'){
        if($old!=='Finalized'){$pdo->rollBack();jsonResponse(false,'Only Finalized agendas can be archived.');}
        $new='Archived';
    }elseif($action==='cancel'){
        if(in_array($old,['Archived','Cancelled'],true)){$pdo->rollBack();jsonResponse(false,'Agenda is already closed.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Cancellation reason is required.');}
        $new='Cancelled';
    }else{
        $pdo->rollBack();jsonResponse(false,'Invalid agenda action.');
    }

    $pdo->prepare(
        'UPDATE lacms_agendas
         SET status=:status,
             finalized_by=CASE WHEN :finalize_flag=1 THEN :user ELSE finalized_by END,
             finalized_at=CASE WHEN :finalize_time=1 THEN NOW() ELSE finalized_at END,
             notes=CASE WHEN :note_check<>"" THEN CONCAT(COALESCE(notes,""),"\n",:note_value) ELSE notes END,
             updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':status'=>$new,
        ':finalize_flag'=>$action==='finalize'?1:0,':user'=>currentUserId(),
        ':finalize_time'=>$action==='finalize'?1:0,
        ':note_check'=>$notes,':note_value'=>$notes,':id'=>$id
    ]);

    lacmsAgendaHistory($pdo,$id,ucfirst($action),$old,$new,$notes?:ucfirst($action).' agenda.');
    lacmsLogActivity(currentUserId(),'LACMS Agenda '.ucfirst($action),
        "{$agenda['agenda_reference']} · {$old} -> {$new}.");

    $pdo->commit();
    jsonResponse(true,"Agenda is now {$new}.",['status'=>$new]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update agenda.');
}
