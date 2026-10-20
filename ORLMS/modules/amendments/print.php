<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_amendment_helpers.php';
requirePermission('orlms.amendments.view');

$pdo=db();$id=(int)($_GET['id']??0);$a=orlmsGetAmendment($pdo,$id);
if(!$a)exit('Amendment not found.');
$changes=orlmsAmendmentChanges($pdo,$id);
$revisions=orlmsRevisionHistoryRows($pdo,(int)$a['legislative_item_id']);
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= e($a['amendment_reference']) ?></title><link rel="icon" type="image/png" href="../../assets/images/manila.png"><style>body{font:12px Arial;margin:28px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.head img{height:54px;width:54px;object-fit:contain;margin-bottom:6px}.box{border:1px solid #ccc;padding:9px;margin:8px 0}.body{white-space:pre-wrap;font-family:Georgia,serif;line-height:1.55}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #ccc;padding:6px;vertical-align:top}th{background:#eee}</style></head><body onload="window.print()">
<div class="head"><img src="../../assets/images/manila.png" alt="City of Manila Seal"><br><strong><?= e(APP_NAME) ?></strong><h2>Amendment & Revision Record</h2><div><?= e($a['amendment_reference']) ?></div></div>
<p><strong>Original:</strong> <?= e(($a['official_number']?:$a['reference_number']).' · '.$a['legislative_title']) ?></p>
<div class="box"><strong><?= e($a['amendment_type'].' · '.$a['title']) ?></strong><br>Status: <?= e($a['status']) ?> · Source Revision: v<?= (int)$a['source_revision_version'] ?> · Effectivity: <?= e($a['effective_date']?:'—') ?><br><br><?= nl2br(e($a['rationale']?:'')) ?></div>
<h3>Affected Provisions</h3><table><thead><tr><th>Section</th><th>Type</th><th>Previous Text</th><th>Revised Text</th><th>Reason</th></tr></thead><tbody><?php foreach($changes as $c): ?><tr><td><?= e($c['section_reference']?:'General') ?></td><td><?= e($c['change_type']) ?></td><td><?= nl2br(e($c['previous_text']?:'')) ?></td><td><?= nl2br(e($c['new_text']?:'')) ?></td><td><?= e($c['rationale']?:'') ?></td></tr><?php endforeach; ?></tbody></table>
<h3>Complete Revised Text</h3><div class="box body"><?= e($a['revised_body_text']?:'No revised text.') ?></div>
<h3>Official Revision History</h3><table><thead><tr><th>Version</th><th>Amendment</th><th>Summary</th><th>Current</th></tr></thead><tbody><?php foreach($revisions as $r): ?><tr><td>v<?= (int)$r['version_number'] ?></td><td><?= e($r['amendment_reference']?:'Baseline') ?></td><td><?= e($r['change_summary']?:'') ?></td><td><?= $r['is_current']?'Yes':'No' ?></td></tr><?php endforeach; ?></tbody></table>
</body></html>
