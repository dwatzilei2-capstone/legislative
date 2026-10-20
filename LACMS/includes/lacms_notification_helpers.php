<?php
declare(strict_types=1);

require_once __DIR__.'/lacms_deadline_helpers.php';
require_once __DIR__.'/../config/lacms_ai.php';

function lacmsTemplateMessage(string $template,array $vars): string
{
    foreach($vars as $key=>$value){
        $template=str_replace('{{'.$key.'}}',(string)$value,$template);
    }
    return $template;
}

function lacmsGenerateDeadlineReminderText(PDO $pdo,int $deadlineId,bool $preferAi=true): array
{
    $d=lacmsDeadlineContext($pdo,$deadlineId);
    if(!$d)throw new RuntimeException('Deadline not found.');

    $dueTs=strtotime((string)$d['due_datetime']);
    $seconds=$dueTs-time();
    $dueText=$seconds<0
        ? 'is overdue'
        : 'is due in '.max(1,(int)ceil($seconds/86400)).' day(s)';

    $contextParts=[];
    if($d['legislative_reference'])$contextParts[]='Legislative item: '.$d['legislative_reference'];
    if($d['agenda_reference'])$contextParts[]='Agenda: '.$d['agenda_reference'];
    if($d['event_reference'])$contextParts[]='Calendar event: '.$d['event_reference'];
    if($d['meeting_reference'])$contextParts[]='Meeting: '.$d['meeting_reference'];
    $context=implode('. ',$contextParts);

    $vars=[
        'title'=>$d['title'],
        'due_text'=>$dueText,
        'due_datetime'=>formatDateTime($d['due_datetime']),
        'priority'=>$d['priority_level'],
        'responsible'=>$d['responsible_name']?:'Assigned legislative personnel',
        'context'=>$context,
    ];

    $q=$pdo->prepare(
        "SELECT subject_template,message_template
         FROM lacms_reminder_templates
         WHERE template_code='DEADLINE_STANDARD'
           AND is_active=1
         LIMIT 1"
    );
    $q->execute();$template=$q->fetch();

    $fallbackSubject=$template
        ? lacmsTemplateMessage($template['subject_template'],$vars)
        : 'Reminder: '.$d['title'].' '.$dueText;
    $fallbackMessage=$template
        ? lacmsTemplateMessage($template['message_template'],$vars)
        : 'This is a reminder for '.$d['title'].' due '.formatDateTime($d['due_datetime']).'.';

    if(!$preferAi || !LACMS_OLLAMA_ENABLED || !function_exists('curl_init')){
        return [
            'subject'=>$fallbackSubject,
            'message'=>$fallbackMessage,
            'generated_by_ai'=>false,
            'ai_status'=>'Template fallback'
        ];
    }

    $prompt=
        "You are drafting a concise professional reminder for a local government legislative office.\n".
        "Do not invent facts. Return JSON only with keys subject and message.\n".
        "Deadline title: {$d['title']}\n".
        "Due: ".formatDateTime($d['due_datetime'])."\n".
        "Priority: {$d['priority_level']}\n".
        "Responsible: ".($d['responsible_name']?:'Assigned personnel')."\n".
        "Context: ".($context?:'General legislative coordination task')."\n".
        "The message should be polite, direct, and under 120 words.";

    $payload=json_encode([
        'model'=>LACMS_OLLAMA_MODEL,
        'prompt'=>$prompt,
        'stream'=>false,
        'format'=>'json',
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

    $started=microtime(true);
    $ch=curl_init(LACMS_OLLAMA_URL);
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CONNECTTIMEOUT=>3,
        CURLOPT_TIMEOUT=>LACMS_OLLAMA_TIMEOUT,
    ]);
    $response=curl_exec($ch);
    $curlError=curl_error($ch);
    $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    $duration=(int)round((microtime(true)-$started)*1000);

    $success=false;$subject=$fallbackSubject;$message=$fallbackMessage;$error=null;

    if($response!==false && $http>=200 && $http<300){
        $outer=json_decode((string)$response,true);
        $raw=(string)($outer['response']??'');
        $generated=json_decode($raw,true);
        if(is_array($generated) && !empty($generated['subject']) && !empty($generated['message'])){
            $subject=clean($generated['subject']);
            $message=trim((string)$generated['message']);
            $success=true;
        }else{
            $error='Ollama returned a response that did not contain the expected JSON subject/message.';
        }
    }else{
        $error=$curlError?:('Ollama HTTP status '.$http);
    }

    try{
        $pdo->prepare(
            'INSERT INTO lacms_ai_request_logs
             (source_type,source_id,provider,model_used,request_prompt,response_payload,
              http_status,success,error_message,duration_ms,created_by,created_at)
             VALUES("Deadline",:source,"ollama",:model,:prompt,:response,:http,:success,
              :error,:duration,:user,NOW())'
        )->execute([
            ':source'=>$deadlineId,':model'=>LACMS_OLLAMA_MODEL,':prompt'=>$prompt,
            ':response'=>$response!==false?(string)$response:null,':http'=>$http?:null,
            ':success'=>$success?1:0,':error'=>$error,':duration'=>$duration,
            ':user'=>currentUserId()
        ]);
    }catch(Throwable $e){
        error_log('[LACMS AI log] '.$e->getMessage());
    }

    return [
        'subject'=>$subject,
        'message'=>$message,
        'generated_by_ai'=>$success,
        'ai_status'=>$success?'Ollama generated':'Template fallback',
        'ai_error'=>$error,
    ];
}

function lacmsCreateDeadlineNotification(
    PDO $pdo,int $deadlineId,string $subject,string $message,bool $generatedByAi=false,
    ?string $scheduledAt=null
): int {
    $recipients=lacmsDeadlineRecipients($pdo,$deadlineId);
    if(!$recipients)throw new RuntimeException('This deadline has no internal user recipient.');

    return lacmsQueueNotification(
        $pdo,'Deadline',$deadlineId,'Deadline Reminder',
        $subject,$message,$recipients,$scheduledAt,$generatedByAi
    );
}

function lacmsProcessNotification(PDO $pdo,int $notificationId): array
{
    $q=$pdo->prepare(
        "SELECT * FROM lacms_notifications
         WHERE id=:id
           AND status IN ('Ready','Scheduled','Partially Sent')
         FOR UPDATE"
    );
    $q->execute([':id'=>$notificationId]);$n=$q->fetch();
    if(!$n)return ['processed'=>0,'external_pending'=>0];

    if($n['scheduled_at'] && strtotime($n['scheduled_at'])>time()){
        return ['processed'=>0,'external_pending'=>0,'not_due'=>true];
    }

    $r=$pdo->prepare(
        "SELECT nr.*,u.full_name,u.email
         FROM lacms_notification_recipients nr
         LEFT JOIN users u ON u.id=nr.user_id
         WHERE nr.notification_id=:id
           AND nr.delivery_status='Pending'"
    );
    $r->execute([':id'=>$notificationId]);
    $recipients=$r->fetchAll();

    $processed=0;$external=0;

    foreach($recipients as $recipient){
        $attempt=(int)$pdo->query(
            'SELECT COALESCE(MAX(attempt_number),0)+1
             FROM lacms_notification_delivery_attempts
             WHERE notification_recipient_id='.(int)$recipient['id']
        )->fetchColumn();

        if($recipient['user_id']){
            $exists=$pdo->prepare(
                'SELECT COUNT(*) FROM notifications
                 WHERE user_id=:user
                   AND system_id=:system
                   AND notification_type=:type
                   AND title=:title
                   AND message=:message'
            );
            $exists->execute([
                ':user'=>$recipient['user_id'],':system'=>lacmsSystemId(),
                ':type'=>$n['notification_type'],':title'=>$n['subject'],':message'=>$n['message']
            ]);

            if((int)$exists->fetchColumn()===0){
                $pdo->prepare(
                    'INSERT INTO notifications
                     (user_id,system_id,notification_type,title,message,target_url,is_read,created_at)
                     VALUES(:user,:system,:type,:title,:message,:url,0,NOW())'
                )->execute([
                    ':user'=>$recipient['user_id'],':system'=>lacmsSystemId(),
                    ':type'=>$n['notification_type'],':title'=>$n['subject'],
                    ':message'=>$n['message'],
                    ':url'=>appUrl('pages/meeting_notifications.php')
                ]);
            }

            $pdo->prepare(
                'UPDATE lacms_notification_recipients
                 SET delivery_status="Sent",sent_at=NOW()
                 WHERE id=:id'
            )->execute([':id'=>$recipient['id']]);

            $pdo->prepare(
                'INSERT INTO lacms_notification_delivery_attempts
                 (notification_recipient_id,delivery_channel,attempt_number,status,
                  provider,response_code,response_message,attempted_at)
                 VALUES(:recipient,:channel,:attempt,"Sent","Shared Notifications",
                  "OK","Internal notification created.",NOW())'
            )->execute([
                ':recipient'=>$recipient['id'],
                ':channel'=>$recipient['delivery_channel'],
                ':attempt'=>$attempt
            ]);
            $processed++;
        }else{
            $pdo->prepare(
                'INSERT INTO lacms_notification_delivery_attempts
                 (notification_recipient_id,delivery_channel,attempt_number,status,
                  provider,response_code,response_message,attempted_at)
                 VALUES(:recipient,:channel,:attempt,"Pending","External SMTP",
                  "NOT_CONFIGURED","External email delivery requires SMTP integration.",NOW())'
            )->execute([
                ':recipient'=>$recipient['id'],
                ':channel'=>$recipient['delivery_channel'],
                ':attempt'=>$attempt
            ]);
            $external++;
        }
    }

    $pending=$pdo->prepare(
        "SELECT COUNT(*) FROM lacms_notification_recipients
         WHERE notification_id=:id AND delivery_status='Pending'"
    );
    $pending->execute([':id'=>$notificationId]);$remaining=(int)$pending->fetchColumn();

    $pdo->prepare(
        'UPDATE lacms_notifications
         SET status=:status,sent_at=CASE WHEN :sent_flag=1 THEN NOW() ELSE sent_at END,
             updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':status'=>$remaining===0?'Sent':'Partially Sent',
        ':sent_flag'=>$remaining===0?1:0,
        ':id'=>$notificationId
    ]);

    return ['processed'=>$processed,'external_pending'=>$external,'not_due'=>false];
}
