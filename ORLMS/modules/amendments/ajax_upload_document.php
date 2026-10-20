<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_amendment_helpers.php';

requirePermission('orlms.amendments.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['amendment_id']??0);
$a=orlmsGetAmendment($pdo,$id);
if(!$a)jsonResponse(false,'Amendment not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a document.');

try{
    $docId=orlmsUploadAmendmentDocument(
        $pdo,$id,$_FILES['document'],
        clean($_POST['document_type']??'Amendment Evidence'),
        trim((string)($_POST['description']??'')),
        clean($_POST['visibility']??'Internal')
    );

    orlmsAmendmentHistory($pdo,$id,(int)$a['legislative_item_id'],
        'Upload Document',$a['status'],$a['status'],
        'Amendment document #'.$docId.' uploaded.');

    logActivity(currentUserId(),'Upload ORLMS Amendment Document',
        "{$a['amendment_reference']} · document #{$docId}.");

    jsonResponse(true,'Amendment document uploaded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to upload amendment document.');
}
