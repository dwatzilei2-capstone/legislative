<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/vqdss_final_helpers.php';
requirePermission('vqd.reports.view');

$s=vqdWorkflowSnapshot(db());
?>
<!doctype html><html><head><meta charset="utf-8"><title>VQDSS Summary</title><style>body{font:12px Arial;margin:30px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}.box{border:1px solid #ccc;padding:10px;text-align:center}.box strong{display:block;font-size:22px}</style></head><body onload="window.print()"><div class="head"><strong><?= e(APP_NAME) ?></strong><h2>VQDSS Consolidated Operational Summary</h2><div>Generated <?= date('F j, Y g:i A') ?></div></div><div class="grid"><div class="box"><strong><?= $s['certified_quorum'] ?></strong>Certified Quorum</div><div class="box"><strong><?= $s['closed_votes'] ?></strong>Closed Votes</div><div class="box"><strong><?= $s['certified_tallies'] ?></strong>Certified Tallies</div><div class="box"><strong><?= $s['certified_decisions'] ?></strong>Official Decisions</div><div class="box"><strong><?= $s['certified_validations'] ?></strong>Certified Validations</div></div><p>The detailed module reports and cross-module workflow trace are available in the VQDSS Reports & Analytics center.</p></body></html>
