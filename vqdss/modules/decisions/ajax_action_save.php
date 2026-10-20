<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.decisions.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$decisionId=(int)($_POST['decision_id']??0);
$title=clean($_POST['action_title']??'');
$description=trim((string)($_POST['action_description']??''));
$office=(int)($_POST['responsible_office_id']??0)?:null;
$user=(int)($_POST['responsible_user_id']??0)?:null;
$priority=clean($_POST['priority']??'Normal');
$due=clean($_POST['due_date']??'')?:null;
$status=clean($_POST['status']??'Pending');
$completion=trim((string)($_POST['completion_notes']??''));

if($decisionId<=0||$title==='')jsonResponse(false,'Decision and action title are required.');
if(!in_array($priority,['Low','Normal','High','Urgent'],true))jsonResponse(false,'Invalid priority.');
if(!in_array($status,['Pending','In Progress','Completed','Cancelled'],true))jsonResponse(false,'Invalid action status.');

$d=$pdo->prepare('SELECT reference_number,record_status FROM vqd_decisions WHERE id=:id');
$d->execute([':id'=>$decisionId]);$decision=$d->fetch();
if(!$decision)jsonResponse(false,'Decision not found.');
if($decision['record_status']==='Cancelled')jsonResponse(false,'Cancelled decisions cannot receive implementation actions.');

$existing=null;
if($id>0){
    $q=$pdo->prepare('SELECT * FROM vqd_decision_actions WHERE id=:id AND decision_id=:decision');
    $q->execute([':id'=>$id,':decision'=>$decisionId]);$existing=$q->fetch();
    if(!$existing)jsonResponse(false,'Decision action not found.');
}

try{
    $pdo->beginTransaction();
    $completedAt=$status==='Completed'
        ? ($existing['completed_at']??date('Y-m-d H:i:s'))
        : null;

    if($existing){
        $pdo->prepare(
          'UPDATE vqd_decision_actions
           SET action_title=:title,action_description=:description,
               responsible_office_id=:office,responsible_user_id=:user,
               priority=:priority,due_date=:due,status=:status,
               completed_at=:completed,completion_notes=:notes,updated_at=NOW()
           WHERE id=:id'
        )->execute([
          ':title'=>$title,':description'=>$description?:null,':office'=>$office,
          ':user'=>$user,':priority'=>$priority,':due'=>$due,':status'=>$status,
          ':completed'=>$completedAt,':notes'=>$completion?:null,':id'=>$id
        ]);
        vqdDecisionActionHistory($pdo,$id,$existing['status'],$status,
          "Implementation action updated: {$title}.");
        $message='Decision action updated.';
    }else{
        $pdo->prepare(
          'INSERT INTO vqd_decision_actions
           (decision_id,action_title,action_description,responsible_office_id,
            responsible_user_id,priority,due_date,status,completed_at,
            completion_notes,created_by,created_at,updated_at)
           VALUES(:decision,:title,:description,:office,:user,:priority,:due,
            :status,:completed,:notes,:created_by,NOW(),NOW())'
        )->execute([
          ':decision'=>$decisionId,':title'=>$title,':description'=>$description?:null,
          ':office'=>$office,':user'=>$user,':priority'=>$priority,':due'=>$due,
          ':status'=>$status,':completed'=>$completedAt,':notes'=>$completion?:null,
          ':created_by'=>currentUserId()
        ]);
        $id=(int)$pdo->lastInsertId();
        vqdDecisionActionHistory($pdo,$id,null,$status,
          "Implementation action created from {$decision['reference_number']}.");
        $message='Decision action created.';
    }

    vqdDecisionHistory($pdo,$decisionId,'Implementation Action',
      $decision['record_status'],$decision['record_status'],$message.' '.$title);
    vqdLogActivity(currentUserId(),'Decision Action',
      "{$decision['reference_number']} · {$title} · {$status}.");

    $pdo->commit();
    jsonResponse(true,$message,['id'=>$id]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save decision action.');
}
