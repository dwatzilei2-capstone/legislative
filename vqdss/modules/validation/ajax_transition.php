<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.validation.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$action=clean($_POST['action']??'');
$findings=trim((string)($_POST['overall_findings']??''));
$corrective=trim((string)($_POST['corrective_action']??''));
$response=trim((string)($_POST['management_response']??''));
$recommendation=trim((string)($_POST['recommendation']??''));
$executive=trim((string)($_POST['executive_summary']??''));
$certification=trim((string)($_POST['certification_statement']??''));

try{
    $pdo->beginTransaction();

    $q=$pdo->prepare('SELECT * FROM vqd_result_validations WHERE id=:id FOR UPDATE');
    $q->execute([':id'=>$id]);$v=$q->fetch();
    if(!$v){$pdo->rollBack();jsonResponse(false,'Validation record not found.');}

    $stats=vqdRefreshValidation($pdo,$id);
    $old=$v['validation_status'];

    $common=[
      ':findings'=>$findings?:$v['overall_findings'],
      ':corrective'=>$corrective?:$v['corrective_action'],
      ':response'=>$response?:$v['management_response'],
      ':recommendation'=>$recommendation?:$v['recommendation'],
      ':executive'=>$executive?:$v['executive_summary'],
      ':certification'=>$certification?:$v['certification_statement'],
      ':id'=>$id
    ];

    if($action==='review'){
        if(!in_array($old,['Draft','Reopened'],true)){$pdo->rollBack();jsonResponse(false,'Validation is not ready for review.');}
        $new='In Review';
        $pdo->prepare(
          'UPDATE vqd_result_validations
           SET validation_status="In Review",reviewed_by_id=:user,
               overall_findings=:findings,corrective_action=:corrective,
               management_response=:response,recommendation=:recommendation,
               executive_summary=:executive,certification_statement=:certification,
               validation_datetime=NOW(),updated_at=NOW()
           WHERE id=:id'
        )->execute(array_merge($common,[':user'=>currentUserId()]));
    }elseif($action==='validate'){
        if(!in_array($old,['Draft','In Review','Reopened'],true)){$pdo->rollBack();jsonResponse(false,'Validation is not ready for validation.');}
        if($stats['pending']>0){$pdo->rollBack();jsonResponse(false,'All validation checks must be completed before validation.');}
        $new=$stats['outcome']==='Failed'?'Failed':'Validated';
        $pdo->prepare(
          'UPDATE vqd_result_validations
           SET validation_status=:status,validation_outcome=:outcome,
               reviewed_by_id=:user,overall_findings=:findings,
               corrective_action=:corrective,management_response=:response,
               recommendation=:recommendation,executive_summary=:executive,
               certification_statement=:certification,
               revalidation_required=:revalidate,validation_datetime=NOW(),
               updated_at=NOW()
           WHERE id=:id'
        )->execute(array_merge($common,[
          ':status'=>$new,':outcome'=>$stats['outcome'],':user'=>currentUserId(),
          ':revalidate'=>$stats['outcome']==='Failed'?1:0
        ]));
    }elseif($action==='certify'){
        if(!vqdHasPermission('vqd.validation.certify')){$pdo->rollBack();jsonResponse(false,'You do not have permission to certify validation results.');}
        if(!in_array($old,['Validated','In Review'],true)){$pdo->rollBack();jsonResponse(false,'Only reviewed/validated results can be certified.');}
        if($stats['failed']>0||$stats['pending']>0){$pdo->rollBack();jsonResponse(false,'Failed or pending integrity checks must be resolved before certification.');}
        if($stats['open_critical']>0||$stats['open_high']>0){$pdo->rollBack();jsonResponse(false,'Resolve all open Critical/High findings before certification.');}
        if($stats['open_challenges']>0){$pdo->rollBack();jsonResponse(false,'Open ballot challenges prevent certification.');}
        $new='Certified';
        $pdo->prepare(
          'UPDATE vqd_result_validations
           SET validation_status="Certified",validation_outcome=:outcome,
               certified_by=:user,certified_at=NOW(),
               overall_findings=:findings,corrective_action=:corrective,
               management_response=:response,recommendation=:recommendation,
               executive_summary=:executive,certification_statement=:certification,
               revalidation_required=0,updated_at=NOW()
           WHERE id=:id'
        )->execute(array_merge($common,[
          ':outcome'=>$stats['outcome'],':user'=>currentUserId()
        ]));
    }elseif($action==='reopen'){
        if(!in_array($old,['Certified','Failed'],true)){$pdo->rollBack();jsonResponse(false,'Only Certified/Failed validations can be reopened.');}
        if($recommendation===''){$pdo->rollBack();jsonResponse(false,'Provide the reason/recommendation for revalidation.');}
        $new='Reopened';
        $pdo->prepare(
          'UPDATE vqd_result_validations
           SET validation_status="Reopened",revalidation_required=1,
               recommendation=:recommendation,updated_at=NOW()
           WHERE id=:id'
        )->execute([':recommendation'=>$recommendation,':id'=>$id]);
    }else{
        $pdo->rollBack();jsonResponse(false,'Invalid validation workflow action.');
    }

    vqdValidationHistory($pdo,$id,ucfirst($action),$old,$new,
      "Validation {$old} -> {$new}; integrity={$stats['score']}%; risk={$stats['risk']}; outcome={$stats['outcome']}.");
    vqdLogActivity(currentUserId(),'Validation '.ucfirst($action),
      "{$v['validation_reference']} {$old} -> {$new}.");

    $pdo->commit();
    jsonResponse(true,"Validation is now {$new}.",['status'=>$new,'stats'=>$stats]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update validation workflow.');
}
