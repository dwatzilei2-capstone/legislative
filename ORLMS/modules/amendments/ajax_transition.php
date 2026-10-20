<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_amendment_helpers.php';

requirePermission('orlms.amendments.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['amendment_id']??0);
$action=clean($_POST['action']??'');
$notes=trim((string)($_POST['notes']??''));
$effectiveDate=clean($_POST['effective_date']??'')?:null;

try{
    $pdo->beginTransaction();

    $q=$pdo->prepare('SELECT * FROM orlms_amendments WHERE id=:id FOR UPDATE');
    $q->execute([':id'=>$id]);$raw=$q->fetch();
    if(!$raw){$pdo->rollBack();jsonResponse(false,'Amendment not found.');}

    $a=orlmsGetAmendment($pdo,$id);
    if(!$a){$pdo->rollBack();jsonResponse(false,'Amendment source item not found.');}

    $old=$raw['status'];
    $itemId=(int)$raw['legislative_item_id'];

    if($action==='submit'){
        if(!in_array($old,['Draft','Revision Required'],true)){
            $pdo->rollBack();jsonResponse(false,'Only editable amendments can be submitted.');
        }

        $changes=$pdo->prepare('SELECT COUNT(*) FROM orlms_amendment_changes WHERE amendment_id=:id');
        $changes->execute([':id'=>$id]);
        $hasBody=trim((string)$raw['revised_body_text'])!=='';

        if((int)$changes->fetchColumn()===0 && !$hasBody){
            $pdo->rollBack();jsonResponse(false,'Add at least one change entry or provide the complete revised legislative text before submission.');
        }

        $current=orlmsEnsureBaselineRevision($pdo,$itemId);
        if((int)$raw['source_revision_version']!==(int)$current['version_number']){
            $pdo->rollBack();
            jsonResponse(false,'The source legislative text has changed since this amendment was created. Create a new amendment from the current revision.');
        }

        $new='Under Review';
        $pdo->prepare(
            'UPDATE orlms_amendments
             SET status=:status,proposed_by=COALESCE(proposed_by,:user),
                 proposed_at=COALESCE(proposed_at,NOW()),review_notes=NULL,
                 reviewed_by=NULL,reviewed_at=NULL,updated_at=NOW()
             WHERE id=:id'
        )->execute([':status'=>$new,':user'=>currentUserId(),':id'=>$id]);
        $detail=$notes?:'Amendment submitted for legislative review.';
    }elseif($action==='return'){
        if($old!=='Under Review'){$pdo->rollBack();jsonResponse(false,'Only amendments under review can be returned.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Return/revision notes are required.');}
        $new='Revision Required';
        $pdo->prepare(
            'UPDATE orlms_amendments
             SET status=:status,review_notes=:notes,reviewed_by=:user,
                 reviewed_at=NOW(),updated_at=NOW()
             WHERE id=:id'
        )->execute([':status'=>$new,':notes'=>$notes,':user'=>currentUserId(),':id'=>$id]);
        $detail='Returned for revision: '.$notes;
    }elseif($action==='approve'){
        if($old!=='Under Review'){$pdo->rollBack();jsonResponse(false,'Only amendments under review can be approved.');}

        $current=orlmsEnsureBaselineRevision($pdo,$itemId);
        if((int)$raw['source_revision_version']!==(int)$current['version_number']){
            $pdo->rollBack();
            jsonResponse(false,'The official source revision changed during review. Approval is blocked to prevent a stale amendment.');
        }

        if(trim((string)$raw['revised_body_text'])===''){
            $pdo->rollBack();jsonResponse(false,'Complete revised legislative text is required before approval.');
        }

        $new='Approved';
        $pdo->prepare(
            'UPDATE orlms_amendments
             SET status=:status_value,review_notes=COALESCE(review_notes,:review_notes),
                 reviewed_by=:reviewed_by,reviewed_at=COALESCE(reviewed_at,NOW()),
                 approved_by=:approved_by,approved_at=NOW(),approval_remarks=:approval_notes,
                 effective_date=COALESCE(:effective,effective_date),updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':status_value'=>$new,
            ':review_notes'=>$notes?:null,
            ':reviewed_by'=>currentUserId(),
            ':approved_by'=>currentUserId(),
            ':approval_notes'=>$notes?:null,
            ':effective'=>$effectiveDate,
            ':id'=>$id
        ]);
        $detail='Amendment approved.'.($notes?' '.$notes:'');
    }elseif($action==='reject'){
        if($old!=='Under Review'){$pdo->rollBack();jsonResponse(false,'Only amendments under review can be rejected.');}
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Rejection reason is required.');}
        $new='Rejected';
        $pdo->prepare(
            'UPDATE orlms_amendments
             SET status=:status,review_notes=:notes,reviewed_by=:user,
                 reviewed_at=NOW(),updated_at=NOW()
             WHERE id=:id'
        )->execute([':status'=>$new,':notes'=>$notes,':user'=>currentUserId(),':id'=>$id]);
        $detail='Amendment rejected: '.$notes;
    }elseif($action==='withdraw'){
        if(!in_array($old,['Draft','Revision Required','Under Review'],true)){
            $pdo->rollBack();jsonResponse(false,'This amendment can no longer be withdrawn.');
        }
        if($notes===''){$pdo->rollBack();jsonResponse(false,'Withdrawal reason is required.');}
        $new='Withdrawn';
        $pdo->prepare(
            'UPDATE orlms_amendments
             SET status=:status,review_notes=:notes,updated_at=NOW()
             WHERE id=:id'
        )->execute([':status'=>$new,':notes'=>$notes,':id'=>$id]);
        $detail='Amendment withdrawn: '.$notes;
    }elseif($action==='apply'){
        if($old!=='Approved'){$pdo->rollBack();jsonResponse(false,'Only Approved amendments can be applied to the official revision history.');}
        if(trim((string)$raw['revised_body_text'])===''){
            $pdo->rollBack();jsonResponse(false,'Approved amendment has no revised legislative text.');
        }

        $current=orlmsEnsureBaselineRevision($pdo,$itemId);
        if((int)$raw['source_revision_version']!==(int)$current['version_number']){
            $pdo->rollBack();
            jsonResponse(false,'The official source revision changed after this amendment was created. Applying this stale amendment is blocked.');
        }

        $next=(int)$current['version_number']+1;

        $pdo->prepare(
            'UPDATE orlms_revision_history
             SET is_current=0
             WHERE legislative_item_id=:item AND is_current=1'
        )->execute([':item'=>$itemId]);

        $summary='Applied '.$raw['amendment_reference'].' - '.$raw['title'];
        $pdo->prepare(
            'INSERT INTO orlms_revision_history
             (legislative_item_id,amendment_id,version_number,title_snapshot,
              body_snapshot,change_summary,is_current,created_by,created_at)
             VALUES(:item,:amendment,:version,:title,:body,:summary,1,:user,NOW())'
        )->execute([
            ':item'=>$itemId,':amendment'=>$id,':version'=>$next,
            ':title'=>$a['legislative_title'],':body'=>$raw['revised_body_text'],
            ':summary'=>$summary,':user'=>currentUserId()
        ]);

        $newItemStatus=$raw['amendment_type']==='Repeal'?'Repealed':'Amended';

        $pdo->prepare(
            'UPDATE orlms_item_details
             SET body_text=:body,draft_status=:status,last_edited_by=:user,updated_at=NOW()
             WHERE legislative_item_id=:item'
        )->execute([
            ':body'=>$raw['revised_body_text'],':status'=>$newItemStatus,
            ':user'=>currentUserId(),':item'=>$itemId
        ]);
        $pdo->prepare(
            'UPDATE legislative_items
             SET current_status=:status,updated_at=NOW()
             WHERE id=:item'
        )->execute([':status'=>$newItemStatus,':item'=>$itemId]);

        $new='Applied';
        $pdo->prepare(
            'UPDATE orlms_amendments
             SET status=:status,effective_date=COALESCE(:effective,effective_date,CURDATE()),
                 applied_by=:user,applied_at=NOW(),updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':status'=>$new,':effective'=>$effectiveDate,
            ':user'=>currentUserId(),':id'=>$id
        ]);

        orlmsAddStatusHistory($pdo,$itemId,$a['item_status'],$newItemStatus,
            "Amendment {$raw['amendment_reference']} applied as official revision v{$next}.");
        orlmsAddWorkflowHistory($pdo,$itemId,'AMENDMENT','Apply Amendment',
            $a['item_status'],$newItemStatus,
            "Official revision v{$next}; {$raw['amendment_reference']} applied.");

        $detail="Amendment applied as official revision v{$next}.";
    }else{
        $pdo->rollBack();jsonResponse(false,'Invalid amendment workflow action.');
    }

    orlmsAmendmentHistory($pdo,$id,$itemId,ucfirst($action),$old,$new,$detail);
    logActivity(currentUserId(),'ORLMS Amendment '.ucfirst($action),
        "{$raw['amendment_reference']} · {$old} -> {$new}.");

    $pdo->commit();
    jsonResponse(true,"Amendment is now {$new}.",['status'=>$new]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update amendment workflow.');
}
