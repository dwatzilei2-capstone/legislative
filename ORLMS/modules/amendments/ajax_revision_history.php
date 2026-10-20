<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_amendment_helpers.php';
requirePermission('orlms.amendments.view');

$itemId=(int)($_GET['legislative_item_id']??0);
if($itemId<=0)jsonResponse(false,'Invalid legislative item.');

$rows=orlmsRevisionHistoryRows(db(),$itemId);
jsonResponse(true,'',['revisions'=>$rows]);
