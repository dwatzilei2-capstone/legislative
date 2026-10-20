<?php
declare(strict_types=1);

require_once __DIR__ . '/vqdss_operational_helpers.php';

function vqdDecisionHistory(
    PDO $pdo,
    int $decisionId,
    string $action,
    ?string $previousStatus,
    ?string $newStatus,
    string $details
): void {
    $pdo->prepare(
        'INSERT INTO vqd_decision_history
         (decision_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:id,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':id'=>$decisionId,
        ':action'=>$action,
        ':old'=>$previousStatus,
        ':new'=>$newStatus,
        ':details'=>$details,
        ':user'=>currentUserId(),
    ]);
}

function vqdDecisionActionHistory(
    PDO $pdo,
    int $actionId,
    ?string $previousStatus,
    ?string $newStatus,
    string $details
): void {
    if (!tableExists('vqd_decision_action_history')) return;

    $pdo->prepare(
        'INSERT INTO vqd_decision_action_history
         (decision_action_id,previous_status,new_status,details,changed_by,created_at)
         VALUES(:id,:old,:new,:details,:user,NOW())'
    )->execute([
        ':id'=>$actionId,
        ':old'=>$previousStatus,
        ':new'=>$newStatus,
        ':details'=>$details,
        ':user'=>currentUserId(),
    ]);
}

function vqdValidationHistory(
    PDO $pdo,
    int $validationId,
    string $action,
    ?string $previousStatus,
    ?string $newStatus,
    string $details
): void {
    $pdo->prepare(
        'INSERT INTO vqd_validation_history
         (validation_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:id,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':id'=>$validationId,
        ':action'=>$action,
        ':old'=>$previousStatus,
        ':new'=>$newStatus,
        ':details'=>$details,
        ':user'=>currentUserId(),
    ]);
}

function vqdValidationDefaultChecks(): array
{
    return [
        ['QRM-CERT','Certified quorum exists'],
        ['QRM-MET','Quorum result is Met'],
        ['VOTE-CLOSED','Voting event is Closed'],
        ['BALLOT-UNIQUE','No duplicate voter ballot records'],
        ['BALLOT-CHALLENGE','No open ballot challenges'],
        ['TALLY-CERT','Vote tally is Certified'],
        ['TALLY-ARITH','Vote arithmetic is internally consistent'],
        ['DECISION-CERT','Decision record is Certified or Published'],
        ['DECISION-MATCH','Decision vote totals match certified tally'],
    ];
}

function vqdRefreshValidation(PDO $pdo, int $validationId): array
{
    $q=$pdo->prepare(
        'SELECT rv.*,qc.verification_status quorum_status,qc.quorum_result,
                ve.status voting_status,
                t.tally_status,t.eligible_voters,t.votes_cast,t.valid_votes,
                t.invalid_ballots,t.challenged_ballots,t.missing_votes,
                t.affirmative_votes,t.negative_votes,t.abstain_votes,
                t.discrepancy_classification,
                d.record_status decision_status,
                d.required_votes decision_required,
                d.affirmative_votes decision_yes,
                d.negative_votes decision_no,
                d.abstain_votes decision_abstain
         FROM vqd_result_validations rv
         LEFT JOIN vqd_quorum_checks qc ON qc.id=rv.quorum_check_id
         LEFT JOIN vqd_voting_events ve ON ve.id=rv.voting_event_id
         LEFT JOIN vqd_vote_tallies t ON t.id=rv.tally_id
         LEFT JOIN vqd_decisions d ON d.id=rv.decision_id
         WHERE rv.id=:id'
    );
    $q->execute([':id'=>$validationId]);
    $v=$q->fetch();

    if(!$v) throw new RuntimeException('Validation record not found.');

    $openChallenges=0;
    if($v['voting_event_id']){
        $c=$pdo->prepare(
            "SELECT COUNT(*)
             FROM vqd_ballot_challenges
             WHERE voting_event_id=:id
               AND status='Open'"
        );
        $c->execute([':id'=>$v['voting_event_id']]);
        $openChallenges=(int)$c->fetchColumn();
    }

    $duplicateBallots=0;
    if($v['voting_event_id']){
        $c=$pdo->prepare(
            "SELECT COUNT(*)
             FROM (
               SELECT event_voter_id
               FROM vqd_votes
               WHERE voting_event_id=:id
               GROUP BY event_voter_id
               HAVING COUNT(*)>1
             ) x"
        );
        $c->execute([':id'=>$v['voting_event_id']]);
        $duplicateBallots=(int)$c->fetchColumn();
    }

    $arithOk =
        (int)$v['votes_cast']
        === ((int)$v['valid_votes'] + (int)$v['invalid_ballots']);

    $decisionMatch =
        (int)$v['decision_required']===(int)$v['required_votes']
        && (int)$v['decision_yes']===(int)$v['affirmative_votes']
        && (int)$v['decision_no']===(int)$v['negative_votes']
        && (int)$v['decision_abstain']===(int)$v['abstain_votes'];

    $auto = [
        'QRM-CERT' => $v['quorum_status']==='Certified',
        'QRM-MET' => $v['quorum_result']==='Met',
        'VOTE-CLOSED' => $v['voting_status']==='Closed',
        'BALLOT-UNIQUE' => $duplicateBallots===0,
        'BALLOT-CHALLENGE' => $openChallenges===0,
        'TALLY-CERT' => $v['tally_status']==='Certified',
        'TALLY-ARITH' => $arithOk,
        'DECISION-CERT' => in_array($v['decision_status'],['Certified','Published'],true),
        'DECISION-MATCH' => $decisionMatch,
    ];

    foreach($auto as $code=>$passed){
        $pdo->prepare(
            'UPDATE vqd_validation_checks
             SET check_status=:status,
                 check_note=:note,
                 reviewed_at=NOW()
             WHERE validation_id=:validation
               AND check_code=:code'
        )->execute([
            ':status'=>$passed?'Passed':'Failed',
            ':note'=>$passed
                ? 'Automatic integrity check passed.'
                : 'Automatic integrity check requires review.',
            ':validation'=>$validationId,
            ':code'=>$code,
        ]);
    }

    $stats=$pdo->prepare(
        "SELECT
           COUNT(*) total,
           SUM(check_status='Passed') passed,
           SUM(check_status='Failed') failed,
           SUM(check_status='Warning') warnings,
           SUM(check_status='Pending') pending
         FROM vqd_validation_checks
         WHERE validation_id=:id"
    );
    $stats->execute([':id'=>$validationId]);
    $s=$stats->fetch() ?: [];

    $findingStats=$pdo->prepare(
        "SELECT
           COUNT(*) total,
           SUM(status<>'Resolved') open_findings,
           SUM(status<>'Resolved' AND severity='Critical') open_critical,
           SUM(status<>'Resolved' AND severity='High') open_high
         FROM vqd_validation_findings
         WHERE validation_id=:id"
    );
    $findingStats->execute([':id'=>$validationId]);
    $f=$findingStats->fetch() ?: [];

    $total=max(1,(int)($s['total']??0));
    $passed=(int)($s['passed']??0);
    $warnings=(int)($s['warnings']??0);
    $score=round((($passed + ($warnings*0.5))/$total)*100,2);

    $failed=(int)($s['failed']??0);
    $openCritical=(int)($f['open_critical']??0);
    $openHigh=(int)($f['open_high']??0);
    $openFindings=(int)($f['open_findings']??0);

    if($openCritical>0 || $score<60){
        $risk='Critical';
    }elseif($failed>=2 || $openHigh>0 || $score<75){
        $risk='High';
    }elseif($failed>0 || $openFindings>0 || $score<90){
        $risk='Medium';
    }else{
        $risk='Low';
    }

    if($failed===0 && $openFindings===0){
        $outcome='Validated';
    }elseif($openCritical>0 || $score<60){
        $outcome='Failed';
    }else{
        $outcome='Validated with Findings';
    }

    $pdo->prepare(
        'UPDATE vqd_result_validations
         SET integrity_score=:score,
             risk_level=:risk,
             validation_outcome=:outcome,
             challenge_status=:challenge,
             updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':score'=>$score,
        ':risk'=>$risk,
        ':outcome'=>$outcome,
        ':challenge'=>$openChallenges>0?'Open Challenge':'None',
        ':id'=>$validationId,
    ]);

    return [
        'score'=>$score,
        'risk'=>$risk,
        'outcome'=>$outcome,
        'failed'=>$failed,
        'pending'=>(int)($s['pending']??0),
        'warnings'=>$warnings,
        'open_findings'=>$openFindings,
        'open_critical'=>$openCritical,
        'open_high'=>$openHigh,
        'open_challenges'=>$openChallenges,
    ];
}

function vqdWorkflowSnapshot(PDO $pdo): array
{
    return [
        'sessions'=>(int)$pdo->query('SELECT COUNT(*) FROM vqd_sessions')->fetchColumn(),
        'certified_quorum'=>(int)$pdo->query("SELECT COUNT(*) FROM vqd_quorum_checks WHERE verification_status='Certified' AND quorum_result='Met'")->fetchColumn(),
        'closed_votes'=>(int)$pdo->query("SELECT COUNT(*) FROM vqd_voting_events WHERE status='Closed'")->fetchColumn(),
        'certified_tallies'=>(int)$pdo->query("SELECT COUNT(*) FROM vqd_vote_tallies WHERE tally_status='Certified'")->fetchColumn(),
        'certified_decisions'=>(int)$pdo->query("SELECT COUNT(*) FROM vqd_decisions WHERE record_status IN ('Certified','Published')")->fetchColumn(),
        'certified_validations'=>(int)$pdo->query("SELECT COUNT(*) FROM vqd_result_validations WHERE validation_status='Certified'")->fetchColumn(),
    ];
}
