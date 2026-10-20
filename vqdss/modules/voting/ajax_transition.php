<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.voting.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();
if(!isAdmin())jsonResponse(false,'Only an administrator can use voting controls.');

$pdo=db();$id=(int)($_POST['id']??0);$action=clean($_POST['action']??'');
$reason=trim((string)($_POST['reason']??''));

try{
 $pdo->beginTransaction();
 $q=$pdo->prepare(
  'SELECT ve.*,qc.verification_status,qc.quorum_result
   FROM vqd_voting_events ve
   LEFT JOIN vqd_quorum_checks qc ON qc.id=ve.quorum_check_id
   WHERE ve.id=:id FOR UPDATE'
 );
 $q->execute([':id'=>$id]);$event=$q->fetch();
 if(!$event){$pdo->rollBack();jsonResponse(false,'Voting event not found.');}

 $old=$event['status'];$new=$old;$sql='';$params=[':id'=>$id];

 if($action==='open'){
   if(!in_array($old,['Ready','Paused'],true)){$pdo->rollBack();jsonResponse(false,'Only Ready or Paused events can be opened.');}
   if($event['verification_status']!=='Certified'||$event['quorum_result']!=='Met'){$pdo->rollBack();jsonResponse(false,'Certified quorum is no longer valid.');}
   $v=$pdo->prepare("SELECT COUNT(*) FROM vqd_voting_event_voters WHERE voting_event_id=:id AND voter_eligibility='Eligible' AND authorization_status<>'Revoked'");
   $v->execute([':id'=>$id]);if((int)$v->fetchColumn()<=0){$pdo->rollBack();jsonResponse(false,'No eligible voters are available.');}
   $new='Open';$sql='UPDATE vqd_voting_events SET status="Open",opened_at=COALESCE(opened_at,NOW()),paused_at=NULL,opening_authority_id=:user,updated_at=NOW() WHERE id=:id';$params[':user']=currentUserId();
 }elseif($action==='pause'){
   if($old!=='Open'){$pdo->rollBack();jsonResponse(false,'Only an Open event can be paused.');}
   $new='Paused';$sql='UPDATE vqd_voting_events SET status="Paused",paused_at=NOW(),updated_at=NOW() WHERE id=:id';
 }elseif($action==='close'){
   if(!in_array($old,['Open','Paused'],true)){$pdo->rollBack();jsonResponse(false,'Only Open or Paused events can be closed.');}
   $new='Closed';$sql='UPDATE vqd_voting_events SET status="Closed",closed_at=NOW(),closing_authority_id=:user,updated_at=NOW() WHERE id=:id';$params[':user']=currentUserId();
 }elseif($action==='cancel'){
   if(in_array($old,['Closed','Cancelled'],true)){$pdo->rollBack();jsonResponse(false,'This event can no longer be cancelled.');}
   if($reason===''){$pdo->rollBack();jsonResponse(false,'Cancellation reason is required.');}
   $new='Cancelled';$sql='UPDATE vqd_voting_events SET status="Cancelled",cancelled_at=NOW(),cancellation_reason=:reason,updated_at=NOW() WHERE id=:id';$params[':reason']=$reason;
 }else{
   $pdo->rollBack();jsonResponse(false,'Invalid voting control action.');
 }

 $pdo->prepare($sql)->execute($params);
 vqdVotingHistory($pdo,$id,ucfirst($action),$old,$new,$reason?:("Voting status {$old} -> {$new}."));
 vqdLogActivity(currentUserId(),'Voting '.ucfirst($action),"{$event['reference_number']} {$old} -> {$new}.");

 $pdo->commit();
 jsonResponse(true,"Voting event is now {$new}.",['status'=>$new]);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update voting control.');
}
