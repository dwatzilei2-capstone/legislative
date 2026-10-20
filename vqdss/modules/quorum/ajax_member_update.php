<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.quorum.attendance');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();

if(isset($_POST['action']) && $_POST['action']==='mark_all_present'){
 $checkId=(int)($_POST['quorum_check_id']??0);
 $qc=$pdo->prepare('SELECT * FROM vqd_quorum_checks WHERE id=:id');
 $qc->execute([':id'=>$checkId]);
 $check=$qc->fetch();
 if(!$check)jsonResponse(false,'Quorum check not found.');
 if($check['verification_status']==='Certified')jsonResponse(false,'A certified quorum roster is locked. Revoke certification before changing attendance.');

 try{
  $pdo->beginTransaction();
  $pdo->prepare(
   'UPDATE vqd_quorum_check_members
    SET attendance_status="Present",voting_eligibility="Eligible",
        included_in_quorum_count=1,verified_by=:u,verified_at=NOW(),updated_at=NOW()
    WHERE quorum_check_id=:check'
  )->execute([':u'=>currentUserId(),':check'=>$checkId]);

  $pdo->prepare(
   'UPDATE vqd_session_members sm
    JOIN vqd_quorum_check_members qcm ON qcm.session_member_id=sm.id
    SET sm.attendance_status="Present",sm.attendance_source="Quorum Verification",
        sm.eligibility_status="Eligible",
        sm.check_in_at=COALESCE(sm.check_in_at,NOW()),sm.updated_at=NOW()
    WHERE qcm.quorum_check_id=:check'
  )->execute([':check'=>$checkId]);

  $stats=vqdRefreshQuorum($pdo,$checkId);
  vqdQuorumHistory($pdo,$checkId,'Roster Batch Update',$check['verification_status'],$check['verification_status'],
   "Marked all roster members Present & Eligible; quorum={$stats['result']}.");

  $pdo->commit();
  jsonResponse(true,'All session members have been marked Present and Eligible.',['stats'=>$stats]);
 }catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to batch update roster attendance.');
 }
}

$id=(int)($_POST['id']??0);
$attendance=clean($_POST['attendance_status']??'Pending');
$eligibility=clean($_POST['voting_eligibility']??'Eligible');
$note=trim((string)($_POST['attendance_note']??''));
$include=isset($_POST['included_in_quorum_count'])?1:0;

if(!in_array($attendance,['Pending','Present','Remote Present','Absent','Excused'],true))jsonResponse(false,'Invalid attendance status.');
if(!in_array($eligibility,['Eligible','Ineligible','Abstain Only','Conflict'],true))jsonResponse(false,'Invalid voting eligibility.');

$stmt=$pdo->prepare(
 'SELECT qcm.*,qc.verification_status,qc.session_id
  FROM vqd_quorum_check_members qcm
  JOIN vqd_quorum_checks qc ON qc.id=qcm.quorum_check_id
  WHERE qcm.id=:id'
);
$stmt->execute([':id'=>$id]);$row=$stmt->fetch();
if(!$row)jsonResponse(false,'Quorum roster member not found.');
if($row['verification_status']==='Certified')jsonResponse(false,'A certified quorum roster is locked. Revoke certification before changing attendance.');

try{
 $pdo->beginTransaction();

 $pdo->prepare(
  'UPDATE vqd_quorum_check_members
   SET attendance_status=:attendance,voting_eligibility=:eligibility,
       included_in_quorum_count=:include,attendance_note=:note,
       verified_by=:user,verified_at=NOW(),updated_at=NOW()
   WHERE id=:id'
 )->execute([
  ':attendance'=>$attendance,':eligibility'=>$eligibility,':include'=>$include,
  ':note'=>$note?:null,':user'=>currentUserId(),':id'=>$id
 ]);

 if($row['session_member_id']){
   $pdo->prepare(
    'UPDATE vqd_session_members
     SET attendance_status=:attendance,
         attendance_source="Quorum Verification",
         eligibility_status=:eligibility,
         conflict_declared=:conflict,
         conflict_notes=:conflict_notes,
         check_in_at=CASE WHEN :present=1 AND check_in_at IS NULL THEN NOW() ELSE check_in_at END,
         updated_at=NOW()
     WHERE id=:id'
   )->execute([
    ':attendance'=>$attendance,':eligibility'=>$eligibility,
    ':conflict'=>$eligibility==='Conflict'?1:0,
    ':conflict_notes'=>$eligibility==='Conflict'?$note:null,
    ':present'=>in_array($attendance,['Present','Remote Present'],true)?1:0,
    ':id'=>$row['session_member_id']
   ]);
 }

 $stats=vqdRefreshQuorum($pdo,(int)$row['quorum_check_id']);
 vqdQuorumHistory($pdo,(int)$row['quorum_check_id'],'Roster Update',$row['verification_status'],$row['verification_status'],
  "Updated attendance/eligibility for user #{$row['user_id']}; quorum={$stats['result']}.");

 $pdo->commit();
 jsonResponse(true,'Roster member updated.',['stats'=>$stats]);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update quorum member.');
}
