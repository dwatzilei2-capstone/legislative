<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.calendar.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$mode=clean($_POST['mode']??'save');
$eventId=(int)($_POST['calendar_event_id']??0);$participantId=(int)($_POST['participant_id']??0);

$q=$pdo->prepare('SELECT * FROM lacms_calendar_events WHERE id=:id');
$q->execute([':id'=>$eventId]);$event=$q->fetch();
if(!$event)jsonResponse(false,'Calendar event not found.');
if(in_array($event['status'],['Completed','Cancelled'],true))jsonResponse(false,'Closed events cannot be changed.');

if($mode==='delete'){
    $q=$pdo->prepare('SELECT * FROM lacms_calendar_event_participants WHERE id=:id AND calendar_event_id=:event');
    $q->execute([':id'=>$participantId,':event'=>$eventId]);$p=$q->fetch();
    if(!$p)jsonResponse(false,'Participant not found.');
    $pdo->prepare('DELETE FROM lacms_calendar_event_participants WHERE id=:id')->execute([':id'=>$participantId]);
    $conflicts=lacmsRefreshEventConflicts($pdo,$eventId);
    lacmsCalendarHistory($pdo,$eventId,'Remove Participant',$event['status'],$event['status'],'A calendar participant was removed.');
    jsonResponse(true,'Participant removed.',['conflict_count'=>count($conflicts)]);
}

$userId=(int)($_POST['user_id']??0)?:null;
$name=clean($_POST['external_name']??'');
$email=clean($_POST['external_email']??'');
$role=clean($_POST['participant_role']??'Participant');
$required=!empty($_POST['attendance_required'])?1:0;
$response=clean($_POST['response_status']??'Pending');
$notes=trim((string)($_POST['notes']??''));

if(!$userId&&$name==='')jsonResponse(false,'Choose an internal user or enter an external participant name.');
if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))jsonResponse(false,'External email address is invalid.');
if(!in_array($response,['Pending','Accepted','Tentative','Declined'],true))jsonResponse(false,'Invalid response status.');

try{
    if($participantId){
        $pdo->prepare(
            'UPDATE lacms_calendar_event_participants
             SET user_id=:user,external_name=:name,external_email=:email,
                 participant_role=:role,attendance_required=:required,response_status=:response,
                 response_at=CASE WHEN :response_check<>"Pending" THEN NOW() ELSE response_at END,
                 notes=:notes
             WHERE id=:id AND calendar_event_id=:event'
        )->execute([
            ':user'=>$userId,':name'=>$name?:null,':email'=>$email?:null,':role'=>$role,
            ':required'=>$required,':response'=>$response,':response_check'=>$response,
            ':notes'=>$notes?:null,':id'=>$participantId,':event'=>$eventId
        ]);
        $message='Participant updated.';
    }else{
        $pdo->prepare(
            'INSERT INTO lacms_calendar_event_participants
             (calendar_event_id,user_id,external_name,external_email,participant_role,
              attendance_required,response_status,response_at,notes,created_at)
             VALUES(:event,:user,:name,:email,:role,:required,:response,
              CASE WHEN :response_check<>"Pending" THEN NOW() ELSE NULL END,:notes,NOW())'
        )->execute([
            ':event'=>$eventId,':user'=>$userId,':name'=>$name?:null,':email'=>$email?:null,
            ':role'=>$role,':required'=>$required,':response'=>$response,
            ':response_check'=>$response,':notes'=>$notes?:null
        ]);
        $participantId=(int)$pdo->lastInsertId();$message='Participant added.';
    }

    $conflicts=lacmsRefreshEventConflicts($pdo,$eventId);
    lacmsCalendarHistory($pdo,$eventId,'Participant',$event['status'],$event['status'],$message);
    jsonResponse(true,$message,['id'=>$participantId,'conflict_count'=>count($conflicts)]);
}catch(PDOException $e){
    if((int)($e->errorInfo[1]??0)===1062)jsonResponse(false,'This internal user is already a participant in the event.');
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save participant.');
}
