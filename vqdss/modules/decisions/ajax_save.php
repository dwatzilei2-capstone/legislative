<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.decisions.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$tallyId=(int)($_POST['tally_id']??0);
$statement=trim((string)($_POST['decision_statement']??''));
$classification=clean($_POST['decision_classification']??'Motion Result');
$movedBy=clean($_POST['moved_by']??'');
$secondedBy=clean($_POST['seconded_by']??'');
$legalBasis=trim((string)($_POST['legal_basis']??''));
$conditions=trim((string)($_POST['conditions']??''));
$effectivityType=clean($_POST['effectivity_type']??'Immediate');
$effectivityDate=clean($_POST['effectivity_date']??'')?:null;
$release='Public';
$implementation=trim((string)($_POST['implementation_notes']??''));
$publication=trim((string)($_POST['publication_notes']??''));
$remarks=trim((string)($_POST['record_remarks']??''));

if($tallyId<=0)jsonResponse(false,'Select a certified tally.');
if($statement==='')jsonResponse(false,'Decision statement is required.');

$q=$pdo->prepare(
 "SELECT t.*,ve.reference_number voting_reference,ve.motion_text,ve.legislative_item_id,
         ve.id voting_event_id,ve.session_date
  FROM vqd_vote_tallies t
  JOIN vqd_voting_events ve ON ve.id=t.voting_event_id
  WHERE t.id=:id"
);
$q->execute([':id'=>$tallyId]);$t=$q->fetch();

if(!$t)jsonResponse(false,'Certified tally not found.');
if($t['tally_status']!=='Certified'||$t['decision_handoff']!=='Ready'){
    if($id===0)jsonResponse(false,'The tally must be Certified with Decision Handoff = Ready.');
}

$existing=null;
if($id>0){
    $d=$pdo->prepare('SELECT * FROM vqd_decisions WHERE id=:id');
    $d->execute([':id'=>$id]);$existing=$d->fetch();
    if(!$existing)jsonResponse(false,'Decision record not found.');
    if(!in_array($existing['record_status'],['Draft','Recorded'],true)){
        jsonResponse(false,'Certified or published decisions are locked from direct editing.');
    }
}else{
    $dup=$pdo->prepare('SELECT id,reference_number FROM vqd_decisions WHERE tally_id=:id LIMIT 1');
    $dup->execute([':id'=>$tallyId]);
    if($row=$dup->fetch())jsonResponse(false,'This tally already has decision record '.$row['reference_number'].'.');
}

$outcome=$t['result_outcome'];

try{
    $pdo->beginTransaction();

    if($id>0){
        $old=$existing['record_status'];
        $pdo->prepare(
          'UPDATE vqd_decisions
           SET decision_classification=:classification,moved_by=:moved_by,
               seconded_by=:seconded_by,decision_statement=:statement,
               legal_basis=:legal_basis,conditions=:conditions,
               effectivity_type=:effectivity_type,effectivity_date=:effectivity_date,
               release_classification=:release,
               implementation_notes=:implementation,
               publication_notes=:publication,record_remarks=:remarks,
               updated_at=NOW()
           WHERE id=:id'
        )->execute([
          ':classification'=>$classification,':moved_by'=>$movedBy?:null,
          ':seconded_by'=>$secondedBy?:null,':statement'=>$statement,
          ':legal_basis'=>$legalBasis?:null,':conditions'=>$conditions?:null,
          ':effectivity_type'=>$effectivityType?:null,':effectivity_date'=>$effectivityDate,
          ':release'=>$release,':implementation'=>$implementation?:null,
          ':publication'=>$publication?:null,':remarks'=>$remarks?:null,':id'=>$id
        ]);
        vqdDecisionHistory($pdo,$id,'Update',$old,$old,'Decision record details updated.');
        $message='Decision record updated.';
    }else{
        $ref=vqdGenerateReference($pdo,'vqd_decisions','reference_number','DEC',$t['session_date']);
        $recordNo=vqdGenerateReference($pdo,'vqd_decisions','decision_record_number','DRN',$t['session_date']);

        $pdo->prepare(
          'INSERT INTO vqd_decisions
           (legislative_item_id,voting_event_id,tally_id,reference_number,
            decision_record_number,decision_source,source_reference,decision_datetime,
            official_outcome,decision_classification,required_votes,affirmative_votes,
            negative_votes,abstain_votes,moved_by,seconded_by,decision_statement,
            legal_basis,conditions,effectivity_type,effectivity_date,release_classification,
            implementation_notes,publication_notes,record_remarks,record_status,
            recorded_by,certified_by,certified_at,published_by,published_at,
            created_by,created_at,updated_at)
           VALUES
           (:item,:event,:tally,:ref,:record_no,"Vote Tally",:source,NOW(),
            :outcome,:classification,:required,:yes,:no,:abstain,:moved_by,:seconded_by,
            :statement,:legal_basis,:conditions,:effectivity_type,:effectivity_date,
            :release,:implementation,:publication,:remarks,"Draft",:recorded_by,
            NULL,NULL,NULL,NULL,:created_by,NOW(),NOW())'
        )->execute([
          ':item'=>$t['legislative_item_id'],':event'=>$t['voting_event_id'],
          ':tally'=>$tallyId,':ref'=>$ref,':record_no'=>$recordNo,
          ':source'=>$t['reference_number'],':outcome'=>$outcome,
          ':classification'=>$classification,':required'=>$t['required_votes'],
          ':yes'=>$t['affirmative_votes'],':no'=>$t['negative_votes'],
          ':abstain'=>$t['abstain_votes'],':moved_by'=>$movedBy?:null,
          ':seconded_by'=>$secondedBy?:null,':statement'=>$statement,
          ':legal_basis'=>$legalBasis?:null,':conditions'=>$conditions?:null,
          ':effectivity_type'=>$effectivityType?:null,':effectivity_date'=>$effectivityDate,
          ':release'=>$release,':implementation'=>$implementation?:null,
          ':publication'=>$publication?:null,':remarks'=>$remarks?:null,
          ':recorded_by'=>currentUserId(),':created_by'=>currentUserId()
        ]);
        $id=(int)$pdo->lastInsertId();

        $pdo->prepare(
          'UPDATE vqd_vote_tallies
           SET decision_handoff="In Progress",updated_at=NOW()
           WHERE id=:id'
        )->execute([':id'=>$tallyId]);

        vqdDecisionHistory($pdo,$id,'Create',null,'Draft',
          "Decision record created from certified tally {$t['reference_number']}.");
        $message='Decision record created.';
    }

    vqdLogActivity(currentUserId(),$existing?'Update Decision':'Create Decision',
      "Decision #{$id}; source tally={$t['reference_number']}; outcome={$outcome}.");

    $pdo->commit();
    jsonResponse(true,$message,['id'=>$id]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('[VQDSS Decision Save] '.$e->getMessage());
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save decision record.');
}
