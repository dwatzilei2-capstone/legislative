<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lph_module_helpers.php';

requireLogin();
if (!canManage()) jsonResponse(false, 'You do not have permission to create invitations.');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Invalid request method.');
requireCsrf();

$pdo = db();
$hearingId = (int)($_POST['hearing_id'] ?? 0);
$stakeholderIds = $_POST['stakeholder_ids'] ?? [];
$expiresAt = clean($_POST['expires_at'] ?? '') ?: null;
$remarks = trim((string)($_POST['remarks'] ?? ''));

if ($hearingId <= 0) jsonResponse(false, 'Select a hearing.');
if (!is_array($stakeholderIds)) $stakeholderIds = [$stakeholderIds];
$stakeholderIds = array_values(array_unique(array_filter(array_map('intval', $stakeholderIds))));
if (!$stakeholderIds) jsonResponse(false, 'Select at least one stakeholder.');

$hearingStmt = $pdo->prepare('SELECT id,title,status FROM hearings WHERE id=:id');
$hearingStmt->execute([':id'=>$hearingId]);
$hearing = $hearingStmt->fetch();
if (!$hearing) jsonResponse(false, 'Hearing not found.');
if (in_array($hearing['status'], ['Completed','Cancelled'], true)) {
    jsonResponse(false, 'Invitations cannot be created for a completed or cancelled hearing.');
}

$created = 0;
$skipped = 0;

try {
    $pdo->beginTransaction();

    foreach ($stakeholderIds as $sid) {
        $s = $pdo->prepare("SELECT id,full_name,status FROM stakeholders WHERE id=:id AND status<>'Inactive'");
        $s->execute([':id'=>$sid]);
        $stakeholder = $s->fetch();
        if (!$stakeholder) { $skipped++; continue; }

        $dup = $pdo->prepare(
            "SELECT id FROM invitations
             WHERE stakeholder_id=:sid AND hearing_id=:hid
               AND status IN ('Pending','Sent','Accepted')
             LIMIT 1"
        );
        $dup->execute([':sid'=>$sid, ':hid'=>$hearingId]);
        if ($dup->fetch()) { $skipped++; continue; }

        $code = lphUniqueCode($pdo, 'invitations', 'invitation_code', 'INV');

        $stmt = $pdo->prepare(
            'INSERT INTO invitations
             (stakeholder_id,hearing_id,status,invitation_code,sent_at,created_at,
              invited_by,responded_at,expires_at,remarks,updated_at)
             VALUES
             (:sid,:hid,"Pending",:code,NULL,NOW(),:user,NULL,:expires,:remarks,NOW())'
        );
        $stmt->execute([
            ':sid'=>$sid, ':hid'=>$hearingId, ':code'=>$code,
            ':user'=>currentUserId(), ':expires'=>$expiresAt, ':remarks'=>$remarks ?: null
        ]);
        $invitationId = (int)$pdo->lastInsertId();

        // Ensure stakeholder has a permanent QR identification code
        $qrCheck = $pdo->prepare('SELECT code_value FROM qr_codes WHERE stakeholder_id=:sid LIMIT 1');
        $qrCheck->execute([':sid'=>$sid]);
        if (!$qrCheck->fetchColumn()) {
            $stkCode = generateCode('STK-');
            $pdo->prepare('INSERT INTO qr_codes (stakeholder_id, code_value, status, created_at) VALUES (:sid, :code, "Active", NOW())')
                ->execute([':sid'=>$sid, ':code'=>$stkCode]);
        }

        // Automatically enroll invited stakeholder into the hearing attendance roster
        $regCheck = $pdo->prepare('SELECT id FROM registrations WHERE stakeholder_id=:sid AND hearing_id=:hid LIMIT 1');
        $regCheck->execute([':sid'=>$sid, ':hid'=>$hearingId]);
        $existingRegId = $regCheck->fetchColumn();

        if (!$existingRegId) {
            $regCode = lphUniqueCode($pdo, 'registrations', 'registration_code', 'REG');
            $regInsert = $pdo->prepare(
                'INSERT INTO registrations
                 (stakeholder_id, hearing_id, registration_code, registration_status, attendance_type, approved_by, approved_at, registered_at)
                 VALUES (:sid, :hid, :code, "Approved", "Invited", :uid, NOW(), NOW())'
            );
            $regInsert->execute([
                ':sid' => $sid,
                ':hid' => $hearingId,
                ':code' => $regCode,
                ':uid' => currentUserId()
            ]);
        } else {
            $pdo->prepare('UPDATE registrations SET registration_status="Approved", approved_by=COALESCE(approved_by, :uid), approved_at=COALESCE(approved_at, NOW()) WHERE id=:id')
                ->execute([':uid' => currentUserId(), ':id' => $existingRegId]);
        }

        lphHistory($pdo, 'invitation', $invitationId, 'Create', null, 'Pending',
            'Invitation created for '.$stakeholder['full_name'].' to '.$hearing['title'].' and enrolled in attendance roster.');
        $created++;
    }

    logActivity(
        currentUserId(),
        'Create Hearing Invitations',
        "Created {$created} invitation(s) for hearing {$hearing['title']}; {$skipped} skipped."
    );

    $pdo->commit();
    jsonResponse(true, "Created {$created} invitation(s). Invited stakeholder(s) are now ready on the session attendance roster for QR scanning!");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Invitation create error: '.$e->getMessage());
    jsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Unable to create invitations.');
}
