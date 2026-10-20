<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.quorum.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$itemId=(int)($_POST['legislative_item_id']??0)?:null;
$committeeId=(int)($_POST['committee_id']??0)?:null;
$officeId=(int)($_POST['office_id']??0)?:null;
$title=clean($_POST['session_title']??'');
$type=clean($_POST['session_type']??'Regular Session');
$mode=clean($_POST['session_mode']??'On-site');
$date=clean($_POST['session_date']??'');
$start=clean($_POST['start_time']??'');
$end=clean($_POST['end_time']??'')?:null;
$venue=clean($_POST['venue']??'');
$link=clean($_POST['meeting_link']??'');
$presiding=(int)($_POST['presiding_officer_id']??0)?:null;
$secretary=(int)($_POST['secretary_user_id']??0)?:null;
$notes=trim((string)($_POST['notes']??''));
$memberIds=$_POST['member_ids']??[];
$includeCommittee=isset($_POST['include_committee_roster']);

if(!is_array($memberIds))$memberIds=[$memberIds];
$memberIds=array_values(array_unique(array_filter(array_map('intval',$memberIds))));

$errors=[];
if($title==='')$errors[]='Session title is required.';
if(!$date||!strtotime($date))$errors[]='A valid session date is required.';
if(!$start||strtotime($start)===false)$errors[]='A valid start time is required.';
if(!in_array($mode,['On-site','Online','Hybrid'],true))$errors[]='Invalid session mode.';
if($link!==''&&filter_var($link,FILTER_VALIDATE_URL)===false)$errors[]='Meeting link must be a valid URL.';
if($end!==null && strtotime($date.' '.$end) <= strtotime($date.' '.$start))$errors[]='End time must be later than the start time.';

if($includeCommittee&&$committeeId){
 $q=$pdo->prepare(
  'SELECT cm.user_id FROM committee_members cm WHERE cm.committee_id=:id1 AND cm.is_active=1
   UNION
   SELECT u.id user_id FROM users u 
   JOIN committees c ON (c.office_id=u.office_id OR (c.id=4 AND u.office_id=6))
   WHERE c.id=:id2 AND u.status="Active" AND u.deleted_at IS NULL AND u.role_id=3'
 );
 $q->execute([':id1'=>$committeeId,':id2'=>$committeeId]);
 $commUserIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));

 $ensureCm=$pdo->prepare(
  'INSERT INTO committee_members (committee_id, user_id, position, is_active, created_at, updated_at)
   VALUES (:comm, :user, "Member", 1, NOW(), NOW())
   ON DUPLICATE KEY UPDATE is_active=1, updated_at=NOW()'
 );
 foreach($commUserIds as $cuid){
   try{
     $ensureCm->execute([':comm'=>$committeeId,':user'=>$cuid]);
   }catch(Throwable $ignored){}
 }

 $memberIds=array_values(array_unique(array_merge($memberIds,$commUserIds)));
}

$existing=null;
if($id>0){
 $q=$pdo->prepare('SELECT * FROM vqd_sessions WHERE id=:id');$q->execute([':id'=>$id]);$existing=$q->fetch();
 if(!$existing)$errors[]='Voting session not found.';
 if($existing){
   $lock=$pdo->prepare("SELECT COUNT(*) FROM vqd_quorum_checks WHERE session_id=:id AND verification_status IN ('Verified','Certified')");
   $lock->execute([':id'=>$id]);
   if((int)$lock->fetchColumn()>0)$errors[]='The roster cannot be structurally changed after quorum verification. Revoke the certified quorum or create a new session.';
 }
}
if(!$memberIds)$errors[]='Add at least one official session member.';

if(!$errors){
 $newStart=$date.' '.$start;
 $newEnd=$date.' '.($end?:date('H:i:s',strtotime($start.' +1 hour')));

 $conditions=[];
 $conflictParams=[':id'=>$id,':new_start'=>$newStart,':new_end'=>$newEnd];

 if($committeeId){$conditions[]='s.committee_id=:committee';$conflictParams[':committee']=$committeeId;}
 if($presiding){$conditions[]='s.presiding_officer_id=:presiding';$conflictParams[':presiding']=$presiding;}
 if($venue!==''){$conditions[]='LOWER(TRIM(s.venue))=LOWER(:venue)';$conflictParams[':venue']=$venue;}

 if($conditions){
   $conflict=$pdo->prepare(
    'SELECT s.reference_number,s.session_title,s.session_date,s.start_time,s.end_time
     FROM vqd_sessions s
     WHERE s.id<>:id
       AND s.status<>"Cancelled"
       AND ('.implode(' OR ',$conditions).')
       AND TIMESTAMP(s.session_date,s.start_time)<:new_end
       AND TIMESTAMP(s.session_date,COALESCE(s.end_time,ADDTIME(s.start_time,"01:00:00")))>:new_start
     LIMIT 5'
   );
   $conflict->execute($conflictParams);
   $rows=$conflict->fetchAll();
   if($rows){
     $errors[]='Schedule conflict detected with '.implode('; ',array_map(
       fn($r)=>$r['reference_number'].' - '.$r['session_title'],
       $rows
     )).'.';
   }
 }

 if(!$errors && $memberIds){
   $marks=implode(',',array_fill(0,count($memberIds),'?'));
   $sql=
    'SELECT DISTINCT s.reference_number,s.session_title,u.full_name
     FROM vqd_sessions s
     JOIN vqd_session_members sm ON sm.session_id=s.id
     JOIN users u ON u.id=sm.user_id
     WHERE s.id<>?
       AND s.status<>"Cancelled"
       AND sm.user_id IN ('.$marks.')
       AND TIMESTAMP(s.session_date,s.start_time)<?
       AND TIMESTAMP(s.session_date,COALESCE(s.end_time,ADDTIME(s.start_time,"01:00:00")))>?
     LIMIT 10';
   $args=array_merge([$id],$memberIds,[$newEnd,$newStart]);
   $memberConflict=$pdo->prepare($sql);
   $memberConflict->execute($args);
   $rows=$memberConflict->fetchAll();
   if($rows){
     $errors[]='One or more roster members are already scheduled in an overlapping VQDSS session: '.implode('; ',array_map(
       fn($r)=>$r['full_name'].' in '.$r['reference_number'],
       $rows
     )).'.';
   }
 }
}

if($errors)jsonResponse(false,implode(' ',$errors));

try{
 $pdo->beginTransaction();

 if($id>0){
   $old=$existing['status'];
   $status=$existing['status']==='Draft'?'Scheduled':$existing['status'];
   $pdo->prepare(
    'UPDATE vqd_sessions
     SET legislative_item_id=:item,committee_id=:committee,office_id=:office,
         session_title=:title,session_type=:type,session_mode=:mode,
         session_date=:date,start_time=:start,end_time=:end,venue=:venue,
         meeting_link=:link,presiding_officer_id=:presiding,
         secretary_user_id=:secretary,status=:status,notes=:notes,updated_at=NOW()
     WHERE id=:id'
   )->execute([
    ':item'=>$itemId,':committee'=>$committeeId,':office'=>$officeId,':title'=>$title,
    ':type'=>$type,':mode'=>$mode,':date'=>$date,':start'=>$start,':end'=>$end,
    ':venue'=>$venue?:null,':link'=>$link?:null,':presiding'=>$presiding,
    ':secretary'=>$secretary,':status'=>$status,':notes'=>$notes?:null,':id'=>$id
   ]);
   vqdSessionHistory($pdo,$id,'Update',$old,$status,'Session information and official roster updated.');
 }else{
   $ref=vqdGenerateReference($pdo,'vqd_sessions','reference_number','SES',$date);
   $status='Scheduled';
   $pdo->prepare(
    'INSERT INTO vqd_sessions
     (reference_number,legislative_item_id,committee_id,office_id,session_title,
      session_type,session_mode,session_date,start_time,end_time,venue,meeting_link,
      presiding_officer_id,secretary_user_id,status,notes,created_by,created_at,updated_at)
     VALUES(:ref,:item,:committee,:office,:title,:type,:mode,:date,:start,:end,:venue,
      :link,:presiding,:secretary,:status,:notes,:user,NOW(),NOW())'
   )->execute([
    ':ref'=>$ref,':item'=>$itemId,':committee'=>$committeeId,':office'=>$officeId,
    ':title'=>$title,':type'=>$type,':mode'=>$mode,':date'=>$date,':start'=>$start,
    ':end'=>$end,':venue'=>$venue?:null,':link'=>$link?:null,':presiding'=>$presiding,
    ':secretary'=>$secretary,':status'=>$status,':notes'=>$notes?:null,':user'=>currentUserId()
   ]);
   $id=(int)$pdo->lastInsertId();
   vqdSessionHistory($pdo,$id,'Create',null,$status,'Voting session created.');
 }

 $pdo->prepare('DELETE FROM vqd_session_members WHERE session_id=:id')->execute([':id'=>$id]);

 $memberStmt=$pdo->prepare(
  'SELECT u.id,u.full_name,cm.position
   FROM users u
   LEFT JOIN committee_members cm ON cm.user_id=u.id AND cm.committee_id=:committee AND cm.is_active=1
   WHERE u.id=:user AND u.status="Active" AND u.deleted_at IS NULL'
 );
 $insert=$pdo->prepare(
  'INSERT INTO vqd_session_members
   (session_id,user_id,committee_id,position_title,membership_type,is_voting_member,
    eligibility_status,attendance_status,attendance_source,conflict_declared,
    added_by,created_at,updated_at)
   VALUES(:session,:user,:committee,:position,"Member",1,"Eligible","Pending",
    "Manual",0,:added_by,NOW(),NOW())'
 );

 $saved=0;
 foreach($memberIds as $uid){
   $memberStmt->execute([':committee'=>$committeeId,':user'=>$uid]);
   $u=$memberStmt->fetch();
   if(!$u)continue;
   $insert->execute([
    ':session'=>$id,':user'=>$uid,':committee'=>$committeeId,
    ':position'=>$u['position']?:null,':added_by'=>currentUserId()
   ]);
   $saved++;
 }

 if($saved===0)throw new RuntimeException('No valid active users were available for the session roster.');

 vqdLogActivity(currentUserId(),$existing?'Update Voting Session':'Create Voting Session',
  "Session #{$id} {$title}; {$saved} official member(s).");

 $pdo->commit();
 jsonResponse(true,$existing?'Session updated successfully.':'Session created successfully.',['id'=>$id]);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 error_log('[VQDSS Session Save] '.$e->getMessage());
 jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save voting session.');
}
