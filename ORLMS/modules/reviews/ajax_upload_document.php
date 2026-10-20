<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_drafting_helpers.php';

requirePermission('orlms.reviews.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$reviewId=(int)($_POST['review_id']??0);

$q=$pdo->prepare(
    'SELECT r.legislative_item_id,li.reference_number,r.status
     FROM orlms_reviews r
     JOIN legislative_items li ON li.id=r.legislative_item_id
     WHERE r.id=:id'
);
$q->execute([':id'=>$reviewId]);$r=$q->fetch();
if(!$r)jsonResponse(false,'Review record not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a review document.');

try{
    $docId=orlmsSaveSharedDocument(
        $pdo,(int)$r['legislative_item_id'],$_FILES['document'],
        clean($_POST['document_type']??'Review Evidence'),
        clean($_POST['document_title']??''),
        clean($_POST['visibility']??'Internal')
    );

    orlmsAddWorkflowHistory(
        $pdo,(int)$r['legislative_item_id'],'REVIEW','Upload Review Evidence',
        null,null,'Review evidence document #'.$docId.' uploaded.'
    );
    logActivity(currentUserId(),'Upload ORLMS Review Evidence',
        "{$r['reference_number']} · document #{$docId}.");
    jsonResponse(true,'Review evidence uploaded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to upload review evidence.');
}
