<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/lacms_sync_helpers.php';
requireLacmsPermission('lacms.sync.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');requireCsrf();
$pdo=db();$id=(int)($_POST['sync_record_id']??0);$action=clean($_POST['action']??'');$notes=trim((string)($_POST['notes']??''));
try{$pdo->beginTransaction();$q=$pdo->prepare('SELECT * FROM lacms_sync_records WHERE id=:id FOR UPDATE');$q->execute([':id'=>$id]);$s=$q->fetch();if(!$s){$pdo->rollBack();jsonResponse(false,'Coordination record not found.');}$old=$s['status'];$new=$old;
 $new=match($action){'coordinate'=>'Coordinating','await_executive'=>'Awaiting Executive','await_legislative'=>'Awaiting Legislative','align'=>'Aligned','resolve'=>'Resolved','close'=>'Closed','cancel'=>'Cancelled',default=>$old};
 if($new===$old&&$action!=='coordinate'){$pdo->rollBack();jsonResponse(false,'Invalid or duplicate coordination transition.');}
 if(in_array($old,['Closed','Cancelled'],true)){$pdo->rollBack();jsonResponse(false,'Closed coordination record cannot transition.');}
 if(in_array($action,['resolve','close','cancel'],true)&&$notes===''){$pdo->rollBack();jsonResponse(false,'Transition notes/reason are required.');}
 if($action==='close'&&!in_array($old,['Aligned','Resolved'],true)){$pdo->rollBack();jsonResponse(false,'Only Aligned or Resolved records can be closed.');}
 if($action==='close'){$q=$pdo->prepare('SELECT COUNT(*) FROM lacms_sync_actions WHERE sync_record_id=:id AND status IN ("Pending","In Progress")');$q->execute([':id'=>$id]);if((int)$q->fetchColumn()>0){$pdo->rollBack();jsonResponse(false,'Complete or cancel all open coordination actions before closing.');}}
 $pdo->prepare('UPDATE lacms_sync_records SET status=:status,resolution_notes=CASE WHEN :notes_check<>"" THEN CONCAT(COALESCE(resolution_notes,""),"\n",:notes_value) ELSE resolution_notes END,resolved_by=CASE WHEN :resolved_flag=1 THEN :user ELSE resolved_by END,resolved_at=CASE WHEN :resolved_time=1 THEN NOW() ELSE resolved_at END,updated_at=NOW() WHERE id=:id')->execute([':status'=>$new,':notes_check'=>$notes,':notes_value'=>$notes,':resolved_flag'=>in_array($new,['Resolved','Closed'],true)?1:0,':user'=>currentUserId(),':resolved_time'=>in_array($new,['Resolved','Closed'],true)?1:0,':id'=>$id]);
 lacmsSyncHistory($pdo,$id,ucwords(str_replace('_',' ',$action)),$old,$new,$notes);lacmsAudit('Transition','LACMS Sync',(string)$id,['status'=>$old],['status'=>$new,'notes'=>$notes]);lacmsLogActivity(currentUserId(),'LACMS Synchronization Transition',"{$s['sync_reference']} · {$old} -> {$new}.");$pdo->commit();jsonResponse(true,"Coordination is now {$new}.",['status'=>$new]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to transition coordination record.');}
