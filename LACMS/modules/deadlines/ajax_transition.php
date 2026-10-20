<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_notification_helpers.php';
requireLacmsPermission('lacms.deadlines.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['deadline_id']??0);$action=clean($_POST['action']??'');
$notes=trim((string)($_POST['notes']??''));

try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT * FROM lacms_deadlines WHERE id=:id FOR UPDATE');
    $q->execute([':id'=>$id]);$d=$q->fetch();
    if(!$d){$pdo->rollBack();jsonResponse(false,'Deadline not found.');}

    $old=$d['status'];$new=$old;

    if($action==='start'){
        if(!in_array($old,['Pending','Overdue'],true)){$pdo->rollBack();jsonResponse(false,'Deadline cannot start from its current status.');}
        $new='In Progress';
        $pdo->prepare('UPDATE lacms_deadlines SET status=:status,updated_at=NOW() WHERE id=:id')
            ->execute([':status'=>$new,':id'=>$id]);
    }elseif($action==='complete'){
        if(in_array($old,['Completed','Cancelled'],true)){$pdo->rollBack();jsonResponse(false,'Deadline is already closed.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Completion notes are required.');}
        $new='Completed';
        $pdo->prepare(
            'UPDATE lacms_deadlines
             SET status="Completed",completion_notes=:notes,completed_by=:user,
                 completed_at=NOW(),updated_at=NOW()
             WHERE id=:id'
        )->execute([':notes'=>$notes,':user'=>currentUserId(),':id'=>$id]);
        $pdo->prepare(
            "UPDATE lacms_deadline_reminders
             SET status='Cancelled',updated_at=NOW()
             WHERE deadline_id=:id AND status='Scheduled'"
        )->execute([':id'=>$id]);
    }elseif($action==='reopen'){
        if($old!=='Completed'){$pdo->rollBack();jsonResponse(false,'Only completed deadlines can be reopened.');}
        if(normalizeRole(currentRole())!==normalizeRole(ROLE_ADMIN)){$pdo->rollBack();jsonResponse(false,'Only an Administrator can reopen a completed deadline.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Reopen reason is required.');}
        $new=strtotime($d['due_datetime'])<time()?'Overdue':'Pending';
        $pdo->prepare(
            'UPDATE lacms_deadlines
             SET status=:status,completion_notes=NULL,completed_by=NULL,completed_at=NULL,
                 updated_at=NOW()
             WHERE id=:id'
        )->execute([':status'=>$new,':id'=>$id]);
        lacmsCreateDefaultDeadlineReminders($pdo,$id,$d['reminder_policy']);
    }elseif($action==='cancel'){
        if(in_array($old,['Completed','Cancelled'],true)){$pdo->rollBack();jsonResponse(false,'Deadline is already closed.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Cancellation reason is required.');}
        $new='Cancelled';
        $pdo->prepare(
            'UPDATE lacms_deadlines
             SET status="Cancelled",completion_notes=:notes,updated_at=NOW()
             WHERE id=:id'
        )->execute([':notes'=>$notes,':id'=>$id]);
        $pdo->prepare(
            "UPDATE lacms_deadline_reminders
             SET status='Cancelled',updated_at=NOW()
             WHERE deadline_id=:id AND status='Scheduled'"
        )->execute([':id'=>$id]);
    }elseif($action==='escalate'){
        if($old==='Completed'||$old==='Cancelled'){$pdo->rollBack();jsonResponse(false,'Closed deadline cannot be escalated.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Escalation reason is required.');}
        $level=$d['priority_level']==='Urgent'||$old==='Overdue'?'Urgent':'Attention';
        $pdo->prepare(
            'INSERT INTO lacms_deadline_escalations
             (deadline_id,escalation_level,reason,status,escalated_to_user_id,
              escalated_to_office_id,created_by,created_at)
             VALUES(:deadline,:level,:reason,"Open",:user,:office,:created,NOW())'
        )->execute([
            ':deadline'=>$id,':level'=>$level,':reason'=>$notes,
            ':user'=>$d['responsible_user_id'],':office'=>$d['office_id'],
            ':created'=>currentUserId()
        ]);

        $text=lacmsGenerateDeadlineReminderText($pdo,$id,true);
        $recipients=lacmsDeadlineRecipients($pdo,$id);
        if($recipients){
            lacmsQueueNotification(
                $pdo,'Deadline',$id,'Deadline Escalation',
                'Escalation: '.$text['subject'],
                $notes."\n\n".$text['message'],
                $recipients,null,$text['generated_by_ai']
            );
        }
        $new=$old;
    }else{
        $pdo->rollBack();jsonResponse(false,'Invalid deadline action.');
    }

    lacmsDeadlineHistory($pdo,$id,ucfirst($action),$old,$new,$notes?:ucfirst($action).' deadline.');
    lacmsLogActivity(currentUserId(),'LACMS Deadline '.ucfirst($action),
        "{$d['deadline_reference']} · {$old} -> {$new}.");

    $pdo->commit();
    jsonResponse(true,$action==='escalate'?'Deadline escalated.':"Deadline is now {$new}.",['status'=>$new]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update deadline.');
}
