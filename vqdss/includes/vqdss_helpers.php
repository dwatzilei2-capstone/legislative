<?php
declare(strict_types=1);

/**
 * Shared VQDSS backend foundation helpers.
 */

function vqdSystemId(): int
{
    static $id = null;

    if ($id !== null) {
        return $id;
    }

    try {
        $stmt = db()->query(
            "SELECT id FROM systems WHERE code='voting' LIMIT 1"
        );
        $value = $stmt->fetchColumn();
        $id = $value ? (int)$value : 3;
    } catch (Throwable $e) {
        error_log('[VQDSS System ID] ' . $e->getMessage());
        $id = 3;
    }

    return $id;
}

function vqdHasSystemAccess(?int $userId): bool
{
    if (!$userId || !tableExists('user_system_access')) {
        return true;
    }

    try {
        $stmt = db()->prepare(
            'SELECT status
             FROM user_system_access
             WHERE user_id=:user_id
               AND system_id=:system_id
             LIMIT 1'
        );

        $stmt->execute([
            ':user_id'=>$userId,
            ':system_id'=>vqdSystemId(),
        ]);

        $row = $stmt->fetch();

        // Before migration 004, preserve legacy behavior. After the
        // final security migration exists, access must be explicit.
        if (!$row) {
            return !tableExists('vqd_login_attempts');
        }

        return strcasecmp((string)$row['status'], 'Active') === 0;
    } catch (Throwable $e) {
        error_log('[VQDSS System Access] ' . $e->getMessage());
        return true;
    }
}

function vqdLogActivity(?int $userId, string $action, string $details=''): void
{
    if (!tableExists('activity_logs')) {
        return;
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_logs
             (user_id,system_id,action,details,ip_address,user_agent,created_at)
             VALUES
             (:user_id,:system_id,:action,:details,:ip,:agent,NOW())'
        );

        $stmt->execute([
            ':user_id'=>$userId,
            ':system_id'=>vqdSystemId(),
            ':action'=>substr($action,0,100),
            ':details'=>$details,
            ':ip'=>substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45) ?: null,
            ':agent'=>substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,500) ?: null,
        ]);
    } catch (Throwable $e) {
        error_log('[VQDSS Activity Log] ' . $e->getMessage());
    }
}

function vqdGenerateReference(
    PDO $pdo,
    string $table,
    string $column,
    string $prefix,
    ?string $date=null
): string {
    $allowed = [
        'vqd_sessions'=>['reference_number'],
        'vqd_quorum_checks'=>['reference_number'],
        'vqd_voting_events'=>['reference_number'],
        'vqd_vote_tallies'=>['reference_number'],
        'vqd_decisions'=>['reference_number','decision_record_number'],
        'vqd_result_validations'=>['validation_reference'],
    ];

    if (!isset($allowed[$table]) || !in_array($column,$allowed[$table],true)) {
        throw new InvalidArgumentException('Unsupported VQDSS reference target.');
    }

    $year = $date && strtotime($date)
        ? date('Y',strtotime($date))
        : date('Y');

    $base = strtoupper($prefix) . '-' . $year . '-';

    $stmt = $pdo->prepare(
        "SELECT `{$column}`
         FROM `{$table}`
         WHERE `{$column}` LIKE :prefix
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([':prefix'=>$base.'%']);

    $last=(string)($stmt->fetchColumn() ?: '');
    $next=1;

    if ($last !== '' && preg_match('/(\\d{4,})$/',$last,$m)) {
        $next=(int)$m[1]+1;
    }

    for ($attempt=0;$attempt<50;$attempt++,$next++) {
        $candidate=$base.str_pad((string)$next,4,'0',STR_PAD_LEFT);
        $check=$pdo->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}`=:reference"
        );
        $check->execute([':reference'=>$candidate]);

        if ((int)$check->fetchColumn()===0) {
            return $candidate;
        }
    }

    return $base.strtoupper(substr(bin2hex(random_bytes(6)),0,10));
}

function vqdSessionStatuses(): array
{
    return ['Draft','Scheduled','Quorum Verification','Voting','Completed','Cancelled'];
}

function vqdQuorumStatuses(): array
{
    return ['Draft','Verified','Certified','Revoked'];
}

function vqdVotingStatuses(): array
{
    return ['Draft','Ready','Open','Paused','Closed','Cancelled'];
}

function vqdTallyStatuses(): array
{
    return ['Draft','Computed','Under Review','Certified','Recounted'];
}

function vqdDecisionStatuses(): array
{
    return ['Draft','Recorded','Certified','Published','Amended','Cancelled'];
}

function vqdValidationStatuses(): array
{
    return ['Draft','In Review','Validated','Certified','Reopened','Failed'];
}
