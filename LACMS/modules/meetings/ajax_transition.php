<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.meetings.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['meeting_id']??0);$action=clean($_POST['action']??'');
$notes=trim((string)($_POST['notes']??''));

try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT * FROM lacms_meetings WHERE id=:id FOR UPDATE');
    $q->execute([':id'=>$id]);$meeting=$q->fetch();
    if(!$meeting){$pdo->rollBack();jsonResponse(false,'Meeting not found.');}

    $old=$meeting['status'];$new=$old;
    $eventId=lacmsSyncMeetingCalendarEvent($pdo,$id);
    lacmsSyncMeetingParticipantsToCalendar($pdo,$id);

    if($action==='confirm'){
        if(!in_array($old,['Planned','Postponed'],true)){$pdo->rollBack();jsonResponse(false,'Only Planned/Postponed meetings can be confirmed.');}
        $conflicts=lacmsRefreshEventConflicts($pdo,$eventId);
        $critical=array_filter($conflicts,fn($c)=>$c['severity']==='Critical');
        if($critical){$pdo->rollBack();jsonResponse(false,'Meeting has critical schedule conflicts. Resolve/override them in Calendar Scheduling before confirmation.');}

        $required=$pdo->prepare('SELECT COUNT(*) FROM lacms_meeting_participants WHERE meeting_id=:id AND attendance_required=1');
        $required->execute([':id'=>$id]);
        if((int)$required->fetchColumn()===0){$pdo->rollBack();jsonResponse(false,'Add at least one required participant before confirming the meeting.');}

        $new='Confirmed';
        $pdo->prepare(
            'UPDATE lacms_meetings
             SET status="Confirmed",confirmed_by=:user,confirmed_at=NOW(),updated_at=NOW()
             WHERE id=:id'
        )->execute([':user'=>currentUserId(),':id'=>$id]);
        $pdo->prepare(
            'UPDATE lacms_calendar_events
             SET status="Confirmed",confirmed_by=:user,confirmed_at=NOW(),updated_at=NOW()
             WHERE id=:event'
        )->execute([':user'=>currentUserId(),':event'=>$eventId]);

        $p=$pdo->prepare(
            'SELECT p.user_id,COALESCE(u.full_name,p.external_name) name,
                    COALESCE(u.email,p.external_email) email
             FROM lacms_meeting_participants p
             LEFT JOIN users u ON u.id=p.user_id
             WHERE p.meeting_id=:id'
        );
        $p->execute([':id'=>$id]);
        $recipients=array_map(fn($x)=>[
            'user_id'=>$x['user_id'],'name'=>$x['name'],'email'=>$x['email'],
            'channel'=>$x['user_id']?'System':'Email'
        ],$p->fetchAll());

        if($recipients){
            lacmsQueueNotification(
                $pdo,'Meeting',$id,'Meeting Confirmation',
                'Meeting confirmed: '.$meeting['title'],
                'Schedule: '.formatDateTime($meeting['start_datetime']).'. Venue: '.($meeting['venue']?:'TBA').'.',
                $recipients
            );
            $pdo->prepare(
                'UPDATE lacms_meeting_participants
                 SET notification_status="Queued",updated_at=NOW()
                 WHERE meeting_id=:id'
            )->execute([':id'=>$id]);
        }
    }elseif($action==='start'){
        if($old!=='Confirmed'){$pdo->rollBack();jsonResponse(false,'Only Confirmed meetings can start.');}
        $new='In Progress';
        $pdo->prepare('UPDATE lacms_meetings SET status=:status,updated_at=NOW() WHERE id=:id')->execute([':status'=>$new,':id'=>$id]);
        $pdo->prepare('UPDATE lacms_calendar_events SET status="In Progress",updated_at=NOW() WHERE id=:id')->execute([':id'=>$eventId]);
    }elseif($action==='complete'){
        if(!in_array($old,['Confirmed','In Progress'],true)){$pdo->rollBack();jsonResponse(false,'Meeting cannot be completed from its current status.');}
        $new='Completed';
        $pdo->prepare('UPDATE lacms_meetings SET status="Completed",completed_at=NOW(),updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
        $pdo->prepare('UPDATE lacms_calendar_events SET status="Completed",updated_at=NOW() WHERE id=:id')->execute([':id'=>$eventId]);
    }elseif($action==='postpone'){
        if(in_array($old,['Completed','Cancelled'],true)){$pdo->rollBack();jsonResponse(false,'Closed meeting cannot be postponed.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Postponement reason is required.');}
        $new='Postponed';
        $pdo->prepare('UPDATE lacms_meetings SET status="Postponed",coordination_notes=CONCAT(COALESCE(coordination_notes,""),"\n",:notes),updated_at=NOW() WHERE id=:id')->execute([':notes'=>$notes,':id'=>$id]);
        $pdo->prepare('UPDATE lacms_calendar_events SET status="Postponed",updated_at=NOW() WHERE id=:id')->execute([':id'=>$eventId]);
    }elseif($action==='cancel'){
        if(in_array($old,['Completed','Cancelled'],true)){$pdo->rollBack();jsonResponse(false,'Meeting is already closed.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Cancellation reason is required.');}
        $new='Cancelled';
        $pdo->prepare('UPDATE lacms_meetings SET status="Cancelled",cancelled_at=NOW(),cancellation_reason=:notes,updated_at=NOW() WHERE id=:id')->execute([':notes'=>$notes,':id'=>$id]);
        $pdo->prepare('UPDATE lacms_calendar_events SET status="Cancelled",cancelled_at=NOW(),cancellation_reason=:notes,updated_at=NOW() WHERE id=:id')->execute([':notes'=>$notes,':id'=>$eventId]);
    }else{
        $pdo->rollBack();jsonResponse(false,'Invalid meeting action.');
    }

    if(in_array($action,['postpone','cancel'],true)){
        $p=$pdo->prepare(
            'SELECT p.user_id,COALESCE(u.full_name,p.external_name) name,
                    COALESCE(u.email,p.external_email) email
             FROM lacms_meeting_participants p
             LEFT JOIN users u ON u.id=p.user_id
             WHERE p.meeting_id=:id'
        );
        $p->execute([':id'=>$id]);
        $recipients=array_map(fn($x)=>[
            'user_id'=>$x['user_id'],'name'=>$x['name'],'email'=>$x['email'],
            'channel'=>$x['user_id']?'System':'Email'
        ],$p->fetchAll());

        if($recipients){
            lacmsQueueNotification(
                $pdo,'Meeting',$id,'Meeting '.ucfirst($action),
                'Meeting '.($action==='cancel'?'cancelled':'postponed').': '.$meeting['title'],
                $notes,
                $recipients
            );
        }
    }

    lacmsMeetingHistory($pdo,$id,ucfirst($action),$old,$new,$notes?:ucfirst($action).' meeting.');
    lacmsCalendarHistory($pdo,$eventId,'Meeting '.ucfirst($action),
        match($old){'Planned'=>'Tentative',default=>$old},
        match($new){'Planned'=>'Tentative',default=>$new},
        'Calendar synchronized from meeting '.$meeting['meeting_reference'].'.'
    );
    lacmsLogActivity(currentUserId(),'LACMS Meeting '.ucfirst($action),
        "{$meeting['meeting_reference']} · {$old} -> {$new}.");

    $pdo->commit();
    jsonResponse(true,"Meeting is now {$new}.",['status'=>$new]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update meeting.');
}
