<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.meetings.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$calendarEventId=(int)($_POST['calendar_event_id']??0)?:null;
$agendaId=(int)($_POST['agenda_id']??0)?:null;
$committee=(int)($_POST['committee_id']??0)?:null;
$office=(int)($_POST['office_id']??0)?:null;
$title=clean($_POST['title']??'');
$type=clean($_POST['meeting_type']??'Committee Meeting');
$purpose=trim((string)($_POST['purpose']??''));
$start=clean($_POST['start_datetime']??'');
$end=clean($_POST['end_datetime']??'')?:null;
$venue=clean($_POST['venue']??'');
$link=clean($_POST['meeting_link']??'');
$chair=(int)($_POST['chair_user_id']??0)?:null;
$secretary=(int)($_POST['secretary_user_id']??0)?:null;
$notes=trim((string)($_POST['coordination_notes']??''));

if($title==='')jsonResponse(false,'Meeting title is required.');
if($start===''||strtotime($start)===false)jsonResponse(false,'Valid meeting start is required.');
if($end&&strtotime($end)<=strtotime($start))jsonResponse(false,'Meeting end must be later than start.');

$existing=null;
if($id){
    $q=$pdo->prepare('SELECT * FROM lacms_meetings WHERE id=:id');
    $q->execute([':id'=>$id]);$existing=$q->fetch();
    if(!$existing)jsonResponse(false,'Meeting not found.');
    if(in_array($existing['status'],['Completed','Cancelled'],true))jsonResponse(false,'Closed meetings cannot be edited.');
}

if($calendarEventId){
    $q=$pdo->prepare('SELECT id,status FROM lacms_calendar_events WHERE id=:id');
    $q->execute([':id'=>$calendarEventId]);$ce=$q->fetch();
    if(!$ce)jsonResponse(false,'Linked calendar event was not found.');
    if($ce['status']==='Cancelled')jsonResponse(false,'A cancelled calendar event cannot be linked to a meeting.');
}

try{
    $pdo->beginTransaction();

    if($existing){
        $pdo->prepare(
            'UPDATE lacms_meetings
             SET calendar_event_id=:event,agenda_id=:agenda,committee_id=:committee,
                 office_id=:office,title=:title,meeting_type=:type,purpose=:purpose,
                 start_datetime=:start,end_datetime=:end,venue=:venue,meeting_link=:link,
                 chair_user_id=:chair,secretary_user_id=:secretary,
                 coordination_notes=:notes,updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':event'=>$calendarEventId?:$existing['calendar_event_id'],':agenda'=>$agendaId,
            ':committee'=>$committee,':office'=>$office,':title'=>$title,':type'=>$type,
            ':purpose'=>$purpose?:null,':start'=>$start,':end'=>$end,':venue'=>$venue?:null,
            ':link'=>$link?:null,':chair'=>$chair,':secretary'=>$secretary,
            ':notes'=>$notes?:null,':id'=>$id
        ]);
        lacmsMeetingHistory($pdo,$id,'Update',$existing['status'],$existing['status'],'Meeting coordination details updated.');
        $message='Meeting updated.';
    }else{
        $ref=lacmsGenerateReference($pdo,'lacms_meetings','meeting_reference','MTG',$start);
        $pdo->prepare(
            'INSERT INTO lacms_meetings
             (meeting_reference,calendar_event_id,agenda_id,committee_id,office_id,title,
              meeting_type,purpose,start_datetime,end_datetime,venue,meeting_link,
              chair_user_id,secretary_user_id,status,coordination_notes,created_by,
              created_at,updated_at)
             VALUES(:ref,:event,:agenda,:committee,:office,:title,:type,:purpose,:start,:end,
              :venue,:link,:chair,:secretary,"Planned",:notes,:user,NOW(),NOW())'
        )->execute([
            ':ref'=>$ref,':event'=>$calendarEventId,':agenda'=>$agendaId,
            ':committee'=>$committee,':office'=>$office,':title'=>$title,':type'=>$type,
            ':purpose'=>$purpose?:null,':start'=>$start,':end'=>$end,':venue'=>$venue?:null,
            ':link'=>$link?:null,':chair'=>$chair,':secretary'=>$secretary,
            ':notes'=>$notes?:null,':user'=>currentUserId()
        ]);
        $id=(int)$pdo->lastInsertId();
        lacmsMeetingHistory($pdo,$id,'Create',null,'Planned',"Meeting {$ref} created.");
        $message='Meeting created.';
    }

    $eventId=lacmsSyncMeetingCalendarEvent($pdo,$id);
    lacmsSyncMeetingParticipantsToCalendar($pdo,$id);

    lacmsLogActivity(currentUserId(),'LACMS Meeting Save',"Meeting #{$id} · {$title}.");
    $pdo->commit();

    jsonResponse(true,$message,['id'=>$id,'calendar_event_id'=>$eventId]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save meeting.');
}
