<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';

requirePermission('orlms.enactments.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$itemId=(int)($_POST['legislative_item_id']??0);
$mode=clean($_POST['mode']??'save');
$item=orlmsApprovalItem($pdo,$itemId);

if(!$item)jsonResponse(false,'Legislative item not found.');
if(!in_array($item['current_status'],['Approved','Enacted'],true)){
    jsonResponse(false,'The legislative item must be Approved before enactment.');
}

$existing=orlmsGetEnactment($pdo,$itemId);

if($mode==='save'){
    $official=clean($_POST['official_number']??'');
    $type=clean($_POST['enactment_type']??($item['item_type_code']==='resolution'?'Resolution Adoption':'Ordinance Enactment'));
    $approvedDate=clean($_POST['approved_date']??'')?:null;
    $signedDate=clean($_POST['signed_date']??'')?:null;
    $enactmentDate=clean($_POST['enactment_date']??'')?:null;
    $authority=clean($_POST['signing_authority']??'');
    $effectivityType=clean($_POST['effectivity_type']??'Upon Approval');
    $effectivityDate=clean($_POST['effectivity_date']??'')?:null;
    $remarks=trim((string)($_POST['remarks']??''));

    if($official==='')jsonResponse(false,'Official ordinance/resolution number is required.');
    if($enactmentDate==='')jsonResponse(false,'Enactment/adoption date is required.');

    $dup=$pdo->prepare(
        'SELECT id FROM orlms_enactments
         WHERE official_number=:number
           AND legislative_item_id<>:item
         LIMIT 1'
    );
    $dup->execute([':number'=>$official,':item'=>$itemId]);
    if($dup->fetch())jsonResponse(false,'That official number is already assigned to another legislative item.');

    if($existing && $existing['enactment_status']==='Finalized'){
        jsonResponse(false,'Finalized enactment records are locked.');
    }

    try{
        $pdo->beginTransaction();

        if($existing){
            $pdo->prepare(
                'UPDATE orlms_enactments
                 SET official_number=:official,enactment_type=:type,approved_date=:approved,
                     signed_date=:signed,enactment_date=:enactment,
                     signing_authority=:authority,effectivity_type=:effectivity_type,
                     effectivity_date=:effectivity_date,remarks=:remarks,updated_at=NOW()
                 WHERE id=:id'
            )->execute([
                ':official'=>$official,':type'=>$type,':approved'=>$approvedDate,
                ':signed'=>$signedDate,':enactment'=>$enactmentDate,
                ':authority'=>$authority?:null,':effectivity_type'=>$effectivityType?:null,
                ':effectivity_date'=>$effectivityDate,':remarks'=>$remarks?:null,
                ':id'=>$existing['id']
            ]);
            $enactmentId=(int)$existing['id'];
            orlmsEnactmentHistory($pdo,$enactmentId,$itemId,'Update',
                $existing['enactment_status'],$existing['enactment_status'],'Enactment details updated.');
            $message='Enactment record updated.';
        }else{
            $ref=orlmsGenerateReference($pdo,'orlms_enactments','enactment_reference','ENA',$enactmentDate);
            $pdo->prepare(
                'INSERT INTO orlms_enactments
                 (legislative_item_id,enactment_reference,official_number,enactment_type,
                  enactment_status,approved_date,signed_date,enactment_date,signing_authority,
                  effectivity_type,effectivity_date,remarks,recorded_by,finalized_by,
                  finalized_at,created_at,updated_at)
                 VALUES(:item,:ref,:official,:type,"Draft",:approved,:signed,:enactment,
                  :authority,:effectivity_type,:effectivity_date,:remarks,:user,NULL,NULL,NOW(),NOW())'
            )->execute([
                ':item'=>$itemId,':ref'=>$ref,':official'=>$official,':type'=>$type,
                ':approved'=>$approvedDate,':signed'=>$signedDate,':enactment'=>$enactmentDate,
                ':authority'=>$authority?:null,':effectivity_type'=>$effectivityType?:null,
                ':effectivity_date'=>$effectivityDate,':remarks'=>$remarks?:null,
                ':user'=>currentUserId()
            ]);
            $enactmentId=(int)$pdo->lastInsertId();
            orlmsEnactmentHistory($pdo,$enactmentId,$itemId,'Create',null,'Draft',
                "Enactment record {$ref} created.");
            $message='Enactment record created.';
        }

        logActivity(currentUserId(),'ORLMS Enactment Save',
            "{$item['reference_number']} · enactment #{$enactmentId}.");
        $pdo->commit();
        jsonResponse(true,$message,['id'=>$enactmentId]);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save enactment.');
    }
}

if($mode==='finalize'){
    if(!$existing)jsonResponse(false,'Create the enactment record first.');
    if($existing['enactment_status']==='Finalized')jsonResponse(false,'Enactment is already finalized.');
    if(!$existing['official_number']||!$existing['enactment_date']){
        jsonResponse(false,'Official number and enactment/adoption date are required.');
    }

    try{
        $pdo->beginTransaction();

        $pdo->prepare(
            'UPDATE orlms_enactments
             SET enactment_status="Finalized",finalized_by=:user,finalized_at=NOW(),updated_at=NOW()
             WHERE id=:id'
        )->execute([':user'=>currentUserId(),':id'=>$existing['id']]);

        $old=$item['current_status'];$new='Enacted';
        $pdo->prepare('UPDATE legislative_items SET current_status=:status,updated_at=NOW() WHERE id=:id')
            ->execute([':status'=>$new,':id'=>$itemId]);
        $pdo->prepare('UPDATE orlms_item_details SET draft_status=:status,updated_at=NOW() WHERE legislative_item_id=:id')
            ->execute([':status'=>$new,':id'=>$itemId]);

        orlmsEnactmentHistory($pdo,(int)$existing['id'],$itemId,'Finalize',
            $existing['enactment_status'],'Finalized',
            "Official number {$existing['official_number']} finalized.");
        orlmsAddStatusHistory($pdo,$itemId,$old,$new,
            "Enactment finalized as {$existing['official_number']}.");
        orlmsAddWorkflowHistory($pdo,$itemId,'ENACTMENT','Finalize Enactment',
            $old,$new,"Official number {$existing['official_number']}.");

        logActivity(currentUserId(),'ORLMS Enactment Finalized',
            "{$item['reference_number']} -> {$existing['official_number']}.");
        $pdo->commit();
        jsonResponse(true,'Enactment finalized. The item is ready for publication.',['status'=>$new]);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to finalize enactment.');
    }
}

jsonResponse(false,'Unsupported enactment mode.');
