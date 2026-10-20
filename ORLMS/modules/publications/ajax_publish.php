<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';

requirePermission('orlms.publications.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$mode=clean($_POST['mode']??'save');
$publicationId=(int)($_POST['publication_id']??0);
$itemId=(int)($_POST['legislative_item_id']??0);

if($mode==='save'){
    $channel=clean($_POST['publication_channel']??'Official Publication');
    $date=clean($_POST['publication_date']??'')?:null;
    $classification=clean($_POST['release_classification']??'Public');
    $url=trim((string)($_POST['public_url']??''));
    $text=trim((string)($_POST['publication_text']??''));
    $remarks=trim((string)($_POST['remarks']??''));

    if(!in_array($classification,['Public','Internal','Restricted'],true)){
        jsonResponse(false,'Invalid release classification.');
    }
    if($url!=='' && filter_var($url,FILTER_VALIDATE_URL)===false){
        jsonResponse(false,'Public URL must be a valid URL.');
    }

    $item=orlmsApprovalItem($pdo,$itemId);
    if(!$item)jsonResponse(false,'Legislative item not found.');

    $enactment=orlmsGetEnactment($pdo,$itemId);
    if(!$enactment || $enactment['enactment_status']!=='Finalized'){
        jsonResponse(false,'Publication requires a finalized enactment/adoption record.');
    }

    $existing=null;
    if($publicationId>0){
        $q=$pdo->prepare('SELECT * FROM orlms_publications WHERE id=:id AND legislative_item_id=:item');
        $q->execute([':id'=>$publicationId,':item'=>$itemId]);$existing=$q->fetch();
        if(!$existing)jsonResponse(false,'Publication record not found.');
        if(in_array($existing['publication_status'],['Published','Withdrawn'],true)){
            jsonResponse(false,'Published or withdrawn publication records are locked from direct editing.');
        }
    }

    try{
        $pdo->beginTransaction();

        if($existing){
            $pdo->prepare(
                'UPDATE orlms_publications
                 SET publication_channel=:channel,publication_date=:date,
                     release_classification=:classification,public_url=:url,
                     publication_text=:text,remarks=:remarks,updated_at=NOW()
                 WHERE id=:id'
            )->execute([
                ':channel'=>$channel,':date'=>$date,':classification'=>$classification,
                ':url'=>$url?:null,':text'=>$text?:null,':remarks'=>$remarks?:null,
                ':id'=>$publicationId
            ]);
            orlmsPublicationHistory($pdo,$publicationId,$itemId,'Update',
                $existing['publication_status'],$existing['publication_status'],
                'Publication details updated.');
            $message='Publication record updated.';
        }else{
            $ref=orlmsGenerateReference($pdo,'orlms_publications','publication_reference','PUB',$date);
            $pdo->prepare(
                'INSERT INTO orlms_publications
                 (legislative_item_id,publication_reference,publication_channel,
                  publication_date,publication_status,release_classification,
                  public_url,publication_text,remarks,published_by,created_at,updated_at)
                 VALUES(:item,:ref,:channel,:date,"Draft",:classification,
                  :url,:text,:remarks,NULL,NOW(),NOW())'
            )->execute([
                ':item'=>$itemId,':ref'=>$ref,':channel'=>$channel,':date'=>$date,
                ':classification'=>$classification,':url'=>$url?:null,
                ':text'=>$text?:null,':remarks'=>$remarks?:null
            ]);
            $publicationId=(int)$pdo->lastInsertId();
            orlmsPublicationHistory($pdo,$publicationId,$itemId,'Create',null,'Draft',
                "Publication {$ref} created.");
            orlmsAddWorkflowHistory($pdo,$itemId,'PUBLICATION','Create Publication',
                $item['current_status'],$item['current_status'],"Publication {$ref} created.");
            $message='Publication record created.';
        }

        logActivity(currentUserId(),'ORLMS Publication Save',
            "{$item['reference_number']} · publication #{$publicationId}.");
        $pdo->commit();
        jsonResponse(true,$message,['id'=>$publicationId]);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save publication.');
    }
}

$q=$pdo->prepare(
    'SELECT p.*,li.reference_number,li.title,li.current_status
     FROM orlms_publications p
     JOIN legislative_items li ON li.id=p.legislative_item_id
     WHERE p.id=:id'
);
$q->execute([':id'=>$publicationId]);$p=$q->fetch();
if(!$p)jsonResponse(false,'Publication record not found.');

$action=clean($_POST['action']??'');
$old=$p['publication_status'];

try{
    $pdo->beginTransaction();

    if($action==='schedule'){
        if($old!=='Draft'){$pdo->rollBack();jsonResponse(false,'Only Draft publications can be scheduled.');}
        if(!$p['publication_date']){$pdo->rollBack();jsonResponse(false,'Set the publication date before scheduling.');}
        $new='Scheduled';
        $pdo->prepare('UPDATE orlms_publications SET publication_status=:status,updated_at=NOW() WHERE id=:id')
            ->execute([':status'=>$new,':id'=>$publicationId]);
    }elseif($action==='publish'){
        if(!in_array($old,['Draft','Scheduled'],true)){$pdo->rollBack();jsonResponse(false,'This publication is not ready for release.');}
        $new='Published';
        $pdo->prepare(
            'UPDATE orlms_publications
             SET publication_status="Published",publication_date=COALESCE(publication_date,NOW()),
                 published_by=:user,updated_at=NOW()
             WHERE id=:id'
        )->execute([':user'=>currentUserId(),':id'=>$publicationId]);

        $newItem='Published';
        if(!in_array($p['current_status'],['Implementation In Progress','Implemented'],true)){
            $pdo->prepare('UPDATE legislative_items SET current_status=:status,visibility=:visibility,updated_at=NOW() WHERE id=:id')
                ->execute([
                    ':status'=>$newItem,
                    ':visibility'=>$p['release_classification'],
                    ':id'=>$p['legislative_item_id']
                ]);
            $pdo->prepare('UPDATE orlms_item_details SET draft_status=:status,updated_at=NOW() WHERE legislative_item_id=:id')
                ->execute([':status'=>$newItem,':id'=>$p['legislative_item_id']]);
            orlmsAddStatusHistory($pdo,(int)$p['legislative_item_id'],$p['current_status'],$newItem,
                "Publication {$p['publication_reference']} released.");
        }

        orlmsAddWorkflowHistory($pdo,(int)$p['legislative_item_id'],'PUBLICATION','Publish',
            $p['current_status'],$newItem,
            "{$p['publication_reference']} released through {$p['publication_channel']}.");

        $recipients=$pdo->prepare(
            'SELECT id,user_id,recipient_name FROM orlms_dissemination_recipients
             WHERE publication_id=:publication AND delivery_status="Pending"'
        );
        $recipients->execute([':publication'=>$publicationId]);
        $notify=$pdo->prepare(
            'INSERT INTO notifications
             (user_id,system_id,notification_type,title,message,target_url,is_read,created_at)
             VALUES(:user,:system,"ORLMS Publication","Legislative item published",
              :message,:url,0,NOW())'
        );
        foreach($recipients->fetchAll() as $r){
            if($r['user_id']){
                $notify->execute([
                    ':user'=>$r['user_id'],':system'=>orlmsSystemId(),
                    ':message'=>$p['reference_number'].' - '.$p['title'],
                    ':url'=>APP_URL.'/modules/publications/view.php?id='.$publicationId
                ]);
                $pdo->prepare(
                    'UPDATE orlms_dissemination_recipients
                     SET delivery_status="Sent",sent_at=NOW(),
                         delivery_notes=COALESCE(delivery_notes,"System notification sent."),
                         updated_at=NOW()
                     WHERE id=:id'
                )->execute([':id'=>$r['id']]);
            }
        }
    }elseif($action==='withdraw'){
        if($old!=='Published'){$pdo->rollBack();jsonResponse(false,'Only Published records can be withdrawn.');}
        $reason=trim((string)($_POST['reason']??''));
        if($reason===''){$pdo->rollBack();jsonResponse(false,'Withdrawal reason is required.');}
        $new='Withdrawn';
        $pdo->prepare(
            'UPDATE orlms_publications
             SET publication_status="Withdrawn",
                 remarks=CONCAT(COALESCE(remarks,""),"\nWithdrawal: ",:reason),
                 updated_at=NOW()
             WHERE id=:id'
        )->execute([':reason'=>$reason,':id'=>$publicationId]);

        if(!in_array($p['current_status'],['Implementation In Progress','Implemented'],true)){
            orlmsRefreshPublicationItemStatus($pdo,(int)$p['legislative_item_id']);
        }
    }else{
        $pdo->rollBack();jsonResponse(false,'Invalid publication workflow action.');
    }

    orlmsPublicationHistory($pdo,$publicationId,(int)$p['legislative_item_id'],
        ucfirst($action),$old,$new,
        $action==='withdraw'?('Publication withdrawn: '.($_POST['reason']??'')):'Publication status updated.');
    logActivity(currentUserId(),'ORLMS Publication '.ucfirst($action),
        "{$p['publication_reference']} · {$old} -> {$new}.");

    $pdo->commit();
    jsonResponse(true,"Publication is now {$new}.",['status'=>$new]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update publication workflow.');
}
