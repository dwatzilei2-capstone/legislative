<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.validation.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['id']??0);
$status=clean($_POST['check_status']??'Pending');
$note=trim((string)($_POST['check_note']??''));

if(!in_array($status,['Pending','Passed','Warning','Failed','Not Applicable'],true))jsonResponse(false,'Invalid check status.');

$q=$pdo->prepare(
 'SELECT c.*,v.validation_status
  FROM vqd_validation_checks c
  JOIN vqd_result_validations v ON v.id=c.validation_id
  WHERE c.id=:id'
);
$q->execute([':id'=>$id]);$c=$q->fetch();
if(!$c)jsonResponse(false,'Validation check not found.');
if($c['validation_status']==='Certified')jsonResponse(false,'Certified validation checks are locked.');

$autoCodes=array_column(vqdValidationDefaultChecks(),0);
if(in_array($c['check_code'],$autoCodes,true)){
    jsonResponse(false,'This check is automatically calculated from operational VQDSS records.');
}

try{
    $pdo->beginTransaction();
    $pdo->prepare(
      'UPDATE vqd_validation_checks
       SET check_status=:status,check_note=:note,reviewed_by=:user,reviewed_at=NOW()
       WHERE id=:id'
    )->execute([
      ':status'=>$status,':note'=>$note?:null,':user'=>currentUserId(),':id'=>$id
    ]);

    $stats=vqdRefreshValidation($pdo,(int)$c['validation_id']);
    vqdValidationHistory($pdo,(int)$c['validation_id'],'Check Review',
      $c['validation_status'],$c['validation_status'],
      "Check {$c['check_name']} updated to {$status}; score={$stats['score']}.");

    $pdo->commit();
    jsonResponse(true,'Validation check updated.',['stats'=>$stats]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update validation check.');
}
