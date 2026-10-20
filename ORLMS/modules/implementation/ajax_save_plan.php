<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';

if (!hasPermission('orlms.implementation.manage') && !(isLoggedIn() && in_array(currentRole(), ['Administrator', 'Legislative Staff'], true))) { requirePermission('orlms.implementation.manage'); }
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$itemId=(int)($_POST['legislative_item_id']??0);
$objective=trim((string)($_POST['objective']??''));
$office=(int)($_POST['responsible_office_id']??0)?:null;
$user=(int)($_POST['responsible_user_id']??0)?:null;
$start=clean($_POST['start_date']??'')?:null;
$due=clean($_POST['due_date']??'')?:null;
$status=clean($_POST['status']??'Pending');
$percent=max(0,min(100,(float)($_POST['compliance_percent']??0)));
$notes=trim((string)($_POST['completion_notes']??''));

if($itemId<=0||$objective==='')jsonResponse(false,'Legislative item and implementation objective are required.');
if(!in_array($status,['Pending','In Progress','On Hold','Completed','Cancelled'],true))jsonResponse(false,'Invalid implementation status.');
if($start&&$due&&strtotime($due)<strtotime($start))jsonResponse(false,'Due date cannot be earlier than the start date.');
if($status==='Completed')$percent=100;

if(!orlmsPublishedExists($pdo,$itemId)){
    jsonResponse(false,'Implementation monitoring requires at least one Published publication record.');
}

$item=orlmsApprovalItem($pdo,$itemId);
if(!$item)jsonResponse(false,'Legislative item not found.');

$existing=null;
if($id>0){
    $q=$pdo->prepare('SELECT * FROM orlms_implementation_plans WHERE id=:id AND legislative_item_id=:item');
    $q->execute([':id'=>$id,':item'=>$itemId]);$existing=$q->fetch();
    if(!$existing)jsonResponse(false,'Implementation plan not found.');

    if($status==='Completed'){
        $risk=$pdo->prepare(
            "SELECT COUNT(*) FROM orlms_implementation_issues
             WHERE implementation_plan_id=:plan
               AND status NOT IN ('Resolved','Accepted Risk')
               AND severity IN ('High','Critical')"
        );
        $risk->execute([':plan'=>$id]);
        if((int)$risk->fetchColumn()>0){
            jsonResponse(false,'Resolve or formally accept all open High/Critical implementation risks before completing the plan.');
        }
    }
}

try{
    $pdo->beginTransaction();
    $completedAt=$status==='Completed'
        ? ($existing['completed_at']??date('Y-m-d H:i:s'))
        : null;

    if($existing){
        $pdo->prepare(
            'UPDATE orlms_implementation_plans
             SET objective=:objective,responsible_office_id=:office,
                 responsible_user_id=:user,start_date=:start,due_date=:due,
                 status=:status,compliance_percent=:percent,
                 completion_notes=:notes,completed_at=:completed,updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':objective'=>$objective,':office'=>$office,':user'=>$user,
            ':start'=>$start,':due'=>$due,':status'=>$status,':percent'=>$percent,
            ':notes'=>$notes?:null,':completed'=>$completedAt,':id'=>$id
        ]);
        orlmsImplementationHistory($pdo,$id,$itemId,'Update Plan',
            $existing['status'],$status,
            "Compliance {$existing['compliance_percent']}% -> {$percent}%.");
        $message='Implementation plan updated.';
    }else{
        $ref=orlmsGenerateReference($pdo,'orlms_implementation_plans','plan_reference','IMP',$start);
        $pdo->prepare(
            'INSERT INTO orlms_implementation_plans
             (legislative_item_id,plan_reference,objective,responsible_office_id,
              responsible_user_id,start_date,due_date,status,compliance_percent,
              completion_notes,completed_at,created_by,created_at,updated_at)
             VALUES(:item,:ref,:objective,:office,:user,:start,:due,:status,
              :percent,:notes,:completed,:created,NOW(),NOW())'
        )->execute([
            ':item'=>$itemId,':ref'=>$ref,':objective'=>$objective,':office'=>$office,
            ':user'=>$user,':start'=>$start,':due'=>$due,':status'=>$status,
            ':percent'=>$percent,':notes'=>$notes?:null,':completed'=>$completedAt,
            ':created'=>currentUserId()
        ]);
        $id=(int)$pdo->lastInsertId();
        orlmsImplementationHistory($pdo,$id,$itemId,'Create Plan',null,$status,
            "Implementation plan {$ref} created.");

        if($user){
            $pdo->prepare(
                'INSERT INTO notifications
                 (user_id,system_id,notification_type,title,message,target_url,is_read,created_at)
                 VALUES(:user,:system,"ORLMS Implementation","Implementation plan assigned",
                  :message,:url,0,NOW())'
            )->execute([
                ':user'=>$user,':system'=>orlmsSystemId(),
                ':message'=>$item['reference_number'].' · '.$objective,
                ':url'=>APP_URL.'/modules/implementation/view.php?id='.$id
            ]);
        }

        orlmsAddWorkflowHistory($pdo,$itemId,'IMPLEMENTATION','Create Plan',
            $item['current_status'],'Implementation In Progress',
            "Implementation plan {$ref}: {$objective}.",null,$office,$user);
        $message='Implementation plan created.';
    }

    $itemStatus=orlmsRefreshImplementationItemStatus($pdo,$itemId);

    logActivity(currentUserId(),'ORLMS Implementation Plan',
        "{$item['reference_number']} · plan #{$id} · {$status}.");
    $pdo->commit();
    jsonResponse(true,$message,['id'=>$id,'item_status'=>$itemStatus]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save implementation plan.');
}
