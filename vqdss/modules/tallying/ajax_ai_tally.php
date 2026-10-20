<?php
declare(strict_types=1);

/**
 * modules/tallying/ajax_ai_tally.php
 * AJAX endpoint for Ollama AI Tally & Recount Assistant in VQDSS.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.tally.view');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}
requireCsrf();

$pdo = db();
$tallyId = (int)($_POST['tally_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? 'draft_recount'));

if ($tallyId <= 0) {
    jsonResponse(false, 'Valid tally ID is required.');
}

$q = $pdo->prepare(
    "SELECT t.*, ve.motion_text, ve.reference_number voting_reference, ve.tie_rule,
            s.reference_number session_reference, li.reference_number legislative_reference
     FROM vqd_vote_tallies t
     JOIN vqd_voting_events ve ON ve.id = t.voting_event_id
     LEFT JOIN vqd_sessions s ON s.id = ve.session_id
     LEFT JOIN legislative_items li ON li.id = t.legislative_item_id
     WHERE t.id = :id"
);
$q->execute([':id' => $tallyId]);
$tally = $q->fetch();

if (!$tally) {
    jsonResponse(false, 'Tally not found.');
}

$ai = vqdssAiService();

if ($action === 'draft_recount') {
    try {
        $res = $ai->draftRecountJustification($tally);
        if (!$res['success']) {
            jsonResponse(false, $res['error'] ?? 'Ollama could not generate recount justification.');
        }

        vqdLogActivity(
            currentUserId(),
            'AI Draft Recount Justification',
            "Tally {$tally['reference_number']} · Drafted recount memo."
        );

        jsonResponse(true, 'Recount justification drafted.', ['data' => $res]);
    } catch (Throwable $e) {
        jsonResponse(false, 'AI Error: ' . $e->getMessage());
    }
}

jsonResponse(false, 'Unknown action.');
