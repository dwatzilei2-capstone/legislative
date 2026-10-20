# VQDSS Subsystem #5 — Final End-to-End Testing Checklist

Install migrations 001 through 004 before running this checklist.

## 1. Authentication and Security
- [ ] Administrator can log in.
- [ ] Invalid credentials return a generic error.
- [ ] Five unsuccessful attempts within 15 minutes trigger temporary throttling.
- [ ] Inactive user cannot sign in.
- [ ] User with Inactive VQDSS system access cannot enter VQDSS.
- [ ] Successful login regenerates the PHP session id.
- [ ] CSRF-protected forms reject missing/invalid tokens.
- [ ] New VQDSS activity logs record `system_id`, IP and user-agent.
- [ ] Permission-aware sidebar hides unauthorized modules.

## 2. Roles and Permissions
### Administrator
- [ ] Can access every operational module.
- [ ] Can manage users.
- [ ] Can view activity logs and system health.

### Legislative Staff
- [ ] Can create/manage quorum sessions.
- [ ] Can verify/certify quorum.
- [ ] Can create/control voting events.
- [ ] Can record manual ballots.
- [ ] Can compute/recount/certify tallies.
- [ ] Can manage/certify/publish authorized decisions.
- [ ] Can manage/certify validation results.
- [ ] Cannot open User Management.

### Committee Member
- [ ] Can view quorum records.
- [ ] Can update quorum attendance where permitted.
- [ ] Can view voting events.
- [ ] Can cast their own eligible vote.
- [ ] Can view tally, decision, validation and reports.
- [ ] Cannot create/certify staff-controlled records.

## 3. Quorum Verification
- [ ] Create session.
- [ ] Link legislative item.
- [ ] Add/import official roster.
- [ ] Schedule conflicts are detected.
- [ ] Record Present / Remote Present / Absent / Excused.
- [ ] Set Eligible / Ineligible / Conflict.
- [ ] Automatic threshold is correct.
- [ ] Verify quorum.
- [ ] Certify only when result is Met.
- [ ] Certified roster is locked.
- [ ] Quorum evidence upload works.
- [ ] Print/report works.

## 4. Voting Management
- [ ] Create voting event only from Certified + Met quorum.
- [ ] Eligible roster imports correctly.
- [ ] Configure Yes / No / Abstain or custom options.
- [ ] Open voting.
- [ ] Pause and resume.
- [ ] Manual staff ballot works.
- [ ] Electronic committee-member ballot works.
- [ ] Duplicate vote is blocked.
- [ ] Secret ballot UI hides selected choice after submission.
- [ ] Close voting.
- [ ] Ballot challenge workflow works.
- [ ] Voting documents/print/report work.

## 5. Vote Tallying
- [ ] Closed voting becomes available for tally.
- [ ] System tally computes eligible/cast/valid/invalid/missing.
- [ ] Threshold calculation is correct.
- [ ] Outcome is Passed / Failed / Tie as expected.
- [ ] Challenge resolution changes valid/invalid count correctly.
- [ ] Recount works and records comparison history.
- [ ] Certification is blocked by open challenges/critical discrepancy.
- [ ] Certified tally sets Decision Handoff = Ready.
- [ ] Print/report works.

## 6. Decision Recording
- [ ] Create decision from Certified tally.
- [ ] Vote totals exactly match tally.
- [ ] One primary decision per tally is enforced.
- [ ] Add legal basis / conditions / effectivity.
- [ ] Add implementation action.
- [ ] Assign office/user, priority and due date.
- [ ] Action progresses Pending -> In Progress -> Completed.
- [ ] Upload decision document.
- [ ] Draft -> Recorded -> Certified.
- [ ] Publication requires Public release classification.
- [ ] Print/report works.

## 7. Result Validation
- [ ] Create validation from Certified/Published decision.
- [ ] Quorum/Voting/Tally/Decision references are preserved.
- [ ] Automatic integrity checks populate.
- [ ] Integrity score and risk level calculate.
- [ ] Add finding.
- [ ] Corrective action and resolution work.
- [ ] Failed/pending checks block certification.
- [ ] Open High/Critical findings block certification.
- [ ] Open ballot challenges block certification.
- [ ] Draft -> In Review -> Validated -> Certified.
- [ ] Reopen/Revalidation works.
- [ ] Generate validation report version.
- [ ] Release report only after certification.
- [ ] Print/report works.

## 8. Dashboard and Reports
- [ ] Dashboard workflow counts match records.
- [ ] Live/paused voting appears in dashboard.
- [ ] Tally outcome chart renders.
- [ ] Validation risk chart renders.
- [ ] Quorum report opens.
- [ ] Voting report opens.
- [ ] Tally report opens.
- [ ] Decision report opens.
- [ ] Validation report opens.
- [ ] Workflow Trace links the correct records.
- [ ] Consolidated CSV export works.
- [ ] Printable summary works.

## 9. User Management and Audit Logs
- [ ] New shared user can be created.
- [ ] Role and office can be changed.
- [ ] VQDSS access can be activated/deactivated.
- [ ] Final Administrator cannot be deactivated/demoted.
- [ ] Current Administrator cannot deactivate themself.
- [ ] Deactivation preserves historical voting/audit data.
- [ ] Activity filters work.
- [ ] Activity CSV works.
- [ ] Activity print works.

## 10. Final Database Integrity
- [ ] Run `database/final_validation.sql`.
- [ ] Every item under `INTEGRITY CHECKS — ALL SHOULD BE 0` returns 0.
- [ ] Open `pages/system_health.php`.
- [ ] No unexpected FAIL check remains.
- [ ] Upload directory is writable.
- [ ] Set `APP_DEBUG=false` before production.
- [ ] Use HTTPS in production.
