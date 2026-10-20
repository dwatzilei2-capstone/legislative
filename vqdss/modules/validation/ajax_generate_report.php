<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.validation.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['validation_id']??0);
$action=clean($_POST['action']??'generate');

$v=$pdo->prepare('SELECT * FROM vqd_result_validations WHERE id=:id');
$v->execute([':id'=>$id]);$validation=$v->fetch();
if(!$validation)jsonResponse(false,'Validation not found.');

try{
    $pdo->beginTransaction();

    if($action==='generate'){
        $n=$pdo->prepare(
          'SELECT COALESCE(MAX(version_number),0)+1
           FROM vqd_validation_reports
           WHERE validation_id=:id AND report_type=:type'
        );
        $n->execute([':id'=>$id,':type'=>$validation['report_type']]);
        $version=(int)$n->fetchColumn();

        $reportRef='VRP-'.date('Y').'-'.str_pad((string)$id,4,'0',STR_PAD_LEFT).'-V'.$version;

        $pdo->prepare(
          'INSERT INTO vqd_validation_reports
           (validation_id,report_type,report_format,version_number,report_reference,
            file_name,file_path,status,generated_by,generated_at,released_by,released_at,created_at)
           VALUES(:validation,:type,:format,:version,:ref,NULL,NULL,"Generated",
            :user,NOW(),NULL,NULL,NOW())'
        )->execute([
          ':validation'=>$id,':type'=>$validation['report_type'],
          ':format'=>$validation['report_format'],':version'=>$version,
          ':ref'=>$reportRef,':user'=>currentUserId()
        ]);

        vqdValidationHistory($pdo,$id,'Generate Report',
          $validation['validation_status'],$validation['validation_status'],
          "Generated validation report {$reportRef}.");
        $message='Validation report version generated.';
    }elseif($action==='release'){
        if($validation['validation_status']!=='Certified'){
            $pdo->rollBack();jsonResponse(false,'Only a Certified validation report can be released.');
        }
        $rid=(int)($_POST['report_id']??0);
        $r=$pdo->prepare('SELECT * FROM vqd_validation_reports WHERE id=:id AND validation_id=:validation');
        $r->execute([':id'=>$rid,':validation'=>$id]);$report=$r->fetch();
        if(!$report){$pdo->rollBack();jsonResponse(false,'Validation report version not found.');}

        $pdo->prepare(
          'UPDATE vqd_validation_reports
           SET status="Released",released_by=:user,released_at=NOW()
           WHERE id=:id'
        )->execute([':user'=>currentUserId(),':id'=>$rid]);

        vqdValidationHistory($pdo,$id,'Release Report',
          $validation['validation_status'],$validation['validation_status'],
          "Released validation report {$report['report_reference']}.");
        $message='Validation report released.';
    }else{
        $pdo->rollBack();jsonResponse(false,'Invalid report action.');
    }

    vqdLogActivity(currentUserId(),'Validation Report',
      "{$validation['validation_reference']} · {$action}.");
    $pdo->commit();
    jsonResponse(true,$message);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to process validation report.');
}
