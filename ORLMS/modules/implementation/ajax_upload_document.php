<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';

requirePermission('orlms.implementation.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$planId=(int)($_POST['implementation_plan_id']??0);
$q=$pdo->prepare('SELECT * FROM orlms_implementation_plans WHERE id=:id');
$q->execute([':id'=>$planId]);$plan=$q->fetch();
if(!$plan)jsonResponse(false,'Implementation plan not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose evidence document.');

try{
    $id=orlmsUploadImplementationDocument(
        $pdo,$planId,null,$_FILES['document'],
        clean($_POST['document_type']??'Implementation Evidence'),
        trim((string)($_POST['description']??'')),
        clean($_POST['visibility']??'Internal')
    );
    orlmsImplementationHistory($pdo,$planId,(int)$plan['legislative_item_id'],
        'Upload Evidence',$plan['status'],$plan['status'],
        'Implementation evidence document #'.$id.' uploaded.');
    jsonResponse(true,'Implementation evidence uploaded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to upload evidence.');
}
