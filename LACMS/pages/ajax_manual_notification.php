<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/lacms_notification_helpers.php';
requireLacmsPermission('lacms.notifications.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$sourceType=clean($_POST['source_type']??'Meeting');
$sourceId=(int)($_POST['source_id']??0);
$type=clean($_POST['notification_type']??'Coordination Notice');
$subject=clean($_POST['subject']??'');
$message=trim((string)($_POST['message']??''));
$scheduled=clean($_POST['scheduled_at']??'')?:null;

if($subject===''||$message==='')jsonResponse(false,'Subject and message are required.');
if($scheduled&&strtotime($scheduled)===false)jsonResponse(false,'Invalid schedule.');

$recipients=[];

if($sourceType==='Meeting'){
    $q=$pdo->prepare(
        "SELECT p.user_id,COALESCE(u.full_name,p.external_name) name,
                COALESCE(u.email,p.external_email) email
         FROM lacms_meeting_participants p
         LEFT JOIN users u ON u.id=p.user_id
         WHERE p.meeting_id=:id"
    );
    $q->execute([':id'=>$sourceId]);
    foreach($q->fetchAll() as $r){
        $recipients[]=[
            'user_id'=>$r['user_id'],
            'name'=>$r['name'],
            'email'=>$r['email'],
            'channel'=>$r['user_id']?'System':'Email'
        ];
    }
}elseif($sourceType==='Deadline'){
    $recipients=lacmsDeadlineRecipients($pdo,$sourceId);
}else{
    jsonResponse(false,'Unsupported notification source.');
}

if(!$recipients)jsonResponse(false,'No recipients are available for this source.');

try{
    $notificationId=lacmsQueueNotification(
        $pdo,$sourceType,$sourceId,$type,$subject,$message,$recipients,$scheduled,false
    );
    lacmsLogActivity(currentUserId(),'LACMS Manual Notification',
        "{$sourceType} #{$sourceId} · notification #{$notificationId} queued.");
    jsonResponse(true,'Notification queued.',['notification_id'=>$notificationId]);
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to queue notification.');
}
