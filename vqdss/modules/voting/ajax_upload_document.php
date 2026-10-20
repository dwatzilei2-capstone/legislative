<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.voting.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['voting_event_id']??0);
$type=clean($_POST['document_type']??'Voting Document');
$visibility=clean($_POST['visibility']??'Internal');
$description=trim((string)($_POST['description']??''));

if(!in_array($visibility,['Public','Internal','Restricted'],true))jsonResponse(false,'Invalid visibility.');
$q=$pdo->prepare('SELECT reference_number FROM vqd_voting_events WHERE id=:id');$q->execute([':id'=>$id]);$ref=$q->fetchColumn();
if(!$ref)jsonResponse(false,'Voting event not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a document.');

$up=handleUpload($_FILES['document'],'vqdss/voting');
if(!$up['success'])jsonResponse(false,$up['message']);

try{
 $pdo->prepare(
  'INSERT INTO vqd_voting_documents
   (voting_event_id,file_name,file_path,document_type,description,visibility,uploaded_by,uploaded_at)
   VALUES(:id,:name,:path,:type,:description,:visibility,:user,NOW())'
 )->execute([
  ':id'=>$id,':name'=>$up['file_name'],':path'=>$up['file_path'],':type'=>$type,
  ':description'=>$description?:null,':visibility'=>$visibility,':user'=>currentUserId()
 ]);
 vqdVotingHistory($pdo,$id,'Upload Document',null,null,'Uploaded voting document '.$up['file_name'].'.');
 vqdLogActivity(currentUserId(),'Upload Voting Document',"Uploaded {$up['file_name']} to {$ref}.");
 jsonResponse(true,'Voting document uploaded.');
}catch(Throwable $e){
 @unlink(dirname(__DIR__,2).'/assets/uploads/'.$up['file_path']);
 jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save voting document.');
}
