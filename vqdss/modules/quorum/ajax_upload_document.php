<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

requirePermission('vqd.quorum.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['quorum_check_id']??0);
$type=clean($_POST['document_type']??'Quorum Evidence');
$visibility=clean($_POST['visibility']??'Internal');
$description=trim((string)($_POST['description']??''));

if(!in_array($visibility,['Public','Internal','Restricted'],true))jsonResponse(false,'Invalid visibility.');
$q=$pdo->prepare('SELECT reference_number FROM vqd_quorum_checks WHERE id=:id');$q->execute([':id'=>$id]);$ref=$q->fetchColumn();
if(!$ref)jsonResponse(false,'Quorum check not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a document.');

$up=handleUpload($_FILES['document'],'vqdss/quorum');
if(!$up['success'])jsonResponse(false,$up['message']);

try{
 $pdo->prepare(
  'INSERT INTO vqd_quorum_documents
   (quorum_check_id,file_name,file_path,document_type,description,visibility,uploaded_by,uploaded_at)
   VALUES(:id,:name,:path,:type,:description,:visibility,:user,NOW())'
 )->execute([
  ':id'=>$id,':name'=>$up['file_name'],':path'=>$up['file_path'],':type'=>$type,
  ':description'=>$description?:null,':visibility'=>$visibility,':user'=>currentUserId()
 ]);
 vqdLogActivity(currentUserId(),'Upload Quorum Document',"Uploaded {$up['file_name']} to {$ref}.");
 jsonResponse(true,'Quorum evidence uploaded.');
}catch(Throwable $e){
 @unlink(dirname(__DIR__,2).'/assets/uploads/'.$up['file_path']);
 jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save quorum document.');
}
