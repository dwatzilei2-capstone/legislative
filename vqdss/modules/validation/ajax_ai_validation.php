<?php
declare(strict_types=1);

/**
 * modules/validation/ajax_ai_validation.php
 * AJAX endpoint for Ollama AI Result Validation Auditor in VQDSS.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.validation.view');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}
requireCsrf();

$pdo = db();
$validationId = (int)($_POST['validation_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? 'audit_review'));

if ($validationId <= 0) {
    jsonResponse(false, 'Valid validation ID is required.');
}

$q = $pdo->prepare(
    "SELECT v.*, d.official_outcome, d.decision_statement,
            li.reference_number legislative_reference, li.title legislative_title,
            qc.verification_status quorum_status, qc.quorum_result,
            ve.reference_number voting_ref, ve.motion_text,
            t.reference_number tally_ref, t.discrepancy_classification, t.audit_findings tally_findings
     FROM vqd_result_validations v
     LEFT JOIN vqd_decisions d ON d.id = v.decision_id
     LEFT JOIN legislative_items li ON li.id = v.legislative_item_id
     LEFT JOIN vqd_quorum_checks qc ON qc.id = v.quorum_check_id
     LEFT JOIN vqd_voting_events ve ON ve.id = v.voting_event_id
     LEFT JOIN vqd_vote_tallies t ON t.id = v.tally_id
     WHERE v.id = :id"
);
$q->execute([':id' => $validationId]);
$val = $q->fetch();

if (!$val) {
    jsonResponse(false, 'Validation record not found.');
}

// Fetch automated checks summary
$checksStmt = $pdo->prepare('SELECT check_name, check_status, check_note FROM vqd_validation_checks WHERE validation_id = :id');
$checksStmt->execute([':id' => $validationId]);
$checkRows = $checksStmt->fetchAll();
$checksSummaryArr = [];
foreach ($checkRows as $c) {
    $checksSummaryArr[] = "{$c['check_name']}: {$c['check_status']}";
}
$val['checks_summary'] = $checksSummaryArr ? implode('; ', $checksSummaryArr) : 'Standard checks evaluated.';

// Fetch findings summary
$findingsStmt = $pdo->prepare('SELECT finding_title, severity, status FROM vqd_validation_findings WHERE validation_id = :id');
$findingsStmt->execute([':id' => $validationId]);
$findingRows = $findingsStmt->fetchAll();
$findingsSummaryArr = [];
foreach ($findingRows as $f) {
    $findingsSummaryArr[] = "[{$f['severity']}] {$f['finding_title']} ({$f['status']})";
}
$val['findings_summary'] = $findingsSummaryArr ? implode('; ', $findingsSummaryArr) : 'No unresolved validation findings.';

$ai = vqdssAiService();

// Action: Conduct Legislative Audit Review
if ($action === 'audit_review') {
    try {
        $res = $ai->auditValidationResult($val);
        if (!$res['success']) {
            jsonResponse(false, $res['error'] ?? 'Ollama could not complete validation audit.');
        }

        vqdLogActivity(
            currentUserId(),
            'AI Validation Audit',
            "Validation {$val['validation_reference']} · Evaluated integrity and drafted report narratives."
        );

        jsonResponse(true, 'Legislative audit analysis complete.', ['data' => $res]);
    } catch (Throwable $e) {
        jsonResponse(false, 'AI Audit Error: ' . $e->getMessage());
    }
}

// Action: Apply Audit to Validation record
if ($action === 'apply_audit') {
    requirePermission('vqd.validation.manage');
    $exec = trim((string)($_POST['executive_summary'] ?? ''));
    $findings = trim((string)($_POST['overall_findings'] ?? ''));
    $corrective = trim((string)($_POST['corrective_action'] ?? ''));
    $response = trim((string)($_POST['management_response'] ?? ''));
    $recom = trim((string)($_POST['recommendation'] ?? ''));
    $cert = trim((string)($_POST['certification_statement'] ?? ''));

    try {
        $pdo->prepare(
            'UPDATE vqd_result_validations
             SET executive_summary = :exec,
                 overall_findings = :findings,
                 corrective_action = :corrective,
                 management_response = :response,
                 recommendation = :recom,
                 certification_statement = :cert,
                 updated_at = NOW()
             WHERE id = :id'
        )->execute([
            ':exec' => $exec ?: null,
            ':findings' => $findings ?: null,
            ':corrective' => $corrective ?: null,
            ':response' => $response ?: null,
            ':recom' => $recom ?: null,
            ':cert' => $cert ?: null,
            ':id' => $validationId,
        ]);

        vqdValidationHistory(
            $pdo,
            $validationId,
            'AI Audit Content Applied',
            $val['validation_status'],
            $val['validation_status'],
            'Applied AI legislative audit report narratives into validation record.'
        );

        vqdLogActivity(
            currentUserId(),
            'AI Apply Validation Content',
            "Validation {$val['validation_reference']} · Updated audit narratives via Ollama AI."
        );

        jsonResponse(true, 'Validation audit narratives applied successfully.');
    } catch (Throwable $e) {
        jsonResponse(false, 'Failed to update validation: ' . $e->getMessage());
    }
}

jsonResponse(false, 'Unknown action.');
