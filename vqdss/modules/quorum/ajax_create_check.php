<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.quorum.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$sessionId=(int)($_POST['session_id']??0);
$rule=clean($_POST['quorum_rule']??'Majority');
$basis=clean($_POST['quorum_basis']??'Eligible Voting Members');
$custom=$_POST['custom_percentage']!==''?(float)($_POST['custom_percentage']??0):null;
$fixed=$_POST['fixed_required_count']!==''?(int)($_POST['fixed_required_count']??0):null;
$notes=trim((string)($_POST['certification_notes']??''));

$allowed=['Majority','Two-Thirds','Three-Fourths','Unanimous','Custom Percentage','Fixed Count'];
if(!in_array($rule,$allowed,true))jsonResponse(false,'Invalid quorum rule.');

$s=$pdo->prepare('SELECT * FROM vqd_sessions WHERE id=:id');
$s->execute([':id'=>$sessionId]);$session=$s->fetch();
if(!$session)jsonResponse(false,'Voting session not found.');

$count=$pdo->prepare('SELECT COUNT(*) FROM vqd_session_members WHERE session_id=:id');
$count->execute([':id'=>$sessionId]);
if((int)$count->fetchColumn()===0)jsonResponse(false,'The session has no official member roster.');

try{
 $pdo->beginTransaction();

 $ref=vqdGenerateReference($pdo,'vqd_quorum_checks','reference_number','QRM',$session['session_date']);
 $pdo->prepare(
  'INSERT INTO vqd_quorum_checks
   (session_id,legislative_item_id,reference_number,quorum_rule,quorum_basis,
    custom_percentage,fixed_required_count,quorum_result,verification_status,
    certification_notes,created_by,created_at,updated_at)
   VALUES(:session,:item,:ref,:rule,:basis,:custom,:fixed,"Pending","Draft",
    :notes,:user,NOW(),NOW())'
 )->execute([
  ':session'=>$sessionId,':item'=>$session['legislative_item_id'],':ref'=>$ref,
  ':rule'=>$rule,':basis'=>$basis,':custom'=>$custom,':fixed'=>$fixed,
  ':notes'=>$notes?:null,':user'=>currentUserId()
 ]);
 $checkId=(int)$pdo->lastInsertId();

 $members=$pdo->prepare('SELECT * FROM vqd_session_members WHERE session_id=:id ORDER BY id');
 $members->execute([':id'=>$sessionId]);
 $insert=$pdo->prepare(
  'INSERT INTO vqd_quorum_check_members
   (quorum_check_id,session_member_id,user_id,attendance_status,voting_eligibility,
    included_in_official_count,included_in_quorum_count,attendance_note,
    verified_by,verified_at,created_at,updated_at)
   VALUES(:check,:member,:user,:attendance,:eligibility,1,:include,NULL,NULL,NULL,NOW(),NOW())'
 );

 foreach($members->fetchAll() as $m){
   $insert->execute([
    ':check'=>$checkId,':member'=>$m['id'],':user'=>$m['user_id'],
    ':attendance'=>$m['attendance_status']?:'Pending',
    ':eligibility'=>$m['eligibility_status']?:'Eligible',
    ':include'=>(int)$m['is_voting_member']===1?1:0
   ]);
 }

 vqdRefreshQuorum($pdo,$checkId);
 vqdQuorumHistory($pdo,$checkId,'Create',null,'Draft','Quorum verification snapshot created from the official session roster.');

 $pdo->prepare('UPDATE vqd_sessions SET status="Quorum Verification",updated_at=NOW() WHERE id=:id')
     ->execute([':id'=>$sessionId]);
 vqdSessionHistory($pdo,$sessionId,'Quorum Verification',$session['status'],'Quorum Verification',"Created quorum check {$ref}.");

 vqdLogActivity(currentUserId(),'Create Quorum Check',"Created {$ref} for {$session['reference_number']}.");

 $pdo->commit();
 jsonResponse(true,'Quorum check created.',['id'=>$checkId,'reference_number'=>$ref]);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to create quorum check.');
}
