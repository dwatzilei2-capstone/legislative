<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';

requirePermission('orlms.approvals.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$itemId=(int)($_POST['legislative_item_id']??0);
$item=orlmsApprovalItem($pdo,$itemId);
if(!$item)jsonResponse(false,'Legislative item not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a document.');

try{
    $docId=orlmsSaveSharedDocument(
        $pdo,$itemId,$_FILES['document'],
        clean($_POST['document_type']??'Approval / Enactment Document'),
        clean($_POST['document_title']??''),
        clean($_POST['visibility']??'Internal')
    );
    orlmsAddWorkflowHistory($pdo,$itemId,'APPROVAL','Upload Approval Document',
        $item['current_status'],$item['current_status'],'Approval/enactment document uploaded.');
    logActivity(currentUserId(),'Upload ORLMS Approval Document',
        "{$item['reference_number']} · document #{$docId}.");
    jsonResponse(true,'Approval/enactment document uploaded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to upload document.');
}
