<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_drafting_helpers.php';

requirePermission('orlms.reviews.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$reviewId=(int)($_POST['review_id']??0);
$action=clean($_POST['action']??'');

$q=$pdo->prepare(
    'SELECT r.*,li.reference_number,li.title,li.current_status,
            d.draft_status,d.submitted_by
     FROM orlms_reviews r
     JOIN legislative_items li ON li.id=r.legislative_item_id
     LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
     WHERE r.id=:id AND li.deleted_at IS NULL'
);
$q->execute([':id'=>$reviewId]);$review=$q->fetch();
if(!$review)jsonResponse(false,'Review record not found.');

if(currentRole()===ROLE_COMMITTEE){
    $membership=$pdo->prepare(
        'SELECT COUNT(*)
         FROM committee_members
         WHERE committee_id=:committee
           AND user_id=:user
           AND is_active=1'
    );
    $membership->execute([
        ':committee'=>$review['committee_id'],
        ':user'=>currentUserId()
    ]);

    $isAssigned=(int)($review['reviewer_id']??0)===(int)currentUserId();

    if((int)$membership->fetchColumn()===0 && !$isAssigned){
        jsonResponse(false,'You are not assigned to this committee review.');
    }
}

try{
    $pdo->beginTransaction();

    if($action==='assign'){
        if(!in_array($review['status'],['Pending','In Progress'],true)){
            $pdo->rollBack();jsonResponse(false,'Completed reviews cannot be reassigned.');
        }
        if(!hasRole([ROLE_ADMIN,ROLE_STAFF])){
            $pdo->rollBack();jsonResponse(false,'Only Legislative Staff or Administrator can reassign a review.');
        }

        $committeeId=(int)($_POST['committee_id']??0);
        $reviewerId=(int)($_POST['reviewer_id']??0)?:null;
        if($committeeId<=0){$pdo->rollBack();jsonResponse(false,'Select a committee.');}

        $c=$pdo->prepare('SELECT name FROM committees WHERE id=:id AND status="Active"');
        $c->execute([':id'=>$committeeId]);$committeeName=$c->fetchColumn();
        if(!$committeeName){$pdo->rollBack();jsonResponse(false,'Selected committee is inactive or missing.');}

        $pdo->prepare(
            'UPDATE orlms_reviews
             SET committee_id=:committee,reviewer_id=:reviewer,updated_at=NOW()
             WHERE id=:id'
        )->execute([':committee'=>$committeeId,':reviewer'=>$reviewerId,':id'=>$reviewId]);

        orlmsAddWorkflowHistory(
            $pdo,(int)$review['legislative_item_id'],'REVIEW','Assign Review',
            $review['current_status'],$review['current_status'],
            'Review assigned/reassigned to '.$committeeName.'.',
            $committeeId,null,$reviewerId
        );
        $message='Review assignment updated.';
    }elseif($action==='start'){
        if($review['status']!=='Pending'){
            $pdo->rollBack();jsonResponse(false,'Only Pending reviews can be started.');
        }

        $pdo->prepare(
            'UPDATE orlms_reviews
             SET status="In Progress",started_at=COALESCE(started_at,NOW()),
                 reviewer_id=COALESCE(reviewer_id,:reviewer),updated_at=NOW()
             WHERE id=:id'
        )->execute([':reviewer'=>currentUserId(),':id'=>$reviewId]);

        $old=$review['current_status'];$new='Under Review';
        $pdo->prepare('UPDATE legislative_items SET current_status=:status,updated_at=NOW() WHERE id=:item')
            ->execute([':status'=>$new,':item'=>$review['legislative_item_id']]);
        $pdo->prepare('UPDATE orlms_item_details SET draft_status=:status,updated_at=NOW() WHERE legislative_item_id=:item')
            ->execute([':status'=>$new,':item'=>$review['legislative_item_id']]);

        orlmsAddStatusHistory($pdo,(int)$review['legislative_item_id'],$old,$new,'Committee review started.');
        orlmsAddWorkflowHistory($pdo,(int)$review['legislative_item_id'],'REVIEW','Start Review',$old,$new,'Committee review is in progress.',$review['committee_id'],null,$review['reviewer_id']?:currentUserId());
        $message='Committee review started.';
    }elseif($action==='checklist'){
        if(!in_array($review['status'],['Pending','In Progress'],true)){
            $pdo->rollBack();jsonResponse(false,'Review checklist is locked after completion.');
        }
        $checkId=(int)($_POST['check_id']??0);
        $status=clean($_POST['check_status']??'Pending');
        $note=trim((string)($_POST['reviewer_note']??''));

        if(!in_array($status,['Pending','Passed','Warning','Failed','Not Applicable'],true)){
            $pdo->rollBack();jsonResponse(false,'Invalid checklist status.');
        }

        $check=$pdo->prepare('SELECT check_name FROM orlms_review_checklist WHERE id=:id AND review_id=:review');
        $check->execute([':id'=>$checkId,':review'=>$reviewId]);$checkName=$check->fetchColumn();
        if(!$checkName){$pdo->rollBack();jsonResponse(false,'Checklist entry not found.');}

        $pdo->prepare(
            'UPDATE orlms_review_checklist
             SET check_status=:status,reviewer_note=:note,reviewed_by=:user,
                 reviewed_at=NOW(),updated_at=NOW()
             WHERE id=:id'
        )->execute([':status'=>$status,':note'=>$note?:null,':user'=>currentUserId(),':id'=>$checkId]);

        $message='Checklist entry updated.';
    }elseif($action==='comment'){
        if(!in_array($review['status'],['Pending','In Progress'],true)){
            $pdo->rollBack();jsonResponse(false,'Comments are locked after review completion.');
        }

        $type=clean($_POST['comment_type']??'Comment');
        $section=clean($_POST['section_reference']??'');
        $text=trim((string)($_POST['comment_text']??''));

        if($text===''){$pdo->rollBack();jsonResponse(false,'Comment text is required.');}
        if(!in_array($type,['Comment','Revision Request','Legal Note','Committee Note'],true))$type='Comment';

        $pdo->prepare(
            'INSERT INTO orlms_review_comments
             (review_id,legislative_item_id,comment_type,section_reference,
              comment_text,status,created_by,created_at,updated_at)
             VALUES(:review,:item,:type,:section,:comment,"Open",:user,NOW(),NOW())'
        )->execute([
            ':review'=>$reviewId,':item'=>$review['legislative_item_id'],':type'=>$type,
            ':section'=>$section?:null,':comment'=>$text,':user'=>currentUserId()
        ]);
        $message='Review comment added.';
    }elseif($action==='resolve_comment'){
        $commentId=(int)($_POST['comment_id']??0);
        $resolution=trim((string)($_POST['resolution_text']??''));
        if($resolution===''){$pdo->rollBack();jsonResponse(false,'Resolution text is required.');}

        $c=$pdo->prepare(
            'SELECT id FROM orlms_review_comments
             WHERE id=:id AND review_id=:review AND status="Open"'
        );
        $c->execute([':id'=>$commentId,':review'=>$reviewId]);
        if(!$c->fetchColumn()){$pdo->rollBack();jsonResponse(false,'Open review comment not found.');}

        $pdo->prepare(
            'UPDATE orlms_review_comments
             SET status="Resolved",resolution_text=:resolution,resolved_by=:user,
                 resolved_at=NOW(),updated_at=NOW()
             WHERE id=:id'
        )->execute([':resolution'=>$resolution,':user'=>currentUserId(),':id'=>$commentId]);
        $message='Review comment resolved.';
    }elseif($action==='complete'){
        if(!in_array($review['status'],['Pending','In Progress'],true)){
            $pdo->rollBack();jsonResponse(false,'Review has already been completed.');
        }

        $recommendation=clean($_POST['recommendation']??'');
        $comments=trim((string)($_POST['comments']??''));
        $allowed=['Endorse','Endorse with Conditions','Return for Revision','Do Not Endorse'];
        if(!in_array($recommendation,$allowed,true)){
            $pdo->rollBack();jsonResponse(false,'Select a valid review recommendation.');
        }

        $pending=$pdo->prepare(
            "SELECT COUNT(*) FROM orlms_review_checklist
             WHERE review_id=:id AND check_status='Pending'"
        );
        $pending->execute([':id'=>$reviewId]);
        if((int)$pending->fetchColumn()>0){
            $pdo->rollBack();jsonResponse(false,'Complete every review checklist item before finalizing the review.');
        }

        $failed=$pdo->prepare(
            "SELECT COUNT(*) FROM orlms_review_checklist
             WHERE review_id=:id AND check_status='Failed'"
        );
        $failed->execute([':id'=>$reviewId]);
        $failedCount=(int)$failed->fetchColumn();

        if($failedCount>0 && $recommendation==='Endorse'){
            $pdo->rollBack();
            jsonResponse(false,'A review with failed checklist items cannot receive a plain Endorse recommendation. Use Endorse with Conditions, Return for Revision, or Do Not Endorse.');
        }

        if(in_array($recommendation,['Endorse','Endorse with Conditions'],true)){
            $openRevision=$pdo->prepare(
                "SELECT COUNT(*) FROM orlms_review_comments
                 WHERE review_id=:id
                   AND status='Open'
                   AND comment_type='Revision Request'"
            );
            $openRevision->execute([':id'=>$reviewId]);

            if((int)$openRevision->fetchColumn()>0){
                $pdo->rollBack();
                jsonResponse(false,'Resolve all open Revision Request comments before recommending endorsement.');
            }
        }

        $pdo->prepare(
            'UPDATE orlms_reviews
             SET status="Completed",recommendation=:recommendation,comments=:comments,
                 started_at=COALESCE(started_at,NOW()),completed_at=NOW(),
                 reviewer_id=COALESCE(reviewer_id,:reviewer),updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':recommendation'=>$recommendation,':comments'=>$comments?:null,
            ':reviewer'=>currentUserId(),':id'=>$reviewId
        ]);

        $old=$review['current_status'];
        if($recommendation==='Return for Revision'){
            $new='Revision Required';
            $reason=$comments!==''?$comments:'Committee requested revision before endorsement.';
            $pdo->prepare(
                'UPDATE orlms_item_details
                 SET draft_status=:status,returned_at=NOW(),return_reason=:reason,updated_at=NOW()
                 WHERE legislative_item_id=:item'
            )->execute([':status'=>$new,':reason'=>$reason,':item'=>$review['legislative_item_id']]);
        }else{
            $new='Committee Reviewed';
            $pdo->prepare(
                'UPDATE orlms_item_details
                 SET draft_status=:status,updated_at=NOW()
                 WHERE legislative_item_id=:item'
            )->execute([':status'=>$new,':item'=>$review['legislative_item_id']]);
        }

        $pdo->prepare('UPDATE legislative_items SET current_status=:status,updated_at=NOW() WHERE id=:item')
            ->execute([':status'=>$new,':item'=>$review['legislative_item_id']]);

        orlmsAddStatusHistory($pdo,(int)$review['legislative_item_id'],$old,$new,
            'Review completed with recommendation: '.$recommendation.'.');
        orlmsAddWorkflowHistory(
            $pdo,(int)$review['legislative_item_id'],'REVIEW','Complete Review',
            $old,$new,
            'Recommendation: '.$recommendation.'. '.($comments?:''),
            $review['committee_id'],null,$review['reviewer_id']?:currentUserId()
        );

        if($review['submitted_by']){
            $pdo->prepare(
                'INSERT INTO notifications
                 (user_id,system_id,notification_type,title,message,target_url,is_read,created_at)
                 VALUES(:user,:system,"ORLMS Review","Committee review completed",
                  :message,:url,0,NOW())'
            )->execute([
                ':user'=>$review['submitted_by'],':system'=>orlmsSystemId(),
                ':message'=>$review['reference_number'].' · '.$recommendation,
                ':url'=>APP_URL.'/modules/reviews/view.php?id='.$reviewId
            ]);
        }

        $message='Committee review completed.';
    }else{
        $pdo->rollBack();jsonResponse(false,'Invalid review action.');
    }

    logActivity(currentUserId(),'ORLMS Review '.ucwords(str_replace('_',' ',$action)),
        "{$review['reference_number']} · review #{$reviewId}.");
    $pdo->commit();
    jsonResponse(true,$message);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('[ORLMS Review] '.$e->getMessage());
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update the review.');
}
