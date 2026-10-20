<?php
declare(strict_types=1);

require_once __DIR__ . '/orlms_lifecycle_helpers.php';

function orlmsAmendmentHistory(
    PDO $pdo,
    int $amendmentId,
    int $itemId,
    string $action,
    ?string $old,
    ?string $new,
    string $details=''
): void {
    if(!orlmsTableExists('orlms_amendment_history'))return;

    $pdo->prepare(
        'INSERT INTO orlms_amendment_history
         (amendment_id,legislative_item_id,action,previous_status,new_status,
          details,changed_by,created_at)
         VALUES(:amendment,:item,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':amendment'=>$amendmentId,
        ':item'=>$itemId,
        ':action'=>$action,
        ':old'=>$old,
        ':new'=>$new,
        ':details'=>$details?:null,
        ':user'=>currentUserId()
    ]);
}

function orlmsCurrentRevision(PDO $pdo,int $itemId): ?array
{
    $q=$pdo->prepare(
        'SELECT rh.*,u.full_name created_name
         FROM orlms_revision_history rh
         LEFT JOIN users u ON u.id=rh.created_by
         WHERE rh.legislative_item_id=:item
         ORDER BY rh.is_current DESC,rh.version_number DESC,rh.id DESC
         LIMIT 1'
    );
    $q->execute([':item'=>$itemId]);
    $row=$q->fetch();
    return $row?:null;
}

function orlmsEnsureBaselineRevision(PDO $pdo,int $itemId): array
{
    $current=orlmsCurrentRevision($pdo,$itemId);
    if($current)return $current;

    $q=$pdo->prepare(
        'SELECT li.title,d.body_text
         FROM legislative_items li
         LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
         WHERE li.id=:id AND li.deleted_at IS NULL'
    );
    $q->execute([':id'=>$itemId]);
    $item=$q->fetch();

    if(!$item)throw new RuntimeException('Legislative item not found.');

    $pdo->prepare(
        'INSERT INTO orlms_revision_history
         (legislative_item_id,amendment_id,version_number,title_snapshot,
          body_snapshot,change_summary,is_current,created_by,created_at)
         VALUES(:item,NULL,1,:title,:body,
          "Baseline official revision snapshot.",1,:user,NOW())'
    )->execute([
        ':item'=>$itemId,
        ':title'=>$item['title'],
        ':body'=>$item['body_text'],
        ':user'=>currentUserId()
    ]);

    return orlmsCurrentRevision($pdo,$itemId)
        ?? throw new RuntimeException('Unable to create baseline revision.');
}

function orlmsGetAmendment(PDO $pdo,int $id): ?array
{
    $q=$pdo->prepare(
        'SELECT a.*,li.reference_number,li.title legislative_title,
                li.current_status item_status,lit.code item_type_code,
                lit.name item_type_name,d.body_text current_body,
                en.official_number,en.enactment_status,en.enactment_date,
                pu.full_name proposed_name,ru.full_name reviewed_name,
                au.full_name approved_name,apu.full_name applied_name,
                rli.reference_number related_reference,
                rli.title related_title
         FROM orlms_amendments a
         JOIN legislative_items li ON li.id=a.legislative_item_id
         JOIN legislative_item_types lit ON lit.id=li.item_type_id
         LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
         LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
         LEFT JOIN users pu ON pu.id=a.proposed_by
         LEFT JOIN users ru ON ru.id=a.reviewed_by
         LEFT JOIN users au ON au.id=a.approved_by
         LEFT JOIN users apu ON apu.id=a.applied_by
         LEFT JOIN legislative_items rli ON rli.id=a.related_legislative_item_id
         WHERE a.id=:id
           AND li.deleted_at IS NULL'
    );
    $q->execute([':id'=>$id]);
    $row=$q->fetch();
    return $row?:null;
}


function orlmsRevisionByVersion(PDO $pdo,int $itemId,int $version): ?array
{
    $q=$pdo->prepare(
        'SELECT rh.*,a.amendment_reference,u.full_name created_name
         FROM orlms_revision_history rh
         LEFT JOIN orlms_amendments a ON a.id=rh.amendment_id
         LEFT JOIN users u ON u.id=rh.created_by
         WHERE rh.legislative_item_id=:item
           AND rh.version_number=:version
         LIMIT 1'
    );
    $q->execute([':item'=>$itemId,':version'=>$version]);
    $row=$q->fetch();
    return $row?:null;
}

function orlmsAmendmentChanges(PDO $pdo,int $amendmentId): array
{
    $q=$pdo->prepare(
        'SELECT * FROM orlms_amendment_changes
         WHERE amendment_id=:id
         ORDER BY sequence_number,id'
    );
    $q->execute([':id'=>$amendmentId]);
    return $q->fetchAll();
}

function orlmsAmendmentDocuments(PDO $pdo,int $amendmentId): array
{
    $q=$pdo->prepare(
        'SELECT d.*,u.full_name uploaded_name
         FROM orlms_amendment_documents d
         LEFT JOIN users u ON u.id=d.uploaded_by
         WHERE d.amendment_id=:id
         ORDER BY d.uploaded_at DESC,d.id DESC'
    );
    $q->execute([':id'=>$amendmentId]);
    return $q->fetchAll();
}

function orlmsAmendmentHistoryRows(PDO $pdo,int $amendmentId): array
{
    $q=$pdo->prepare(
        'SELECT h.*,u.full_name changed_name
         FROM orlms_amendment_history h
         LEFT JOIN users u ON u.id=h.changed_by
         WHERE h.amendment_id=:id
         ORDER BY h.created_at DESC,h.id DESC'
    );
    $q->execute([':id'=>$amendmentId]);
    return $q->fetchAll();
}

function orlmsRevisionHistoryRows(PDO $pdo,int $itemId): array
{
    $q=$pdo->prepare(
        'SELECT rh.*,a.amendment_reference,u.full_name created_name
         FROM orlms_revision_history rh
         LEFT JOIN orlms_amendments a ON a.id=rh.amendment_id
         LEFT JOIN users u ON u.id=rh.created_by
         WHERE rh.legislative_item_id=:item
         ORDER BY rh.version_number DESC,rh.id DESC'
    );
    $q->execute([':item'=>$itemId]);
    return $q->fetchAll();
}

function orlmsUploadAmendmentDocument(
    PDO $pdo,int $amendmentId,array $file,string $type,string $description,string $visibility
): int {
    if(!in_array($visibility,['Public','Internal','Restricted'],true))$visibility='Internal';

    $up=handleUpload($file,'orlms/amendments');
    if(!$up['success'])throw new RuntimeException($up['message']);

    $pdo->prepare(
        'INSERT INTO orlms_amendment_documents
         (amendment_id,file_name,file_path,document_type,description,
          visibility,uploaded_by,uploaded_at)
         VALUES(:amendment,:name,:path,:type,:description,:visibility,:user,NOW())'
    )->execute([
        ':amendment'=>$amendmentId,
        ':name'=>$up['file_name'],
        ':path'=>$up['file_path'],
        ':type'=>$type?:'Amendment Evidence',
        ':description'=>$description?:null,
        ':visibility'=>$visibility,
        ':user'=>currentUserId()
    ]);

    return (int)$pdo->lastInsertId();
}
