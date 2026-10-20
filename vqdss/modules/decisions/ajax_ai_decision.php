<?php
declare(strict_types=1);

/**
 * modules/decisions/ajax_ai_decision.php
 * AJAX endpoint for Ollama AI Decision Assistant in VQDSS.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.decisions.view');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}
requireCsrf();

$pdo = db();
$decisionId = (int)($_POST['decision_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? 'draft_decision'));

if ($decisionId <= 0) {
    jsonResponse(false, 'Valid decision ID is required.');
}

$q = $pdo->prepare(
    "SELECT d.*, t.reference_number tally_reference, t.tally_status, t.result_statement,
            ve.reference_number voting_reference, ve.motion_text,
            li.reference_number legislative_reference, li.title legislative_title
     FROM vqd_decisions d
     LEFT JOIN vqd_vote_tallies t ON t.id = d.tally_id
     LEFT JOIN vqd_voting_events ve ON ve.id = d.voting_event_id
     LEFT JOIN legislative_items li ON li.id = d.legislative_item_id
     WHERE d.id = :id"
);
$q->execute([':id' => $decisionId]);
$decision = $q->fetch();

if (!$decision) {
    jsonResponse(false, 'Decision record not found.');
}

$ai = vqdssAiService();

// Action 1: AI Draft Decision Excerpt & Legal Basis
if ($action === 'draft_decision') {
    try {
        $res = $ai->draftDecisionStatement($decision);
        if (!$res['success']) {
            jsonResponse(false, $res['error'] ?? 'Ollama could not draft decision statement.');
        }

        vqdLogActivity(
            currentUserId(),
            'AI Draft Decision Statement',
            "Decision {$decision['reference_number']} · Generated decision statement and legal basis."
        );

        jsonResponse(true, 'Decision statement and legal basis drafted.', ['data' => $res]);
    } catch (Throwable $e) {
        jsonResponse(false, 'AI Error: ' . $e->getMessage());
    }
}

// Action 2: Suggest Implementation Actions
if ($action === 'suggest_actions') {
    try {
        $res = $ai->suggestImplementationActions($decision);
        if (!$res['success']) {
            jsonResponse(false, $res['error'] ?? 'Ollama could not generate implementation actions.');
        }

        vqdLogActivity(
            currentUserId(),
            'AI Suggest Actions',
            "Decision {$decision['reference_number']} · Generated implementation action plan."
        );

        jsonResponse(true, 'Implementation action suggestions generated.', ['data' => $res]);
    } catch (Throwable $e) {
        jsonResponse(false, 'AI Error: ' . $e->getMessage());
    }
}

// Action 3: Apply Suggested Implementation Actions (Batch insert)
if ($action === 'apply_actions') {
    requirePermission('vqd.decisions.manage');
    $actionsJson = (string)($_POST['actions_json'] ?? '[]');
    $actions = json_decode($actionsJson, true);

    if (!is_array($actions) || empty($actions)) {
        jsonResponse(false, 'No actions provided to apply.');
    }

    try {
        $pdo->beginTransaction();

        $offices = $pdo->query('SELECT id, name FROM offices WHERE status = "Active"')->fetchAll();
        $officeMap = [];
        foreach ($offices as $off) {
            $officeMap[strtolower(trim($off['name']))] = (int)$off['id'];
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO vqd_decision_actions
             (decision_id, action_title, action_description, responsible_office_id, responsible_user_id,
              priority, due_date, status, created_by, created_at, updated_at)
             VALUES
             (:dec, :title, :desc, :office, :user, :priority, :due, "Pending", :creator, NOW(), NOW())'
        );

        $insertedCount = 0;
        foreach ($actions as $act) {
            $title = clean($act['title'] ?? '');
            if ($title === '') continue;

            $desc = clean($act['description'] ?? '');
            $prio = clean($act['priority'] ?? 'Normal');
            if (!in_array($prio, ['Low', 'Normal', 'High', 'Urgent'], true)) $prio = 'Normal';

            $days = max(1, min(90, (int)($act['days_due'] ?? 14)));
            $dueDate = date('Y-m-d', strtotime("+{$days} days"));

            // Match office
            $suggOffice = strtolower(trim((string)($act['suggested_office'] ?? '')));
            $matchedOfficeId = null;
            if ($suggOffice !== '') {
                foreach ($officeMap as $offName => $offId) {
                    if (str_contains($offName, $suggOffice) || str_contains($suggOffice, $offName)) {
                        $matchedOfficeId = $offId;
                        break;
                    }
                }
            }

            $insertStmt->execute([
                ':dec' => $decisionId,
                ':title' => $title,
                ':desc' => $desc ?: null,
                ':office' => $matchedOfficeId,
                ':user' => null,
                ':priority' => $prio,
                ':due' => $dueDate,
                ':creator' => currentUserId(),
            ]);
            $insertedCount++;
        }

        vqdDecisionHistory(
            $pdo,
            $decisionId,
            'AI Implementation Actions Applied',
            $decision['record_status'],
            $decision['record_status'],
            "Applied {$insertedCount} AI-recommended implementation actions."
        );

        vqdLogActivity(
            currentUserId(),
            'AI Apply Actions',
            "Decision {$decision['reference_number']} · Inserted {$insertedCount} implementation actions."
        );

        $pdo->commit();
        jsonResponse(true, "Successfully applied {$insertedCount} implementation action(s).", ['count' => $insertedCount]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(false, 'Failed to save implementation actions: ' . $e->getMessage());
    }
}

// Action 4: Apply Decision Statement & Legal Basis to Decision record
if ($action === 'apply_decision') {
    requirePermission('vqd.decisions.manage');
    $stmt = trim((string)($_POST['decision_statement'] ?? ''));
    $basis = trim((string)($_POST['legal_basis'] ?? ''));
    $cond = trim((string)($_POST['conditions'] ?? ''));

    if ($stmt === '') {
        jsonResponse(false, 'Decision statement is required.');
    }

    try {
        $pdo->prepare(
            'UPDATE vqd_decisions
             SET decision_statement = :stmt,
                 legal_basis = :basis,
                 conditions = :cond,
                 updated_at = NOW()
             WHERE id = :id'
        )->execute([
            ':stmt' => $stmt,
            ':basis' => $basis ?: null,
            ':cond' => $cond ?: null,
            ':id' => $decisionId,
        ]);

        vqdDecisionHistory(
            $pdo,
            $decisionId,
            'AI Decision Content Applied',
            $decision['record_status'],
            $decision['record_status'],
            "Updated decision statement and legal basis via Ollama AI."
        );

        vqdLogActivity(
            currentUserId(),
            'AI Apply Decision Content',
            "Decision {$decision['reference_number']} · Updated decision statement & legal basis."
        );

        jsonResponse(true, 'Decision statement and legal basis updated.');
    } catch (Throwable $e) {
        jsonResponse(false, 'Failed to update decision: ' . $e->getMessage());
    }
}

jsonResponse(false, 'Unknown action.');
