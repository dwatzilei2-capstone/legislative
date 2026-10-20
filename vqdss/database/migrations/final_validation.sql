USE `legislative_management_db`;

-- ============================================================
-- VQDSS SUBSYSTEM #5 — FINAL READ-ONLY VALIDATION
-- This file DOES NOT modify records.
-- ============================================================

SELECT 'VQDSS MIGRATIONS' AS section_name;
SELECT migration_key,description,applied_at
FROM vqd_schema_migrations
ORDER BY applied_at,migration_key;

SELECT 'CORE RECORD COUNTS' AS section_name;
SELECT
  (SELECT COUNT(*) FROM vqd_sessions) sessions,
  (SELECT COUNT(*) FROM vqd_quorum_checks) quorum_checks,
  (SELECT COUNT(*) FROM vqd_voting_events) voting_events,
  (SELECT COUNT(*) FROM vqd_votes) ballots,
  (SELECT COUNT(*) FROM vqd_vote_tallies) tallies,
  (SELECT COUNT(*) FROM vqd_decisions) decisions,
  (SELECT COUNT(*) FROM vqd_result_validations) validations;

SELECT 'INTEGRITY CHECKS — ALL SHOULD BE 0' AS section_name;

SELECT 'Sessions without official roster members' check_name,COUNT(*) problem_rows
FROM vqd_sessions s
WHERE s.status<>'Cancelled'
  AND NOT EXISTS(SELECT 1 FROM vqd_session_members sm WHERE sm.session_id=s.id)

UNION ALL
SELECT 'Certified quorum whose result is not Met',COUNT(*)
FROM vqd_quorum_checks
WHERE verification_status='Certified'
  AND quorum_result<>'Met'

UNION ALL
SELECT 'Open/Closed voting without certified Met quorum',COUNT(*)
FROM vqd_voting_events ve
LEFT JOIN vqd_quorum_checks qc ON qc.id=ve.quorum_check_id
WHERE ve.status IN ('Open','Paused','Closed')
  AND (qc.id IS NULL OR qc.verification_status<>'Certified' OR qc.quorum_result<>'Met')

UNION ALL
SELECT 'Duplicate ballots for same event voter',COUNT(*)
FROM (
  SELECT voting_event_id,event_voter_id
  FROM vqd_votes
  GROUP BY voting_event_id,event_voter_id
  HAVING COUNT(*)>1
) x

UNION ALL
SELECT 'Votes linked to missing voter authorization',COUNT(*)
FROM vqd_votes v
LEFT JOIN vqd_voting_event_voters ev ON ev.id=v.event_voter_id
WHERE ev.id IS NULL

UNION ALL
SELECT 'Certified tallies not sourced from Closed voting',COUNT(*)
FROM vqd_vote_tallies t
JOIN vqd_voting_events ve ON ve.id=t.voting_event_id
WHERE t.tally_status='Certified'
  AND ve.status<>'Closed'

UNION ALL
SELECT 'Certified tallies with open ballot challenges',COUNT(*)
FROM vqd_vote_tallies t
WHERE t.tally_status='Certified'
  AND EXISTS(
    SELECT 1 FROM vqd_ballot_challenges bc
    WHERE bc.voting_event_id=t.voting_event_id
      AND bc.status='Open'
  )

UNION ALL
SELECT 'Certified tally arithmetic mismatches',COUNT(*)
FROM vqd_vote_tallies
WHERE tally_status='Certified'
  AND votes_cast<>(valid_votes+invalid_ballots)

UNION ALL
SELECT 'Decision vote totals differ from certified tally',COUNT(*)
FROM vqd_decisions d
JOIN vqd_vote_tallies t ON t.id=d.tally_id
WHERE d.record_status IN ('Certified','Published')
  AND (
    d.required_votes<>t.required_votes OR
    d.affirmative_votes<>t.affirmative_votes OR
    d.negative_votes<>t.negative_votes OR
    d.abstain_votes<>t.abstain_votes OR
    d.official_outcome<>t.result_outcome
  )

UNION ALL
SELECT 'Certified/Published decision sourced from non-certified tally',COUNT(*)
FROM vqd_decisions d
JOIN vqd_vote_tallies t ON t.id=d.tally_id
WHERE d.record_status IN ('Certified','Published')
  AND t.tally_status<>'Certified'

UNION ALL
SELECT 'Published decisions not classified Public',COUNT(*)
FROM vqd_decisions
WHERE record_status='Published'
  AND release_classification<>'Public'

UNION ALL
SELECT 'Certified validation with failed/pending checks',COUNT(*)
FROM vqd_result_validations rv
WHERE rv.validation_status='Certified'
  AND EXISTS(
    SELECT 1 FROM vqd_validation_checks c
    WHERE c.validation_id=rv.id
      AND c.check_status IN ('Failed','Pending')
  )

UNION ALL
SELECT 'Certified validation with open High/Critical findings',COUNT(*)
FROM vqd_result_validations rv
WHERE rv.validation_status='Certified'
  AND EXISTS(
    SELECT 1 FROM vqd_validation_findings f
    WHERE f.validation_id=rv.id
      AND f.status<>'Resolved'
      AND f.severity IN ('High','Critical')
  )
;

SELECT 'VQDSS ACCESS SUMMARY' AS section_name;
SELECT usa.status,COUNT(*) total
FROM user_system_access usa
JOIN systems s ON s.id=usa.system_id AND s.code='voting'
GROUP BY usa.status;

SELECT 'OPERATIONAL WATCH LISTS' AS section_name;

SELECT reference_number,session_title,session_date,start_time,status
FROM vqd_sessions
WHERE session_date>=CURDATE()
  AND status NOT IN ('Completed','Cancelled')
ORDER BY session_date,start_time;

SELECT reference_number,motion_text,status,opened_at,closed_at
FROM vqd_voting_events
WHERE status IN ('Ready','Open','Paused')
ORDER BY created_at DESC;

SELECT d.reference_number,a.action_title,a.priority,a.due_date,a.status
FROM vqd_decision_actions a
JOIN vqd_decisions d ON d.id=a.decision_id
WHERE a.due_date<CURDATE()
  AND a.status NOT IN ('Completed','Cancelled')
ORDER BY a.due_date;

SELECT rv.validation_reference,f.finding_title,f.severity,f.due_date,f.status
FROM vqd_validation_findings f
JOIN vqd_result_validations rv ON rv.id=f.validation_id
WHERE f.status NOT IN ('Resolved','Accepted Risk')
ORDER BY FIELD(f.severity,'Critical','High','Medium','Low'),f.due_date;
