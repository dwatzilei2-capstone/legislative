<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.validation.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['validation_id']??0);
$type=clean($_POST['document_type']??'Validation Evidence');
$visibility=clean($_POST['visibility']??'Internal');
$description=trim((string)($_POST['description']??''));

if(!in_array($visibility,['Public','Internal','Restricted'],true))jsonResponse(false,'Invalid visibility.');
$q=$pdo->prepare('SELECT validation_reference FROM vqd_result_validations WHERE id=:id');
$q->execute([':id'=>$id]);$ref=$q->fetchColumn();
if(!$ref)jsonResponse(false,'Validation not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a document.');

$up=handleUpload($_FILES['document'],'vqdss/validation');
if(!$up['success'])jsonResponse(false,$up['message']);

try{
    $pdo->prepare(
      'INSERT INTO vqd_validation_documents
       (validation_id,file_name,file_path,document_type,description,visibility,uploaded_by,uploaded_at)
       VALUES(:validation,:name,:path,:type,:description,:visibility,:user,NOW())'
    )->execute([
      ':validation'=>$id,':name'=>$up['file_name'],':path'=>$up['file_path'],
      ':type'=>$type,':description'=>$description?:null,
      ':visibility'=>$visibility,':user'=>currentUserId()
    ]);
    vqdValidationHistory($pdo,$id,'Upload Document',null,null,'Uploaded '.$up['file_name'].'.');
    vqdLogActivity(currentUserId(),'Upload Validation Document',"{$ref} · {$up['file_name']}.");
    jsonResponse(true,'Validation evidence uploaded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save validation document.');
}
