<?php
declare(strict_types=1);

require_once __DIR__ . '/orlms_helpers.php';

function orlmsDraftEditableStatuses(): array
{
    return ['Draft','Returned','Revision Required'];
}

function orlmsCanEditDraft(?string $status): bool
{
    if ($status === null) {
        return false;
    }
    return in_array($status, orlmsDraftEditableStatuses(), true);
}

function orlmsItemTypeLabel(string $code): string
{
    return $code==='resolution' ? 'Resolution' : 'Ordinance';
}

function orlmsReferencePrefix(string $code): string
{
    return $code==='resolution' ? 'RES' : 'ORD';
}

function orlmsDefaultReviewChecklist(): array
{
    return [
        ['REQ-FIELDS','Required legislative fields are complete'],
        ['FORM-TEMPLATE','Draft follows the required legislative form/template'],
        ['LEGAL-BASIS','Legal authority and cited basis are sufficiently stated'],
        ['LANGUAGE','Provisions are clear, consistent and implementable'],
        ['IMPACT','Fiscal/implementation implications are identified where applicable'],
    ];
}

function orlmsGetDraft(PDO $pdo,int $id,string $typeCode): ?array
{
    $stmt=$pdo->prepare(
        'SELECT li.*,lit.code item_type_code,lit.name item_type_name,
                o.name originating_office,
                d.id detail_id,d.template_id,d.short_title,d.subject,
                d.legislative_category,d.policy_area,d.purpose,
                d.explanatory_note,d.body_text,d.legal_basis,d.fiscal_impact,
                d.keywords,d.draft_status,d.submitted_by,d.submitted_at,
                d.submission_note,d.returned_at,d.return_reason,d.last_edited_by,
                t.template_name,t.template_code,t.template_body
         FROM legislative_items li
         JOIN legislative_item_types lit ON lit.id=li.item_type_id
         LEFT JOIN offices o ON o.id=li.originating_office_id
         LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
         LEFT JOIN orlms_templates t ON t.id=d.template_id
         WHERE li.id=:id
           AND lit.code=:type
           AND li.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([':id'=>$id,':type'=>$typeCode]);
    $row=$stmt->fetch();
    return $row?:null;
}

function orlmsDraftAuthors(PDO $pdo,int $itemId): array
{
    $stmt=$pdo->prepare(
        'SELECT a.*,u.full_name user_name,o.name office_name
         FROM orlms_item_authors a
         LEFT JOIN users u ON u.id=a.user_id
         LEFT JOIN offices o ON o.id=a.office_id
         WHERE a.legislative_item_id=:id
         ORDER BY a.sequence_number,a.id'
    );
    $stmt->execute([':id'=>$itemId]);
    return $stmt->fetchAll();
}

function orlmsDraftDocuments(PDO $pdo,int $itemId): array
{
    $stmt=$pdo->prepare(
        'SELECT d.*,u.full_name uploaded_by_name
         FROM documents d
         LEFT JOIN users u ON u.id=d.uploaded_by
         WHERE d.legislative_item_id=:id
           AND (d.system_id=:system OR d.system_id IS NULL)
         ORDER BY d.created_at DESC,d.id DESC'
    );
    $stmt->execute([':id'=>$itemId,':system'=>orlmsSystemId()]);
    return $stmt->fetchAll();
}

function orlmsDraftVersions(PDO $pdo,int $itemId): array
{
    $stmt=$pdo->prepare(
        'SELECT v.*,u.full_name created_by_name
         FROM orlms_draft_versions v
         LEFT JOIN users u ON u.id=v.created_by
         WHERE v.legislative_item_id=:id
         ORDER BY v.version_number DESC'
    );
    $stmt->execute([':id'=>$itemId]);
    return $stmt->fetchAll();
}

function orlmsWorkflow(PDO $pdo,int $itemId): array
{
    $stmt=$pdo->prepare(
        'SELECT h.*,u.full_name actor_name,c.name to_committee_name,
                o.name to_office_name,au.full_name assigned_user_name
         FROM orlms_workflow_history h
         LEFT JOIN users u ON u.id=h.acted_by
         LEFT JOIN committees c ON c.id=h.to_committee_id
         LEFT JOIN offices o ON o.id=h.to_office_id
         LEFT JOIN users au ON au.id=h.assigned_user_id
         WHERE h.legislative_item_id=:id
         ORDER BY h.created_at DESC,h.id DESC'
    );
    $stmt->execute([':id'=>$itemId]);
    return $stmt->fetchAll();
}

function orlmsSyncAuthors(PDO $pdo,int $itemId,array $post): int
{
    $names=$post['author_name']??[];
    $types=$post['author_type']??[];
    $users=$post['author_user_id']??[];
    $offices=$post['author_office_id']??[];

    if(!is_array($names))$names=[$names];
    if(!is_array($types))$types=[$types];
    if(!is_array($users))$users=[$users];
    if(!is_array($offices))$offices=[$offices];

    $pdo->prepare('DELETE FROM orlms_item_authors WHERE legislative_item_id=:id')
        ->execute([':id'=>$itemId]);

    $insert=$pdo->prepare(
        'INSERT INTO orlms_item_authors
         (legislative_item_id,user_id,office_id,author_name,author_type,
          sequence_number,created_by,created_at)
         VALUES(:item,:user,:office,:name,:type,:seq,:created_by,NOW())'
    );

    $count=0;
    foreach($names as $i=>$raw){
        $name=clean((string)$raw);
        if($name==='')continue;

        $type=clean((string)($types[$i]??'Author'));
        if(!in_array($type,['Author','Co-Author','Sponsor','Co-Sponsor','Proponent'],true)){
            $type='Author';
        }

        $uid=(int)($users[$i]??0)?:null;
        $office=(int)($offices[$i]??0)?:null;

        $insert->execute([
            ':item'=>$itemId,':user'=>$uid,':office'=>$office,':name'=>$name,
            ':type'=>$type,':seq'=>$count+1,':created_by'=>currentUserId()
        ]);
        $count++;
    }

    return $count;
}

function orlmsSaveSharedDocument(
    PDO $pdo,
    int $itemId,
    array $file,
    string $documentType='Supporting Document',
    string $title='',
    string $visibility='Internal'
): int {
    if(!in_array($visibility,['Public','Internal','Restricted'],true)){
        $visibility='Internal';
    }

    $upload=handleUpload($file,'orlms/legislative-items');
    if(!$upload['success'])throw new RuntimeException($upload['message']);

    $v=$pdo->prepare(
        'SELECT COALESCE(MAX(version_number),0)+1
         FROM documents
         WHERE legislative_item_id=:item
           AND document_type=:type'
    );
    $v->execute([':item'=>$itemId,':type'=>$documentType]);
    $version=(int)$v->fetchColumn();

    $pdo->prepare(
        'INSERT INTO documents
         (system_id,legislative_item_id,document_type,title,file_name,file_path,
          version_number,visibility,uploaded_by,created_at,updated_at)
         VALUES(:system,:item,:type,:title,:name,:path,:version,:visibility,
          :user,NOW(),NOW())'
    )->execute([
        ':system'=>orlmsSystemId(),':item'=>$itemId,':type'=>$documentType,
        ':title'=>$title!==''?$title:$upload['file_name'],
        ':name'=>$upload['file_name'],':path'=>$upload['file_path'],
        ':version'=>$version,':visibility'=>$visibility,':user'=>currentUserId()
    ]);

    return (int)$pdo->lastInsertId();
}

function orlmsCreateReviewChecklist(PDO $pdo,int $reviewId): void
{
    $insert=$pdo->prepare(
        'INSERT IGNORE INTO orlms_review_checklist
         (review_id,check_code,check_name,check_status,reviewer_note,
          sequence_number,reviewed_by,reviewed_at,created_at,updated_at)
         VALUES(:review,:code,:name,"Pending",NULL,:seq,NULL,NULL,NOW(),NOW())'
    );

    foreach(orlmsDefaultReviewChecklist() as $i=>$check){
        $insert->execute([
            ':review'=>$reviewId,':code'=>$check[0],':name'=>$check[1],':seq'=>$i+1
        ]);
    }
}

function orlmsLatestReview(PDO $pdo,int $itemId): ?array
{
    $stmt=$pdo->prepare(
        'SELECT r.*,c.name committee_name,u.full_name reviewer_name,
                e.id endorsement_id,e.endorsement_status,e.recommendation endorsement_recommendation,
                e.vote_summary,e.remarks endorsement_remarks,e.endorsed_at
         FROM orlms_reviews r
         LEFT JOIN committees c ON c.id=r.committee_id
         LEFT JOIN users u ON u.id=r.reviewer_id
         LEFT JOIN orlms_endorsements e ON e.review_id=r.id
         WHERE r.legislative_item_id=:id
         ORDER BY r.review_round DESC,r.id DESC LIMIT 1'
    );
    $stmt->execute([':id'=>$itemId]);
    $row=$stmt->fetch();
    return $row?:null;
}


function orlmsGetAnyDraft(PDO $pdo,int $id): ?array
{
    $stmt=$pdo->prepare(
        'SELECT li.*,lit.code item_type_code,lit.name item_type_name,
                o.name originating_office,
                d.id detail_id,d.template_id,d.short_title,d.subject,
                d.legislative_category,d.policy_area,d.purpose,
                d.explanatory_note,d.body_text,d.legal_basis,d.fiscal_impact,
                d.keywords,d.draft_status,d.submitted_by,d.submitted_at,
                d.submission_note,d.returned_at,d.return_reason,d.last_edited_by,
                t.template_name,t.template_code
         FROM legislative_items li
         JOIN legislative_item_types lit ON lit.id=li.item_type_id
         LEFT JOIN offices o ON o.id=li.originating_office_id
         LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
         LEFT JOIN orlms_templates t ON t.id=d.template_id
         WHERE li.id=:id
           AND lit.code IN ("ordinance","resolution")
           AND li.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([':id'=>$id]);
    $row=$stmt->fetch();
    return $row?:null;
}
