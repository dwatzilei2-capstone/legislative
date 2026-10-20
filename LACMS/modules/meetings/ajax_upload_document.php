<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.meetings.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['meeting_id']??0);
$q=$pdo->prepare('SELECT * FROM lacms_meetings WHERE id=:id');$q->execute([':id'=>$id]);$meeting=$q->fetch();
if(!$meeting)jsonResponse(false,'Meeting not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a document.');

try{
    $doc=lacmsUploadDocument($pdo,'meeting',$id,$_FILES['document'],
        clean($_POST['document_type']??'Meeting Document'),
        trim((string)($_POST['description']??'')),
        clean($_POST['visibility']??'Internal')
    );
    lacmsMeetingHistory($pdo,$id,'Upload Document',$meeting['status'],$meeting['status'],'Document #'.$doc.' uploaded.');
    jsonResponse(true,'Meeting document uploaded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to upload document.');
}
