# Voting, Quorum, and Decision Support System

Suggested local folder:

C:\xampp\htdocs\vqdss\

Suggested URL:

http://localhost/vqdss/

## Main Modules

1. Quorum Verification
2. Voting Management
3. Vote Tallying
4. Decision Recording
5. Result Validation and Reporting

## Shared Integration Plan

This website will use the same shared MySQL database, user accounts,
roles, and PHP session used by the other legislative websites.

Shared database:

legislative_management_db

Shared session settings:

SESSION_NAME = lph_session
Cookie path = /
Idle timeout = 8 hours

## Folder Structure

vqdss/
├── assets/
│   ├── css/
│   ├── images/
│   ├── js/
│   ├── uploads/
│   └── vendor/
├── auth/
├── config/
├── database/
│   ├── migrations/
│   └── seeds/
├── includes/
├── layouts/
├── logs/
├── modules/
│   ├── quorum/
│   ├── voting/
│   ├── tallying/
│   ├── decisions/
│   └── validation/
├── pages/
├── reports/
├── dashboard.php
├── index.php
├── login.php
├── logout.php
└── README.md

## Recommended Database Prefix

Use the prefix:

vqd_

Examples:

vqd_sessions
vqd_attendance
vqd_quorum_checks
vqd_voting_events
vqd_vote_options
vqd_votes
vqd_vote_tallies
vqd_decisions
vqd_result_validations
vqd_documents
vqd_activity_logs

The exact database schema will be finalized during backend development.