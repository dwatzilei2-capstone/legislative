<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/AI/OllamaService.php';

requirePermission('orlms.ai.view');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$action=trim((string)($_POST['action']??'classify'));$id=(int)($_POST['legislative_item_id']??0);
if($id<=0)jsonResponse(false,'Select an ordinance or resolution first.');
if(!in_array($action,['classify','review','apply'],true))jsonResponse(false,'Unknown AI action.');

$q=$pdo->prepare("SELECT li.id,li.reference_number,li.title,li.summary,li.priority_level,li.current_status,
                         t.name item_type,d.purpose,d.explanatory_note,d.body_text,d.legal_basis,d.fiscal_impact,
                         d.legislative_category,d.policy_area,d.keywords
                  FROM legislative_items li
                  JOIN legislative_item_types t ON t.id=li.item_type_id
                  LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
                  WHERE li.id=:id AND li.deleted_at IS NULL AND t.code IN ('ordinance','resolution') LIMIT 1");
$q->execute([':id'=>$id]);$item=$q->fetch();
if(!$item)jsonResponse(false,'Legislative document not found.');

if($action==='apply'){
    requirePermission('orlms.ai.manage');
    $latest=$pdo->prepare("SELECT id,result_json FROM orlms_ai_analysis WHERE legislative_item_id=:id AND analysis_type='classification' AND review_status='Pending Review' ORDER BY id DESC LIMIT 1");
    $latest->execute([':id'=>$id]);$analysis=$latest->fetch();
    if(!$analysis)jsonResponse(false,'No pending AI classification is available for approval. Run classification again.');
    $approved=json_decode((string)$analysis['result_json'],true);
    if(!is_array($approved))jsonResponse(false,'The saved AI classification is invalid. Run classification again.');
    $category=clean(is_scalar($approved['legislative_category']??null)?(string)$approved['legislative_category']:'');
    $policy=clean(is_scalar($approved['policy_area']??null)?(string)$approved['policy_area']:'');
    $keywordValues=is_array($approved['keywords']??null)?array_filter($approved['keywords'],'is_scalar'):[];
    $keywords=clean(implode(', ',array_map('strval',$keywordValues)));
    $priority=clean(is_scalar($approved['recommended_priority']??null)?(string)$approved['recommended_priority']:'Normal');
    if($category===''||$policy==='')jsonResponse(false,'The AI category and policy area are required.');
    if(!in_array($priority,['Low','Normal','High','Urgent'],true))$priority='Normal';
    $pdo->beginTransaction();
    try{
        $pdo->prepare('UPDATE orlms_item_details SET legislative_category=:category,policy_area=:policy,keywords=:keywords WHERE legislative_item_id=:id')
            ->execute([':category'=>$category,':policy'=>$policy,':keywords'=>$keywords,':id'=>$id]);
        $pdo->prepare('UPDATE legislative_items SET priority_level=:priority WHERE id=:id')->execute([':priority'=>$priority,':id'=>$id]);
        $pdo->prepare("UPDATE orlms_ai_analysis SET review_status='Approved',reviewed_by=:user,reviewed_at=NOW() WHERE id=:analysis")
            ->execute([':user'=>currentUserId(),':analysis'=>$analysis['id']]);
        $pdo->prepare('INSERT INTO legislative_item_status_history (legislative_item_id,previous_status,new_status,remarks,changed_by,created_at) VALUES (:id,:status,:status,:remarks,:user,NOW())')
            ->execute([':id'=>$id,':status'=>$item['current_status'],':remarks'=>'Human-approved Ollama classification applied: '.$category.' / '.$policy.'.',':user'=>currentUserId()]);
        $pdo->commit();
        logActivity(currentUserId(),'ORLMS AI Classification Approved',$item['reference_number'].' · '.$category.' / '.$policy.'.');
        jsonResponse(true,'The reviewed AI classification was applied to the document.');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

$ai=new OllamaService();
$result=$action==='review'?$ai->reviewDraft($item):$ai->classifyDocument($item);
if(empty($result['success']))jsonResponse(false,$result['error']??'Ollama could not complete the analysis.');
logActivity(currentUserId(),$action==='review'?'ORLMS AI Draft Review':'ORLMS AI Document Classification',$item['reference_number'].' analyzed using '.ORLMS_OLLAMA_MODEL.'.');
jsonResponse(true,$action==='review'?'Draft quality review completed.':'Document classification completed.',['data'=>$result]);
