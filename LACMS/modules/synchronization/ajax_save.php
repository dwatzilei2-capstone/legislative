<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/lacms_sync_helpers.php';
requireLacmsPermission('lacms.sync.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['id']??0);
$title=clean($_POST['title']??'');$type=clean($_POST['sync_type']??'Policy Coordination');
$legislative=(int)($_POST['legislative_item_id']??0)?:null;$agenda=(int)($_POST['agenda_id']??0)?:null;
$event=(int)($_POST['calendar_event_id']??0)?:null;$office=(int)($_POST['source_office_id']??0)?:null;
$committee=(int)($_POST['target_committee_id']??0)?:null;$priority=clean($_POST['priority_level']??'Normal');
$executive=trim((string)($_POST['executive_position']??''));$legislativePosition=trim((string)($_POST['legislative_position']??''));
$resolution=trim((string)($_POST['resolution_notes']??''));
if($title==='')jsonResponse(false,'Coordination title is required.');
if(!in_array($priority,['Low','Normal','High','Urgent'],true))jsonResponse(false,'Invalid priority.');

$existing=null;if($id){$q=$pdo->prepare('SELECT * FROM lacms_sync_records WHERE id=:id');$q->execute([':id'=>$id]);$existing=$q->fetch();if(!$existing)jsonResponse(false,'Synchronization record not found.');if(in_array($existing['status'],['Closed','Cancelled'],true))jsonResponse(false,'Closed records cannot be edited.');}

try{
 $pdo->beginTransaction();
 if($existing){
   $pdo->prepare('UPDATE lacms_sync_records SET title=:title,sync_type=:type,legislative_item_id=:legislative,agenda_id=:agenda,calendar_event_id=:event,source_office_id=:office,target_committee_id=:committee,priority_level=:priority,executive_position=:executive,legislative_position=:legislative_position,resolution_notes=:resolution,updated_at=NOW() WHERE id=:id')->execute([':title'=>$title,':type'=>$type,':legislative'=>$legislative,':agenda'=>$agenda,':event'=>$event,':office'=>$office,':committee'=>$committee,':priority'=>$priority,':executive'=>$executive?:null,':legislative_position'=>$legislativePosition?:null,':resolution'=>$resolution?:null,':id'=>$id]);
   lacmsSyncHistory($pdo,$id,'Update',$existing['status'],$existing['status'],'Coordination details updated.');
   lacmsAudit('Update','LACMS Sync',(string)$id,$existing,['title'=>$title,'priority_level'=>$priority,'executive_position'=>$executive,'legislative_position'=>$legislativePosition]);
   $message='Coordination record updated.';
 }else{
   $ref=lacmsGenerateReference($pdo,'lacms_sync_records','sync_reference','SYN');
   $pdo->prepare('INSERT INTO lacms_sync_records (sync_reference,title,sync_type,legislative_item_id,agenda_id,calendar_event_id,source_office_id,target_committee_id,priority_level,executive_position,legislative_position,status,resolution_notes,created_by,created_at,updated_at) VALUES(:ref,:title,:type,:legislative,:agenda,:event,:office,:committee,:priority,:executive,:legislative_position,"Draft",:resolution,:user,NOW(),NOW())')->execute([':ref'=>$ref,':title'=>$title,':type'=>$type,':legislative'=>$legislative,':agenda'=>$agenda,':event'=>$event,':office'=>$office,':committee'=>$committee,':priority'=>$priority,':executive'=>$executive?:null,':legislative_position'=>$legislativePosition?:null,':resolution'=>$resolution?:null,':user'=>currentUserId()]);
   $id=(int)$pdo->lastInsertId();lacmsSyncHistory($pdo,$id,'Create',null,'Draft',"Coordination {$ref} created.");lacmsAudit('Create','LACMS Sync',(string)$id,null,['reference'=>$ref,'title'=>$title]);$message='Coordination record created.';
 }
 lacmsLogActivity(currentUserId(),'LACMS Synchronization Save',"Synchronization #{$id}: {$title}.");
 $pdo->commit();jsonResponse(true,$message,['id'=>$id]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save coordination record.');}
