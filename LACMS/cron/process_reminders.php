<?php
declare(strict_types=1);

/*
 * LACMS reminder processor.
 *
 * Recommended Windows Task Scheduler command:
 * C:\xampp\php\php.exe C:\xampp\htdocs\lacms\cron\process_reminders.php
 *
 * This processes due deadline reminders and internal notification delivery.
 * External email delivery remains pending until an SMTP provider is configured.
 */

require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/lacms_helpers.php';
require_once __DIR__.'/../includes/lacms_notification_helpers.php';

if(PHP_SAPI!=='cli'){
    require_once __DIR__.'/../includes/auth.php';
    requireRole([ROLE_ADMIN]);
}

$pdo=db();
lacmsRefreshDeadlineStatuses($pdo);

$due=$pdo->query(
    "SELECT r.id,r.deadline_id,r.remind_at
     FROM lacms_deadline_reminders r
     JOIN lacms_deadlines d ON d.id=r.deadline_id
     WHERE r.status='Scheduled'
       AND r.remind_at<=NOW()
       AND d.status NOT IN ('Completed','Cancelled')
     ORDER BY r.remind_at
     LIMIT 200"
)->fetchAll();

$queued=0;$internal=0;$external=0;$errors=[];

foreach($due as $r){
    try{
        $pdo->beginTransaction();
        $text=lacmsGenerateDeadlineReminderText($pdo,(int)$r['deadline_id'],true);
        $notificationId=lacmsCreateDeadlineNotification(
            $pdo,(int)$r['deadline_id'],$text['subject'],$text['message'],
            $text['generated_by_ai'],null
        );
        $pdo->prepare(
            'UPDATE lacms_deadline_reminders
             SET status="Queued",subject=:subject,message=:message,
                 generated_by_ai=:ai,updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':subject'=>$text['subject'],':message'=>$text['message'],
            ':ai'=>$text['generated_by_ai']?1:0,':id'=>$r['id']
        ]);
        $pdo->commit();
        $queued++;

        $pdo->beginTransaction();
        $result=lacmsProcessNotification($pdo,$notificationId);
        $pdo->commit();
        $internal+=$result['processed'];
        $external+=$result['external_pending'];

        $pdo->prepare(
            'UPDATE lacms_deadline_reminders
             SET status=:status,
                 sent_at=CASE WHEN :sent_flag=1 THEN NOW() ELSE sent_at END,
                 updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':status'=>$result['external_pending']>0?'Queued':'Sent',
            ':sent_flag'=>$result['external_pending']>0?0:1,
            ':id'=>$r['id']
        ]);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        $errors[]='Reminder #'.$r['id'].': '.$e->getMessage();
    }
}

$output=[
    'time'=>date('Y-m-d H:i:s'),
    'reminders_queued'=>$queued,
    'internal_deliveries'=>$internal,
    'external_email_pending'=>$external,
    'errors'=>$errors,
];

if(PHP_SAPI==='cli'){
    echo json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
}else{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
