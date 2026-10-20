<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';

if (!hasPermission('orlms.publications.manage') && !(isLoggedIn() && in_array(currentRole(), ['Administrator', 'Legislative Staff'], true))) {
    requirePermission('orlms.publications.manage');
}
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$mode=clean($_POST['mode']??'save');
$publicationId=(int)($_POST['publication_id']??0);
$recipientId=(int)($_POST['recipient_id']??0);

$p=orlmsGetPublication($pdo,$publicationId);
if(!$p)jsonResponse(false,'Publication not found.');

if($mode==='save'){
    $type=clean($_POST['recipient_type']??'Office');
    $office=(int)($_POST['office_id']??0)?:null;
    $user=(int)($_POST['user_id']??0)?:null;
    $name=clean($_POST['recipient_name']??'');
    $email=clean($_POST['recipient_email']??'');
    $channel=clean($_POST['delivery_channel']??'System Notification');
    $notes=trim((string)($_POST['delivery_notes']??''));

    if(!in_array($type,['Office','User','External'],true))jsonResponse(false,'Invalid recipient type.');
    if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))jsonResponse(false,'Recipient email is invalid.');

    if($type==='Office' && !$office)jsonResponse(false,'Select an office recipient.');
    if($type==='User' && !$user)jsonResponse(false,'Select a user recipient.');
    if($type==='External' && $name==='')jsonResponse(false,'External recipient name is required.');

    try{
        if($recipientId){
            $pdo->prepare(
                'UPDATE orlms_dissemination_recipients
                 SET recipient_type=:type,office_id=:office,user_id=:user,
                     recipient_name=:name,recipient_email=:email,
                     delivery_channel=:channel,delivery_notes=:notes,updated_at=NOW()
                 WHERE id=:id AND publication_id=:publication'
            )->execute([
                ':type'=>$type,':office'=>$office,':user'=>$user,':name'=>$name?:null,
                ':email'=>$email?:null,':channel'=>$channel,':notes'=>$notes?:null,
                ':id'=>$recipientId,':publication'=>$publicationId
            ]);
            $message='Recipient updated.';
        }else{
            $pdo->prepare(
                'INSERT INTO orlms_dissemination_recipients
                 (publication_id,recipient_type,office_id,user_id,recipient_name,
                  recipient_email,delivery_channel,delivery_status,delivery_notes,
                  sent_at,acknowledged_at,created_at,updated_at)
                 VALUES(:publication,:type,:office,:user,:name,:email,:channel,
                  "Pending",:notes,NULL,NULL,NOW(),NOW())'
            )->execute([
                ':publication'=>$publicationId,':type'=>$type,':office'=>$office,
                ':user'=>$user,':name'=>$name?:null,':email'=>$email?:null,
                ':channel'=>$channel,':notes'=>$notes?:null
            ]);
            $recipientId=(int)$pdo->lastInsertId();
            $message='Recipient added.';
        }

        orlmsPublicationHistory($pdo,$publicationId,(int)$p['legislative_item_id'],
            'Dissemination Recipient',null,null,$message);
        jsonResponse(true,$message,['id'=>$recipientId]);
    }catch(Throwable $e){
        jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save recipient.');
    }
}

if($mode==='status'){
    $status=clean($_POST['delivery_status']??'');
    $notes=trim((string)($_POST['delivery_notes']??''));
    if(!in_array($status,['Pending','Sent','Acknowledged','Failed'],true))jsonResponse(false,'Invalid delivery status.');

    $q=$pdo->prepare(
        'SELECT * FROM orlms_dissemination_recipients
         WHERE id=:id AND publication_id=:publication'
    );
    $q->execute([':id'=>$recipientId,':publication'=>$publicationId]);$r=$q->fetch();
    if(!$r)jsonResponse(false,'Recipient not found.');

    try{
        $pdo->beginTransaction();
        $pdo->prepare(
            'UPDATE orlms_dissemination_recipients
             SET delivery_status=:status_value,
                 sent_at=CASE WHEN :status_sent IN ("Sent","Acknowledged") THEN COALESCE(sent_at,NOW()) ELSE sent_at END,
                 acknowledged_at=CASE WHEN :status_ack="Acknowledged" THEN NOW() ELSE acknowledged_at END,
                 delivery_notes=CASE WHEN :notes_check<>"" THEN :notes_value ELSE delivery_notes END,
                 updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':status_value'=>$status,
            ':status_sent'=>$status,
            ':status_ack'=>$status,
            ':notes_check'=>$notes,
            ':notes_value'=>$notes,
            ':id'=>$recipientId
        ]);

        if($status==='Sent' && $r['user_id']){
            $pdo->prepare(
                'INSERT INTO notifications
                 (user_id,system_id,notification_type,title,message,target_url,is_read,created_at)
                 VALUES(:user,:system,"ORLMS Dissemination","Official legislative publication",
                  :message,:url,0,NOW())'
            )->execute([
                ':user'=>$r['user_id'],':system'=>orlmsSystemId(),
                ':message'=>$p['reference_number'].' - '.$p['title'],
                ':url'=>APP_URL.'/modules/publications/view.php?id='.$publicationId
            ]);
        }

        orlmsPublicationHistory($pdo,$publicationId,(int)$p['legislative_item_id'],
            'Dissemination Status',$r['delivery_status'],$status,
            'Recipient #'.$recipientId.' delivery status updated.');
        logActivity(currentUserId(),'ORLMS Dissemination Status',
            "{$p['publication_reference']} · recipient #{$recipientId} -> {$status}.");
        $pdo->commit();
        jsonResponse(true,'Delivery status updated.');
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update delivery status.');
    }
}

jsonResponse(false,'Unsupported recipient action.');
