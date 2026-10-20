<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requireRole([ROLE_ADMIN,ROLE_STAFF]);
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$action=clean($_POST['action']??'');
$notes=trim((string)($_POST['notes']??''));

try{
 $pdo->beginTransaction();
 $q=$pdo->prepare(
  'SELECT t.*,ve.session_id
   FROM vqd_vote_tallies t
   JOIN vqd_voting_events ve ON ve.id=t.voting_event_id
   WHERE t.id=:id FOR UPDATE'
 );
 $q->execute([':id'=>$id]);$t=$q->fetch();
 if(!$t){$pdo->rollBack();jsonResponse(false,'Tally not found.');}
 $old=$t['tally_status'];

 if($action==='review'){
   if(!in_array($old,['Computed','Recounted'],true)){$pdo->rollBack();jsonResponse(false,'Only computed/recounted tallies can move to review.');}
   $new='Under Review';
   $pdo->prepare('UPDATE vqd_vote_tallies SET tally_status=:status,reviewer_id=:user,audit_findings=CONCAT(COALESCE(audit_findings,""),:notes),updated_at=NOW() WHERE id=:id')
       ->execute([':status'=>$new,':user'=>currentUserId(),':notes'=>$notes!==''?" Review note: {$notes}":'',':id'=>$id]);
 }elseif($action==='certify'){
   if(!in_array($old,['Computed','Under Review','Recounted'],true)){$pdo->rollBack();jsonResponse(false,'This tally is not ready for certification.');}
   if($t['discrepancy_classification']==='Critical'){$pdo->rollBack();jsonResponse(false,'Critical tally discrepancies must be corrected before certification.');}
   $ch=$pdo->prepare("SELECT COUNT(*) FROM vqd_ballot_challenges WHERE voting_event_id=:event AND status='Open'");
   $ch->execute([':event'=>$t['voting_event_id']]);
   if((int)$ch->fetchColumn()>0){$pdo->rollBack();jsonResponse(false,'Resolve all open ballot challenges before certification.');}
   $new='Certified';
   $pdo->prepare(
    'UPDATE vqd_vote_tallies
     SET tally_status="Certified",
         certified_by=:certified_user,
         certified_at=NOW(),
         decision_handoff="Ready",
         reviewer_id=COALESCE(reviewer_id,:reviewer_user),
         updated_at=NOW()
     WHERE id=:id'
   )->execute([
    ':certified_user'=>currentUserId(),
    ':reviewer_user'=>currentUserId(),
    ':id'=>$id
   ]);

   if($t['session_id']){
     $pdo->prepare('UPDATE vqd_sessions SET status="Completed",closed_at=COALESCE(closed_at,NOW()),updated_at=NOW() WHERE id=:id')
         ->execute([':id'=>$t['session_id']]);
   }
 }else{
   $pdo->rollBack();jsonResponse(false,'Invalid tally workflow action.');
 }

 vqdTallyHistory($pdo,$id,ucfirst($action),$old,$new,$notes?:("Tally status {$old} -> {$new}."));
 vqdLogActivity(currentUserId(),'Tally '.ucfirst($action),"{$t['reference_number']} {$old} -> {$new}.");

 $pdo->commit();
 jsonResponse(true,"Tally is now {$new}.",['status'=>$new]);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update tally workflow.');
}
