<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.meetings.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$mode=clean($_POST['mode']??'save');
$meetingId=(int)($_POST['meeting_id']??0);$participantId=(int)($_POST['participant_id']??0);

$q=$pdo->prepare('SELECT * FROM lacms_meetings WHERE id=:id');
$q->execute([':id'=>$meetingId]);$meeting=$q->fetch();
if(!$meeting)jsonResponse(false,'Meeting not found.');
if(in_array($meeting['status'],['Completed','Cancelled'],true))jsonResponse(false,'Closed meeting cannot be changed.');

if($mode==='delete'){
    $q=$pdo->prepare('SELECT * FROM lacms_meeting_participants WHERE id=:id AND meeting_id=:meeting');
    $q->execute([':id'=>$participantId,':meeting'=>$meetingId]);$p=$q->fetch();
    if(!$p)jsonResponse(false,'Participant not found.');
    $pdo->prepare('DELETE FROM lacms_meeting_participants WHERE id=:id')->execute([':id'=>$participantId]);
    lacmsSyncMeetingParticipantsToCalendar($pdo,$meetingId);
    lacmsMeetingHistory($pdo,$meetingId,'Remove Participant',$meeting['status'],$meeting['status'],'A participant was removed.');
    jsonResponse(true,'Participant removed.');
}

$userId=(int)($_POST['user_id']??0)?:null;
$name=clean($_POST['external_name']??'');
$email=clean($_POST['external_email']??'');
$role=clean($_POST['participant_role']??'Participant');
$required=!empty($_POST['attendance_required'])?1:0;
$response=clean($_POST['response_status']??'Pending');
$attendance=clean($_POST['attendance_status']??'Not Recorded');
$notes=trim((string)($_POST['notes']??''));

if(!$userId&&$name==='')jsonResponse(false,'Choose an internal user or enter an external participant.');
if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))jsonResponse(false,'External email is invalid.');
if(!in_array($response,['Pending','Accepted','Tentative','Declined'],true))jsonResponse(false,'Invalid response status.');
if(!in_array($attendance,['Not Recorded','Present','Remote','Absent','Excused'],true))jsonResponse(false,'Invalid attendance status.');

try{
    if($participantId){
        $pdo->prepare(
            'UPDATE lacms_meeting_participants
             SET user_id=:user,external_name=:name,external_email=:email,
                 participant_role=:role,attendance_required=:required,response_status=:response,
                 attendance_status=:attendance,notes=:notes,updated_at=NOW()
             WHERE id=:id AND meeting_id=:meeting'
        )->execute([
            ':user'=>$userId,':name'=>$name?:null,':email'=>$email?:null,
            ':role'=>$role,':required'=>$required,':response'=>$response,
            ':attendance'=>$attendance,':notes'=>$notes?:null,
            ':id'=>$participantId,':meeting'=>$meetingId
        ]);
        $message='Participant updated.';
    }else{
        $pdo->prepare(
            'INSERT INTO lacms_meeting_participants
             (meeting_id,user_id,external_name,external_email,participant_role,
              attendance_required,notification_status,response_status,attendance_status,
              notes,created_at,updated_at)
             VALUES(:meeting,:user,:name,:email,:role,:required,"Pending",:response,
              :attendance,:notes,NOW(),NOW())'
        )->execute([
            ':meeting'=>$meetingId,':user'=>$userId,':name'=>$name?:null,
            ':email'=>$email?:null,':role'=>$role,':required'=>$required,
            ':response'=>$response,':attendance'=>$attendance,':notes'=>$notes?:null
        ]);
        $participantId=(int)$pdo->lastInsertId();$message='Participant added.';
    }

    lacmsSyncMeetingParticipantsToCalendar($pdo,$meetingId);
    lacmsMeetingHistory($pdo,$meetingId,'Participant',$meeting['status'],$meeting['status'],$message);
    jsonResponse(true,$message,['id'=>$participantId]);
}catch(PDOException $e){
    if((int)($e->errorInfo[1]??0)===1062)jsonResponse(false,'This internal user is already a participant in the meeting.');
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save participant.');
}
