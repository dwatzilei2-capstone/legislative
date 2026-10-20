<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requirePermission('vqd.reports.view');

$stmt=db()->query(
 "SELECT
    s.reference_number session_reference,
    qc.reference_number quorum_reference,qc.quorum_result,qc.verification_status,
    ve.reference_number voting_reference,ve.status voting_status,
    t.reference_number tally_reference,t.result_outcome,t.tally_status,
    d.reference_number decision_reference,d.official_outcome,d.record_status,
    v.validation_reference,v.integrity_score,v.risk_level,v.validation_outcome,v.validation_status
  FROM vqd_sessions s
  LEFT JOIN vqd_quorum_checks qc ON qc.id=(SELECT MAX(q2.id) FROM vqd_quorum_checks q2 WHERE q2.session_id=s.id)
  LEFT JOIN vqd_voting_events ve ON ve.id=(SELECT MAX(v2.id) FROM vqd_voting_events v2 WHERE v2.session_id=s.id)
  LEFT JOIN vqd_vote_tallies t ON t.id=(SELECT MAX(t2.id) FROM vqd_vote_tallies t2 WHERE t2.voting_event_id=ve.id)
  LEFT JOIN vqd_decisions d ON d.id=(SELECT MAX(d2.id) FROM vqd_decisions d2 WHERE d2.tally_id=t.id)
  LEFT JOIN vqd_result_validations v ON v.id=(SELECT MAX(v3.id) FROM vqd_result_validations v3 WHERE v3.decision_id=d.id)
  ORDER BY s.session_date DESC,s.id DESC"
);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="vqdss_workflow_'.date('Ymd_His').'.csv"');
$out=fopen('php://output','w');
fputcsv($out,['Session','Quorum','Quorum Result','Quorum Status','Voting','Voting Status','Tally','Tally Outcome','Tally Status','Decision','Decision Outcome','Decision Status','Validation','Integrity Score','Risk','Validation Outcome','Validation Status']);
while($r=$stmt->fetch())fputcsv($out,$r);
fclose($out);
vqdLogActivity(currentUserId(),'Export VQDSS Report','Exported consolidated workflow CSV.');
exit;
