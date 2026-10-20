<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/AI/OllamaService.php';
requireLacmsPermission('lacms.ai.view');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();
$pdo=db();$type=trim((string)($_POST['analysis_type']??''));$id=(int)($_POST['record_id']??0);
if($id<=0)jsonResponse(false,'Select a record first.');

if($type==='apply_priority'){
    requireLacmsPermission('lacms.ai.manage');
    $latest=$pdo->prepare("SELECT id,result_json FROM lacms_ai_analysis WHERE source_type='Priority' AND source_id=:id AND review_status='Advisory' ORDER BY id DESC LIMIT 1");$latest->execute([':id'=>$id]);$analysis=$latest->fetch();
    if(!$analysis)jsonResponse(false,'No pending AI priority analysis is available. Run the analysis again.');
    $approved=json_decode((string)$analysis['result_json'],true);$priority=trim((string)($approved['recommended_priority']??''));
    if(!in_array($priority,['Low','Normal','High','Urgent'],true))jsonResponse(false,'Invalid priority recommendation.');
    $q=$pdo->prepare('SELECT reference_number,priority_level,current_status FROM legislative_items WHERE id=:id AND deleted_at IS NULL');$q->execute([':id'=>$id]);$item=$q->fetch();
    if(!$item)jsonResponse(false,'Legislative item not found.');
    $pdo->beginTransaction();try{
        $pdo->prepare('UPDATE legislative_items SET priority_level=:priority WHERE id=:id')->execute([':priority'=>$priority,':id'=>$id]);
        $pdo->prepare('INSERT INTO legislative_item_status_history (legislative_item_id,previous_status,new_status,remarks,changed_by,created_at) VALUES (:id,:status,:status,:remarks,:user,NOW())')->execute([
            ':id'=>$id,':status'=>$item['current_status'],':remarks'=>'Human-approved LACMS Ollama priority recommendation applied: '.$priority.'.',':user'=>currentUserId()]);
        $pdo->prepare("UPDATE lacms_ai_analysis SET review_status='Approved',reviewed_by=:user,reviewed_at=NOW() WHERE id=:analysis")->execute([':user'=>currentUserId(),':analysis'=>$analysis['id']]);
        $pdo->commit();lacmsLogActivity(currentUserId(),'LACMS AI Priority Approved',$item['reference_number'].' · '.$priority.'.');jsonResponse(true,'Reviewed AI priority was applied.');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

$ai=new OllamaService();
if($type==='priority'){
    $q=$pdo->prepare('SELECT li.id,li.reference_number,li.title,li.summary,li.current_status,li.priority_level,d.legislative_category,d.policy_area FROM legislative_items li LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id WHERE li.id=:id AND li.deleted_at IS NULL');$q->execute([':id'=>$id]);$c=$q->fetch();
    if(!$c)jsonResponse(false,'Legislative item not found.');$result=$ai->analyzePriority($c);$label=$c['reference_number'];
}elseif($type==='calendar'){
    $q=$pdo->prepare('SELECT * FROM lacms_calendar_events WHERE id=:id');$q->execute([':id'=>$id]);$c=$q->fetch();if(!$c)jsonResponse(false,'Calendar event not found.');
    $end=$c['end_datetime']?:$c['start_datetime'];$x=$pdo->prepare('SELECT event_reference,title,start_datetime,end_datetime,venue FROM lacms_calendar_events WHERE id<>:id AND status<>"Cancelled" AND start_datetime<:event_end AND COALESCE(end_datetime,start_datetime)>:event_start ORDER BY start_datetime');$x->execute([':id'=>$id,':event_end'=>$end,':event_start'=>$c['start_datetime']]);$c['conflicting_events']=$x->fetchAll();$result=$ai->adviseSchedule($c);$label=$c['event_reference'];
}elseif($type==='synchronization'){
    $q=$pdo->prepare('SELECT id,sync_reference,title,sync_type,priority_level,executive_position,legislative_position,resolution_notes,status FROM lacms_sync_records WHERE id=:id');$q->execute([':id'=>$id]);$c=$q->fetch();if(!$c)jsonResponse(false,'Synchronization record not found.');$result=$ai->draftSynchronizationBrief($c);$label=$c['sync_reference'];
}else jsonResponse(false,'Unknown AI analysis type.');

if(empty($result['success']))jsonResponse(false,$result['error']??'Ollama could not complete the analysis.');
lacmsLogActivity(currentUserId(),'LACMS Ollama '.ucfirst($type).' Analysis',$label.' analyzed using '.LACMS_OLLAMA_MODEL.'.');
jsonResponse(true,'Ollama analysis completed.',['data'=>$result]);
