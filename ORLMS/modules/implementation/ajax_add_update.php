<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';

if (!hasPermission('orlms.implementation.manage') && !(isLoggedIn() && in_array(currentRole(), ['Administrator', 'Legislative Staff'], true))) { requirePermission('orlms.implementation.manage'); }
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$planId=(int)($_POST['implementation_plan_id']??0);
$percent=max(0,min(100,(float)($_POST['progress_percent']??0)));
$status=clean($_POST['status']??'In Progress');
$text=trim((string)($_POST['update_text']??''));
$issues=trim((string)($_POST['issues_encountered']??''));
$next=trim((string)($_POST['next_steps']??''));
$evidence=trim((string)($_POST['evidence_summary']??''));

if($text==='')jsonResponse(false,'Progress update text is required.');
if(!in_array($status,['Pending','In Progress','On Hold','Completed','Cancelled'],true))jsonResponse(false,'Invalid progress status.');
if($status==='Completed')$percent=100;

$q=$pdo->prepare(
 'SELECT p.*,li.reference_number,li.current_status
  FROM orlms_implementation_plans p
  JOIN legislative_items li ON li.id=p.legislative_item_id
  WHERE p.id=:id'
);
$q->execute([':id'=>$planId]);$plan=$q->fetch();
if(!$plan)jsonResponse(false,'Implementation plan not found.');

if($status==='Completed'){
    $risk=$pdo->prepare(
        "SELECT COUNT(*) FROM orlms_implementation_issues
         WHERE implementation_plan_id=:plan
           AND status NOT IN ('Resolved','Accepted Risk')
           AND severity IN ('High','Critical')"
    );
    $risk->execute([':plan'=>$planId]);
    if((int)$risk->fetchColumn()>0){
        jsonResponse(false,'Resolve or formally accept all open High/Critical implementation risks before completing the plan.');
    }
}

try{
    $pdo->beginTransaction();

    $pdo->prepare(
        'INSERT INTO orlms_implementation_updates
         (implementation_plan_id,update_date,progress_percent,status,update_text,
          issues_encountered,next_steps,evidence_summary,updated_by,created_at)
         VALUES(:plan,NOW(),:percent,:status,:text,:issues,:next,:evidence,:user,NOW())'
    )->execute([
        ':plan'=>$planId,':percent'=>$percent,':status'=>$status,':text'=>$text,
        ':issues'=>$issues?:null,':next'=>$next?:null,':evidence'=>$evidence?:null,
        ':user'=>currentUserId()
    ]);
    $updateId=(int)$pdo->lastInsertId();

    $pdo->prepare(
        'UPDATE orlms_implementation_plans
         SET status=:status_value,compliance_percent=:percent,
             completed_at=CASE WHEN :status_completed="Completed" THEN COALESCE(completed_at,NOW()) ELSE NULL END,
             updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':status_value'=>$status,
        ':status_completed'=>$status,
        ':percent'=>$percent,
        ':id'=>$planId
    ]);

    if(!empty($_FILES['evidence_document'])
        && ($_FILES['evidence_document']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
        orlmsUploadImplementationDocument(
            $pdo,$planId,$updateId,$_FILES['evidence_document'],
            clean($_POST['document_type']??'Implementation Evidence'),
            $evidence,
            clean($_POST['visibility']??'Internal')
        );
    }

    orlmsImplementationHistory($pdo,$planId,(int)$plan['legislative_item_id'],
        'Progress Update',$plan['status'],$status,
        "Progress {$plan['compliance_percent']}% -> {$percent}%. {$text}");

    $itemStatus=orlmsRefreshImplementationItemStatus($pdo,(int)$plan['legislative_item_id']);

    orlmsAddWorkflowHistory($pdo,(int)$plan['legislative_item_id'],'IMPLEMENTATION','Progress Update',
        $plan['current_status'],$itemStatus,
        "{$plan['plan_reference']} · {$percent}% · {$status}.");

    logActivity(currentUserId(),'ORLMS Implementation Update',
        "{$plan['reference_number']} · {$plan['plan_reference']} · {$percent}%.");

    $pdo->commit();
    jsonResponse(true,'Implementation progress update recorded.',['id'=>$updateId,'item_status'=>$itemStatus]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to record implementation update.');
}
