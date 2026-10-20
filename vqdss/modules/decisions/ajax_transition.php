<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.decisions.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$action=clean($_POST['action']??'');
$notes=trim((string)($_POST['notes']??''));

try{
    $pdo->beginTransaction();

    $q=$pdo->prepare('SELECT * FROM vqd_decisions WHERE id=:id FOR UPDATE');
    $q->execute([':id'=>$id]);$d=$q->fetch();
    if(!$d){$pdo->rollBack();jsonResponse(false,'Decision record not found.');}

    $old=$d['record_status'];

    if($action==='record'){
        if(!vqdHasPermission('vqd.decisions.certify')){$pdo->rollBack();jsonResponse(false,'You do not have permission to formally record decisions.');}
        if($old!=='Draft'){$pdo->rollBack();jsonResponse(false,'Only Draft decisions can be formally recorded.');}
        $new='Recorded';
        $pdo->prepare(
          'UPDATE vqd_decisions SET record_status="Recorded",recorded_by=:user,
           decision_datetime=COALESCE(decision_datetime,NOW()),updated_at=NOW()
           WHERE id=:id'
        )->execute([':user'=>currentUserId(),':id'=>$id]);
    }elseif($action==='certify'){
        if(!vqdHasPermission('vqd.decisions.certify')){$pdo->rollBack();jsonResponse(false,'You do not have permission to certify decisions.');}
        if(!in_array($old,['Draft','Recorded'],true)){$pdo->rollBack();jsonResponse(false,'This decision is not ready for certification.');}
        if(trim((string)$d['decision_statement'])===''){$pdo->rollBack();jsonResponse(false,'Decision statement is required.');}
        $new='Certified';
        $pdo->prepare(
          'UPDATE vqd_decisions
           SET record_status="Certified",certified_by=:user,certified_at=NOW(),
               decision_datetime=COALESCE(decision_datetime,NOW()),updated_at=NOW()
           WHERE id=:id'
        )->execute([':user'=>currentUserId(),':id'=>$id]);

        if($d['tally_id']){
            $pdo->prepare(
              'UPDATE vqd_vote_tallies SET decision_handoff="Completed",updated_at=NOW()
               WHERE id=:id'
            )->execute([':id'=>$d['tally_id']]);
        }
    }elseif($action==='publish'){
        if(!vqdHasPermission('vqd.decisions.publish')){$pdo->rollBack();jsonResponse(false,'You do not have permission to publish decisions.');}
        if($old!=='Certified'){$pdo->rollBack();jsonResponse(false,'Only a Certified decision can be published.');}
        $new='Published';
        $pdo->prepare(
          'UPDATE vqd_decisions
           SET record_status="Published",release_classification="Public",
               published_by=:user,published_at=NOW(),
               updated_at=NOW()
           WHERE id=:id'
        )->execute([':user'=>currentUserId(),':id'=>$id]);
    }elseif($action==='cancel'){
        if(in_array($old,['Published','Cancelled'],true)){$pdo->rollBack();jsonResponse(false,'This decision cannot be cancelled.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Cancellation reason is required.');}
        $new='Cancelled';
        $pdo->prepare(
          'UPDATE vqd_decisions SET record_status="Cancelled",
           record_remarks=CONCAT(COALESCE(record_remarks,""),:note),updated_at=NOW()
           WHERE id=:id'
        )->execute([':note'=>"\nCancellation: ".$notes,':id'=>$id]);
    }else{
        $pdo->rollBack();jsonResponse(false,'Invalid decision workflow action.');
    }

    vqdDecisionHistory($pdo,$id,ucfirst($action),$old,$new,
      $notes?:("Decision status {$old} -> {$new}."));
    vqdLogActivity(currentUserId(),'Decision '.ucfirst($action),
      "{$d['reference_number']} {$old} -> {$new}.");

    $pdo->commit();
    jsonResponse(true,"Decision is now {$new}.",['status'=>$new]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update decision workflow.');
}
