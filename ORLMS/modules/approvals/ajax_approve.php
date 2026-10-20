<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';

requirePermission('orlms.approvals.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$itemId=(int)($_POST['legislative_item_id']??0);
$approvalId=(int)($_POST['approval_id']??0);
$mode=clean($_POST['mode']??'save');

$item=orlmsApprovalItem($pdo,$itemId);
if(!$item)jsonResponse(false,'Legislative item not found.');
if($item['endorsement_status']!=='Endorsed' && !in_array($item['current_status'],['Pending Approval','Approved','Vetoed','Revision Required'],true)){
    jsonResponse(false,'Only committee-endorsed items can enter the approval workflow.');
}

if($mode==='save'){
    $type=clean($_POST['action_type']??'Final Approval');
    $authorityUser=(int)($_POST['authority_user_id']??0)?:null;
    $authorityOffice=(int)($_POST['authority_office_id']??0)?:null;
    $remarks=trim((string)($_POST['remarks']??''));

    $allowed=['Final Approval','Executive Approval','Adoption','Veto Review','Veto Override','Return for Clarification'];
    if(!in_array($type,$allowed,true))jsonResponse(false,'Invalid approval action type.');

    $existing=null;
    if($approvalId>0){
        $q=$pdo->prepare('SELECT * FROM orlms_approval_actions WHERE id=:id AND legislative_item_id=:item');
        $q->execute([':id'=>$approvalId,':item'=>$itemId]);$existing=$q->fetch();
        if(!$existing)jsonResponse(false,'Approval action not found.');
        if(!in_array($existing['action_status'],['Pending','Returned'],true)){
            jsonResponse(false,'Completed approval actions cannot be edited.');
        }
    }

    try{
        $pdo->beginTransaction();

        if($existing){
            $pdo->prepare(
                'UPDATE orlms_approval_actions
                 SET action_type=:type,authority_user_id=:user,authority_office_id=:office,
                     remarks=:remarks,updated_at=NOW()
                 WHERE id=:id'
            )->execute([
                ':type'=>$type,':user'=>$authorityUser,':office'=>$authorityOffice,
                ':remarks'=>$remarks?:null,':id'=>$approvalId
            ]);
            orlmsApprovalHistory($pdo,$approvalId,$itemId,'Update',$existing['action_status'],$existing['action_status'],'Approval action details updated.');
            $message='Approval action updated.';
        }else{
            $pending=$pdo->prepare(
                'SELECT action_reference FROM orlms_approval_actions
                 WHERE legislative_item_id=:item
                   AND action_status="Pending"
                 ORDER BY id DESC LIMIT 1'
            );
            $pending->execute([':item'=>$itemId]);
            $pendingRef=$pending->fetchColumn();
            if($pendingRef && $item['current_status']!=='Vetoed'){
                $pdo->rollBack();
                jsonResponse(false,'A pending approval action already exists: '.$pendingRef.'.');
            }

            $ref=orlmsGenerateReference($pdo,'orlms_approval_actions','action_reference','APR');
            $pdo->prepare(
                'INSERT INTO orlms_approval_actions
                 (legislative_item_id,action_reference,action_type,action_status,
                  authority_user_id,authority_office_id,action_date,remarks,created_by,
                  created_at,updated_at)
                 VALUES(:item,:ref,:type,"Pending",:user,:office,NULL,:remarks,:created,NOW(),NOW())'
            )->execute([
                ':item'=>$itemId,':ref'=>$ref,':type'=>$type,':user'=>$authorityUser,
                ':office'=>$authorityOffice,':remarks'=>$remarks?:null,
                ':created'=>currentUserId()
            ]);
            $approvalId=(int)$pdo->lastInsertId();

            $old=$item['current_status'];
            if($old==='Endorsed'){
                $pdo->prepare('UPDATE legislative_items SET current_status="Pending Approval",updated_at=NOW() WHERE id=:id')
                    ->execute([':id'=>$itemId]);
                $pdo->prepare('UPDATE orlms_item_details SET draft_status="Pending Approval",updated_at=NOW() WHERE legislative_item_id=:id')
                    ->execute([':id'=>$itemId]);
                orlmsAddStatusHistory($pdo,$itemId,$old,'Pending Approval','Approval workflow started.');
                orlmsAddWorkflowHistory($pdo,$itemId,'APPROVAL','Start Approval',$old,'Pending Approval',
                    "Approval action {$ref} created.",null,$authorityOffice,$authorityUser);
            }

            orlmsApprovalHistory($pdo,$approvalId,$itemId,'Create',null,'Pending',
                "Approval action {$ref} created.");

            if($authorityUser){
                $pdo->prepare(
                    'INSERT INTO notifications
                     (user_id,system_id,notification_type,title,message,target_url,is_read,created_at)
                     VALUES(:user,:system,"ORLMS Approval","Legislative approval action assigned",
                      :message,:url,0,NOW())'
                )->execute([
                    ':user'=>$authorityUser,':system'=>orlmsSystemId(),
                    ':message'=>$item['reference_number'].' - '.$item['title'],
                    ':url'=>APP_URL.'/modules/approvals/view.php?id='.$itemId
                ]);
            }

            $message='Approval action created.';
        }

        logActivity(currentUserId(),'ORLMS Approval Action',
            "{$item['reference_number']} · action #{$approvalId}.");
        $pdo->commit();
        jsonResponse(true,$message,['id'=>$approvalId]);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save approval action.');
    }
}

if($mode==='transition'){
    $action=clean($_POST['action']??'');
    $remarks=trim((string)($_POST['remarks']??''));

    try{
        $pdo->beginTransaction();

        $q=$pdo->prepare(
            'SELECT a.*,li.current_status locked_item_status
             FROM orlms_approval_actions a
             JOIN legislative_items li ON li.id=a.legislative_item_id
             WHERE a.id=:id AND a.legislative_item_id=:item
             FOR UPDATE'
        );
        $q->execute([':id'=>$approvalId,':item'=>$itemId]);$approval=$q->fetch();
        if(!$approval){
            $pdo->rollBack();
            jsonResponse(false,'Approval action not found.');
        }

        $oldAction=$approval['action_status'];
        $oldItem=$approval['locked_item_status'];

        if($action==='approve'){
            if(!in_array($oldAction,['Pending','Returned'],true)){$pdo->rollBack();jsonResponse(false,'This action is not awaiting approval.');}
            $newAction='Approved';$newItem='Approved';
        }elseif($action==='return'){
            if(!in_array($oldAction,['Pending'],true)){$pdo->rollBack();jsonResponse(false,'Only pending actions can be returned.');}
            if($remarks===''){$pdo->rollBack();jsonResponse(false,'Return reason is required.');}
            $newAction='Returned';$newItem='Revision Required';
        }elseif($action==='reject'){
            if(!in_array($oldAction,['Pending'],true)){$pdo->rollBack();jsonResponse(false,'Only pending actions can be rejected.');}
            if($remarks===''){$pdo->rollBack();jsonResponse(false,'Rejection reason is required.');}
            $newAction='Rejected';$newItem='Rejected';
        }elseif($action==='veto'){
            if(!in_array($oldAction,['Pending'],true)){$pdo->rollBack();jsonResponse(false,'Only pending actions can be vetoed.');}
            if($remarks===''){$pdo->rollBack();jsonResponse(false,'Veto reason is required.');}
            $newAction='Vetoed';$newItem='Vetoed';
        }elseif($action==='override'){
            if($oldItem!=='Vetoed'){$pdo->rollBack();jsonResponse(false,'Veto override is only available for a vetoed item.');}
            $newAction='Overridden';$newItem='Approved';
        }elseif($action==='cancel'){
            if(in_array($oldAction,['Approved','Overridden','Rejected','Vetoed','Cancelled'],true)){$pdo->rollBack();jsonResponse(false,'This approval action is already finalized.');}
            $newAction='Cancelled';$newItem=$oldItem;
        }else{
            $pdo->rollBack();jsonResponse(false,'Invalid approval workflow action.');
        }

        $pdo->prepare(
            'UPDATE orlms_approval_actions
             SET action_status=:status_value,action_date=NOW(),
                 remarks=CASE WHEN :remarks_check<>"" THEN CONCAT(COALESCE(remarks,""),"\n",:remarks_value) ELSE remarks END,
                 updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':status_value'=>$newAction,
            ':remarks_check'=>$remarks,
            ':remarks_value'=>$remarks,
            ':id'=>$approvalId
        ]);

        if($newItem!==$oldItem){
            $pdo->prepare('UPDATE legislative_items SET current_status=:status,updated_at=NOW() WHERE id=:id')
                ->execute([':status'=>$newItem,':id'=>$itemId]);
            $pdo->prepare(
                'UPDATE orlms_item_details
                 SET draft_status=:status_value,
                     returned_at=CASE WHEN :status_returned="Revision Required" THEN NOW() ELSE returned_at END,
                     return_reason=CASE WHEN :status_reason="Revision Required" THEN :reason ELSE return_reason END,
                     updated_at=NOW()
                 WHERE legislative_item_id=:id'
            )->execute([
                ':status_value'=>$newItem,
                ':status_returned'=>$newItem,
                ':status_reason'=>$newItem,
                ':reason'=>$remarks?:null,
                ':id'=>$itemId
            ]);

            orlmsAddStatusHistory($pdo,$itemId,$oldItem,$newItem,
                "Approval action {$approval['action_reference']}: {$newAction}.");
            orlmsAddWorkflowHistory($pdo,$itemId,'APPROVAL',ucfirst($action),
                $oldItem,$newItem,$remarks?:("Approval action {$newAction}."),
                null,$approval['authority_office_id'],$approval['authority_user_id']);
        }

        orlmsApprovalHistory($pdo,$approvalId,$itemId,ucfirst($action),$oldAction,$newAction,$remarks);

        if($item['submitted_by']){
            $pdo->prepare(
                'INSERT INTO notifications
                 (user_id,system_id,notification_type,title,message,target_url,is_read,created_at)
                 VALUES(:user,:system,"ORLMS Approval","Approval workflow updated",
                  :message,:url,0,NOW())'
            )->execute([
                ':user'=>$item['submitted_by'],':system'=>orlmsSystemId(),
                ':message'=>$item['reference_number'].' · '.$newAction,
                ':url'=>APP_URL.'/modules/approvals/view.php?id='.$itemId
            ]);
        }

        logActivity(currentUserId(),'ORLMS Approval '.ucfirst($action),
            "{$item['reference_number']} · {$approval['action_reference']} · {$newAction}.");

        $pdo->commit();
        jsonResponse(true,"Approval action is now {$newAction}.",['item_status'=>$newItem]);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update approval workflow.');
    }
}

jsonResponse(false,'Unsupported approval mode.');
