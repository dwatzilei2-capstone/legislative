<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('vqd.tally.view');

$pageTitle='Vote Tally Report';$activeMenu='tallying';$extraCss=[appUrl('assets/css/vqdss-operational.css')];
$rows=db()->query(
 "SELECT t.reference_number,ve.reference_number voting_reference,
         t.eligible_voters,t.votes_cast,t.valid_votes,t.invalid_ballots,
         t.challenged_ballots,t.affirmative_votes,t.negative_votes,t.abstain_votes,
         t.required_votes,t.result_outcome,t.discrepancy_classification,t.tally_status
  FROM vqd_vote_tallies t JOIN vqd_voting_events ve ON ve.id=t.voting_event_id
  ORDER BY t.created_at DESC"
)->fetchAll();
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content"><div class="vqdx-head"><div><div class="vqdx-eyebrow">Operational Report</div><h1>Vote Tally Report</h1><p>Live computed/certified results including ballot-integrity indicators.</p></div><a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Tallying</a></div><div class="card vqdx-card"><div class="table-responsive"><table class="table vqdx-table mb-0"><thead><tr><th>Reference</th><th>Voting</th><th>Eligible</th><th>Cast</th><th>Valid</th><th>Invalid</th><th>Yes</th><th>No</th><th>Abstain</th><th>Required</th><th>Outcome</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['reference_number']) ?></td><td><?= e($r['voting_reference']) ?></td><td><?= (int)$r['eligible_voters'] ?></td><td><?= (int)$r['votes_cast'] ?></td><td><?= (int)$r['valid_votes'] ?></td><td><?= (int)$r['invalid_ballots'] ?></td><td><?= (int)$r['affirmative_votes'] ?></td><td><?= (int)$r['negative_votes'] ?></td><td><?= (int)$r['abstain_votes'] ?></td><td><?= (int)$r['required_votes'] ?></td><td><?= e($r['result_outcome']) ?></td><td><?= e($r['tally_status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
