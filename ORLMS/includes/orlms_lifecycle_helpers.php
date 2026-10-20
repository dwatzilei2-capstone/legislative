<?php
declare(strict_types=1);

require_once __DIR__ . '/orlms_drafting_helpers.php';

function orlmsApprovalHistory(
    PDO $pdo,int $approvalId,int $itemId,string $action,
    ?string $old,?string $new,string $details=''
): void {
    if(!orlmsTableExists('orlms_approval_history'))return;
    $pdo->prepare(
        'INSERT INTO orlms_approval_history
         (approval_action_id,legislative_item_id,action,previous_status,new_status,
          details,changed_by,created_at)
         VALUES(:approval,:item,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':approval'=>$approvalId,':item'=>$itemId,':action'=>$action,
        ':old'=>$old,':new'=>$new,':details'=>$details?:null,':user'=>currentUserId()
    ]);
}

function orlmsEnactmentHistory(
    PDO $pdo,int $enactmentId,int $itemId,string $action,
    ?string $old,?string $new,string $details=''
): void {
    if(!orlmsTableExists('orlms_enactment_history'))return;
    $pdo->prepare(
        'INSERT INTO orlms_enactment_history
         (enactment_id,legislative_item_id,action,previous_status,new_status,
          details,changed_by,created_at)
         VALUES(:enactment,:item,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':enactment'=>$enactmentId,':item'=>$itemId,':action'=>$action,
        ':old'=>$old,':new'=>$new,':details'=>$details?:null,':user'=>currentUserId()
    ]);
}

function orlmsPublicationHistory(
    PDO $pdo,int $publicationId,int $itemId,string $action,
    ?string $old,?string $new,string $details=''
): void {
    if(!orlmsTableExists('orlms_publication_history'))return;
    $pdo->prepare(
        'INSERT INTO orlms_publication_history
         (publication_id,legislative_item_id,action,previous_status,new_status,
          details,changed_by,created_at)
         VALUES(:publication,:item,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':publication'=>$publicationId,':item'=>$itemId,':action'=>$action,
        ':old'=>$old,':new'=>$new,':details'=>$details?:null,':user'=>currentUserId()
    ]);
}

function orlmsImplementationHistory(
    PDO $pdo,int $planId,int $itemId,string $action,
    ?string $old,?string $new,string $details=''
): void {
    if(!orlmsTableExists('orlms_implementation_history'))return;
    $pdo->prepare(
        'INSERT INTO orlms_implementation_history
         (implementation_plan_id,legislative_item_id,action,previous_status,
          new_status,details,changed_by,created_at)
         VALUES(:plan,:item,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':plan'=>$planId,':item'=>$itemId,':action'=>$action,
        ':old'=>$old,':new'=>$new,':details'=>$details?:null,':user'=>currentUserId()
    ]);
}

function orlmsApprovalEligibleStatuses(): array
{
    return ['Endorsed','Pending Approval','Approval Returned','Vetoed','Approved'];
}

function orlmsApprovalItem(PDO $pdo,int $itemId): ?array
{
    $q=$pdo->prepare(
        'SELECT li.*,lit.code item_type_code,lit.name item_type_name,
                d.subject,d.body_text,d.legal_basis,d.fiscal_impact,d.submitted_by,
                o.name originating_office,
                e.endorsement_status,e.recommendation endorsement_recommendation,
                e.vote_summary,e.endorsed_at,
                c.name committee_name
         FROM legislative_items li
         JOIN legislative_item_types lit ON lit.id=li.item_type_id
         LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
         LEFT JOIN offices o ON o.id=li.originating_office_id
         LEFT JOIN orlms_endorsements e
           ON e.id=(SELECT MAX(e2.id) FROM orlms_endorsements e2
                    WHERE e2.legislative_item_id=li.id)
         LEFT JOIN committees c ON c.id=e.committee_id
         WHERE li.id=:id
           AND lit.code IN ("ordinance","resolution")
           AND li.deleted_at IS NULL'
    );
    $q->execute([':id'=>$itemId]);
    $row=$q->fetch();
    return $row?:null;
}

function orlmsLatestApproval(PDO $pdo,int $itemId): ?array
{
    $q=$pdo->prepare(
        'SELECT a.*,u.full_name authority_name,o.name authority_office_name
         FROM orlms_approval_actions a
         LEFT JOIN users u ON u.id=a.authority_user_id
         LEFT JOIN offices o ON o.id=a.authority_office_id
         WHERE a.legislative_item_id=:id
         ORDER BY a.created_at DESC,a.id DESC LIMIT 1'
    );
    $q->execute([':id'=>$itemId]);
    $row=$q->fetch();
    return $row?:null;
}

function orlmsGetEnactment(PDO $pdo,int $itemId): ?array
{
    $q=$pdo->prepare(
        'SELECT e.*,rb.full_name recorded_name,fb.full_name finalized_name
         FROM orlms_enactments e
         LEFT JOIN users rb ON rb.id=e.recorded_by
         LEFT JOIN users fb ON fb.id=e.finalized_by
         WHERE e.legislative_item_id=:id LIMIT 1'
    );
    $q->execute([':id'=>$itemId]);
    $row=$q->fetch();
    return $row?:null;
}

function orlmsGetPublication(PDO $pdo,int $publicationId): ?array
{
    $q=$pdo->prepare(
        'SELECT p.*,li.reference_number,li.title,li.current_status,
                lit.code item_type_code,lit.name item_type_name,
                en.official_number,en.enactment_reference,en.enactment_date,
                u.full_name published_name
         FROM orlms_publications p
         JOIN legislative_items li ON li.id=p.legislative_item_id
         JOIN legislative_item_types lit ON lit.id=li.item_type_id
         LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
         LEFT JOIN users u ON u.id=p.published_by
         WHERE p.id=:id AND li.deleted_at IS NULL'
    );
    $q->execute([':id'=>$publicationId]);
    $row=$q->fetch();
    return $row?:null;
}

function orlmsPublishedExists(PDO $pdo,int $itemId): bool
{
    $q=$pdo->prepare(
        "SELECT COUNT(*) FROM orlms_publications
         WHERE legislative_item_id=:id
           AND publication_status='Published'"
    );
    $q->execute([':id'=>$itemId]);
    return (int)$q->fetchColumn()>0;
}

function orlmsRefreshPublicationItemStatus(PDO $pdo,int $itemId): string
{
    $item=orlmsApprovalItem($pdo,$itemId);
    if(!$item)throw new RuntimeException('Legislative item not found.');

    $old=(string)$item['current_status'];
    $published=orlmsPublishedExists($pdo,$itemId);

    $new=$published?'Published':'Enacted';

    if($old!==$new){
        $pdo->prepare(
            'UPDATE legislative_items SET current_status=:status,updated_at=NOW() WHERE id=:id'
        )->execute([':status'=>$new,':id'=>$itemId]);
        $pdo->prepare(
            'UPDATE orlms_item_details SET draft_status=:status,updated_at=NOW()
             WHERE legislative_item_id=:id'
        )->execute([':status'=>$new,':id'=>$itemId]);
        orlmsAddStatusHistory($pdo,$itemId,$old,$new,'Publication status synchronized.');
    }

    return $new;
}

function orlmsRefreshImplementationItemStatus(PDO $pdo,int $itemId): string
{
    $q=$pdo->prepare(
        "SELECT
           COUNT(*) total,
           SUM(status='Completed') completed,
           SUM(status='Cancelled') cancelled,
           SUM(status IN ('Pending','In Progress','On Hold')) active
         FROM orlms_implementation_plans
         WHERE legislative_item_id=:id"
    );
    $q->execute([':id'=>$itemId]);
    $s=$q->fetch()?:[];

    $total=(int)($s['total']??0);
    $completed=(int)($s['completed']??0);
    $active=(int)($s['active']??0);

    if($total===0){
        $new=orlmsPublishedExists($pdo,$itemId)?'Published':'Enacted';
    }elseif($active>0){
        $new='Implementation In Progress';
    }elseif($completed>0){
        $new='Implemented';
    }else{
        $new=orlmsPublishedExists($pdo,$itemId)?'Published':'Enacted';
    }

    $q=$pdo->prepare('SELECT current_status FROM legislative_items WHERE id=:id');
    $q->execute([':id'=>$itemId]);$old=(string)$q->fetchColumn();

    if($old!==$new){
        $pdo->prepare(
            'UPDATE legislative_items SET current_status=:status,updated_at=NOW() WHERE id=:id'
        )->execute([':status'=>$new,':id'=>$itemId]);
        $pdo->prepare(
            'UPDATE orlms_item_details SET draft_status=:status,updated_at=NOW()
             WHERE legislative_item_id=:id'
        )->execute([':status'=>$new,':id'=>$itemId]);
        orlmsAddStatusHistory($pdo,$itemId,$old,$new,'Implementation monitoring status synchronized.');
    }

    return $new;
}

function orlmsUploadPublicationDocument(
    PDO $pdo,int $publicationId,array $file,string $type,string $description,string $visibility
): int {
    if(!in_array($visibility,['Public','Internal','Restricted'],true))$visibility='Public';
    $up=handleUpload($file,'orlms/publications');
    if(!$up['success'])throw new RuntimeException($up['message']);

    $pdo->prepare(
        'INSERT INTO orlms_publication_documents
         (publication_id,file_name,file_path,document_type,description,visibility,
          uploaded_by,uploaded_at)
         VALUES(:publication,:name,:path,:type,:description,:visibility,:user,NOW())'
    )->execute([
        ':publication'=>$publicationId,':name'=>$up['file_name'],':path'=>$up['file_path'],
        ':type'=>$type?:'Publication Document',':description'=>$description?:null,
        ':visibility'=>$visibility,':user'=>currentUserId()
    ]);
    return (int)$pdo->lastInsertId();
}

function orlmsUploadImplementationDocument(
    PDO $pdo,int $planId,?int $updateId,array $file,string $type,string $description,string $visibility
): int {
    if(!in_array($visibility,['Public','Internal','Restricted'],true))$visibility='Internal';
    $up=handleUpload($file,'orlms/implementation');
    if(!$up['success'])throw new RuntimeException($up['message']);

    $pdo->prepare(
        'INSERT INTO orlms_implementation_documents
         (implementation_plan_id,implementation_update_id,file_name,file_path,
          document_type,description,visibility,uploaded_by,uploaded_at)
         VALUES(:plan,:update,:name,:path,:type,:description,:visibility,:user,NOW())'
    )->execute([
        ':plan'=>$planId,':update'=>$updateId,':name'=>$up['file_name'],
        ':path'=>$up['file_path'],':type'=>$type?:'Implementation Evidence',
        ':description'=>$description?:null,':visibility'=>$visibility,
        ':user'=>currentUserId()
    ]);
    return (int)$pdo->lastInsertId();
}
