<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.voting.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$checkId=(int)($_POST['quorum_check_id']??0);
$motion=trim((string)($_POST['motion_text']??''));
$procedural=trim((string)($_POST['procedural_basis']??''));
$method=clean($_POST['voting_method']??'Manual');
$visibility=clean($_POST['vote_visibility']??'Recorded');
$threshold=clean($_POST['decision_threshold']??'Simple Majority');
$requiredPercentage=$_POST['required_percentage']!==''?(float)($_POST['required_percentage']??0):null;
$fixed=$_POST['fixed_required_votes']!==''?(int)($_POST['fixed_required_votes']??0):null;
$abstentionRule=clean($_POST['abstention_rule']??'Allowed');
$tieRule=clean($_POST['tie_rule']??'Presiding Officer Decides');
$reauth=isset($_POST['reauthentication_required'])?1:0;
$instructions=trim((string)($_POST['ballot_instructions']??''));
$optionLabels=$_POST['option_label']??[];
$optionCodes=$_POST['option_code']??[];
$optionAffirmative=$_POST['option_affirmative']??[];
$optionAbstain=$_POST['option_abstain']??[];

if(!is_array($optionLabels))$optionLabels=[$optionLabels];
if($checkId<=0)jsonResponse(false,'Select a certified quorum check.');
if($motion==='')jsonResponse(false,'Motion text is required.');
if(!in_array($method,['Manual','Electronic','Hybrid'],true))jsonResponse(false,'Invalid voting method.');
if(!in_array($visibility,['Recorded','Secret'],true))jsonResponse(false,'Invalid vote visibility.');

$thresholds=['Simple Majority','Majority of Eligible Members','Two-Thirds','Three-Fourths','Unanimous','Custom Percentage','Fixed Count'];
if(!in_array($threshold,$thresholds,true))jsonResponse(false,'Invalid decision threshold.');

$q=$pdo->prepare(
 "SELECT qc.*,s.reference_number session_reference,s.committee_id,s.office_id,s.presiding_officer_id,
         s.session_date,s.status session_status
  FROM vqd_quorum_checks qc
  JOIN vqd_sessions s ON s.id=qc.session_id
  WHERE qc.id=:id"
);
$q->execute([':id'=>$checkId]);$check=$q->fetch();
if(!$check)jsonResponse(false,'Quorum check not found.');
if($check['verification_status']!=='Certified'||$check['quorum_result']!=='Met'){
 jsonResponse(false,'Voting requires a Certified quorum result of Met.');
}

$validOptions=[];
foreach($optionLabels as $i=>$label){
 $label=clean($label);
 if($label==='')continue;
 $validOptions[]=[
  'label'=>$label,
  'code'=>clean($optionCodes[$i]??'')?:strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/','',$label),0,20)),
  'affirmative'=>((string)($optionAffirmative[$i]??'0'))==='1'?1:0,
  'abstain'=>((string)($optionAbstain[$i]??'0'))==='1'?1:0,
 ];
}
if(count($validOptions)<2)jsonResponse(false,'Add at least two active vote options.');
if(array_sum(array_column($validOptions,'affirmative'))<1)jsonResponse(false,'Mark at least one option as affirmative.');

$existing=null;
if($id>0){
 $e=$pdo->prepare('SELECT * FROM vqd_voting_events WHERE id=:id');$e->execute([':id'=>$id]);$existing=$e->fetch();
 if(!$existing)jsonResponse(false,'Voting event not found.');
 if(!in_array($existing['status'],['Draft','Ready'],true))jsonResponse(false,'Voting configuration is locked after the event opens.');
}

try{
 $pdo->beginTransaction();

 if($id>0){
   $old=$existing['status'];
   $pdo->prepare(
    'UPDATE vqd_voting_events
     SET session_id=:session,quorum_check_id=:check,legislative_item_id=:item,
         committee_id=:committee,office_id=:office,motion_text=:motion,
         procedural_basis=:procedural,voting_method=:method,vote_visibility=:visibility,
         decision_threshold=:threshold,required_percentage=:percentage,
         fixed_required_votes=:fixed,abstention_rule=:abstain_rule,tie_rule=:tie_rule,
         voting_operator_id=:operator,presiding_officer_id=:presiding,
         session_date=:session_date,reauthentication_required=:reauth,
         ballot_instructions=:instructions,status="Ready",updated_at=NOW()
     WHERE id=:id'
   )->execute([
    ':session'=>$check['session_id'],':check'=>$checkId,':item'=>$check['legislative_item_id'],
    ':committee'=>$check['committee_id'],':office'=>$check['office_id'],':motion'=>$motion,
    ':procedural'=>$procedural?:null,':method'=>$method,':visibility'=>$visibility,
    ':threshold'=>$threshold,':percentage'=>$requiredPercentage,':fixed'=>$fixed,
    ':abstain_rule'=>$abstentionRule,':tie_rule'=>$tieRule,':operator'=>currentUserId(),
    ':presiding'=>$check['presiding_officer_id'],':session_date'=>$check['session_date'],
    ':reauth'=>$reauth,':instructions'=>$instructions?:null,':id'=>$id
   ]);
   vqdVotingHistory($pdo,$id,'Update',$old,'Ready','Voting configuration updated before opening.');
 }else{
   $ref=vqdGenerateReference($pdo,'vqd_voting_events','reference_number','VOT',$check['session_date']);
   $pdo->prepare(
    'INSERT INTO vqd_voting_events
     (session_id,quorum_check_id,legislative_item_id,committee_id,office_id,
      reference_number,motion_text,procedural_basis,voting_method,vote_visibility,
      decision_threshold,required_percentage,fixed_required_votes,abstention_rule,
      tie_rule,opening_authority_id,closing_authority_id,voting_operator_id,
      presiding_officer_id,session_date,reauthentication_required,
      ballot_instructions,status,created_by,created_at,updated_at)
     VALUES
     (:session,:check,:item,:committee,:office,:ref,:motion,:procedural,:method,
      :visibility,:threshold,:percentage,:fixed,:abstain_rule,:tie_rule,NULL,NULL,
      :operator,:presiding,:session_date,:reauth,:instructions,"Ready",:user,NOW(),NOW())'
   )->execute([
    ':session'=>$check['session_id'],':check'=>$checkId,':item'=>$check['legislative_item_id'],
    ':committee'=>$check['committee_id'],':office'=>$check['office_id'],':ref'=>$ref,
    ':motion'=>$motion,':procedural'=>$procedural?:null,':method'=>$method,
    ':visibility'=>$visibility,':threshold'=>$threshold,':percentage'=>$requiredPercentage,
    ':fixed'=>$fixed,':abstain_rule'=>$abstentionRule,':tie_rule'=>$tieRule,
    ':operator'=>currentUserId(),':presiding'=>$check['presiding_officer_id'],
    ':session_date'=>$check['session_date'],':reauth'=>$reauth,
    ':instructions'=>$instructions?:null,':user'=>currentUserId()
   ]);
   $id=(int)$pdo->lastInsertId();
   vqdVotingHistory($pdo,$id,'Create',null,'Ready','Voting event created from certified quorum.');
 }

 $pdo->prepare('DELETE FROM vqd_vote_options WHERE voting_event_id=:id')->execute([':id'=>$id]);
 $insert=$pdo->prepare(
  'INSERT INTO vqd_vote_options
   (voting_event_id,option_code,option_label,option_category,option_description,
    sequence_number,is_affirmative,is_abstention,is_active)
   VALUES(:event,:code,:label,:category,NULL,:seq,:affirmative,:abstain,1)'
 );
 foreach($validOptions as $i=>$o){
   $category=$o['abstain']?'Abstention':($o['affirmative']?'Affirmative':'Negative');
   $insert->execute([
    ':event'=>$id,':code'=>$o['code']?:('OPT'.($i+1)),':label'=>$o['label'],
    ':category'=>$category,':seq'=>$i+1,':affirmative'=>$o['affirmative'],':abstain'=>$o['abstain']
   ]);
 }

 $voters=vqdCopyCertifiedVoters($pdo,$id,$checkId);
 if($voters<=0)throw new RuntimeException('The certified quorum contains no present eligible voters.');

 $pdo->prepare('UPDATE vqd_sessions SET status="Voting",updated_at=NOW() WHERE id=:id')
     ->execute([':id'=>$check['session_id']]);

 vqdLogActivity(currentUserId(),$existing?'Update Voting Event':'Create Voting Event',
  "Voting event #{$id}; {$voters} eligible voter(s); method={$method}.");

 $pdo->commit();
 jsonResponse(true,$existing?'Voting event updated.':'Voting event created.',['id'=>$id]);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 error_log('[VQDSS Voting Save] '.$e->getMessage());
 jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save voting event.');
}
