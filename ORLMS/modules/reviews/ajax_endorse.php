<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_drafting_helpers.php';

requirePermission('orlms.endorsements.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$reviewId=(int)($_POST['review_id']??0);
$status=clean($_POST['endorsement_status']??'');
$recommendation=clean($_POST['recommendation']??'');
$voteSummary=clean($_POST['vote_summary']??'');
$remarks=trim((string)($_POST['remarks']??''));

if(!in_array($status,['Endorsed','Not Endorsed','Deferred'],true)){
    jsonResponse(false,'Select a valid committee endorsement status.');
}

$q=$pdo->prepare(
    'SELECT r.*,li.reference_number,li.title,li.current_status,d.submitted_by
     FROM orlms_reviews r
     JOIN legislative_items li ON li.id=r.legislative_item_id
     LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
     WHERE r.id=:id AND li.deleted_at IS NULL'
);
$q->execute([':id'=>$reviewId]);$r=$q->fetch();
if(!$r)jsonResponse(false,'Review record not found.');
if($r['status']!=='Completed')jsonResponse(false,'Complete the committee review before endorsement.');
if($r['recommendation']==='Return for Revision')jsonResponse(false,'This review returned the draft for revision and cannot be endorsed.');

if(currentRole()===ROLE_COMMITTEE){
    $membership=$pdo->prepare(
        'SELECT COUNT(*)
         FROM committee_members
         WHERE committee_id=:committee
           AND user_id=:user
           AND is_active=1'
    );
    $membership->execute([
        ':committee'=>$r['committee_id'],
        ':user'=>currentUserId()
    ]);

    if((int)$membership->fetchColumn()===0){
        jsonResponse(false,'You are not an active member of the reviewing committee.');
    }
}

$openRevision=$pdo->prepare(
    "SELECT COUNT(*) FROM orlms_review_comments
     WHERE review_id=:id
       AND status='Open'
       AND comment_type='Revision Request'"
);
$openRevision->execute([':id'=>$reviewId]);

if($status==='Endorsed' && (int)$openRevision->fetchColumn()>0){
    jsonResponse(false,'Resolve all open Revision Request comments before endorsement.');
}

try{
    $pdo->beginTransaction();

    $pdo->prepare(
        'INSERT INTO orlms_endorsements
         (legislative_item_id,review_id,committee_id,endorsement_status,
          recommendation,vote_summary,remarks,endorsed_by,endorsed_at,created_at,updated_at)
         VALUES(:item,:review,:committee,:status,:recommendation,:vote,:remarks,:user,NOW(),NOW(),NOW())
         ON DUPLICATE KEY UPDATE
          endorsement_status=VALUES(endorsement_status),
          recommendation=VALUES(recommendation),vote_summary=VALUES(vote_summary),
          remarks=VALUES(remarks),endorsed_by=VALUES(endorsed_by),
          endorsed_at=NOW(),updated_at=NOW()'
    )->execute([
        ':item'=>$r['legislative_item_id'],':review'=>$reviewId,
        ':committee'=>$r['committee_id'],':status'=>$status,
        ':recommendation'=>$recommendation?:$r['recommendation'],
        ':vote'=>$voteSummary?:null,':remarks'=>$remarks?:null,':user'=>currentUserId()
    ]);

    $old=$r['current_status'];
    $new=$status==='Endorsed'?'Endorsed':($status==='Not Endorsed'?'Not Endorsed':'Committee Reviewed');

    $pdo->prepare('UPDATE legislative_items SET current_status=:status,updated_at=NOW() WHERE id=:item')
        ->execute([':status'=>$new,':item'=>$r['legislative_item_id']]);
    $pdo->prepare('UPDATE orlms_item_details SET draft_status=:status,updated_at=NOW() WHERE legislative_item_id=:item')
        ->execute([':status'=>$new,':item'=>$r['legislative_item_id']]);

    orlmsAddStatusHistory($pdo,(int)$r['legislative_item_id'],$old,$new,
        'Committee endorsement status: '.$status.'.');
    orlmsAddWorkflowHistory(
        $pdo,(int)$r['legislative_item_id'],'ENDORSEMENT','Committee Endorsement',
        $old,$new,
        ($recommendation?:$r['recommendation']).'. '.($voteSummary?'Vote: '.$voteSummary.'. ':'').($remarks?:''),
        $r['committee_id'],null,null
    );

    if($r['submitted_by']){
        $pdo->prepare(
            'INSERT INTO notifications
             (user_id,system_id,notification_type,title,message,target_url,is_read,created_at)
             VALUES(:user,:system,"ORLMS Endorsement","Committee endorsement recorded",
              :message,:url,0,NOW())'
        )->execute([
            ':user'=>$r['submitted_by'],':system'=>orlmsSystemId(),
            ':message'=>$r['reference_number'].' · '.$status,
            ':url'=>APP_URL.'/modules/reviews/view.php?id='.$reviewId
        ]);
    }

    logActivity(currentUserId(),'ORLMS Committee Endorsement',
        "{$r['reference_number']} · {$status} · review #{$reviewId}.");

    $pdo->commit();
    jsonResponse(true,'Committee endorsement recorded.',['status'=>$new]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save endorsement.');
}
