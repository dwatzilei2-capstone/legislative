<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requirePermission('vqd.reports.view');

$pdo=db();$pageTitle='Workflow Trace';$activeMenu='reports';
$extraCss=[appUrl('assets/css/vqdss-final-ops.css')];

$rows=$pdo->query(
 "SELECT
    s.id session_id,s.reference_number session_reference,s.session_title,
    qc.id quorum_id,qc.reference_number quorum_reference,qc.quorum_result,qc.verification_status,
    ve.id voting_id,ve.reference_number voting_reference,ve.status voting_status,
    t.id tally_id,t.reference_number tally_reference,t.result_outcome,t.tally_status,
    d.id decision_id,d.reference_number decision_reference,d.record_status,d.official_outcome,
    v.id validation_id,v.validation_reference,v.integrity_score,v.risk_level,v.validation_status,
    li.reference_number legislative_reference
  FROM vqd_sessions s
  LEFT JOIN legislative_items li ON li.id=s.legislative_item_id
  LEFT JOIN vqd_quorum_checks qc
    ON qc.id=(SELECT MAX(q2.id) FROM vqd_quorum_checks q2 WHERE q2.session_id=s.id)
  LEFT JOIN vqd_voting_events ve
    ON ve.id=(SELECT MAX(v2.id) FROM vqd_voting_events v2 WHERE v2.session_id=s.id)
  LEFT JOIN vqd_vote_tallies t
    ON t.id=(SELECT MAX(t2.id) FROM vqd_vote_tallies t2 WHERE t2.voting_event_id=ve.id)
  LEFT JOIN vqd_decisions d
    ON d.id=(SELECT MAX(d2.id) FROM vqd_decisions d2 WHERE d2.tally_id=t.id)
  LEFT JOIN vqd_result_validations v
    ON v.id=(SELECT MAX(v3.id) FROM vqd_result_validations v3 WHERE v3.decision_id=d.id)
  ORDER BY s.session_date DESC,s.start_time DESC,s.id DESC"
)->fetchAll();

include __DIR__.'/../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="main-content">
<div class="vqdf-head"><div><div class="vqdf-eyebrow">Cross-Module Traceability</div><h1>Voting-to-Decision Workflow Trace</h1><p>One screen showing the latest connected quorum, voting event, tally, decision, and validation for every voting session.</p></div><a class="btn btn-outline-secondary" href="index.php">Reports</a></div>

<div class="card vqdf-card"><div class="table-responsive"><table class="table table-hover vqdf-table mb-0"><thead><tr><th>Session / Item</th><th>Quorum</th><th>Voting</th><th>Tally</th><th>Decision</th><th>Validation</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="6" class="text-center text-muted py-5">No VQDSS workflow records.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr>
<td><a href="<?= e(appUrl('modules/quorum/index.php')) ?>"><span class="vqdf-code"><?= e($r['session_reference']) ?></span></a><div><strong><?= e($r['legislative_reference']?:$r['session_title']) ?></strong></div></td>
<td><?php if($r['quorum_id']): ?><a href="<?= e(appUrl('modules/quorum/view.php?id='.$r['quorum_id'])) ?>"><?= e($r['quorum_reference']) ?></a><div class="small text-muted"><?= e($r['quorum_result'].' · '.$r['verification_status']) ?></div><?php else: ?><span class="text-muted">Pending</span><?php endif; ?></td>
<td><?php if($r['voting_id']): ?><a href="<?= e(appUrl('modules/voting/view.php?id='.$r['voting_id'])) ?>"><?= e($r['voting_reference']) ?></a><div class="small text-muted"><?= e($r['voting_status']) ?></div><?php else: ?><span class="text-muted">Pending</span><?php endif; ?></td>
<td><?php if($r['tally_id']): ?><a href="<?= e(appUrl('modules/tallying/view.php?id='.$r['tally_id'])) ?>"><?= e($r['tally_reference']) ?></a><div class="small text-muted"><?= e($r['result_outcome'].' · '.$r['tally_status']) ?></div><?php else: ?><span class="text-muted">Pending</span><?php endif; ?></td>
<td><?php if($r['decision_id']): ?><a href="<?= e(appUrl('modules/decisions/view.php?id='.$r['decision_id'])) ?>"><?= e($r['decision_reference']) ?></a><div class="small text-muted"><?= e($r['official_outcome'].' · '.$r['record_status']) ?></div><?php else: ?><span class="text-muted">Pending</span><?php endif; ?></td>
<td><?php if($r['validation_id']): ?><a href="<?= e(appUrl('modules/validation/view.php?id='.$r['validation_id'])) ?>"><?= e($r['validation_reference']) ?></a><div class="small text-muted"><?= round((float)$r['integrity_score'],2) ?>% · <?= e($r['risk_level'].' · '.$r['validation_status']) ?></div><?php else: ?><span class="text-muted">Pending</span><?php endif; ?></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
