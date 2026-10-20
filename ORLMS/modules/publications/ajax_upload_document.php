<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';

requirePermission('orlms.publications.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$publicationId=(int)($_POST['publication_id']??0);
$p=orlmsGetPublication($pdo,$publicationId);
if(!$p)jsonResponse(false,'Publication not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a publication document.');

try{
    $id=orlmsUploadPublicationDocument(
        $pdo,$publicationId,$_FILES['document'],
        clean($_POST['document_type']??'Publication Document'),
        trim((string)($_POST['description']??'')),
        clean($_POST['visibility']??$p['release_classification'])
    );
    orlmsPublicationHistory($pdo,$publicationId,(int)$p['legislative_item_id'],
        'Upload Document',null,null,'Publication document #'.$id.' uploaded.');
    logActivity(currentUserId(),'Upload ORLMS Publication Document',
        "{$p['publication_reference']} · document #{$id}.");
    jsonResponse(true,'Publication document uploaded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to upload publication document.');
}
