<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_amendment_helpers.php';

if (!hasPermission('orlms.amendments.manage') && !(isLoggedIn() && in_array(currentRole(), ['Administrator', 'Legislative Staff'], true))) { requirePermission('orlms.amendments.manage'); }
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$itemId=(int)($_POST['legislative_item_id']??0);
$type=clean($_POST['amendment_type']??'Amendment');
$title=clean($_POST['title']??'');
$summary=trim((string)($_POST['summary']??''));
$rationale=trim((string)($_POST['rationale']??''));
$revisedBody=trim((string)($_POST['revised_body_text']??''));
$related=(int)($_POST['related_legislative_item_id']??0)?:null;
$relationship=clean($_POST['relationship_type']??'');
$effectiveDate=clean($_POST['effective_date']??'')?:null;

if($itemId<=0)jsonResponse(false,'Select the legislative item being amended.');
if($title==='')jsonResponse(false,'Amendment/revision title is required.');
if(!in_array($type,['Amendment','Revision','Technical Correction','Repeal','Superseding Measure'],true)){
    jsonResponse(false,'Invalid amendment type.');
}
if($related===$itemId)jsonResponse(false,'A legislative item cannot be related to itself.');

$item=orlmsApprovalItem($pdo,$itemId);
if(!$item)jsonResponse(false,'Legislative item not found.');

$enactment=orlmsGetEnactment($pdo,$itemId);
if(!$enactment || $enactment['enactment_status']!=='Finalized'){
    jsonResponse(false,'Amendment tracking requires a finalized enacted/adopted legislative item.');
}

$existing=null;
if($id>0){
    $existing=orlmsGetAmendment($pdo,$id);
    if(!$existing || (int)$existing['legislative_item_id']!==$itemId){
        jsonResponse(false,'Amendment record not found.');
    }
    if(!in_array($existing['status'],['Draft','Revision Required'],true)){
        jsonResponse(false,'Only Draft or Revision Required amendments can be edited.');
    }
}else{
    $active=$pdo->prepare(
        "SELECT amendment_reference
         FROM orlms_amendments
         WHERE legislative_item_id=:item
           AND status IN ('Draft','Under Review','Revision Required','Approved')
         ORDER BY id DESC LIMIT 1"
    );
    $active->execute([':item'=>$itemId]);
    $activeRef=$active->fetchColumn();
    if($activeRef){
        jsonResponse(false,'An active amendment already exists for this item: '.$activeRef.'. Complete or withdraw it first.');
    }
}

try{
    $pdo->beginTransaction();

    $baseline=orlmsEnsureBaselineRevision($pdo,$itemId);

    if($related){
        $q=$pdo->prepare(
            'SELECT COUNT(*) FROM legislative_items li
             JOIN legislative_item_types lit ON lit.id=li.item_type_id
             WHERE li.id=:id AND li.deleted_at IS NULL
               AND lit.code IN ("ordinance","resolution")'
        );
        $q->execute([':id'=>$related]);
        if((int)$q->fetchColumn()===0){
            $pdo->rollBack();jsonResponse(false,'Related legislative item is invalid.');
        }
    }

    if($existing){
        $pdo->prepare(
            'UPDATE orlms_amendments
             SET amendment_type=:type,related_legislative_item_id=:related,
                 relationship_type=:relationship,title=:title,summary=:summary,
                 rationale=:rationale,revised_body_text=:body,effective_date=:effective,
                 updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':type'=>$type,':related'=>$related,':relationship'=>$relationship?:null,
            ':title'=>$title,':summary'=>$summary?:null,':rationale'=>$rationale?:null,
            ':body'=>$revisedBody?:null,':effective'=>$effectiveDate,':id'=>$id
        ]);
        orlmsAmendmentHistory($pdo,$id,$itemId,'Update',
            $existing['status'],$existing['status'],'Amendment draft details updated.');
        $message='Amendment draft updated.';
    }else{
        $ref=orlmsGenerateReference($pdo,'orlms_amendments','amendment_reference','AMN');
        $pdo->prepare(
            'INSERT INTO orlms_amendments
             (legislative_item_id,source_revision_version,source_status,
              amendment_reference,amendment_type,related_legislative_item_id,
              relationship_type,title,summary,rationale,revised_body_text,status,
              review_notes,reviewed_by,reviewed_at,proposed_by,proposed_at,
              approved_by,approved_at,approval_remarks,effective_date,applied_by,
              applied_at,created_at,updated_at)
             VALUES(:item,:source_version,:source_status,:ref,:type,:related,
              :relationship,:title,:summary,:rationale,:body,"Draft",
              NULL,NULL,NULL,:proposed,NOW(),NULL,NULL,NULL,:effective,NULL,NULL,NOW(),NOW())'
        )->execute([
            ':item'=>$itemId,':source_version'=>$baseline['version_number'],
            ':source_status'=>$item['current_status'],':ref'=>$ref,':type'=>$type,
            ':related'=>$related,':relationship'=>$relationship?:null,':title'=>$title,
            ':summary'=>$summary?:null,':rationale'=>$rationale?:null,
            ':body'=>$revisedBody?:null,':proposed'=>currentUserId(),
            ':effective'=>$effectiveDate
        ]);
        $id=(int)$pdo->lastInsertId();

        orlmsAmendmentHistory($pdo,$id,$itemId,'Create',null,'Draft',
            "Amendment {$ref} created from official revision v{$baseline['version_number']}.");
        orlmsAddWorkflowHistory($pdo,$itemId,'AMENDMENT','Create Amendment',
            $item['current_status'],$item['current_status'],
            "Amendment {$ref} created from revision v{$baseline['version_number']}.");
        $message='Amendment draft created.';
    }

    logActivity(currentUserId(),'ORLMS Amendment Save',
        "{$item['reference_number']} · amendment #{$id}.");

    $pdo->commit();
    jsonResponse(true,$message,['id'=>$id]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save amendment.');
}
