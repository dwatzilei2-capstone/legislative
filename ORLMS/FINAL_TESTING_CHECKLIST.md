# ORLMS Final End-to-End Testing Checklist

Use this after migrations 001 through 005 are applied.

## A. Authentication and security
- [ ] Administrator can log in.
- [ ] Legislative Staff can log in when ORLMS access is Active.
- [ ] Committee Member can log in when ORLMS access is Active.
- [ ] Stakeholder/Public User is denied ORLMS access unless deliberately granted.
- [ ] Five failed login attempts trigger the 15-minute rate limit.
- [ ] Successful login clears recent failed attempts for that login/IP.
- [ ] Session expiry returns JSON to AJAX requests instead of login-page HTML.
- [ ] CSRF-protected POST endpoints reject an invalid token.
- [ ] Logout destroys the shared authenticated session.

## B. Fine-grained RBAC
- [ ] Administrator sees all ORLMS lifecycle and administration menus.
- [ ] Legislative Staff sees lifecycle modules and reports, but not Activity Logs/User Management/System Health.
- [ ] Committee Member sees review/endorsement and downstream read-only modules/reports.
- [ ] Committee Member cannot use drafting endpoints.
- [ ] Committee Member cannot create approval/publication/implementation/amendment records.
- [ ] Direct URL access to a missing permission returns 403.
- [ ] AJAX access to a missing permission returns JSON 403.
- [ ] Permission denial is recorded in ORLMS activity logs.

## C. Ordinance and Resolution drafting
- [ ] Create an ordinance and confirm ORD-YYYY-#### reference.
- [ ] Create a resolution and confirm RES-YYYY-#### reference.
- [ ] Add authors/sponsors and supporting documents.
- [ ] Save multiple draft versions.
- [ ] Submit to a committee.
- [ ] Confirm status becomes Submitted for Review.

## D. Review and committee endorsement
- [ ] Start a Pending review.
- [ ] Complete all checklist items.
- [ ] Add Comment and Revision Request.
- [ ] Confirm unresolved Revision Request blocks endorsement.
- [ ] Return one draft for revision.
- [ ] Revise/resubmit and confirm review round increments.
- [ ] Complete another review with Endorse/Endorse with Conditions.
- [ ] Record committee endorsement and vote summary.
- [ ] Confirm item becomes Endorsed.

## E. Approval and enactment
- [ ] Create APR-YYYY-#### approval action.
- [ ] Confirm duplicate Pending approval action is blocked.
- [ ] Test Return for Revision.
- [ ] Test Reject on a test item.
- [ ] Test Veto and Veto Override on a test item.
- [ ] Approve an item.
- [ ] Create ENA-YYYY-#### enactment/adoption record.
- [ ] Confirm duplicate official number is blocked.
- [ ] Finalize enactment.
- [ ] Confirm item becomes Enacted.

## F. Publication and dissemination
- [ ] Create PUB-YYYY-#### publication.
- [ ] Add Office, User and External recipients.
- [ ] Schedule publication.
- [ ] Publish it.
- [ ] Confirm item becomes Published.
- [ ] Confirm linked system user receives notification.
- [ ] Mark manual recipient Sent.
- [ ] Mark recipient Acknowledged.
- [ ] Upload publication evidence.
- [ ] Test withdrawal on a separate publication record.

## G. Implementation monitoring
- [ ] Create IMP-YYYY-#### plan for a Published item.
- [ ] Assign office/user.
- [ ] Add progress update and evidence.
- [ ] Add Low/Medium issue.
- [ ] Add High/Critical issue.
- [ ] Confirm unresolved High/Critical risk blocks completion.
- [ ] Resolve or formally Accept Risk.
- [ ] Complete plan at 100%.
- [ ] Confirm multi-plan logic does not mark item Implemented until applicable plans are complete.
- [ ] Confirm item becomes Implemented.

## H. Amendment and revision tracking
- [ ] Create AMN-YYYY-#### amendment.
- [ ] Confirm source official revision is locked.
- [ ] Add structured provision changes.
- [ ] Add full revised legislative text.
- [ ] Submit for review.
- [ ] Return for Revision and resubmit.
- [ ] Approve amendment.
- [ ] Apply amendment.
- [ ] Confirm new official revision version is created.
- [ ] Confirm prior revisions remain preserved.
- [ ] Confirm current ORLMS body equals current revision snapshot.
- [ ] Create a stale-source test and confirm application is blocked.
- [ ] Test Repeal on a disposable/test record if required.

## I. Dashboard, workflow and reports
- [ ] Dashboard counts match module records.
- [ ] Review queue is correct.
- [ ] Approval queue is correct.
- [ ] Scheduled publication list is correct.
- [ ] Overdue implementation list is correct.
- [ ] Active amendment list is correct.
- [ ] Life Cycle Workflow links open valid records.
- [ ] Cross-Module Workflow Trace is complete.
- [ ] Legislative Registry filters work.
- [ ] Excel export downloads.
- [ ] PDF export downloads and opens.
- [ ] Print views render correctly.

## J. User management and audit
- [ ] Create test Legislative Staff account.
- [ ] Explicit ORLMS access row is created.
- [ ] Role and user_roles stay synchronized.
- [ ] ORLMS access Active/Inactive is respected at login.
- [ ] Self-deactivation is blocked.
- [ ] Final active Administrator protections work.
- [ ] Final ORLMS-accessible Administrator protection works.
- [ ] Shared soft deactivation preserves historical foreign-key records.
- [ ] Activity Log filters work.
- [ ] Activity Log CSV export works.
- [ ] User print report works.

## K. System Health and database validation
- [ ] Open `/pages/system_health.php`.
- [ ] Required migrations 001-005 show PASS.
- [ ] Fine-grained permission count is at least 21.
- [ ] At least one active ORLMS Administrator exists.
- [ ] Upload directory is writable.
- [ ] APP_DEBUG is disabled before production deployment.
- [ ] Run `database/final_validation.sql`.
- [ ] Every row under `INTEGRITY CHECKS - EXPECT ZERO PROBLEM ROWS` returns 0.
