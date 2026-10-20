<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.validation.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$decisionId=(int)($_POST['decision_id']??0);
$reviewType=clean($_POST['review_type']??'Standard Validation');
$reason=trim((string)($_POST['validation_reason']??''));
$office=(int)($_POST['requesting_office_id']??0)?:null;
$lead=(int)($_POST['lead_validator_id']??0)?:currentUserId();
$release=clean($_POST['release_classification']??'Internal');

if($decisionId<=0)jsonResponse(false,'Select a certified decision.');
if(!in_array($release,['Public','Internal','Restricted'],true))jsonResponse(false,'Invalid release classification.');

$q=$pdo->prepare(
 "SELECT d.*,t.reference_number tally_reference,t.tally_status,
         ve.reference_number voting_reference,ve.id voting_event_id,
         qc.reference_number quorum_reference,qc.id quorum_check_id,
         li.reference_number legislative_reference
  FROM vqd_decisions d
  LEFT JOIN vqd_vote_tallies t ON t.id=d.tally_id
  LEFT JOIN vqd_voting_events ve ON ve.id=d.voting_event_id
  LEFT JOIN vqd_quorum_checks qc ON qc.id=ve.quorum_check_id
  LEFT JOIN legislative_items li ON li.id=d.legislative_item_id
  WHERE d.id=:id"
);
$q->execute([':id'=>$decisionId]);$d=$q->fetch();

if(!$d)jsonResponse(false,'Decision not found.');
if(!in_array($d['record_status'],['Certified','Published'],true)){
    jsonResponse(false,'Decision must be Certified or Published before validation.');
}

$open=$pdo->prepare(
 "SELECT id,validation_reference
  FROM vqd_result_validations
  WHERE decision_id=:id
    AND validation_status NOT IN ('Certified','Failed')
  ORDER BY id DESC LIMIT 1"
);
$open->execute([':id'=>$decisionId]);
if($existing=$open->fetch()){
    jsonResponse(false,'An active validation already exists: '.$existing['validation_reference'].'.');
}

try{
    $pdo->beginTransaction();

    $ref=vqdGenerateReference($pdo,'vqd_result_validations','validation_reference','VAL');
    $pdo->prepare(
      'INSERT INTO vqd_result_validations
       (legislative_item_id,quorum_check_id,voting_event_id,tally_id,decision_id,
        validation_reference,review_type,validation_reason,requesting_office_id,
        lead_validator_id,reviewed_by_id,validation_datetime,quorum_reference,
        voting_reference,tally_reference,decision_reference,integrity_score,
        risk_level,challenge_status,validation_outcome,overall_findings,
        corrective_action,management_response,recommendation,revalidation_required,
        executive_summary,certification_statement,release_classification,
        report_type,report_format,validation_status,certified_by,certified_at,
        created_by,created_at,updated_at)
       VALUES(:item,:quorum,:vote,:tally,:decision,:ref,:type,:reason,:office,
        :lead,NULL,NOW(),:quorum_ref,:vote_ref,:tally_ref,:decision_ref,NULL,
        "Low","None","Pending",NULL,NULL,NULL,NULL,0,NULL,NULL,:release,
        "Validation Report","PDF","Draft",NULL,NULL,:user,NOW(),NOW())'
    )->execute([
      ':item'=>$d['legislative_item_id'],':quorum'=>$d['quorum_check_id'],
      ':vote'=>$d['voting_event_id'],':tally'=>$d['tally_id'],':decision'=>$decisionId,
      ':ref'=>$ref,':type'=>$reviewType,':reason'=>$reason?:null,':office'=>$office,
      ':lead'=>$lead,':quorum_ref'=>$d['quorum_reference'],
      ':vote_ref'=>$d['voting_reference'],':tally_ref'=>$d['tally_reference'],
      ':decision_ref'=>$d['reference_number'],':release'=>$release,':user'=>currentUserId()
    ]);
    $id=(int)$pdo->lastInsertId();

    $insert=$pdo->prepare(
      'INSERT INTO vqd_validation_checks
       (validation_id,check_code,check_name,check_status,check_note,
        sequence_number,reviewed_by,reviewed_at)
       VALUES(:validation,:code,:name,"Pending",NULL,:seq,NULL,NULL)'
    );
    foreach(vqdValidationDefaultChecks() as $i=>$check){
        $insert->execute([
          ':validation'=>$id,':code'=>$check[0],':name'=>$check[1],':seq'=>$i+1
        ]);
    }

    $stats=vqdRefreshValidation($pdo,$id);
    vqdValidationHistory($pdo,$id,'Create',null,'Draft',
      "Validation {$ref} created from decision {$d['reference_number']}; integrity={$stats['score']}.");
    vqdLogActivity(currentUserId(),'Create Result Validation',
      "{$ref} · decision={$d['reference_number']} · score={$stats['score']}.");

    $pdo->commit();
    jsonResponse(true,'Validation record created.',['id'=>$id]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('[VQDSS Validation Create] '.$e->getMessage());
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to create validation.');
}
