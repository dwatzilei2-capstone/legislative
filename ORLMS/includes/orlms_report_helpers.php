<?php
declare(strict_types=1);

require_once __DIR__ . '/orlms_amendment_helpers.php';

function orlmsReportFilterValues(): array
{
    return [
        'search'=>clean($_GET['search']??''),
        'type'=>clean($_GET['type']??''),
        'status'=>clean($_GET['status']??''),
        'date_from'=>clean($_GET['date_from']??''),
        'date_to'=>clean($_GET['date_to']??''),
    ];
}

function orlmsLegislativeReportRows(PDO $pdo,array $f): array
{
    $where=[
        'li.deleted_at IS NULL',
        'lit.code IN ("ordinance","resolution")'
    ];
    $params=[];

    if(($f['search']??'')!==''){
        $where[]='(li.reference_number LIKE :s1 OR li.title LIKE :s2 OR en.official_number LIKE :s3)';
        $like='%'.$f['search'].'%';
        $params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;
    }
    if(($f['type']??'')!==''){
        $where[]='lit.code=:type';$params[':type']=$f['type'];
    }
    if(($f['status']??'')!==''){
        $where[]='li.current_status=:status';$params[':status']=$f['status'];
    }
    if(($f['date_from']??'')!==''){
        $where[]='DATE(li.created_at)>=:df';$params[':df']=$f['date_from'];
    }
    if(($f['date_to']??'')!==''){
        $where[]='DATE(li.created_at)<=:dt';$params[':dt']=$f['date_to'];
    }

    $stmt=$pdo->prepare(
        'SELECT li.id,li.reference_number,li.title,li.current_status,
                li.priority_level,li.visibility,li.created_at,li.updated_at,
                lit.code item_type_code,lit.name item_type_name,o.name office_name,
                d.subject,d.policy_area,
                en.official_number,en.enactment_date,en.enactment_status,
                (SELECT COUNT(*) FROM orlms_reviews r WHERE r.legislative_item_id=li.id) review_rounds,
                (SELECT COUNT(*) FROM orlms_publications p WHERE p.legislative_item_id=li.id AND p.publication_status="Published") published_count,
                (SELECT COUNT(*) FROM orlms_implementation_plans ip WHERE ip.legislative_item_id=li.id) implementation_plans,
                (SELECT COUNT(*) FROM orlms_amendments a WHERE a.legislative_item_id=li.id) amendment_count,
                (SELECT MAX(rh.version_number) FROM orlms_revision_history rh WHERE rh.legislative_item_id=li.id) revision_version
         FROM legislative_items li
         JOIN legislative_item_types lit ON lit.id=li.item_type_id
         LEFT JOIN offices o ON o.id=li.originating_office_id
         LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
         LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
         WHERE '.implode(' AND ',$where).'
         ORDER BY li.updated_at DESC,li.id DESC'
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function orlmsWorkflowTraceRows(PDO $pdo,?int $itemId=null): array
{
    $where='li.deleted_at IS NULL AND lit.code IN ("ordinance","resolution")';
    $params=[];
    if($itemId){
        $where.=' AND li.id=:item';$params[':item']=$itemId;
    }

    $stmt=$pdo->prepare(
        'SELECT li.id,li.reference_number,li.title,li.current_status,
                lit.code item_type_code,lit.name item_type_name,
                d.submitted_at,
                r.id review_id,r.review_round,r.status review_status,
                r.recommendation review_recommendation,c.name committee_name,
                e.endorsement_status,e.endorsed_at,
                a.id approval_id,a.action_reference,a.action_status,a.action_date,
                en.id enactment_id,en.enactment_reference,en.official_number,
                en.enactment_status,en.enactment_date,
                p.id publication_id,p.publication_reference,p.publication_status,
                p.publication_date,
                am.id amendment_id,am.amendment_reference,am.status amendment_status,
                am.applied_at,
                (SELECT COUNT(*) FROM orlms_implementation_plans ip
                 WHERE ip.legislative_item_id=li.id) implementation_count,
                (SELECT COUNT(*) FROM orlms_implementation_plans ip
                 WHERE ip.legislative_item_id=li.id AND ip.status="Completed") implementation_completed,
                (SELECT ROUND(AVG(ip.compliance_percent),2)
                 FROM orlms_implementation_plans ip
                 WHERE ip.legislative_item_id=li.id) implementation_average
         FROM legislative_items li
         JOIN legislative_item_types lit ON lit.id=li.item_type_id
         LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
         LEFT JOIN orlms_reviews r
           ON r.id=(SELECT MAX(r2.id) FROM orlms_reviews r2 WHERE r2.legislative_item_id=li.id)
         LEFT JOIN committees c ON c.id=r.committee_id
         LEFT JOIN orlms_endorsements e ON e.review_id=r.id
         LEFT JOIN orlms_approval_actions a
           ON a.id=(SELECT MAX(a2.id) FROM orlms_approval_actions a2 WHERE a2.legislative_item_id=li.id)
         LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
         LEFT JOIN orlms_publications p
           ON p.id=(SELECT MAX(p2.id) FROM orlms_publications p2 WHERE p2.legislative_item_id=li.id)
         LEFT JOIN orlms_amendments am
           ON am.id=(SELECT MAX(am2.id) FROM orlms_amendments am2 WHERE am2.legislative_item_id=li.id)
         WHERE '.$where.'
         ORDER BY li.updated_at DESC,li.id DESC'
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function orlmsReportStatusList(PDO $pdo): array
{
    return $pdo->query(
        "SELECT DISTINCT li.current_status
         FROM legislative_items li
         JOIN legislative_item_types lit ON lit.id=li.item_type_id
         WHERE li.deleted_at IS NULL
           AND lit.code IN ('ordinance','resolution')
         ORDER BY li.current_status"
    )->fetchAll(PDO::FETCH_COLUMN);
}
