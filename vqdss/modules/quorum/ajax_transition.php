<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requireRole([ROLE_ADMIN,ROLE_STAFF]);
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['id']??0);$action=clean($_POST['action']??'');
$notes=trim((string)($_POST['notes']??''));

try{
 $pdo->beginTransaction();
 $q=$pdo->prepare('SELECT * FROM vqd_quorum_checks WHERE id=:id FOR UPDATE');$q->execute([':id'=>$id]);$check=$q->fetch();
 if(!$check){$pdo->rollBack();jsonResponse(false,'Quorum check not found.');}

 $stats=vqdRefreshQuorum($pdo,$id);
 $old=$check['verification_status'];

 if($action==='verify'){
   if($stats['eligible']<=0){$pdo->rollBack();jsonResponse(false,'No eligible voting members are recorded.');}
   $new='Verified';
   $pdo->prepare(
    'UPDATE vqd_quorum_checks
     SET verification_status="Verified",checked_by=:user,checked_at=NOW(),
         certification_notes=:notes,updated_at=NOW()
     WHERE id=:id'
   )->execute([':user'=>currentUserId(),':notes'=>$notes?:$check['certification_notes'],':id'=>$id]);
 }elseif($action==='certify'){
   if($stats['result']!=='Met'){$pdo->rollBack();jsonResponse(false,'Quorum cannot be certified because the required threshold is not met.');}
   $new='Certified';
   $pdo->prepare(
    'UPDATE vqd_quorum_checks
     SET verification_status="Certified",
         checked_by=COALESCE(checked_by,:checked_user),
         checked_at=COALESCE(checked_at,NOW()),
         certified_by=:certified_user,
         certified_at=NOW(),
         certification_notes=:notes,
         updated_at=NOW()
     WHERE id=:id'
   )->execute([
    ':checked_user'=>currentUserId(),
    ':certified_user'=>currentUserId(),
    ':notes'=>$notes?:$check['certification_notes'],
    ':id'=>$id
   ]);
 }elseif($action==='revoke'){
   if($old!=='Certified'){$pdo->rollBack();jsonResponse(false,'Only a certified quorum can be revoked.');}
   if($notes===''){$pdo->rollBack();jsonResponse(false,'Revocation reason is required.');}
   $activeVote=$pdo->prepare("SELECT COUNT(*) FROM vqd_voting_events WHERE quorum_check_id=:id AND status IN ('Open','Paused','Closed')");
   $activeVote->execute([':id'=>$id]);
   if((int)$activeVote->fetchColumn()>0){$pdo->rollBack();jsonResponse(false,'Certification cannot be revoked after voting has opened.');}
   $new='Revoked';
   $pdo->prepare(
    'UPDATE vqd_quorum_checks
     SET verification_status="Revoked",revoked_by=:user,revoked_at=NOW(),
         revocation_reason=:reason,updated_at=NOW()
     WHERE id=:id'
   )->execute([':user'=>currentUserId(),':reason'=>$notes,':id'=>$id]);
 }else{
   $pdo->rollBack();jsonResponse(false,'Invalid quorum workflow action.');
 }

 vqdQuorumHistory($pdo,$id,ucfirst($action),$old,$new,$notes?:("Quorum status changed from {$old} to {$new}."));
 vqdLogActivity(currentUserId(),'Quorum '.ucfirst($action),"{$check['reference_number']} {$old} -> {$new}; result={$stats['result']}.");

 $pdo->commit();
 jsonResponse(true,"Quorum {$action} completed.",['status'=>$new,'stats'=>$stats]);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update quorum workflow.');
}
