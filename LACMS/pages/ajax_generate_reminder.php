<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/lacms_notification_helpers.php';
requireLacmsPermission('lacms.notifications.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$deadlineId=(int)($_POST['deadline_id']??0);
$mode=clean($_POST['mode']??'preview');
$preferAi=!empty($_POST['prefer_ai']);
$scheduled=clean($_POST['scheduled_at']??'')?:null;

try{
    $text=lacmsGenerateDeadlineReminderText($pdo,$deadlineId,$preferAi);

    if($mode==='preview'){
        jsonResponse(true,'Reminder content generated.',[
            'subject'=>$text['subject'],
            'message_text'=>$text['message'],
            'generated_by_ai'=>$text['generated_by_ai'],
            'ai_status'=>$text['ai_status'],
            'ai_error'=>$text['ai_error']??null
        ]);
    }

    if($mode==='queue'){
        $subject=clean($_POST['subject']??$text['subject']);
        $message=trim((string)($_POST['message']??$text['message']));
        if($subject===''||$message==='')jsonResponse(false,'Subject and message are required.');
        if($scheduled&&strtotime($scheduled)===false)jsonResponse(false,'Invalid scheduled date/time.');

        $notificationId=lacmsCreateDeadlineNotification(
            $pdo,$deadlineId,$subject,$message,$text['generated_by_ai'],$scheduled
        );

        lacmsDeadlineHistory(
            $pdo,$deadlineId,'Queue Reminder',
            (string)(lacmsDeadlineContext($pdo,$deadlineId)['status']??''),
            (string)(lacmsDeadlineContext($pdo,$deadlineId)['status']??''),
            'Notification #'.$notificationId.' queued from the reminder workspace.'
        );

        lacmsLogActivity(currentUserId(),'LACMS Reminder Queued',
            "Deadline #{$deadlineId} · notification #{$notificationId}.");

        jsonResponse(true,'Reminder notification queued.',[
            'notification_id'=>$notificationId
        ]);
    }

    jsonResponse(false,'Invalid reminder action.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to generate reminder.');
}
