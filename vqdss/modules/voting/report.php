<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('vqd.voting.view');

$pageTitle='Voting Report';$activeMenu='voting';$extraCss=[appUrl('assets/css/vqdss-operational.css')];
$rows=db()->query(
 "SELECT ve.reference_number,ve.motion_text,ve.voting_method,ve.vote_visibility,
         ve.decision_threshold,ve.status,ve.opened_at,ve.closed_at,
         (SELECT COUNT(*) FROM vqd_voting_event_voters ev WHERE ev.voting_event_id=ve.id AND ev.voter_eligibility='Eligible') eligible,
         (SELECT COUNT(*) FROM vqd_votes v WHERE v.voting_event_id=ve.id) ballots
  FROM vqd_voting_events ve ORDER BY ve.created_at DESC"
)->fetchAll();
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content"><div class="vqdx-head"><div><div class="vqdx-eyebrow">Operational Report</div><h1>Voting Management Report</h1><p>Live voting-event, voter and ballot counts from the operational database.</p></div><a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Voting</a></div><div class="card vqdx-card"><div class="table-responsive"><table class="table vqdx-table mb-0"><thead><tr><th>Reference</th><th>Motion</th><th>Method</th><th>Visibility</th><th>Threshold</th><th>Eligible</th><th>Ballots</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['reference_number']) ?></td><td><?= e(mb_strimwidth($r['motion_text'],0,120,'…')) ?></td><td><?= e($r['voting_method']) ?></td><td><?= e($r['vote_visibility']) ?></td><td><?= e($r['decision_threshold']) ?></td><td><?= (int)$r['eligible'] ?></td><td><?= (int)$r['ballots'] ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
