<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.decisions.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['decision_id']??0);
$type=clean($_POST['document_type']??'Decision Document');
$visibility=clean($_POST['visibility']??'Internal');
$description=trim((string)($_POST['description']??''));

if(!in_array($visibility,['Public','Internal','Restricted'],true))jsonResponse(false,'Invalid visibility.');
$q=$pdo->prepare('SELECT reference_number FROM vqd_decisions WHERE id=:id');$q->execute([':id'=>$id]);$ref=$q->fetchColumn();
if(!$ref)jsonResponse(false,'Decision not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a document.');

$up=handleUpload($_FILES['document'],'vqdss/decisions');
if(!$up['success'])jsonResponse(false,$up['message']);

try{
    $pdo->prepare(
      'INSERT INTO vqd_decision_documents
       (decision_id,file_name,file_path,document_type,description,visibility,uploaded_by,uploaded_at)
       VALUES(:decision,:name,:path,:type,:description,:visibility,:user,NOW())'
    )->execute([
      ':decision'=>$id,':name'=>$up['file_name'],':path'=>$up['file_path'],
      ':type'=>$type,':description'=>$description?:null,
      ':visibility'=>$visibility,':user'=>currentUserId()
    ]);

    vqdDecisionHistory($pdo,$id,'Upload Document',null,null,'Uploaded '.$up['file_name'].'.');
    vqdLogActivity(currentUserId(),'Upload Decision Document',"{$ref} · {$up['file_name']}.");
    jsonResponse(true,'Decision document uploaded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save decision document.');
}
