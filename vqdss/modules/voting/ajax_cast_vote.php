<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.voting.cast');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$eventId=(int)($_POST['event_id']??0);
$optionId=(int)($_POST['option_id']??0);
$eventVoterId=(int)($_POST['event_voter_id']??0)?:null;
$password=(string)($_POST['reauth_password']??'');

$eventStmt=$pdo->prepare('SELECT * FROM vqd_voting_events WHERE id=:id');
$eventStmt->execute([':id'=>$eventId]);$event=$eventStmt->fetch();
if(!$event)jsonResponse(false,'Voting event not found.');
if($event['status']!=='Open')jsonResponse(false,'Voting is not currently open.');

$operator=isAdmin();
$channel=$operator?clean($_POST['cast_channel']??'Electronic'):'Electronic';

if($eventVoterId){
 if(!$operator)jsonResponse(false,'Only an administrator can record another member’s ballot.');
 $voterStmt=$pdo->prepare(
  'SELECT ev.*,u.full_name FROM vqd_voting_event_voters ev JOIN users u ON u.id=ev.user_id
   WHERE ev.id=:id AND ev.voting_event_id=:event'
 );
 $voterStmt->execute([':id'=>$eventVoterId,':event'=>$eventId]);
}else{
 $voterStmt=$pdo->prepare(
  'SELECT ev.*,u.full_name FROM vqd_voting_event_voters ev JOIN users u ON u.id=ev.user_id
   WHERE ev.user_id=:user AND ev.voting_event_id=:event'
 );
 $voterStmt->execute([':user'=>currentUserId(),':event'=>$eventId]);
}
$voter=$voterStmt->fetch();
if(!$voter)jsonResponse(false,'You are not listed as an eligible voter for this event.');
if($voter['voter_eligibility']!=='Eligible'||$voter['authorization_status']==='Revoked')jsonResponse(false,'This voter is not authorized to cast a ballot.');
if($voter['voted_at'])jsonResponse(false,'A ballot has already been recorded for this voter.');

$option=$pdo->prepare('SELECT * FROM vqd_vote_options WHERE id=:id AND voting_event_id=:event AND is_active=1');
$option->execute([':id'=>$optionId,':event'=>$eventId]);$opt=$option->fetch();
if(!$opt)jsonResponse(false,'Selected vote option is invalid.');

if((int)$event['reauthentication_required']===1&&!$operator){
 $u=$pdo->prepare('SELECT password FROM users WHERE id=:id');$u->execute([':id'=>currentUserId()]);
 if($password===''||!password_verify($password,(string)$u->fetchColumn()))jsonResponse(false,'Re-authentication failed. Enter your current account password.');
}

try{
 $pdo->beginTransaction();

 $lock=$pdo->prepare('SELECT * FROM vqd_voting_event_voters WHERE id=:id FOR UPDATE');
 $lock->execute([':id'=>$voter['id']]);$fresh=$lock->fetch();
 if(!$fresh||$fresh['voted_at']){$pdo->rollBack();jsonResponse(false,'A ballot has already been recorded for this voter.');}

 $ballot='BLL-'.date('Y').'-'.strtoupper(substr(bin2hex(random_bytes(7)),0,12));
 $isSecret=$event['vote_visibility']==='Secret'?1:0;

 $pdo->prepare(
  'INSERT INTO vqd_votes
   (voting_event_id,event_voter_id,user_id,option_id,vote_value,ballot_reference,
    cast_channel,is_secret,is_valid,invalid_reason,cast_by,cast_at,updated_at)
   VALUES(:event,:voter,:user,:option,:value,:ballot,:channel,:secret,1,NULL,:cast_by,NOW(),NOW())'
 )->execute([
  ':event'=>$eventId,':voter'=>$fresh['id'],
  ':user'=>$isSecret?null:$fresh['user_id'],
  ':option'=>$optionId,':value'=>$opt['option_label'],':ballot'=>$ballot,
  ':channel'=>$channel,':secret'=>$isSecret,':cast_by'=>$operator?currentUserId():$fresh['user_id']
 ]);

 $pdo->prepare('UPDATE vqd_voting_event_voters SET voted_at=NOW(),voting_channel=:channel,updated_at=NOW() WHERE id=:id')
     ->execute([':channel'=>$channel,':id'=>$fresh['id']]);

 vqdVotingHistory($pdo,$eventId,'Ballot Cast',$event['status'],$event['status'],
   $isSecret ? "Secret ballot {$ballot} recorded." : "Recorded ballot {$ballot} for {$voter['full_name']}.");
 vqdLogActivity(currentUserId(),'Cast Vote',"{$event['reference_number']} ballot={$ballot}; channel={$channel}; secret={$isSecret}.");

 $pdo->commit();
 jsonResponse(true,'Vote recorded successfully.',['ballot_reference'=>$ballot]);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 if(str_contains($e->getMessage(),'Duplicate'))jsonResponse(false,'A ballot has already been recorded for this voter.');
 jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to record vote.');
}
