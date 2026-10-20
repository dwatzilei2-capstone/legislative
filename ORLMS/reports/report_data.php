<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requirePermission('orlms.reports.view');

$dataset=clean($_GET['dataset']??'status');
$pdo=db();

if($dataset==='status'){
    $rows=$pdo->query(
      "SELECT li.current_status label,COUNT(*) total
       FROM legislative_items li
       JOIN legislative_item_types lit ON lit.id=li.item_type_id
       WHERE li.deleted_at IS NULL AND lit.code IN ('ordinance','resolution')
       GROUP BY li.current_status ORDER BY total DESC"
    )->fetchAll();
}elseif($dataset==='type'){
    $rows=$pdo->query(
      "SELECT lit.name label,COUNT(*) total
       FROM legislative_items li
       JOIN legislative_item_types lit ON lit.id=li.item_type_id
       WHERE li.deleted_at IS NULL AND lit.code IN ('ordinance','resolution')
       GROUP BY lit.id,lit.name ORDER BY lit.name"
    )->fetchAll();
}elseif($dataset==='month'){
    $rows=$pdo->query(
      "SELECT DATE_FORMAT(li.created_at,'%Y-%m') label,COUNT(*) total
       FROM legislative_items li
       JOIN legislative_item_types lit ON lit.id=li.item_type_id
       WHERE li.deleted_at IS NULL AND lit.code IN ('ordinance','resolution')
       GROUP BY DATE_FORMAT(li.created_at,'%Y-%m')
       ORDER BY label"
    )->fetchAll();
}else{
    jsonResponse(false,'Unknown report dataset.');
}
jsonResponse(true,'',['rows'=>$rows]);
