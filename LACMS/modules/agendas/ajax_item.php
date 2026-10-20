<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.agendas.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$mode=clean($_POST['mode']??'save');
$agendaId=(int)($_POST['agenda_id']??0);$itemId=(int)($_POST['item_id']??0);

$q=$pdo->prepare('SELECT * FROM lacms_agendas WHERE id=:id');
$q->execute([':id'=>$agendaId]);$agenda=$q->fetch();
if(!$agenda)jsonResponse(false,'Agenda not found.');
if($agenda['status']==='Finalized')jsonResponse(false,'Finalized agenda items are locked.');

if($mode==='delete'){
    $q=$pdo->prepare('SELECT * FROM lacms_agenda_items WHERE id=:id AND agenda_id=:agenda');
    $q->execute([':id'=>$itemId,':agenda'=>$agendaId]);$row=$q->fetch();
    if(!$row)jsonResponse(false,'Agenda item not found.');
    $pdo->prepare('DELETE FROM lacms_agenda_items WHERE id=:id')->execute([':id'=>$itemId]);
    lacmsAgendaHistory($pdo,$agendaId,'Delete Item',$agenda['status'],$agenda['status'],
        'Removed agenda item: '.$row['title'].'.');
    jsonResponse(true,'Agenda item deleted.');
}

$legislativeItem=(int)($_POST['legislative_item_id']??0)?:null;
$itemNumber=clean($_POST['item_number']??'');
$section=clean($_POST['agenda_section']??'New Business');
$title=clean($_POST['title']??'');
$description=trim((string)($_POST['description']??''));
$sponsor=(int)($_POST['sponsor_user_id']??0)?:null;
$presenter=(int)($_POST['presenter_user_id']??0)?:null;
$committee=(int)($_POST['committee_id']??0)?:null;
$priority=clean($_POST['priority_level']??'Normal');
$minutes=(int)($_POST['estimated_minutes']??0)?:null;
$status=clean($_POST['item_status']??'Pending');
$disposition=clean($_POST['disposition']??'');
$notes=trim((string)($_POST['notes']??''));

if($title==='')jsonResponse(false,'Agenda item title is required.');
if(!in_array($priority,['Low','Normal','High','Urgent'],true))jsonResponse(false,'Invalid priority.');
if(!in_array($status,['Pending','Ready','Deferred','Completed','Removed'],true))jsonResponse(false,'Invalid agenda item status.');

try{
    $pdo->beginTransaction();

    if($itemId){
        $q=$pdo->prepare('SELECT * FROM lacms_agenda_items WHERE id=:id AND agenda_id=:agenda');
        $q->execute([':id'=>$itemId,':agenda'=>$agendaId]);$existing=$q->fetch();
        if(!$existing){$pdo->rollBack();jsonResponse(false,'Agenda item not found.');}

        $pdo->prepare(
            'UPDATE lacms_agenda_items
             SET legislative_item_id=:legislative,item_number=:number,agenda_section=:section,
                 title=:title,description=:description,sponsor_user_id=:sponsor,
                 presenter_user_id=:presenter,committee_id=:committee,priority_level=:priority,
                 estimated_minutes=:minutes,item_status=:status,disposition=:disposition,
                 notes=:notes,updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':legislative'=>$legislativeItem,':number'=>$itemNumber?:null,':section'=>$section,
            ':title'=>$title,':description'=>$description?:null,':sponsor'=>$sponsor,
            ':presenter'=>$presenter,':committee'=>$committee,':priority'=>$priority,
            ':minutes'=>$minutes,':status'=>$status,':disposition'=>$disposition?:null,
            ':notes'=>$notes?:null,':id'=>$itemId
        ]);
        lacmsAgendaItemHistory($pdo,$itemId,$agendaId,'Update',"{$title} updated.");
        $message='Agenda item updated.';
    }else{
        $q=$pdo->prepare('SELECT COALESCE(MAX(sequence_number),0)+1 FROM lacms_agenda_items WHERE agenda_id=:agenda');
        $q->execute([':agenda'=>$agendaId]);$seq=(int)$q->fetchColumn();

        $pdo->prepare(
            'INSERT INTO lacms_agenda_items
             (agenda_id,legislative_item_id,item_number,sequence_number,agenda_section,title,
              description,sponsor_user_id,presenter_user_id,committee_id,priority_level,
              estimated_minutes,item_status,disposition,notes,created_by,created_at,updated_at)
             VALUES(:agenda,:legislative,:number,:seq,:section,:title,:description,:sponsor,
              :presenter,:committee,:priority,:minutes,:status,:disposition,:notes,:user,NOW(),NOW())'
        )->execute([
            ':agenda'=>$agendaId,':legislative'=>$legislativeItem,':number'=>$itemNumber?:null,
            ':seq'=>$seq,':section'=>$section,':title'=>$title,
            ':description'=>$description?:null,':sponsor'=>$sponsor,':presenter'=>$presenter,
            ':committee'=>$committee,':priority'=>$priority,':minutes'=>$minutes,
            ':status'=>$status,':disposition'=>$disposition?:null,':notes'=>$notes?:null,
            ':user'=>currentUserId()
        ]);
        $itemId=(int)$pdo->lastInsertId();
        lacmsAgendaItemHistory($pdo,$itemId,$agendaId,'Create',"{$title} added as sequence {$seq}.");
        $message='Agenda item added.';
    }

    lacmsAgendaHistory($pdo,$agendaId,'Agenda Items',$agenda['status'],$agenda['status'],$message);
    lacmsLogActivity(currentUserId(),'LACMS Agenda Item',"Agenda {$agenda['agenda_reference']} · {$title}.");
    $pdo->commit();
    jsonResponse(true,$message,['id'=>$itemId]);
}catch(PDOException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    if((int)$e->errorInfo[1]===1062)jsonResponse(false,'This legislative item is already included in this agenda.');
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save agenda item.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save agenda item.');
}
