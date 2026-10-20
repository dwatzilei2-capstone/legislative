<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.calendar.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$agendaId=(int)($_POST['agenda_id']??0)?:null;
$legislativeItem=(int)($_POST['legislative_item_id']??0)?:null;
$type=clean($_POST['event_type']??'Legislative Activity');
$title=clean($_POST['title']??'');
$description=trim((string)($_POST['description']??''));
$start=clean($_POST['start_datetime']??'');
$end=clean($_POST['end_datetime']??'')?:null;
$allDay=!empty($_POST['all_day'])?1:0;
$venue=clean($_POST['venue']??'');
$link=clean($_POST['meeting_link']??'');
$committee=(int)($_POST['committee_id']??0)?:null;
$office=(int)($_POST['office_id']??0)?:null;

if($title==='')jsonResponse(false,'Event title is required.');
if($start===''||strtotime($start)===false)jsonResponse(false,'Valid event start date/time is required.');
if($end&&strtotime($end)<=strtotime($start))jsonResponse(false,'End date/time must be later than the start.');

$existing=null;
if($id){
    $q=$pdo->prepare('SELECT * FROM lacms_calendar_events WHERE id=:id');
    $q->execute([':id'=>$id]);$existing=$q->fetch();
    if(!$existing)jsonResponse(false,'Calendar event not found.');
    if(in_array($existing['status'],['Completed','Cancelled'],true))jsonResponse(false,'Closed events cannot be edited.');
}

try{
    $pdo->beginTransaction();

    if($existing){
        $pdo->prepare(
            'UPDATE lacms_calendar_events
             SET agenda_id=:agenda,legislative_item_id=:legislative,event_type=:type,
                 title=:title,description=:description,start_datetime=:start,end_datetime=:end,
                 all_day=:all_day,venue=:venue,meeting_link=:link,committee_id=:committee,
                 office_id=:office,conflict_status="Unchecked",conflict_notes=NULL,updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':agenda'=>$agendaId,':legislative'=>$legislativeItem,':type'=>$type,
            ':title'=>$title,':description'=>$description?:null,':start'=>$start,':end'=>$end,
            ':all_day'=>$allDay,':venue'=>$venue?:null,':link'=>$link?:null,
            ':committee'=>$committee,':office'=>$office,':id'=>$id
        ]);
        lacmsCalendarHistory($pdo,$id,'Update',$existing['status'],$existing['status'],'Calendar event details updated.');
        $message='Calendar event updated.';
    }else{
        $ref=lacmsGenerateReference($pdo,'lacms_calendar_events','event_reference','CAL',$start);
        $pdo->prepare(
            'INSERT INTO lacms_calendar_events
             (event_reference,agenda_id,legislative_item_id,event_type,title,description,
              start_datetime,end_datetime,all_day,venue,meeting_link,committee_id,office_id,
              status,conflict_status,created_by,created_at,updated_at)
             VALUES(:ref,:agenda,:legislative,:type,:title,:description,:start,:end,:all_day,
              :venue,:link,:committee,:office,"Tentative","Unchecked",:user,NOW(),NOW())'
        )->execute([
            ':ref'=>$ref,':agenda'=>$agendaId,':legislative'=>$legislativeItem,':type'=>$type,
            ':title'=>$title,':description'=>$description?:null,':start'=>$start,':end'=>$end,
            ':all_day'=>$allDay,':venue'=>$venue?:null,':link'=>$link?:null,
            ':committee'=>$committee,':office'=>$office,':user'=>currentUserId()
        ]);
        $id=(int)$pdo->lastInsertId();
        lacmsCalendarHistory($pdo,$id,'Create',null,'Tentative',"Calendar event {$ref} created.");
        $message='Calendar event created.';
    }

    $conflicts=lacmsRefreshEventConflicts($pdo,$id);
    lacmsLogActivity(currentUserId(),'LACMS Calendar Save',"Calendar event #{$id} · {$title}.");

    $pdo->commit();
    jsonResponse(true,$message,[
        'id'=>$id,
        'conflict_count'=>count($conflicts),
        'conflict_status'=>$conflicts?'Detected':'Clear'
    ]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save calendar event.');
}
