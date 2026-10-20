<?php
declare(strict_types=1);

/**
 * modules/voting/ajax_ai_voting.php
 * AJAX endpoint for Ollama AI Voting Procedure & Threshold Advisor in VQDSS.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.voting.view');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}
requireCsrf();

$pdo = db();
$eventId = (int)($_POST['event_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? 'advise_threshold'));

if ($eventId <= 0) {
    jsonResponse(false, 'Valid voting event ID is required.');
}

$q = $pdo->prepare(
    "SELECT ve.*, qc.reference_number quorum_reference, qc.quorum_result, qc.eligible_member_count,
            s.reference_number session_reference, s.session_title,
            li.reference_number legislative_reference, li.title legislative_title
     FROM vqd_voting_events ve
     LEFT JOIN vqd_quorum_checks qc ON qc.id = ve.quorum_check_id
     LEFT JOIN vqd_sessions s ON s.id = ve.session_id
     LEFT JOIN legislative_items li ON li.id = ve.legislative_item_id
     WHERE ve.id = :id"
);
$q->execute([':id' => $eventId]);
$event = $q->fetch();

if (!$event) {
    jsonResponse(false, 'Voting event not found.');
}

$ai = vqdssAiService();

if ($action === 'advise_threshold') {
    try {
        $context = [
            'id' => $eventId,
            'reference_number' => $event['reference_number'],
            'title' => $event['legislative_title'] ?: $event['motion_text'],
            'motion_text' => $event['motion_text'],
            'quorum_status' => $event['quorum_result'] ? "Quorum {$event['quorum_result']}" : 'Quorum Met',
            'eligible_members' => (int)($event['eligible_member_count'] ?: 38),
        ];

        $res = $ai->adviseVotingThreshold($context);
        if (!$res['success']) {
            jsonResponse(false, $res['error'] ?? 'Ollama could not generate threshold advisory.');
        }

        vqdLogActivity(
            currentUserId(),
            'AI Advise Voting Threshold',
            "Voting Event {$event['reference_number']} · Advised threshold: {$res['recommended_threshold']}."
        );

        jsonResponse(true, 'Voting procedure advice generated.', ['data' => $res]);
    } catch (Throwable $e) {
        jsonResponse(false, 'AI Error: ' . $e->getMessage());
    }
}

jsonResponse(false, 'Unknown action.');
