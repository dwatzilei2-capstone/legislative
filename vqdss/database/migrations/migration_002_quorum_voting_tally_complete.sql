USE `legislative_management_db`;

CREATE TABLE IF NOT EXISTS `vqd_session_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `session_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vqd_session_history_session` (`session_id`,`created_at`),
    CONSTRAINT `fk_vqd_session_history_session`
        FOREIGN KEY (`session_id`) REFERENCES `vqd_sessions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_vqd_session_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `vqd_ballot_challenges` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `voting_event_id` INT NOT NULL,
    `vote_id` BIGINT NOT NULL,
    `challenge_type` VARCHAR(100) NOT NULL DEFAULT 'Ballot Review',
    `reason` TEXT NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Open',
    `resolution` TEXT DEFAULT NULL,
    `raised_by` INT DEFAULT NULL,
    `raised_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_by` INT DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_vqd_ballot_challenge_event` (`voting_event_id`,`status`),
    KEY `idx_vqd_ballot_challenge_vote` (`vote_id`),
    CONSTRAINT `fk_vqd_ballot_challenge_event`
        FOREIGN KEY (`voting_event_id`) REFERENCES `vqd_voting_events` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_vqd_ballot_challenge_vote`
        FOREIGN KEY (`vote_id`) REFERENCES `vqd_votes` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_vqd_ballot_challenge_raised_by`
        FOREIGN KEY (`raised_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_vqd_ballot_challenge_resolved_by`
        FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `vqd_schema_migrations` (`migration_key`,`description`)
VALUES (
    '002_quorum_voting_tally_complete',
    'Completes operational session history and ballot challenge support for Quorum, Voting and Vote Tallying.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

SELECT
    EXISTS(
        SELECT 1 FROM information_schema.tables
        WHERE table_schema=DATABASE()
          AND table_name='vqd_session_history'
    ) AS session_history_ok,
    EXISTS(
        SELECT 1 FROM information_schema.tables
        WHERE table_schema=DATABASE()
          AND table_name='vqd_ballot_challenges'
    ) AS ballot_challenges_ok,
    EXISTS(
        SELECT 1 FROM information_schema.tables
        WHERE table_schema=DATABASE()
          AND table_name='vqd_quorum_checks'
    ) AS quorum_tables_ok,
    EXISTS(
        SELECT 1 FROM information_schema.tables
        WHERE table_schema=DATABASE()
          AND table_name='vqd_voting_events'
    ) AS voting_tables_ok,
    EXISTS(
        SELECT 1 FROM information_schema.tables
        WHERE table_schema=DATABASE()
          AND table_name='vqd_vote_tallies'
    ) AS tally_tables_ok;
