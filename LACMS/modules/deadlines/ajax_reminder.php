<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_notification_helpers.php';
requireLacmsPermission('lacms.deadlines.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$deadlineId=(int)($_POST['deadline_id']??0);$mode=clean($_POST['mode']??'add');

$q=$pdo->prepare('SELECT * FROM lacms_deadlines WHERE id=:id');
$q->execute([':id'=>$deadlineId]);$deadline=$q->fetch();
if(!$deadline)jsonResponse(false,'Deadline not found.');

if($mode==='add'){
    $at=clean($_POST['remind_at']??'');
    if($at===''||strtotime($at)===false)jsonResponse(false,'Valid reminder date/time is required.');
    if(strtotime($at)>=strtotime($deadline['due_datetime']))jsonResponse(false,'Reminder must be scheduled before the deadline.');

    try{
        $pdo->prepare(
            'INSERT INTO lacms_deadline_reminders
             (deadline_id,remind_at,reminder_type,status,subject,message,
              generated_by_ai,created_by,created_at,updated_at)
             VALUES(:deadline,:at,"Manual","Scheduled",:subject,:message,0,:user,NOW(),NOW())'
        )->execute([
            ':deadline'=>$deadlineId,':at'=>$at,
            ':subject'=>'Reminder: '.$deadline['title'],
            ':message'=>'Manual reminder scheduled for this legislative deadline.',
            ':user'=>currentUserId()
        ]);
        lacmsDeadlineHistory($pdo,$deadlineId,'Schedule Reminder',$deadline['status'],$deadline['status'],'Manual reminder scheduled for '.formatDateTime($at).'.');
        jsonResponse(true,'Reminder scheduled.');
    }catch(PDOException $e){
        if((int)($e->errorInfo[1]??0)===1062)jsonResponse(false,'That reminder schedule already exists.');
        jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to schedule reminder.');
    }
}

if($mode==='regenerate'){
    $pdo->prepare("DELETE FROM lacms_deadline_reminders WHERE deadline_id=:id AND status='Scheduled'")
        ->execute([':id'=>$deadlineId]);
    $count=lacmsCreateDefaultDeadlineReminders($pdo,$deadlineId,$deadline['reminder_policy']);
    lacmsDeadlineHistory($pdo,$deadlineId,'Regenerate Reminders',$deadline['status'],$deadline['status'],"{$count} reminder(s) scheduled.");
    jsonResponse(true,"{$count} reminder(s) scheduled.");
}

jsonResponse(false,'Invalid reminder action.');
