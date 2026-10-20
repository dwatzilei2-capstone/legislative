<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/vqdss_final_helpers.php';
requirePermission('vqd.dashboard.view');

$pdo=db();$pageTitle='Dashboard';$activeMenu='dashboard';
$extraCss=[appUrl('assets/css/vqdss-final-ops.css')];

$s=vqdWorkflowSnapshot($pdo);

$live=$pdo->query(
 "SELECT ve.id,ve.reference_number,ve.motion_text,ve.status,ve.voting_method,
   (SELECT COUNT(*) FROM vqd_voting_event_voters ev WHERE ev.voting_event_id=ve.id AND ev.voter_eligibility='Eligible') eligible,
   (SELECT COUNT(*) FROM vqd_votes v WHERE v.voting_event_id=ve.id) ballots
  FROM vqd_voting_events ve
  WHERE ve.status IN ('Open','Paused')
  ORDER BY ve.opened_at DESC,ve.id DESC LIMIT 5"
)->fetchAll();

$recent=$pdo->prepare(
 "SELECT al.action,al.details,al.created_at,u.full_name
  FROM activity_logs al
  LEFT JOIN users u ON u.id=al.user_id
  WHERE al.system_id=:system
  ORDER BY al.created_at DESC,al.id DESC LIMIT 50"
);$recent->execute([':system'=>vqdSystemId()]);$activities=$recent->fetchAll();

$outcomes=$pdo->query(
 "SELECT result_outcome outcome,COUNT(*) total
  FROM vqd_vote_tallies
  WHERE tally_status='Certified'
  GROUP BY result_outcome"
)->fetchAll();

$risk=$pdo->query(
 "SELECT risk_level,COUNT(*) total
  FROM vqd_result_validations
  GROUP BY risk_level"
)->fetchAll();

$pending=[
 'quorum'=>(int)$pdo->query("SELECT COUNT(*) FROM vqd_quorum_checks WHERE verification_status<>'Certified'")->fetchColumn(),
 'votes'=>(int)$pdo->query("SELECT COUNT(*) FROM vqd_voting_events WHERE status IN ('Ready','Open','Paused')")->fetchColumn(),
 'tallies'=>(int)$pdo->query("SELECT COUNT(*) FROM vqd_vote_tallies WHERE tally_status<>'Certified'")->fetchColumn(),
 'decisions'=>(int)$pdo->query("SELECT COUNT(*) FROM vqd_decisions WHERE record_status NOT IN ('Certified','Published','Cancelled')")->fetchColumn(),
 'validations'=>(int)$pdo->query("SELECT COUNT(*) FROM vqd_result_validations WHERE validation_status NOT IN ('Certified','Failed')")->fetchColumn(),
];

$myPendingVotes=$pdo->prepare(
 "SELECT ve.id,ve.reference_number,ve.motion_text,ve.status,ve.voting_method,
         s.session_title,s.reference_number session_ref
  FROM vqd_voting_events ve
  JOIN vqd_voting_event_voters ev ON ev.voting_event_id=ve.id
  LEFT JOIN vqd_sessions s ON s.id=ve.session_id
  WHERE ev.user_id=:user
    AND ev.voter_eligibility='Eligible'
    AND ev.authorization_status<>'Revoked'
    AND ev.voted_at IS NULL
    AND ve.status='Open'
  ORDER BY ve.opened_at DESC,ve.id DESC"
);
$myPendingVotes->execute([':user'=>currentUserId()]);
$myPendingBallots=$myPendingVotes->fetchAll();

include __DIR__.'/layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/layouts/sidebar.php'; ?><main class="main-content">
<div class="vqdf-head"><div><div class="vqdf-eyebrow"><i class="bi bi-speedometer2"></i> Step 7 · Consolidated Operations</div><h1>VQDSS Dashboard</h1><p>Monitor the complete decision-support chain from session and certified quorum through live voting, tally certification, official decision recording, and final result validation.</p></div><a class="btn btn-primary" href="<?= e(appUrl('reports/index.php')) ?>"><i class="bi bi-bar-chart-line"></i> Reports & Analytics</a></div>

<?php if($myPendingBallots): ?>
<div class="card border-0 shadow-sm mb-3" style="background: linear-gradient(135deg, #0f2137 0%, #1e3a8a 100%); border-radius: 12px; color: #fff;">
  <div class="card-body p-3.5">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2.5">
      <div class="d-flex align-items-center gap-2">
        <span class="spinner-grow spinner-grow-sm text-warning" role="status"></span>
        <h6 class="mb-0 fw-bold text-white"><i class="bi bi-box-arrow-in-right text-warning me-1"></i> Active Ballot(s) Awaiting Your Vote</h6>
      </div>
      <span class="badge bg-warning text-dark fw-bold px-2.5 py-1"><?= count($myPendingBallots) ?> Voting Event(s) Open</span>
    </div>
    <div class="d-flex flex-column gap-2">
      <?php foreach($myPendingBallots as $b): ?>
        <div class="d-flex align-items-center justify-content-between p-2.5 rounded bg-white bg-opacity-10 flex-wrap gap-2">
          <div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-light text-dark fw-semibold"><?= e($b['reference_number']) ?></span>
              <strong class="text-white small"><?= e($b['session_ref']?:'Session') ?> · <?= e($b['session_title']?:'') ?></strong>
            </div>
            <div class="text-white-50 small mt-1"><?= e(mb_strimwidth($b['motion_text'], 0, 110, '…')) ?></div>
          </div>
          <a href="<?= e(appUrl('modules/voting/ballot.php?event_id='.$b['id'])) ?>" class="btn btn-sm btn-warning fw-bold px-3 py-1.5 shadow-sm">
            <i class="bi bi-check2-square me-1"></i> Cast Vote Now
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="vqdf-trace mb-3">
<a href="<?= e(appUrl('modules/quorum/index.php')) ?>"><strong><?= $s['certified_quorum'] ?></strong><small>Certified Quorum</small></a>
<a href="<?= e(appUrl('modules/voting/index.php')) ?>"><strong><?= $s['closed_votes'] ?></strong><small>Closed Voting Events</small></a>
<a href="<?= e(appUrl('modules/tallying/index.php')) ?>"><strong><?= $s['certified_tallies'] ?></strong><small>Certified Tallies</small></a>
<a href="<?= e(appUrl('modules/decisions/index.php')) ?>"><strong><?= $s['certified_decisions'] ?></strong><small>Official Decisions</small></a>
<a href="<?= e(appUrl('modules/validation/index.php')) ?>"><strong><?= $s['certified_validations'] ?></strong><small>Certified Validations</small></a>
</div>

<div class="row g-3 mb-3"><?php foreach([
 ['Pending Quorum',$pending['quorum'],'bi-person-check'],['Active/Ready Votes',$pending['votes'],'bi-broadcast'],
 ['Pending Tallies',$pending['tallies'],'bi-calculator'],['Pending Decisions',$pending['decisions'],'bi-journal-text'],
 ['Pending Validations',$pending['validations'],'bi-patch-question'],
] as [$l,$v,$i]): ?><div class="col-6 col-xl"><div class="vqdf-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= $v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="row g-3 mb-3">
<div class="col-xl-7"><div class="card vqdf-card h-100"><div class="card-header">Live Voting Activity</div><div class="table-responsive"><table class="table vqdf-table mb-0"><thead><tr><th>Voting Event</th><th>Motion</th><th>Method</th><th>Progress</th><th>Status</th></tr></thead><tbody><?php if(!$live): ?><tr><td colspan="5" class="text-center text-muted py-4">No live or paused voting event.</td></tr><?php endif; ?><?php foreach($live as $e): ?><tr><td><a href="<?= e(appUrl('modules/voting/view.php?id='.$e['id'])) ?>"><?= e($e['reference_number']) ?></a></td><td><?= e(mb_strimwidth($e['motion_text'],0,100,'…')) ?></td><td><?= e($e['voting_method']) ?></td><td><?= (int)$e['ballots'] ?> / <?= (int)$e['eligible'] ?> ballots</td><td><?= e($e['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<div class="col-xl-5"><div class="card vqdf-card h-100" id="vqdssRecentActivityCard">
    <div class="card-header">Recent VQDSS Activity</div>
    <div class="card-body vqdf-history" id="vqdssActivityList">
        <?php if(!$activities): ?>
            <div class="text-muted small">No recent VQDSS activity.</div>
        <?php endif; ?>
        <?php foreach($activities as $idx => $a): ?>
            <div class="<?= $idx >= 5 ? 'vqdss-activity-extra d-none' : '' ?>">
                <strong><?= e($a['action']) ?> · <?= e($a['full_name']?:'System') ?></strong>
                <small><?= e(mb_strimwidth($a['details']?:'',0,130,'…')) ?><br><?= formatDateTime($a['created_at']) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if(count($activities) > 5): ?>
        <div class="card-footer bg-transparent border-top-0 pt-0 pb-3 px-3">
            <button type="button" class="btn btn-sm btn-light border w-100 py-1 fw-semibold text-secondary d-flex align-items-center justify-content-center gap-2" id="btnToggleVqdssActivityBottom" onclick="toggleVqdssActivity()" style="font-size: 0.8rem; border-radius: 8px;">
                <i class="bi bi-chevron-down" id="iconToggleVqdssActivityBottom"></i>
                <span id="textToggleVqdssActivityBottom">See All (<?= count($activities) ?>)</span>
            </button>
        </div>
    <?php endif; ?>
</div></div>
</div>

<div class="row g-3">
<div class="col-xl-6"><div class="card vqdf-card"><div class="card-header">Certified Tally Outcomes</div><div class="card-body"><canvas id="outcomeChart" height="130"></canvas></div></div></div>
<div class="col-xl-6"><div class="card vqdf-card"><div class="card-header">Validation Risk Distribution</div><div class="card-body"><canvas id="riskChart" height="130"></canvas></div></div></div>
</div>
</main></div>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const goldBluePalette = [
        '#b8860b', // Primary Dark Gold
        '#0f2137', // Deep Navy Blue
        '#2563eb', // Royal Blue
        '#d97706', // Warm Amber Gold
        '#0284c7', // Sky Blue
        '#1e40af'  // Dark Blue
    ];

    const outcomeCanvas = document.getElementById('outcomeChart');
    if (outcomeCanvas && typeof Chart !== 'undefined') {
        new Chart(outcomeCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($outcomes, 'outcome')) ?>,
                datasets: [{
                    label: 'Certified Tallies',
                    data: <?= json_encode(array_map('intval', array_column($outcomes, 'total'))) ?>,
                    backgroundColor: goldBluePalette,
                    borderColor: goldBluePalette,
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

    const riskCanvas = document.getElementById('riskChart');
    if (riskCanvas && typeof Chart !== 'undefined') {
        new Chart(riskCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($risk, 'risk_level')) ?>,
                datasets: [{
                    data: <?= json_encode(array_map('intval', array_column($risk, 'total'))) ?>,
                    backgroundColor: goldBluePalette,
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: { size: 12 }
                        }
                    }
                }
            }
        });
    }
});
</script>
<style>
.vqdss-activity-extra:not(.d-none) {
    animation: fadeInVqdssActivity 0.25s ease-in-out;
}
@keyframes fadeInVqdssActivity {
    from { opacity: 0; transform: translateY(-3px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
<script>
function toggleVqdssActivity() {
  const extras = document.querySelectorAll('.vqdss-activity-extra');
  if (!extras.length) return;
  const isHidden = extras[0].classList.contains('d-none');

  extras.forEach(el => {
    if (isHidden) {
      el.classList.remove('d-none');
    } else {
      el.classList.add('d-none');
    }
  });

  const totalCount = extras.length + 5;
  const textBottom = document.getElementById('textToggleVqdssActivityBottom');
  const iconBottom = document.getElementById('iconToggleVqdssActivityBottom');

  if (isHidden) {
    if (textBottom) textBottom.textContent = 'Hide';
    if (iconBottom) iconBottom.className = 'bi bi-chevron-up';
  } else {
    if (textBottom) textBottom.textContent = 'See All (' + totalCount + ')';
    if (iconBottom) iconBottom.className = 'bi bi-chevron-down';

    const card = document.getElementById('vqdssRecentActivityCard');
    if (card) {
      card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }
}
</script>
<?php include __DIR__.'/layouts/footer.php'; ?>
