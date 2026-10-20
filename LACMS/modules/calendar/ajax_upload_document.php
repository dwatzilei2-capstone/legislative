<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.calendar.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['calendar_event_id']??0);
$q=$pdo->prepare('SELECT * FROM lacms_calendar_events WHERE id=:id');$q->execute([':id'=>$id]);$event=$q->fetch();
if(!$event)jsonResponse(false,'Event not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a document.');

try{
    $doc=lacmsUploadDocument($pdo,'calendar',$id,$_FILES['document'],
        clean($_POST['document_type']??'Calendar Document'),
        trim((string)($_POST['description']??'')),
        clean($_POST['visibility']??'Internal')
    );
    lacmsCalendarHistory($pdo,$id,'Upload Document',$event['status'],$event['status'],'Document #'.$doc.' uploaded.');
    jsonResponse(true,'Calendar document uploaded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to upload document.');
}
