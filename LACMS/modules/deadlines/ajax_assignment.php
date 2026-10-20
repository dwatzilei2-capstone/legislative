<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_deadline_helpers.php';
requireLacmsPermission('lacms.deadlines.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$deadlineId=(int)($_POST['deadline_id']??0);$assignmentId=(int)($_POST['assignment_id']??0);
$mode=clean($_POST['mode']??'save');

$q=$pdo->prepare('SELECT * FROM lacms_deadlines WHERE id=:id');
$q->execute([':id'=>$deadlineId]);$deadline=$q->fetch();
if(!$deadline)jsonResponse(false,'Deadline not found.');
if(in_array($deadline['status'],['Completed','Cancelled'],true))jsonResponse(false,'Closed deadline assignments cannot be changed.');

if($mode==='delete'){
    $q=$pdo->prepare('SELECT * FROM lacms_deadline_assignments WHERE id=:id AND deadline_id=:deadline');
    $q->execute([':id'=>$assignmentId,':deadline'=>$deadlineId]);$row=$q->fetch();
    if(!$row)jsonResponse(false,'Assignment not found.');
    $pdo->prepare('DELETE FROM lacms_deadline_assignments WHERE id=:id')->execute([':id'=>$assignmentId]);
    lacmsDeadlineHistory($pdo,$deadlineId,'Remove Assignment',$deadline['status'],$deadline['status'],'Deadline assignment removed.');
    jsonResponse(true,'Assignment removed.');
}

$user=(int)($_POST['user_id']??0)?:null;
$office=(int)($_POST['office_id']??0)?:null;
$committee=(int)($_POST['committee_id']??0)?:null;
$role=clean($_POST['assignment_role']??'Responsible');
if(!$user&&!$office&&!$committee)jsonResponse(false,'Assign at least one user, office, or committee.');

try{
    if($assignmentId){
        $pdo->prepare(
            'UPDATE lacms_deadline_assignments
             SET user_id=:user,office_id=:office,committee_id=:committee,
                 assignment_role=:role,status="Assigned"
             WHERE id=:id AND deadline_id=:deadline'
        )->execute([
            ':user'=>$user,':office'=>$office,':committee'=>$committee,
            ':role'=>$role,':id'=>$assignmentId,':deadline'=>$deadlineId
        ]);
        $message='Assignment updated.';
    }else{
        $pdo->prepare(
            'INSERT INTO lacms_deadline_assignments
             (deadline_id,user_id,office_id,committee_id,assignment_role,status,
              assigned_by,assigned_at)
             VALUES(:deadline,:user,:office,:committee,:role,"Assigned",:by,NOW())'
        )->execute([
            ':deadline'=>$deadlineId,':user'=>$user,':office'=>$office,
            ':committee'=>$committee,':role'=>$role,':by'=>currentUserId()
        ]);
        $assignmentId=(int)$pdo->lastInsertId();$message='Assignment added.';
    }

    lacmsDeadlineHistory($pdo,$deadlineId,'Assignment',$deadline['status'],$deadline['status'],$message);
    jsonResponse(true,$message,['id'=>$assignmentId]);
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save assignment.');
}
