<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_deadline_helpers.php';
requireLacmsPermission('lacms.deadlines.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$title=clean($_POST['title']??'');
$description=trim((string)($_POST['description']??''));
$type=clean($_POST['deadline_type']??'Legislative Task');
$due=clean($_POST['due_datetime']??'');
$priority=clean($_POST['priority_level']??'Normal');
$legislative=(int)($_POST['legislative_item_id']??0)?:null;
$agenda=(int)($_POST['agenda_id']??0)?:null;
$event=(int)($_POST['calendar_event_id']??0)?:null;
$meeting=(int)($_POST['meeting_id']??0)?:null;
$committee=(int)($_POST['committee_id']??0)?:null;
$office=(int)($_POST['office_id']??0)?:null;
$responsible=(int)($_POST['responsible_user_id']??0)?:null;
$policy=clean($_POST['reminder_policy']??'Standard');

if($title==='')jsonResponse(false,'Deadline title is required.');
if($due===''||strtotime($due)===false)jsonResponse(false,'Valid due date/time is required.');
if(!in_array($priority,['Low','Normal','High','Urgent'],true))jsonResponse(false,'Invalid priority.');
if(!in_array($policy,['None','Minimal','Standard','Extended','Urgent'],true))jsonResponse(false,'Invalid reminder policy.');

$existing=null;
if($id){
    $q=$pdo->prepare('SELECT * FROM lacms_deadlines WHERE id=:id');
    $q->execute([':id'=>$id]);$existing=$q->fetch();
    if(!$existing)jsonResponse(false,'Deadline not found.');
    if(in_array($existing['status'],['Completed','Cancelled'],true)){
        jsonResponse(false,'Completed or cancelled deadlines cannot be edited.');
    }
}

try{
    $pdo->beginTransaction();

    if($existing){
        $old=$existing['status'];
        $new=$old==='Overdue' && strtotime($due)>time() ? 'Pending' : $old;

        $pdo->prepare(
            'UPDATE lacms_deadlines
             SET title=:title,description=:description,deadline_type=:type,
                 due_datetime=:due,priority_level=:priority,status=:status,
                 legislative_item_id=:legislative,agenda_id=:agenda,
                 calendar_event_id=:event,meeting_id=:meeting,committee_id=:committee,
                 office_id=:office,responsible_user_id=:responsible,
                 reminder_policy=:policy,updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':title'=>$title,':description'=>$description?:null,':type'=>$type,
            ':due'=>$due,':priority'=>$priority,':status'=>$new,
            ':legislative'=>$legislative,':agenda'=>$agenda,':event'=>$event,
            ':meeting'=>$meeting,':committee'=>$committee,':office'=>$office,
            ':responsible'=>$responsible,':policy'=>$policy,':id'=>$id
        ]);

        $primary=$pdo->prepare(
            "SELECT id FROM lacms_deadline_assignments
             WHERE deadline_id=:deadline
               AND assignment_role='Primary'
             ORDER BY id LIMIT 1"
        );
        $primary->execute([':deadline'=>$id]);
        $primaryId=(int)($primary->fetchColumn()?:0);

        if($responsible||$office||$committee){
            if($primaryId){
                $pdo->prepare(
                    'UPDATE lacms_deadline_assignments
                     SET user_id=:user,office_id=:office,committee_id=:committee,
                         status="Assigned",assigned_by=:by,assigned_at=NOW()
                     WHERE id=:id'
                )->execute([
                    ':user'=>$responsible,':office'=>$office,':committee'=>$committee,
                    ':by'=>currentUserId(),':id'=>$primaryId
                ]);
            }else{
                $pdo->prepare(
                    'INSERT INTO lacms_deadline_assignments
                     (deadline_id,user_id,office_id,committee_id,assignment_role,status,
                      assigned_by,assigned_at)
                     VALUES(:deadline,:user,:office,:committee,"Primary","Assigned",:by,NOW())'
                )->execute([
                    ':deadline'=>$id,':user'=>$responsible,':office'=>$office,
                    ':committee'=>$committee,':by'=>currentUserId()
                ]);
            }
        }elseif($primaryId){
            $pdo->prepare('DELETE FROM lacms_deadline_assignments WHERE id=:id')
                ->execute([':id'=>$primaryId]);
        }

        if($existing['due_datetime']!==$due || $existing['reminder_policy']!==$policy){
            $pdo->prepare(
                "DELETE FROM lacms_deadline_reminders
                 WHERE deadline_id=:deadline AND status='Scheduled'"
            )->execute([':deadline'=>$id]);
            lacmsCreateDefaultDeadlineReminders($pdo,$id,$policy);
        }

        lacmsDeadlineHistory($pdo,$id,'Update',$old,$new,'Deadline details updated.');
        $message='Deadline updated.';
    }else{
        $ref=lacmsGenerateReference($pdo,'lacms_deadlines','deadline_reference','DLN',$due);
        $initial=strtotime($due)<time()?'Overdue':'Pending';

        $pdo->prepare(
            'INSERT INTO lacms_deadlines
             (deadline_reference,title,description,deadline_type,due_datetime,priority_level,
              status,legislative_item_id,agenda_id,calendar_event_id,meeting_id,committee_id,
              office_id,responsible_user_id,reminder_policy,created_by,created_at,updated_at)
             VALUES(:ref,:title,:description,:type,:due,:priority,:status,:legislative,
              :agenda,:event,:meeting,:committee,:office,:responsible,:policy,:user,NOW(),NOW())'
        )->execute([
            ':ref'=>$ref,':title'=>$title,':description'=>$description?:null,':type'=>$type,
            ':due'=>$due,':priority'=>$priority,':status'=>$initial,
            ':legislative'=>$legislative,':agenda'=>$agenda,':event'=>$event,
            ':meeting'=>$meeting,':committee'=>$committee,':office'=>$office,
            ':responsible'=>$responsible,':policy'=>$policy,':user'=>currentUserId()
        ]);
        $id=(int)$pdo->lastInsertId();

        if($responsible){
            $pdo->prepare(
                'INSERT INTO lacms_deadline_assignments
                 (deadline_id,user_id,office_id,committee_id,assignment_role,status,
                  assigned_by,assigned_at)
                 VALUES(:deadline,:user,:office,:committee,"Primary","Assigned",:by,NOW())'
            )->execute([
                ':deadline'=>$id,':user'=>$responsible,':office'=>$office,
                ':committee'=>$committee,':by'=>currentUserId()
            ]);
        }

        $reminders=lacmsCreateDefaultDeadlineReminders($pdo,$id,$policy);
        lacmsDeadlineHistory($pdo,$id,'Create',null,$initial,
            "Deadline {$ref} created with {$reminders} scheduled reminder(s).");
        $message='Deadline created.';
    }

    lacmsLogActivity(currentUserId(),'LACMS Deadline Save',"Deadline #{$id}: {$title}.");
    $pdo->commit();
    jsonResponse(true,$message,['id'=>$id]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save deadline.');
}
