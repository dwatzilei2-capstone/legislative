<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_deadline_helpers.php';
requireLacmsPermission('lacms.deadlines.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['deadline_id']??0);
$q=$pdo->prepare('SELECT * FROM lacms_deadlines WHERE id=:id');$q->execute([':id'=>$id]);$d=$q->fetch();
if(!$d)jsonResponse(false,'Deadline not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a document.');

try{
    $up=handleUpload($_FILES['document'],'lacms/deadlines');
    if(!$up['success'])jsonResponse(false,$up['message']);

    $pdo->prepare(
        'INSERT INTO lacms_deadline_documents
         (deadline_id,file_name,file_path,document_type,description,visibility,
          uploaded_by,uploaded_at)
         VALUES(:deadline,:name,:path,:type,:description,:visibility,:user,NOW())'
    )->execute([
        ':deadline'=>$id,':name'=>$up['file_name'],':path'=>$up['file_path'],
        ':type'=>clean($_POST['document_type']??'Deadline Document'),
        ':description'=>trim((string)($_POST['description']??''))?:null,
        ':visibility'=>clean($_POST['visibility']??'Internal'),
        ':user'=>currentUserId()
    ]);

    lacmsDeadlineHistory($pdo,$id,'Upload Document',$d['status'],$d['status'],'Deadline evidence uploaded.');
    jsonResponse(true,'Deadline document uploaded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to upload document.');
}
