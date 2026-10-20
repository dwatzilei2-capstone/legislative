<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.agendas.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['agenda_id']??0);
$q=$pdo->prepare('SELECT * FROM lacms_agendas WHERE id=:id');$q->execute([':id'=>$id]);$agenda=$q->fetch();
if(!$agenda)jsonResponse(false,'Agenda not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a document.');

try{
    $docId=lacmsUploadDocument(
        $pdo,'agenda',$id,$_FILES['document'],
        clean($_POST['document_type']??'Agenda Document'),
        trim((string)($_POST['description']??'')),
        clean($_POST['visibility']??'Internal'),
        (int)($_POST['agenda_item_id']??0)?:null
    );
    lacmsAgendaHistory($pdo,$id,'Upload Document',$agenda['status'],$agenda['status'],'Document #'.$docId.' uploaded.');
    jsonResponse(true,'Agenda document uploaded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to upload document.');
}
