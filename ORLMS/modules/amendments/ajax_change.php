<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_amendment_helpers.php';

if (!hasPermission('orlms.amendments.manage') && !(isLoggedIn() && in_array(currentRole(), ['Administrator', 'Legislative Staff'], true))) { requirePermission('orlms.amendments.manage'); }
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$mode=clean($_POST['mode']??'save');
$amendmentId=(int)($_POST['amendment_id']??0);
$changeId=(int)($_POST['change_id']??0);

$a=orlmsGetAmendment($pdo,$amendmentId);
if(!$a)jsonResponse(false,'Amendment not found.');
if(!in_array($a['status'],['Draft','Revision Required'],true)){
    jsonResponse(false,'Amendment changes are locked while under review or after approval.');
}

if($mode==='delete'){
    $q=$pdo->prepare('SELECT section_reference FROM orlms_amendment_changes WHERE id=:id AND amendment_id=:amendment');
    $q->execute([':id'=>$changeId,':amendment'=>$amendmentId]);
    $section=$q->fetchColumn();
    if($section===false)jsonResponse(false,'Change entry not found.');

    $pdo->prepare('DELETE FROM orlms_amendment_changes WHERE id=:id')->execute([':id'=>$changeId]);
    orlmsAmendmentHistory($pdo,$amendmentId,(int)$a['legislative_item_id'],
        'Delete Change',$a['status'],$a['status'],'Removed change entry for '.($section?:'unspecified section').'.');
    jsonResponse(true,'Change entry deleted.');
}

$section=clean($_POST['section_reference']??'');
$type=clean($_POST['change_type']??'Modify');
$previous=trim((string)($_POST['previous_text']??''));
$new=trim((string)($_POST['new_text']??''));
$rationale=trim((string)($_POST['rationale']??''));

if(!in_array($type,['Add','Modify','Delete','Replace','Repeal'],true))jsonResponse(false,'Invalid change type.');
if($section==='' && $previous==='' && $new==='')jsonResponse(false,'Enter a section reference or changed text.');
if(in_array($type,['Add','Modify','Replace'],true) && $new==='')jsonResponse(false,'New/revised text is required for this change type.');

try{
    if($changeId){
        $q=$pdo->prepare('SELECT id FROM orlms_amendment_changes WHERE id=:id AND amendment_id=:amendment');
        $q->execute([':id'=>$changeId,':amendment'=>$amendmentId]);
        if(!$q->fetchColumn())jsonResponse(false,'Change entry not found.');

        $pdo->prepare(
            'UPDATE orlms_amendment_changes
             SET section_reference=:section,change_type=:type,previous_text=:previous,
                 new_text=:new,rationale=:rationale
             WHERE id=:id'
        )->execute([
            ':section'=>$section?:null,':type'=>$type,':previous'=>$previous?:null,
            ':new'=>$new?:null,':rationale'=>$rationale?:null,':id'=>$changeId
        ]);
        $message='Change entry updated.';
    }else{
        $n=$pdo->prepare(
            'SELECT COALESCE(MAX(sequence_number),0)+1
             FROM orlms_amendment_changes WHERE amendment_id=:id'
        );
        $n->execute([':id'=>$amendmentId]);$seq=(int)$n->fetchColumn();

        $pdo->prepare(
            'INSERT INTO orlms_amendment_changes
             (amendment_id,section_reference,change_type,previous_text,new_text,
              rationale,sequence_number,created_at)
             VALUES(:amendment,:section,:type,:previous,:new,:rationale,:seq,NOW())'
        )->execute([
            ':amendment'=>$amendmentId,':section'=>$section?:null,':type'=>$type,
            ':previous'=>$previous?:null,':new'=>$new?:null,
            ':rationale'=>$rationale?:null,':seq'=>$seq
        ]);
        $changeId=(int)$pdo->lastInsertId();$message='Change entry added.';
    }

    orlmsAmendmentHistory($pdo,$amendmentId,(int)$a['legislative_item_id'],
        'Change Entry',$a['status'],$a['status'],
        "{$type} · ".($section?:'General provision').'.');

    jsonResponse(true,$message,['id'=>$changeId]);
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save change entry.');
}
