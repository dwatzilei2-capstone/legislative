<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.tally.view');

$pageTitle='Vote Tallying';
$activeMenu='tallying';
$extraCss=[appUrl('assets/css/vqdss-operational.css')];
$pdo=db();

$closedEvents=$pdo->query(
 "SELECT ve.id,ve.reference_number,ve.motion_text,ve.decision_threshold,ve.closed_at,
         s.reference_number session_reference,li.reference_number legislative_reference,
         (SELECT COUNT(*) FROM vqd_votes v WHERE v.voting_event_id=ve.id) votes_cast,
         (SELECT t.id FROM vqd_vote_tallies t WHERE t.voting_event_id=ve.id ORDER BY t.id DESC LIMIT 1) tally_id
  FROM vqd_voting_events ve
  LEFT JOIN vqd_sessions s ON s.id=ve.session_id
  LEFT JOIN legislative_items li ON li.id=ve.legislative_item_id
  WHERE ve.status='Closed'
  ORDER BY ve.closed_at DESC,ve.id DESC"
)->fetchAll();

$tallies=$pdo->query(
 "SELECT t.*,ve.motion_text,ve.reference_number voting_reference,
         s.reference_number session_reference,li.reference_number legislative_reference,
         (SELECT COUNT(*) FROM vqd_tally_recounts r WHERE r.tally_id=t.id) recount_count
  FROM vqd_vote_tallies t
  JOIN vqd_voting_events ve ON ve.id=t.voting_event_id
  LEFT JOIN vqd_sessions s ON s.id=ve.session_id
  LEFT JOIN legislative_items li ON li.id=t.legislative_item_id
  ORDER BY t.created_at DESC,t.id DESC"
)->fetchAll();

$stats=$pdo->query(
 "SELECT COUNT(*) tallies,
   SUM(tally_status='Certified') certified,
   SUM(result_outcome='Passed') passed,
   SUM(discrepancy_classification IN ('Critical','Review Required')) review_required
  FROM vqd_vote_tallies"
)->fetch();

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="vqdx-head"><div><div class="vqdx-eyebrow"><i class="bi bi-calculator"></i> Step 4 · Operational Backend</div><h1>Vote Tallying</h1><p>Compute locked voting results from system ballots, calculate required thresholds, identify invalid/challenged/missing ballots, perform controlled recounts and certify the result for Decision Recording.</p></div></div>

<div class="vqdx-flow">
<div><strong>1. Closed Vote</strong><small>Ballots locked</small></div>
<div><strong>2. System Compute</strong><small>Valid / invalid / missing</small></div>
<div><strong>3. Integrity Review</strong><small>Challenges and discrepancies</small></div>
<div><strong>4. Recount</strong><small>Controlled recalculation</small></div>
<div><strong>5. Certification</strong><small>Decision handoff ready</small></div>
</div>

<div class="row g-3 mb-3"><?php foreach([
 ['Tallies',$stats['tallies']??0,'bi-calculator'],
 ['Certified',$stats['certified']??0,'bi-patch-check'],
 ['Passed',$stats['passed']??0,'bi-check-circle'],
 ['Need Review',$stats['review_required']??0,'bi-exclamation-triangle'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="vqdx-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<?php if(vqdOperationalManager()): ?>
<div class="card vqdx-card mb-3"><div class="card-header">Closed Voting Events Ready for Tally</div><div class="table-responsive"><table class="table vqdx-table mb-0"><thead><tr><th>Voting Event</th><th>Motion</th><th>Session / Item</th><th>Ballots</th><th>Tally</th><th class="text-end">Action</th></tr></thead><tbody>
<?php if(!$closedEvents): ?><tr><td colspan="6" class="text-center text-muted py-4">No closed voting events are awaiting tally.</td></tr><?php endif; ?>
<?php foreach($closedEvents as $e): ?><tr><td><span class="vqdx-code"><?= e($e['reference_number']) ?></span></td><td><?= e(mb_strimwidth($e['motion_text'],0,120,'…')) ?></td><td><?= e($e['session_reference']?:'—') ?><div class="small text-muted"><?= e($e['legislative_reference']?:'—') ?></div></td><td><?= (int)$e['votes_cast'] ?></td><td><?= $e['tally_id']?'<span class="badge text-bg-success">Computed</span>':'<span class="badge text-bg-light">Not computed</span>' ?></td><td class="text-end"><?php if($e['tally_id']): ?><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$e['tally_id'] ?>">Open Tally</a><?php else: ?><button class="btn btn-sm btn-primary compute-tally" data-event="<?= (int)$e['id'] ?>">Compute</button><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<div class="card vqdx-card"><div class="card-header">Tally Register</div><div class="table-responsive"><table class="table table-hover vqdx-table mb-0"><thead><tr><th>Reference</th><th>Voting Event / Motion</th><th>Votes</th><th>Threshold</th><th>Outcome</th><th>Discrepancy</th><th>Status</th><th class="text-end">View</th></tr></thead><tbody>
<?php if(!$tallies): ?><tr><td colspan="8" class="text-center text-muted py-5">No vote tally has been computed yet.</td></tr><?php endif; ?>
<?php foreach($tallies as $t): ?><tr><td><span class="vqdx-code"><?= e($t['reference_number']) ?></span></td><td><?= e($t['voting_reference']) ?><div class="small text-muted"><?= e(mb_strimwidth($t['motion_text'],0,90,'…')) ?></div></td><td><?= (int)$t['votes_cast'] ?> cast / <?= (int)$t['eligible_voters'] ?> eligible</td><td><?= e($t['decision_threshold']) ?><div class="small text-muted"><?= (int)$t['required_votes'] ?> required</div></td><td><span class="badge text-bg-<?= $t['result_outcome']==='Passed'?'success':($t['result_outcome']==='Tie'?'warning':'danger') ?>"><?= e($t['result_outcome']) ?></span></td><td><?= e($t['discrepancy_classification']) ?></td><td><?= e($t['tally_status']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$t['id'] ?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>
<?php if(vqdOperationalManager()): ?><script>document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.compute-tally').forEach(b=>b.onclick=async function(){const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('voting_event_id',this.dataset.event);const r=await fetch('ajax_compute.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.href='view.php?id='+r.tally_id;else Swal.fire('Tally Error',r.message,'error');});});</script><?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
