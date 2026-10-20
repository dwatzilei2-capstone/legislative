<?php
declare(strict_types=1);

if(!isset($orlmsDraftingAction,$orlmsItemTypeCode)){
    throw new RuntimeException('ORLMS drafting endpoint configuration is missing.');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/orlms_drafting_helpers.php';

requirePermission('orlms.drafting.manage');

$pdo=db();
$typeCode=$orlmsItemTypeCode;
$label=orlmsItemTypeLabel($typeCode);

if($orlmsDraftingAction==='get'){
    $id=(int)($_GET['id']??0);
    $item=orlmsGetDraft($pdo,$id,$typeCode);
    if(!$item)jsonResponse(false,$label.' draft not found.');

    jsonResponse(true,'',[
        'item'=>$item,
        'authors'=>orlmsDraftAuthors($pdo,$id),
        'documents'=>orlmsDraftDocuments($pdo,$id),
    ]);
}

if($orlmsDraftingAction==='search'){
    $search=clean($_GET['search']??'');
    $status=clean($_GET['status']??'');
    $params=[':type'=>$typeCode];
    $where=["lit.code=:type","li.deleted_at IS NULL"];

    if($search!==''){
        $where[]='(li.reference_number LIKE :s1 OR li.title LIKE :s2 OR d.subject LIKE :s3)';
        $like='%'.$search.'%';
        $params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;
    }
    if($status!==''){
        $where[]='li.current_status=:status';
        $params[':status']=$status;
    }

    $stmt=$pdo->prepare(
        'SELECT li.id,li.reference_number,li.title,li.current_status,
                li.priority_level,li.updated_at,o.name originating_office,
                d.subject,d.draft_status,
                (SELECT COUNT(*) FROM orlms_item_authors a WHERE a.legislative_item_id=li.id) author_count,
                (SELECT COUNT(*) FROM orlms_draft_versions v WHERE v.legislative_item_id=li.id) version_count
         FROM legislative_items li
         JOIN legislative_item_types lit ON lit.id=li.item_type_id
         LEFT JOIN offices o ON o.id=li.originating_office_id
         LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
         WHERE '.implode(' AND ',$where).'
         ORDER BY li.updated_at DESC LIMIT 100'
    );
    $stmt->execute($params);
    jsonResponse(true,'',['items'=>$stmt->fetchAll()]);
}

if($_SERVER['REQUEST_METHOD']!=='POST'){
    jsonResponse(false,'Invalid request method.');
}
requireCsrf();

if($orlmsDraftingAction==='save'){
    $id=(int)($_POST['id']??0);
    $title=clean($_POST['title']??'');
    $summary=trim((string)($_POST['summary']??''));
    $officeId=(int)($_POST['originating_office_id']??0)?:null;
    $priority=clean($_POST['priority_level']??'Normal');
    $visibility=clean($_POST['visibility']??'Internal');
    $templateId=(int)($_POST['template_id']??0)?:null;
    $shortTitle=clean($_POST['short_title']??'');
    $subject=clean($_POST['subject']??'');
    $category=clean($_POST['legislative_category']??'');
    $policyArea=clean($_POST['policy_area']??'');
    $purpose=trim((string)($_POST['purpose']??''));
    $note=trim((string)($_POST['explanatory_note']??''));
    $body=trim((string)($_POST['body_text']??''));
    $legal=trim((string)($_POST['legal_basis']??''));
    $fiscal=trim((string)($_POST['fiscal_impact']??''));
    $keywords=clean($_POST['keywords']??'');
    $changeSummary=trim((string)($_POST['change_summary']??''));

    if($title==='')jsonResponse(false,$label.' title is required.');
    if(!in_array($priority,['Low','Normal','High','Urgent'],true))jsonResponse(false,'Invalid priority level.');
    if(!in_array($visibility,['Internal','Restricted','Public'],true))jsonResponse(false,'Invalid visibility.');

    $typeId=orlmsItemTypeId($pdo,$typeCode);
    if(!$typeId)jsonResponse(false,'Shared '.$label.' item type is missing from legislative_item_types.');

    if($templateId){
        $q=$pdo->prepare(
            'SELECT template_body FROM orlms_templates
             WHERE id=:id AND item_type_id=:type AND status="Active"'
        );
        $q->execute([':id'=>$templateId,':type'=>$typeId]);
        $templateBody=$q->fetchColumn();
        if($templateBody===false)jsonResponse(false,'Selected drafting template is invalid.');
        if($body==='')$body=(string)$templateBody;
    }

    $existing=null;
    if($id>0){
        $existing=orlmsGetDraft($pdo,$id,$typeCode);
        if(!$existing)jsonResponse(false,$label.' draft not found.');
        if(!orlmsCanEditDraft((string)$existing['current_status'])){
            jsonResponse(false,'This draft is locked because it has already entered the legislative workflow.');
        }
    }

    try{
        $pdo->beginTransaction();

        if($existing){
            $pdo->prepare(
                'UPDATE legislative_items
                 SET originating_office_id=:office,title=:title,summary=:summary,
                     priority_level=:priority,visibility=:visibility,updated_at=NOW()
                 WHERE id=:id'
            )->execute([
                ':office'=>$officeId,':title'=>$title,':summary'=>$summary?:null,
                ':priority'=>$priority,':visibility'=>$visibility,':id'=>$id
            ]);

            $pdo->prepare(
                'INSERT INTO orlms_item_details
                 (legislative_item_id,template_id,short_title,subject,legislative_category,
                  policy_area,purpose,explanatory_note,body_text,legal_basis,fiscal_impact,
                  keywords,draft_status,last_edited_by,created_at,updated_at)
                 VALUES(:item,:template,:short,:subject,:category,:policy,:purpose,:note,
                  :body,:legal,:fiscal,:keywords,:draft_status,:editor,NOW(),NOW())
                 ON DUPLICATE KEY UPDATE
                  template_id=VALUES(template_id),short_title=VALUES(short_title),
                  subject=VALUES(subject),legislative_category=VALUES(legislative_category),
                  policy_area=VALUES(policy_area),purpose=VALUES(purpose),
                  explanatory_note=VALUES(explanatory_note),body_text=VALUES(body_text),
                  legal_basis=VALUES(legal_basis),fiscal_impact=VALUES(fiscal_impact),
                  keywords=VALUES(keywords),last_edited_by=VALUES(last_edited_by),
                  updated_at=NOW()'
            )->execute([
                ':item'=>$id,':template'=>$templateId,':short'=>$shortTitle?:null,
                ':subject'=>$subject?:null,':category'=>$category?:null,
                ':policy'=>$policyArea?:null,':purpose'=>$purpose?:null,':note'=>$note?:null,
                ':body'=>$body?:null,':legal'=>$legal?:null,':fiscal'=>$fiscal?:null,
                ':keywords'=>$keywords?:null,':draft_status'=>$existing['current_status'],
                ':editor'=>currentUserId()
            ]);
            $action='Update Draft';
            $message=$label.' draft updated.';
        }else{
            $ref=orlmsGenerateReference($pdo,'legislative_items','reference_number',orlmsReferencePrefix($typeCode));
            $pdo->prepare(
                'INSERT INTO legislative_items
                 (reference_number,item_type_id,origin_system_id,originating_office_id,
                  title,summary,current_status,priority_level,visibility,created_by,
                  created_at,updated_at,deleted_at)
                 VALUES(:ref,:type,:system,:office,:title,:summary,"Draft",:priority,
                  :visibility,:user,NOW(),NOW(),NULL)'
            )->execute([
                ':ref'=>$ref,':type'=>$typeId,':system'=>orlmsSystemId(),':office'=>$officeId,
                ':title'=>$title,':summary'=>$summary?:null,':priority'=>$priority,
                ':visibility'=>$visibility,':user'=>currentUserId()
            ]);
            $id=(int)$pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO orlms_item_details
                 (legislative_item_id,template_id,short_title,subject,legislative_category,
                  policy_area,purpose,explanatory_note,body_text,legal_basis,fiscal_impact,
                  keywords,draft_status,last_edited_by,created_at,updated_at)
                 VALUES(:item,:template,:short,:subject,:category,:policy,:purpose,:note,
                  :body,:legal,:fiscal,:keywords,"Draft",:editor,NOW(),NOW())'
            )->execute([
                ':item'=>$id,':template'=>$templateId,':short'=>$shortTitle?:null,
                ':subject'=>$subject?:null,':category'=>$category?:null,
                ':policy'=>$policyArea?:null,':purpose'=>$purpose?:null,':note'=>$note?:null,
                ':body'=>$body?:null,':legal'=>$legal?:null,':fiscal'=>$fiscal?:null,
                ':keywords'=>$keywords?:null,':editor'=>currentUserId()
            ]);

            orlmsAddStatusHistory($pdo,$id,null,'Draft',$label.' drafting record created.');
            $action='Create Draft';
            $message=$label.' draft created.';
        }

        $authorCount=orlmsSyncAuthors($pdo,$id,$_POST);

        if(!empty($_FILES['supporting_document'])
            && ($_FILES['supporting_document']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            orlmsSaveSharedDocument(
                $pdo,$id,$_FILES['supporting_document'],
                clean($_POST['document_type']??'Supporting Document'),
                clean($_POST['document_title']??''),
                clean($_POST['document_visibility']??'Internal')
            );
        }

        $snapshot=orlmsCreateDraftSnapshot(
            $pdo,$id,
            $changeSummary!==''?$changeSummary:($existing?'Draft updated':'Initial draft created')
        );

        orlmsAddWorkflowHistory(
            $pdo,$id,'DRAFTING',$action,
            $existing?$existing['current_status']:null,
            $existing?$existing['current_status']:'Draft',
            ($changeSummary?:$message)." Draft version #{$snapshot}; authors={$authorCount}."
        );

        logActivity(currentUserId(),$action,
            "{$label} #{$id}; version={$snapshot}; authors={$authorCount}.");

        $pdo->commit();
        jsonResponse(true,$message,['id'=>$id,'version_id'=>$snapshot]);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        error_log('[ORLMS Draft Save] '.$e->getMessage());
        jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save the draft.');
    }
}

if($orlmsDraftingAction==='delete'){
    $id=(int)($_POST['id']??0);
    $item=orlmsGetDraft($pdo,$id,$typeCode);
    if(!$item)jsonResponse(false,$label.' draft not found.');
    if(!orlmsCanEditDraft((string)$item['current_status'])){
        jsonResponse(false,'Only editable drafts can be deactivated.');
    }

    $reviews=$pdo->prepare('SELECT COUNT(*) FROM orlms_reviews WHERE legislative_item_id=:id');
    $reviews->execute([':id'=>$id]);
    if((int)$reviews->fetchColumn()>0){
        jsonResponse(false,'This item already has review history and cannot be removed. Keep it in the workflow for audit integrity.');
    }

    try{
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE legislative_items SET deleted_at=NOW(),updated_at=NOW() WHERE id=:id')
            ->execute([':id'=>$id]);

        orlmsAddWorkflowHistory($pdo,$id,'DRAFTING','Deactivate Draft',
            $item['current_status'],'Deactivated','Draft soft-deactivated; historical records preserved.');
        logActivity(currentUserId(),'Deactivate '.$label,"{$item['reference_number']} {$item['title']}");
        $pdo->commit();
        jsonResponse(true,$label.' draft deactivated.');
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to deactivate draft.');
    }
}

if($orlmsDraftingAction==='submit'){
    $id=(int)($_POST['id']??0);
    $committeeId=(int)($_POST['committee_id']??0);
    $reviewerId=(int)($_POST['reviewer_id']??0)?:null;
    $submissionNote=trim((string)($_POST['submission_note']??''));

    $item=orlmsGetDraft($pdo,$id,$typeCode);
    if(!$item)jsonResponse(false,$label.' draft not found.');
    if(!orlmsCanEditDraft((string)$item['current_status'])){
        jsonResponse(false,'This draft has already been submitted or is not editable.');
    }
    if($committeeId<=0)jsonResponse(false,'Select the reviewing committee.');
    if(trim((string)$item['body_text'])==='')jsonResponse(false,'Draft body is required before submission.');
    if(trim((string)$item['subject'])==='')jsonResponse(false,'Subject is required before submission.');

    $authors=$pdo->prepare('SELECT COUNT(*) FROM orlms_item_authors WHERE legislative_item_id=:id');
    $authors->execute([':id'=>$id]);
    if((int)$authors->fetchColumn()<=0)jsonResponse(false,'Add at least one author, sponsor or proponent before submission.');

    $committee=$pdo->prepare('SELECT id,name FROM committees WHERE id=:id AND status="Active"');
    $committee->execute([':id'=>$committeeId]);$committeeRow=$committee->fetch();
    if(!$committeeRow)jsonResponse(false,'Selected committee is not active.');

    if($reviewerId){
        $reviewer=$pdo->prepare('SELECT COUNT(*) FROM users WHERE id=:id AND status="Active" AND deleted_at IS NULL');
        $reviewer->execute([':id'=>$reviewerId]);
        if((int)$reviewer->fetchColumn()===0)jsonResponse(false,'Selected reviewer is not an active user.');
    }

    try{
        $pdo->beginTransaction();

        $round=$pdo->prepare(
            'SELECT COALESCE(MAX(review_round),0)+1
             FROM orlms_reviews WHERE legislative_item_id=:id'
        );
        $round->execute([':id'=>$id]);$reviewRound=(int)$round->fetchColumn();

        $pdo->prepare(
            'INSERT INTO orlms_reviews
             (legislative_item_id,committee_id,reviewer_id,review_round,review_type,
              recommendation,comments,status,started_at,completed_at,created_by,
              created_at,updated_at)
             VALUES(:item,:committee,:reviewer,:round,"Committee Review",
              NULL,NULL,"Pending",NULL,NULL,:user,NOW(),NOW())'
        )->execute([
            ':item'=>$id,':committee'=>$committeeId,':reviewer'=>$reviewerId,
            ':round'=>$reviewRound,':user'=>currentUserId()
        ]);
        $reviewId=(int)$pdo->lastInsertId();
        orlmsCreateReviewChecklist($pdo,$reviewId);

        $snapshot=orlmsCreateDraftSnapshot($pdo,$id,'Submitted for committee review, round '.$reviewRound.'.');

        $old=$item['current_status'];
        $new='Submitted for Review';

        $pdo->prepare(
            'UPDATE legislative_items SET current_status=:status,updated_at=NOW() WHERE id=:id'
        )->execute([':status'=>$new,':id'=>$id]);

        $pdo->prepare(
            'UPDATE orlms_item_details
             SET draft_status=:status,submitted_by=:user,submitted_at=NOW(),
                 submission_note=:note,returned_at=NULL,return_reason=NULL,updated_at=NOW()
             WHERE legislative_item_id=:id'
        )->execute([
            ':status'=>$new,':user'=>currentUserId(),':note'=>$submissionNote?:null,':id'=>$id
        ]);

        orlmsAddStatusHistory($pdo,$id,$old,$new,
            "Submitted to {$committeeRow['name']} for review round {$reviewRound}.");
        orlmsAddWorkflowHistory(
            $pdo,$id,'SUBMISSION','Submit for Review',$old,$new,
            ($submissionNote?:'Draft submitted for committee review.')." Snapshot version #{$snapshot}.",
            $committeeId,null,$reviewerId
        );

        $targets=[];
        if($reviewerId){
            $targets[]=$reviewerId;
        }else{
            $members=$pdo->prepare(
                'SELECT user_id FROM committee_members
                 WHERE committee_id=:committee AND is_active=1'
            );
            $members->execute([':committee'=>$committeeId]);
            $targets=array_map('intval',$members->fetchAll(PDO::FETCH_COLUMN));
        }

        $notify=$pdo->prepare(
            'INSERT INTO notifications
             (user_id,system_id,notification_type,title,message,target_url,is_read,created_at)
             VALUES(:user,:system,"ORLMS Review","Draft submitted for committee review",
              :message,:url,0,NOW())'
        );
        foreach(array_unique(array_filter($targets)) as $uid){
            $notify->execute([
                ':user'=>$uid,':system'=>orlmsSystemId(),
                ':message'=>$item['reference_number'].' - '.$item['title'],
                ':url'=>APP_URL.'/modules/reviews/view.php?id='.$reviewId
            ]);
        }

        logActivity(currentUserId(),'Submit '.$label,
            "{$item['reference_number']} -> {$committeeRow['name']}; review #{$reviewId}.");
        $pdo->commit();
        jsonResponse(true,$label.' submitted for committee review.',['review_id'=>$reviewId]);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to submit the draft.');
    }
}

if($orlmsDraftingAction==='document_upload'){
    $id=(int)($_POST['id']??0);
    $item=orlmsGetDraft($pdo,$id,$typeCode);
    if(!$item)jsonResponse(false,$label.' draft not found.');
    if(!orlmsCanEditDraft((string)$item['current_status'])){
        jsonResponse(false,'Supporting documents are locked after submission. Add review-stage evidence from the Review module.');
    }
    if(empty($_FILES['document']))jsonResponse(false,'Choose a document.');

    try{
        $docId=orlmsSaveSharedDocument(
            $pdo,$id,$_FILES['document'],
            clean($_POST['document_type']??'Supporting Document'),
            clean($_POST['document_title']??''),
            clean($_POST['visibility']??'Internal')
        );
        orlmsAddWorkflowHistory($pdo,$id,'DRAFTING','Upload Document',
            $item['current_status'],$item['current_status'],'Supporting document uploaded.');
        logActivity(currentUserId(),'Upload '.$label.' Document',"{$item['reference_number']} document #{$docId}.");
        jsonResponse(true,'Supporting document uploaded.',['id'=>$docId]);
    }catch(Throwable $e){
        jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to upload document.');
    }
}

if($orlmsDraftingAction==='document_delete'){
    $docId=(int)($_POST['document_id']??0);
    $doc=$pdo->prepare(
        'SELECT d.*,li.current_status,lit.code item_type_code
         FROM documents d
         JOIN legislative_items li ON li.id=d.legislative_item_id
         JOIN legislative_item_types lit ON lit.id=li.item_type_id
         WHERE d.id=:id
           AND lit.code=:type
           AND li.deleted_at IS NULL'
    );
    $doc->execute([':id'=>$docId,':type'=>$typeCode]);$row=$doc->fetch();
    if(!$row)jsonResponse(false,'Document not found.');
    if(!orlmsCanEditDraft((string)$row['current_status']))jsonResponse(false,'Document is locked after submission.');

    try{
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM documents WHERE id=:id')->execute([':id'=>$docId]);
        $pdo->commit();

        $path=UPLOAD_DIR.ltrim((string)$row['file_path'],'/');
        if(is_file($path))@unlink($path);

        logActivity(currentUserId(),'Delete '.$label.' Document',"Document #{$docId} removed.");
        jsonResponse(true,'Document deleted.');
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to delete document.');
    }
}

jsonResponse(false,'Unsupported ORLMS drafting action.');
