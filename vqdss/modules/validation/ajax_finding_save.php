<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.validation.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$validationId=(int)($_POST['validation_id']??0);
$title=clean($_POST['finding_title']??'');
$description=trim((string)($_POST['finding_description']??''));
$severity=clean($_POST['severity']??'Low');
$office=(int)($_POST['responsible_office_id']??0)?:null;
$due=clean($_POST['due_date']??'')?:null;
$status=clean($_POST['status']??'Open');
$corrective=trim((string)($_POST['corrective_action']??''));
$resolution=trim((string)($_POST['resolution']??''));

if($validationId<=0||$title==='')jsonResponse(false,'Validation and finding title are required.');
if(!in_array($severity,['Low','Medium','High','Critical'],true))jsonResponse(false,'Invalid severity.');
if(!in_array($status,['Open','In Progress','Resolved','Accepted Risk'],true))jsonResponse(false,'Invalid finding status.');
if($status==='Resolved'&&$resolution==='')jsonResponse(false,'Resolution is required when closing a finding.');

$v=$pdo->prepare('SELECT validation_reference,validation_status FROM vqd_result_validations WHERE id=:id');
$v->execute([':id'=>$validationId]);$validation=$v->fetch();
if(!$validation)jsonResponse(false,'Validation not found.');
if($validation['validation_status']==='Certified')jsonResponse(false,'Certified validation findings are locked.');

$existing=null;
if($id>0){
    $q=$pdo->prepare('SELECT * FROM vqd_validation_findings WHERE id=:id AND validation_id=:validation');
    $q->execute([':id'=>$id,':validation'=>$validationId]);$existing=$q->fetch();
    if(!$existing)jsonResponse(false,'Finding not found.');
}

try{
    $pdo->beginTransaction();
    $resolvedBy=$status==='Resolved'?currentUserId():null;
    $resolvedAt=$status==='Resolved'?date('Y-m-d H:i:s'):null;

    if($existing){
        $pdo->prepare(
          'UPDATE vqd_validation_findings
           SET finding_title=:title,finding_description=:description,severity=:severity,
               responsible_office_id=:office,due_date=:due,status=:status,
               corrective_action=:corrective,resolution=:resolution,
               resolved_by=:resolved_by,resolved_at=:resolved_at,updated_at=NOW()
           WHERE id=:id'
        )->execute([
          ':title'=>$title,':description'=>$description?:null,':severity'=>$severity,
          ':office'=>$office,':due'=>$due,':status'=>$status,
          ':corrective'=>$corrective?:null,':resolution'=>$resolution?:null,
          ':resolved_by'=>$resolvedBy,':resolved_at'=>$resolvedAt,':id'=>$id
        ]);
        $message='Finding updated.';
    }else{
        $pdo->prepare(
          'INSERT INTO vqd_validation_findings
           (validation_id,finding_title,finding_description,severity,
            responsible_office_id,due_date,status,corrective_action,resolution,
            resolved_by,resolved_at,created_at,updated_at)
           VALUES(:validation,:title,:description,:severity,:office,:due,:status,
            :corrective,:resolution,:resolved_by,:resolved_at,NOW(),NOW())'
        )->execute([
          ':validation'=>$validationId,':title'=>$title,':description'=>$description?:null,
          ':severity'=>$severity,':office'=>$office,':due'=>$due,':status'=>$status,
          ':corrective'=>$corrective?:null,':resolution'=>$resolution?:null,
          ':resolved_by'=>$resolvedBy,':resolved_at'=>$resolvedAt
        ]);
        $id=(int)$pdo->lastInsertId();
        $message='Finding created.';
    }

    $stats=vqdRefreshValidation($pdo,$validationId);
    vqdValidationHistory($pdo,$validationId,'Finding',
      $validation['validation_status'],$validation['validation_status'],
      "{$message} {$title}; severity={$severity}; status={$status}; risk={$stats['risk']}.");
    vqdLogActivity(currentUserId(),'Validation Finding',
      "{$validation['validation_reference']} · {$title} · {$severity}.");

    $pdo->commit();
    jsonResponse(true,$message,['id'=>$id,'stats'=>$stats]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save validation finding.');
}
