<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('vqd.decisions.view');

$rows=db()->query(
 "SELECT d.reference_number,d.decision_record_number,d.official_outcome,
         d.required_votes,d.affirmative_votes,d.negative_votes,d.abstain_votes,
         d.release_classification,d.record_status,d.certified_at,d.published_at,
         t.reference_number tally_reference,li.reference_number legislative_reference
  FROM vqd_decisions d
  LEFT JOIN vqd_vote_tallies t ON t.id=d.tally_id
  LEFT JOIN legislative_items li ON li.id=d.legislative_item_id
  ORDER BY d.created_at DESC"
)->fetchAll();

$pageTitle='Decision Report';$activeMenu='decisions';$extraCss=[appUrl('assets/css/vqdss-final-ops.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content"><div class="vqdf-head"><div><div class="vqdf-eyebrow">Operational Report</div><h1>Decision Recording Report</h1><p>Official decision records generated from certified VQDSS tally results.</p></div><a href="index.php" class="btn btn-outline-secondary">Decision Recording</a></div><div class="card vqdf-card"><div class="table-responsive"><table class="table vqdf-table mb-0"><thead><tr><th>Decision</th><th>Item</th><th>Tally</th><th>Outcome</th><th>Yes</th><th>No</th><th>Abstain</th><th>Release</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['reference_number']) ?><div class="small text-muted"><?= e($r['decision_record_number']?:'') ?></div></td><td><?= e($r['legislative_reference']?:'—') ?></td><td><?= e($r['tally_reference']?:'—') ?></td><td><?= e($r['official_outcome']) ?></td><td><?= (int)$r['affirmative_votes'] ?></td><td><?= (int)$r['negative_votes'] ?></td><td><?= (int)$r['abstain_votes'] ?></td><td><?= e($r['release_classification']) ?></td><td><?= e($r['record_status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
