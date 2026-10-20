<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.tally.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$eventId=(int)($_POST['voting_event_id']??0);

$existing=$pdo->prepare('SELECT id,tally_status FROM vqd_vote_tallies WHERE voting_event_id=:id ORDER BY id DESC LIMIT 1');
$existing->execute([':id'=>$eventId]);
$tally=$existing->fetch();

if($tally&&$tally['tally_status']==='Certified'){
 jsonResponse(false,'A certified tally is locked. Start the Decision Recording workflow or perform a controlled recount through the tally detail page.');
}

try{
 $pdo->beginTransaction();
 $result=vqdComputeTally($pdo,$eventId,$tally?(int)$tally['id']:null);
 $old=$tally?$tally['tally_status']:null;
 vqdTallyHistory($pdo,(int)$result['tally_id'],'Compute',$old,'Computed',
   "System tally computed. Outcome={$result['outcome']}; discrepancy={$result['discrepancy']}.");
 vqdLogActivity(currentUserId(),'Compute Vote Tally',
   "Voting event #{$eventId}; tally #{$result['tally_id']}; outcome={$result['outcome']}.");
 $pdo->commit();
 jsonResponse(true,'Vote tally computed successfully.',$result);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to compute vote tally.');
}
