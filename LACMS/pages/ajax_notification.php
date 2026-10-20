<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/lacms_notification_helpers.php';
requireLacmsPermission('lacms.notifications.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['notification_id']??0);$mode=clean($_POST['mode']??'process');

try{
    $pdo->beginTransaction();

    $q=$pdo->prepare('SELECT * FROM lacms_notifications WHERE id=:id FOR UPDATE');
    $q->execute([':id'=>$id]);$n=$q->fetch();
    if(!$n){$pdo->rollBack();jsonResponse(false,'Notification not found.');}

    if($mode==='process'){
        if(in_array($n['status'],['Sent','Cancelled'],true)){
            $pdo->rollBack();jsonResponse(false,'Notification is already closed.');
        }
        $pdo->commit();

        // Process in its own transaction because helper also reads recipient state.
        $pdo->beginTransaction();
        $result=lacmsProcessNotification($pdo,$id);
        $pdo->commit();

        lacmsLogActivity(currentUserId(),'LACMS Notification Process',
            "Notification {$n['notification_reference']} processed; internal={$result['processed']}; external pending={$result['external_pending']}.");

        $message=!empty($result['not_due'])
            ? 'This notification is scheduled for a future date/time and was not delivered early.'
            : ($result['external_pending']>0
                ? 'Internal delivery processed. External email recipients remain pending until SMTP is configured.'
                : 'Notification delivery processed.');

        jsonResponse(true,$message,$result);
    }

    if($mode==='cancel'){
        if(in_array($n['status'],['Sent','Cancelled'],true)){
            $pdo->rollBack();jsonResponse(false,'Notification cannot be cancelled.');
        }

        $pdo->prepare(
            'UPDATE lacms_notifications
             SET status="Cancelled",cancelled_at=NOW(),updated_at=NOW()
             WHERE id=:id'
        )->execute([':id'=>$id]);
        $pdo->prepare(
            "UPDATE lacms_notification_recipients
             SET delivery_status='Cancelled'
             WHERE notification_id=:id
               AND delivery_status='Pending'"
        )->execute([':id'=>$id]);
        $pdo->prepare(
            'INSERT INTO lacms_notification_history
             (notification_id,action,previous_status,new_status,details,changed_by,created_at)
             VALUES(:id,"Cancel",:old,"Cancelled","Notification cancelled from queue.",:user,NOW())'
        )->execute([':id'=>$id,':old'=>$n['status'],':user'=>currentUserId()]);

        $pdo->commit();
        lacmsLogActivity(currentUserId(),'LACMS Notification Cancel',
            "Notification {$n['notification_reference']} cancelled.");
        jsonResponse(true,'Notification cancelled.');
    }

    $pdo->rollBack();
    jsonResponse(false,'Invalid notification action.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update notification.');
}
