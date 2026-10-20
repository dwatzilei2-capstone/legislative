<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

requirePermission('vqd.voting.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$voteId=(int)($_POST['vote_id']??0);
$reason=trim((string)($_POST['reason']??''));
$action=clean($_POST['action']??'raise');
$resolution=trim((string)($_POST['resolution']??''));

$q=$pdo->prepare('SELECT * FROM vqd_votes WHERE id=:id');$q->execute([':id'=>$voteId]);$vote=$q->fetch();
if(!$vote)jsonResponse(false,'Ballot not found.');

try{
 $pdo->beginTransaction();

 if($action==='raise'){
   if($reason===''){$pdo->rollBack();jsonResponse(false,'Challenge reason is required.');}
   $open=$pdo->prepare("SELECT COUNT(*) FROM vqd_ballot_challenges WHERE vote_id=:id AND status='Open'");$open->execute([':id'=>$voteId]);
   if((int)$open->fetchColumn()>0){$pdo->rollBack();jsonResponse(false,'This ballot already has an open challenge.');}
   $pdo->prepare(
    'INSERT INTO vqd_ballot_challenges
     (voting_event_id,vote_id,challenge_type,reason,status,raised_by,raised_at)
     VALUES(:event,:vote,"Ballot Review",:reason,"Open",:user,NOW())'
   )->execute([':event'=>$vote['voting_event_id'],':vote'=>$voteId,':reason'=>$reason,':user'=>currentUserId()]);
   $message='Ballot challenge opened.';
 }elseif(in_array($action,['validate','invalidate'],true)){
   $challenge=$pdo->prepare("SELECT * FROM vqd_ballot_challenges WHERE vote_id=:id AND status='Open' ORDER BY id DESC LIMIT 1");
   $challenge->execute([':id'=>$voteId]);$c=$challenge->fetch();
   if(!$c){$pdo->rollBack();jsonResponse(false,'No open challenge exists for this ballot.');}
   if($resolution===''){$pdo->rollBack();jsonResponse(false,'Challenge resolution is required.');}
   $status=$action==='validate'?'Resolved - Valid':'Resolved - Invalid';
   $pdo->prepare(
    'UPDATE vqd_ballot_challenges
     SET status=:status,resolution=:resolution,resolved_by=:user,resolved_at=NOW()
     WHERE id=:id'
   )->execute([':status'=>$status,':resolution'=>$resolution,':user'=>currentUserId(),':id'=>$c['id']]);
   if($action==='invalidate'){
     $pdo->prepare('UPDATE vqd_votes SET is_valid=0,invalid_reason=:reason,updated_at=NOW() WHERE id=:id')
         ->execute([':reason'=>$resolution,':id'=>$voteId]);
   }
   $message='Ballot challenge resolved.';
 }else{
   $pdo->rollBack();jsonResponse(false,'Invalid challenge action.');
 }

 vqdLogActivity(currentUserId(),'Ballot Challenge',"Vote #{$voteId}: {$action}.");
 $pdo->commit();
 jsonResponse(true,$message);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to process ballot challenge.');
}
