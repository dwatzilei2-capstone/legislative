<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';

if (!hasPermission('orlms.implementation.manage') && !(isLoggedIn() && in_array(currentRole(), ['Administrator', 'Legislative Staff'], true))) { requirePermission('orlms.implementation.manage'); }
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['id']??0);$planId=(int)($_POST['implementation_plan_id']??0);
$title=clean($_POST['issue_title']??'');$description=trim((string)($_POST['description']??''));
$severity=clean($_POST['severity']??'Medium');$status=clean($_POST['status']??'Open');
$office=(int)($_POST['responsible_office_id']??0)?:null;$due=clean($_POST['due_date']??'')?:null;
$resolution=trim((string)($_POST['resolution']??''));

if($title==='')jsonResponse(false,'Issue title is required.');
if(!in_array($severity,['Low','Medium','High','Critical'],true))jsonResponse(false,'Invalid severity.');
if(!in_array($status,['Open','In Progress','Resolved','Accepted Risk'],true))jsonResponse(false,'Invalid issue status.');
if($status==='Resolved'&&$resolution==='')jsonResponse(false,'Resolution is required to resolve an implementation issue.');

$q=$pdo->prepare('SELECT * FROM orlms_implementation_plans WHERE id=:id');
$q->execute([':id'=>$planId]);$plan=$q->fetch();
if(!$plan)jsonResponse(false,'Implementation plan not found.');

$existing=null;
if($id){
    $q=$pdo->prepare('SELECT * FROM orlms_implementation_issues WHERE id=:id AND implementation_plan_id=:plan');
    $q->execute([':id'=>$id,':plan'=>$planId]);$existing=$q->fetch();
    if(!$existing)jsonResponse(false,'Implementation issue not found.');
}

try{
    $pdo->beginTransaction();
    $resolvedBy=$status==='Resolved'?currentUserId():null;
    $resolvedAt=$status==='Resolved'?date('Y-m-d H:i:s'):null;

    if($existing){
        $pdo->prepare(
            'UPDATE orlms_implementation_issues
             SET issue_title=:title,description=:description,severity=:severity,
                 status=:status,responsible_office_id=:office,due_date=:due,
                 resolution=:resolution,resolved_by=:resolved_by,resolved_at=:resolved_at,
                 updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':title'=>$title,':description'=>$description?:null,':severity'=>$severity,
            ':status'=>$status,':office'=>$office,':due'=>$due,
            ':resolution'=>$resolution?:null,':resolved_by'=>$resolvedBy,
            ':resolved_at'=>$resolvedAt,':id'=>$id
        ]);
        $message='Implementation issue updated.';
    }else{
        $pdo->prepare(
            'INSERT INTO orlms_implementation_issues
             (implementation_plan_id,issue_title,description,severity,status,
              responsible_office_id,due_date,resolution,created_by,created_at,
              resolved_by,resolved_at,updated_at)
             VALUES(:plan,:title,:description,:severity,:status,:office,:due,
              :resolution,:created,NOW(),:resolved_by,:resolved_at,NOW())'
        )->execute([
            ':plan'=>$planId,':title'=>$title,':description'=>$description?:null,
            ':severity'=>$severity,':status'=>$status,':office'=>$office,':due'=>$due,
            ':resolution'=>$resolution?:null,':created'=>currentUserId(),
            ':resolved_by'=>$resolvedBy,':resolved_at'=>$resolvedAt
        ]);
        $id=(int)$pdo->lastInsertId();$message='Implementation issue created.';
    }

    orlmsImplementationHistory($pdo,$planId,(int)$plan['legislative_item_id'],
        'Implementation Issue',$plan['status'],$plan['status'],
        "{$title} · {$severity} · {$status}.");
    logActivity(currentUserId(),'ORLMS Implementation Issue',
        "{$plan['plan_reference']} · {$title} · {$status}.");
    $pdo->commit();
    jsonResponse(true,$message,['id'=>$id]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save implementation issue.');
}
