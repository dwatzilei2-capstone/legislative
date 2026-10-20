<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.agendas.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$title=clean($_POST['title']??'');
$type=clean($_POST['agenda_type']??'Legislative Session');
$committee=(int)($_POST['committee_id']??0)?:null;
$office=(int)($_POST['office_id']??0)?:null;
$date=clean($_POST['agenda_date']??'')?:null;
$start=clean($_POST['start_time']??'')?:null;
$end=clean($_POST['end_time']??'')?:null;
$venue=clean($_POST['venue']??'');
$link=clean($_POST['meeting_link']??'');
$description=trim((string)($_POST['description']??''));
$notes=trim((string)($_POST['notes']??''));

if($title==='')jsonResponse(false,'Agenda title is required.');
if($start&&$end&&strtotime($end)<=strtotime($start))jsonResponse(false,'End time must be later than start time.');

$existing=null;
if($id){
    $q=$pdo->prepare('SELECT * FROM lacms_agendas WHERE id=:id');
    $q->execute([':id'=>$id]);$existing=$q->fetch();
    if(!$existing)jsonResponse(false,'Agenda not found.');
    if(!in_array($existing['status'],['Draft','Under Review'],true)){
        jsonResponse(false,'Only Draft or Under Review agendas can be edited.');
    }
}

try{
    $pdo->beginTransaction();

    if($existing){
        $pdo->prepare(
            'UPDATE lacms_agendas
             SET title=:title,agenda_type=:type,committee_id=:committee,office_id=:office,
                 agenda_date=:date,start_time=:start,end_time=:end,venue=:venue,
                 meeting_link=:link,description=:description,notes=:notes,updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':title'=>$title,':type'=>$type,':committee'=>$committee,':office'=>$office,
            ':date'=>$date,':start'=>$start,':end'=>$end,':venue'=>$venue?:null,
            ':link'=>$link?:null,':description'=>$description?:null,':notes'=>$notes?:null,
            ':id'=>$id
        ]);
        lacmsAgendaHistory($pdo,$id,'Update',$existing['status'],$existing['status'],'Agenda details updated.');
        $message='Agenda updated.';
    }else{
        $ref=lacmsGenerateReference($pdo,'lacms_agendas','agenda_reference','AGD',$date);
        $pdo->prepare(
            'INSERT INTO lacms_agendas
             (agenda_reference,title,agenda_type,committee_id,office_id,agenda_date,
              start_time,end_time,venue,meeting_link,description,notes,status,created_by,
              created_at,updated_at)
             VALUES(:ref,:title,:type,:committee,:office,:date,:start,:end,:venue,:link,
              :description,:notes,"Draft",:user,NOW(),NOW())'
        )->execute([
            ':ref'=>$ref,':title'=>$title,':type'=>$type,':committee'=>$committee,
            ':office'=>$office,':date'=>$date,':start'=>$start,':end'=>$end,
            ':venue'=>$venue?:null,':link'=>$link?:null,
            ':description'=>$description?:null,':notes'=>$notes?:null,
            ':user'=>currentUserId()
        ]);
        $id=(int)$pdo->lastInsertId();
        lacmsAgendaHistory($pdo,$id,'Create',null,'Draft',"Agenda {$ref} created.");
        $message='Agenda created.';
    }

    lacmsLogActivity(currentUserId(),'LACMS Agenda Save',"Agenda #{$id}: {$title}.");
    $pdo->commit();
    jsonResponse(true,$message,['id'=>$id]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save agenda.');
}
