<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.tally.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$tallyId=(int)($_POST['tally_id']??0);
$method=clean($_POST['recount_method']??'System Recount');
$reason=trim((string)($_POST['reason']??''));

if($reason==='')jsonResponse(false,'Recount reason is required.');

$q=$pdo->prepare('SELECT * FROM vqd_vote_tallies WHERE id=:id');
$q->execute([':id'=>$tallyId]);$tally=$q->fetch();
if(!$tally)jsonResponse(false,'Tally not found.');
if($tally['tally_status']==='Certified')jsonResponse(false,'Certified results cannot be recounted without reopening validation in a later workflow.');

try{
 $pdo->beginTransaction();

 $n=$pdo->prepare('SELECT COALESCE(MAX(recount_number),0)+1 FROM vqd_tally_recounts WHERE tally_id=:id');
 $n->execute([':id'=>$tallyId]);$number=(int)$n->fetchColumn();

 $previous=$tally['result_outcome'];
 $result=vqdComputeTally($pdo,(int)$tally['voting_event_id'],$tallyId);

 $difference=
   "Before: {$previous}; After: {$result['outcome']}. ".
   "Valid={$result['valid']}; Invalid={$result['invalid']}; ".
   "Affirmative={$result['affirmative']}; Negative={$result['negative']}; Abstain={$result['abstain']}.";

 $pdo->prepare(
  'INSERT INTO vqd_tally_recounts
   (tally_id,recount_number,recount_method,reason,previous_result,new_result,
    difference_summary,performed_by,performed_at,created_at)
   VALUES(:tally,:number,:method,:reason,:previous,:new,:summary,:user,NOW(),NOW())'
 )->execute([
  ':tally'=>$tallyId,':number'=>$number,':method'=>$method,':reason'=>$reason,
  ':previous'=>$previous,':new'=>$result['outcome'],':summary'=>$difference,
  ':user'=>currentUserId()
 ]);

 $pdo->prepare(
  'UPDATE vqd_vote_tallies
   SET recount_status="Completed",recount_method=:method,recount_notes=:notes,
       tally_status="Recounted",updated_at=NOW()
   WHERE id=:id'
 )->execute([':method'=>$method,':notes'=>$reason,':id'=>$tallyId]);

 vqdTallyHistory($pdo,$tallyId,'Recount',$tally['tally_status'],'Recounted',$difference);
 vqdLogActivity(currentUserId(),'Recount Vote Tally',"Tally {$tally['reference_number']} recount #{$number}.");

 $pdo->commit();
 jsonResponse(true,'Recount completed.',['tally_id'=>$tallyId,'outcome'=>$result['outcome']]);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to recount tally.');
}
