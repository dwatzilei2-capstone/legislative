<?php
declare(strict_types=1);

require_once __DIR__ . '/vqdss_helpers.php';

function vqdOperationalManager(): bool
{
    return function_exists('vqdHasPermission')
        ? vqdHasPermission('vqd.records.manage')
        : in_array(currentRole(), [ROLE_ADMIN, ROLE_STAFF], true);
}

function vqdQuorumRequiredCount(
    int $eligible,
    string $rule,
    ?float $customPercentage=null,
    ?int $fixedCount=null
): int {
    if ($eligible <= 0) return 0;

    return match ($rule) {
        'Two-Thirds' => (int)ceil($eligible * (2 / 3)),
        'Three-Fourths' => (int)ceil($eligible * 0.75),
        'Unanimous' => $eligible,
        'Custom Percentage' => (int)ceil(
            $eligible * max(0, min(100, (float)$customPercentage)) / 100
        ),
        'Fixed Count' => max(0, min($eligible, (int)$fixedCount)),
        default => (int)floor($eligible / 2) + 1,
    };
}

function vqdRefreshQuorum(PDO $pdo, int $checkId): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM vqd_quorum_checks WHERE id=:id'
    );
    $stmt->execute([':id'=>$checkId]);
    $check = $stmt->fetch();

    if (!$check) {
        throw new RuntimeException('Quorum check not found.');
    }

    $stats = $pdo->prepare(
        "SELECT
            COUNT(*) official_count,
            SUM(voting_eligibility='Eligible' AND included_in_quorum_count=1) eligible_count,
            SUM(
                voting_eligibility='Eligible'
                AND included_in_quorum_count=1
                AND attendance_status IN ('Present','Remote Present')
            ) present_eligible_count,
            SUM(
                included_in_official_count=1
                AND attendance_status IN ('Present','Remote Present')
            ) present_official_count,
            SUM(attendance_status='Absent') absent_count,
            SUM(attendance_status='Excused') excused_count,
            SUM(attendance_status='Remote Present') remote_present_count
         FROM vqd_quorum_check_members
         WHERE quorum_check_id=:id
           AND included_in_official_count=1"
    );
    $stats->execute([':id'=>$checkId]);
    $s = $stats->fetch() ?: [];

    $official = (int)($s['official_count'] ?? 0);
    $eligible = (int)($s['eligible_count'] ?? 0);
    $present = (int)($s['present_eligible_count'] ?? 0);
    $presentOfficial = (int)($s['present_official_count'] ?? 0);
    $basisCount = (string)$check['quorum_basis'] === 'Official Members'
        ? $official
        : $eligible;
    $presentForBasis = (string)$check['quorum_basis'] === 'Official Members'
        ? $presentOfficial
        : $present;

    $required = vqdQuorumRequiredCount(
        $basisCount,
        (string)$check['quorum_rule'],
        $check['custom_percentage'] !== null ? (float)$check['custom_percentage'] : null,
        $check['fixed_required_count'] !== null ? (int)$check['fixed_required_count'] : null
    );

    $result = $basisCount <= 0
        ? 'Pending'
        : ($presentForBasis >= $required ? 'Met' : 'Not Met');

    $pdo->prepare(
        'UPDATE vqd_quorum_checks
         SET official_member_count=:official,
             eligible_member_count=:eligible,
             present_eligible_count=:present,
             absent_count=:absent,
             excused_count=:excused,
             remote_present_count=:remote,
             required_count=:required,
             quorum_result=:result,
             updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':official'=>$official,
        ':eligible'=>$eligible,
        ':present'=>$present,
        ':absent'=>(int)($s['absent_count'] ?? 0),
        ':excused'=>(int)($s['excused_count'] ?? 0),
        ':remote'=>(int)($s['remote_present_count'] ?? 0),
        ':required'=>$required,
        ':result'=>$result,
        ':id'=>$checkId,
    ]);

    return [
        'official'=>$official,
        'eligible'=>$eligible,
        'present'=>$present,
        'present_for_basis'=>$presentForBasis,
        'basis_count'=>$basisCount,
        'required'=>$required,
        'result'=>$result,
    ];
}

function vqdSessionHistory(
    PDO $pdo,
    int $sessionId,
    string $action,
    ?string $previousStatus,
    ?string $newStatus,
    string $details
): void {
    if (!tableExists('vqd_session_history')) return;

    $pdo->prepare(
        'INSERT INTO vqd_session_history
         (session_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:session,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':session'=>$sessionId,
        ':action'=>$action,
        ':old'=>$previousStatus,
        ':new'=>$newStatus,
        ':details'=>$details,
        ':user'=>currentUserId(),
    ]);
}

function vqdQuorumHistory(
    PDO $pdo,
    int $checkId,
    string $action,
    ?string $previousStatus,
    ?string $newStatus,
    string $details
): void {
    $pdo->prepare(
        'INSERT INTO vqd_quorum_history
         (quorum_check_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:id,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':id'=>$checkId, ':action'=>$action,
        ':old'=>$previousStatus, ':new'=>$newStatus,
        ':details'=>$details, ':user'=>currentUserId()
    ]);
}

function vqdVotingHistory(
    PDO $pdo,
    int $eventId,
    string $action,
    ?string $previousStatus,
    ?string $newStatus,
    string $details
): void {
    $pdo->prepare(
        'INSERT INTO vqd_voting_history
         (voting_event_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:id,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':id'=>$eventId, ':action'=>$action,
        ':old'=>$previousStatus, ':new'=>$newStatus,
        ':details'=>$details, ':user'=>currentUserId()
    ]);
}

function vqdTallyHistory(
    PDO $pdo,
    int $tallyId,
    string $action,
    ?string $previousStatus,
    ?string $newStatus,
    string $details
): void {
    $pdo->prepare(
        'INSERT INTO vqd_tally_history
         (tally_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:id,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':id'=>$tallyId, ':action'=>$action,
        ':old'=>$previousStatus, ':new'=>$newStatus,
        ':details'=>$details, ':user'=>currentUserId()
    ]);
}

function vqdCopyCertifiedVoters(PDO $pdo, int $eventId, int $checkId): int
{
    $pdo->prepare(
        'DELETE FROM vqd_voting_event_voters
         WHERE voting_event_id=:event
           AND voted_at IS NULL'
    )->execute([':event'=>$eventId]);

    $stmt = $pdo->prepare(
        "SELECT qcm.session_member_id,qcm.user_id
         FROM vqd_quorum_check_members qcm
         WHERE qcm.quorum_check_id=:check
           AND qcm.voting_eligibility='Eligible'
           AND qcm.included_in_quorum_count=1
           AND qcm.attendance_status IN ('Present','Remote Present')
         ORDER BY qcm.id"
    );
    $stmt->execute([':check'=>$checkId]);
    $rows = $stmt->fetchAll();

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO vqd_voting_event_voters
         (voting_event_id,session_member_id,user_id,voter_eligibility,
          voting_channel,voter_note,authorization_token_hash,
          authorization_status,authorized_at,voted_at,revoked_at,
          revocation_reason,created_at,updated_at)
         VALUES
         (:event,:member,:user,"Eligible",:channel,NULL,NULL,
          "Eligible",NOW(),NULL,NULL,NULL,NOW(),NOW())'
    );

    $method = $pdo->prepare(
        'SELECT voting_method FROM vqd_voting_events WHERE id=:id'
    );
    $method->execute([':id'=>$eventId]);
    $votingMethod = (string)$method->fetchColumn();

    $channel = $votingMethod === 'Electronic'
        ? 'Electronic'
        : ($votingMethod === 'Hybrid' ? 'Hybrid' : 'Manual');

    $count=0;
    foreach ($rows as $row) {
        $insert->execute([
            ':event'=>$eventId,
            ':member'=>$row['session_member_id'],
            ':user'=>$row['user_id'],
            ':channel'=>$channel,
        ]);
        $count += $insert->rowCount() > 0 ? 1 : 0;
    }

    return $count;
}

function vqdTallyRequiredVotes(
    int $eligible,
    int $valid,
    int $abstain,
    string $threshold,
    ?float $requiredPercentage,
    ?int $fixedRequired
): int {
    $decisive = max(0, $valid - $abstain);

    return match ($threshold) {
        'Majority of Eligible Members' => $eligible > 0
            ? (int)floor($eligible/2)+1 : 0,
        'Two-Thirds' => $decisive > 0
            ? (int)ceil($decisive*(2/3)) : 0,
        'Three-Fourths' => $decisive > 0
            ? (int)ceil($decisive*0.75) : 0,
        'Unanimous' => $decisive,
        'Custom Percentage' => $decisive > 0
            ? (int)ceil($decisive * max(0,min(100,(float)$requiredPercentage))/100)
            : 0,
        'Fixed Count' => max(0,(int)$fixedRequired),
        default => $decisive > 0 ? (int)floor($decisive/2)+1 : 0,
    };
}

function vqdComputeTally(PDO $pdo, int $eventId, ?int $existingTallyId=null): array
{
    $eventStmt = $pdo->prepare(
        'SELECT * FROM vqd_voting_events WHERE id=:id'
    );
    $eventStmt->execute([':id'=>$eventId]);
    $event = $eventStmt->fetch();

    if (!$event) throw new RuntimeException('Voting event not found.');
    if ($event['status'] !== 'Closed') {
        throw new RuntimeException('The voting event must be Closed before tallying.');
    }

    $eligibleStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM vqd_voting_event_voters
         WHERE voting_event_id=:id
           AND voter_eligibility='Eligible'
           AND authorization_status<>'Revoked'"
    );
    $eligibleStmt->execute([':id'=>$eventId]);
    $eligible=(int)$eligibleStmt->fetchColumn();

    $voteStats = $pdo->prepare(
        "SELECT
           COUNT(*) votes_cast,
           SUM(v.is_valid=1) valid_votes,
           SUM(v.is_valid=0) invalid_ballots,
           SUM(v.is_valid=1 AND o.is_affirmative=1) affirmative_votes,
           SUM(
             v.is_valid=1
             AND o.is_affirmative=0
             AND o.is_abstention=0
           ) negative_votes,
           SUM(v.is_valid=1 AND o.is_abstention=1) abstain_votes
         FROM vqd_votes v
         LEFT JOIN vqd_vote_options o ON o.id=v.option_id
         WHERE v.voting_event_id=:id"
    );
    $voteStats->execute([':id'=>$eventId]);
    $s=$voteStats->fetch() ?: [];

    $challengeStmt=$pdo->prepare(
        "SELECT COUNT(*)
         FROM vqd_ballot_challenges
         WHERE voting_event_id=:id AND status='Open'"
    );
    $challengeStmt->execute([':id'=>$eventId]);
    $challenged=(int)$challengeStmt->fetchColumn();

    $votesCast=(int)($s['votes_cast']??0);
    $valid=(int)($s['valid_votes']??0);
    $invalid=(int)($s['invalid_ballots']??0);
    $affirmative=(int)($s['affirmative_votes']??0);
    $negative=(int)($s['negative_votes']??0);
    $abstain=(int)($s['abstain_votes']??0);
    $missing=max(0,$eligible-$votesCast);

    $required=vqdTallyRequiredVotes(
        $eligible,$valid,$abstain,
        (string)$event['decision_threshold'],
        $event['required_percentage']!==null?(float)$event['required_percentage']:null,
        $event['fixed_required_votes']!==null?(int)$event['fixed_required_votes']:null
    );

    if ($affirmative === $negative && ($affirmative+$negative)>0) {
        $outcome='Tie';
        $statement='The affirmative and negative votes are tied. The configured tie rule requires procedural resolution.';
    } elseif ($required > 0 && $affirmative >= $required) {
        $outcome='Passed';
        $statement="Motion passed with {$affirmative} affirmative vote(s); {$required} required.";
    } else {
        $outcome='Failed';
        $statement="Motion did not reach the required threshold. {$affirmative} affirmative vote(s); {$required} required.";
    }

    $discrepancy='None';
    $findings=[];

    if ($votesCast > $eligible) {
        $discrepancy='Critical';
        $findings[]='Votes cast exceed the number of eligible voters.';
    } elseif ($invalid > 0 || $challenged > 0) {
        $discrepancy='Review Required';
        if ($invalid>0) $findings[]="{$invalid} invalid ballot(s) recorded.";
        if ($challenged>0) $findings[]="{$challenged} open ballot challenge(s) require resolution.";
    } elseif ($missing > 0) {
        $discrepancy='Missing Votes';
        $findings[]="{$missing} eligible voter(s) did not cast a vote.";
    }

    $auditFindings=$findings ? implode(' ', $findings) : 'No tally integrity discrepancy detected.';

    if ($existingTallyId) {
        $tallyId=$existingTallyId;
        $pdo->prepare(
            'UPDATE vqd_vote_tallies
             SET legislative_item_id=:item,
                 voting_session_reference=:session_ref,
                 vote_source="System Votes",
                 tally_datetime=NOW(),
                 tally_officer_id=:officer,
                 eligible_voters=:eligible,
                 votes_cast=:cast,
                 valid_votes=:valid,
                 invalid_ballots=:invalid,
                 challenged_ballots=:challenged,
                 missing_votes=:missing,
                 affirmative_votes=:yes,
                 negative_votes=:no,
                 abstain_votes=:abstain,
                 decision_threshold=:threshold,
                 fixed_required_votes=:fixed,
                 required_votes=:required,
                 result_outcome=:outcome,
                 result_statement=:statement,
                 discrepancy_classification=:discrepancy,
                 audit_findings=:findings,
                 updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':item'=>$event['legislative_item_id'],
            ':session_ref'=>$event['reference_number'],
            ':officer'=>currentUserId(),
            ':eligible'=>$eligible,':cast'=>$votesCast,':valid'=>$valid,
            ':invalid'=>$invalid,':challenged'=>$challenged,':missing'=>$missing,
            ':yes'=>$affirmative,':no'=>$negative,':abstain'=>$abstain,
            ':threshold'=>$event['decision_threshold'],
            ':fixed'=>$event['fixed_required_votes'],
            ':required'=>$required,':outcome'=>$outcome,':statement'=>$statement,
            ':discrepancy'=>$discrepancy,':findings'=>$auditFindings,
            ':id'=>$tallyId,
        ]);
    } else {
        $ref=vqdGenerateReference($pdo,'vqd_vote_tallies','reference_number','TLY',$event['session_date']);
        $pdo->prepare(
            'INSERT INTO vqd_vote_tallies
             (voting_event_id,legislative_item_id,reference_number,voting_session_reference,
              vote_source,tally_datetime,tally_officer_id,reviewer_id,eligible_voters,
              votes_cast,valid_votes,invalid_ballots,challenged_ballots,missing_votes,
              affirmative_votes,negative_votes,abstain_votes,decision_threshold,
              fixed_required_votes,required_votes,result_outcome,result_statement,
              discrepancy_classification,audit_findings,recount_status,recount_method,
              recount_notes,decision_handoff,tally_status,certified_by,certified_at,
              created_by,created_at,updated_at)
             VALUES
             (:event,:item,:ref,:session_ref,"System Votes",NOW(),:officer,NULL,:eligible,
              :cast,:valid,:invalid,:challenged,:missing,:yes,:no,:abstain,:threshold,
              :fixed,:required,:outcome,:statement,:discrepancy,:findings,"Not Required",
              NULL,NULL,"Pending","Computed",NULL,NULL,:user,NOW(),NOW())'
        )->execute([
            ':event'=>$eventId,':item'=>$event['legislative_item_id'],':ref'=>$ref,
            ':session_ref'=>$event['reference_number'],':officer'=>currentUserId(),
            ':eligible'=>$eligible,':cast'=>$votesCast,':valid'=>$valid,
            ':invalid'=>$invalid,':challenged'=>$challenged,':missing'=>$missing,
            ':yes'=>$affirmative,':no'=>$negative,':abstain'=>$abstain,
            ':threshold'=>$event['decision_threshold'],':fixed'=>$event['fixed_required_votes'],
            ':required'=>$required,':outcome'=>$outcome,':statement'=>$statement,
            ':discrepancy'=>$discrepancy,':findings'=>$auditFindings,
            ':user'=>currentUserId(),
        ]);
        $tallyId=(int)$pdo->lastInsertId();
    }

    $pdo->prepare('DELETE FROM vqd_vote_tally_options WHERE tally_id=:id')
        ->execute([':id'=>$tallyId]);

    $options=$pdo->prepare(
        "SELECT
           o.id,o.option_label,o.option_category,o.sequence_number,
           COUNT(v.id) vote_count
         FROM vqd_vote_options o
         LEFT JOIN vqd_votes v
           ON v.option_id=o.id
          AND v.voting_event_id=o.voting_event_id
          AND v.is_valid=1
         WHERE o.voting_event_id=:event
           AND o.is_active=1
         GROUP BY o.id,o.option_label,o.option_category,o.sequence_number
         ORDER BY o.sequence_number,o.id"
    );
    $options->execute([':event'=>$eventId]);

    $insert=$pdo->prepare(
        'INSERT INTO vqd_vote_tally_options
         (tally_id,option_id,option_label,option_category,vote_count,
          percentage,option_note,sequence_number)
         VALUES(:tally,:option,:label,:category,:count,:percentage,NULL,:seq)'
    );

    foreach($options->fetchAll() as $option){
        $count=(int)$option['vote_count'];
        $percentage=$valid>0?round(($count/$valid)*100,4):0;
        $insert->execute([
            ':tally'=>$tallyId,':option'=>$option['id'],':label'=>$option['option_label'],
            ':category'=>$option['option_category'],':count'=>$count,
            ':percentage'=>$percentage,':seq'=>$option['sequence_number'],
        ]);
    }

    return [
        'tally_id'=>$tallyId,
        'eligible'=>$eligible,
        'votes_cast'=>$votesCast,
        'valid'=>$valid,
        'invalid'=>$invalid,
        'challenged'=>$challenged,
        'missing'=>$missing,
        'affirmative'=>$affirmative,
        'negative'=>$negative,
        'abstain'=>$abstain,
        'required'=>$required,
        'outcome'=>$outcome,
        'discrepancy'=>$discrepancy,
        'findings'=>$auditFindings,
    ];
}

function vqdssAiService(): AIServiceInterface
{
    static $service = null;
    if ($service === null) {
        require_once __DIR__ . '/AI/OllamaService.php';
        $service = new OllamaService();
    }
    return $service;
}

