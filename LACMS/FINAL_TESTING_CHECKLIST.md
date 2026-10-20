# LACMS Final End-to-End Testing Checklist

Use this after migrations 001 through 004 have been applied.

## A. Authentication and security
- [ ] Administrator can log in.
- [ ] Legislative Staff can log in when LACMS access is Active.
- [ ] Committee Member can log in when LACMS access is Active.
- [ ] Stakeholder/Public account is denied internal LACMS access by default.
- [ ] Five failed login attempts trigger the 15-minute rate limit.
- [ ] Successful login clears the relevant recent failed attempts.
- [ ] AJAX session expiry returns JSON rather than login-page HTML.
- [ ] Invalid CSRF token is rejected on state-changing requests.
- [ ] Permission denial returns HTTP/JSON 403 as appropriate.
- [ ] Permission denial appears in LACMS Activity Logs.

## B. Fine-grained RBAC
- [ ] Administrator sees all modules and administration pages.
- [ ] Legislative Staff can manage agendas/calendar/meetings/deadlines/notifications/synchronization.
- [ ] Legislative Staff cannot use shared User Management or System Health.
- [ ] Committee Member has read-only operational visibility.
- [ ] Committee Member cannot call management AJAX endpoints directly.
- [ ] Revoking a test user's LACMS access blocks subsystem access.
- [ ] Last LACMS-accessible Administrator protection works.

## C. Legislative Agenda Management
- [ ] Create AGD-YYYY-#### agenda.
- [ ] Set agenda type, committee, office, date, time and venue.
- [ ] Add standalone agenda item.
- [ ] Add linked legislative item.
- [ ] Duplicate linked legislative item in the same agenda is blocked.
- [ ] Edit agenda item.
- [ ] Save agenda order.
- [ ] Upload agenda document.
- [ ] Send Draft to Under Review.
- [ ] Finalize agenda.
- [ ] Finalized agenda editing is locked.
- [ ] Administrator can reopen with reason.
- [ ] Print agenda.

## D. Calendar Scheduling
- [ ] Create CAL-YYYY-#### event.
- [ ] Link finalized agenda.
- [ ] Add internal participant.
- [ ] Add external participant.
- [ ] Create overlapping same-venue test event.
- [ ] Create overlapping same-committee test event.
- [ ] Confirm conflict detection.
- [ ] Critical conflict blocks ordinary confirmation.
- [ ] Administrator override requires reason.
- [ ] Confirm event.
- [ ] Start event.
- [ ] Complete event.
- [ ] Postpone/cancel another test event with reason.
- [ ] Upload calendar document.
- [ ] Print event.

## E. Meeting Coordination
- [ ] Create MTG-YYYY-#### meeting.
- [ ] Meeting automatically creates/links a calendar event when none is selected.
- [ ] Meeting and calendar schedule/venue remain synchronized.
- [ ] Link agenda.
- [ ] Assign chair and secretary.
- [ ] Add required participant.
- [ ] Add external participant.
- [ ] Participant list synchronizes to linked calendar event.
- [ ] Meeting confirmation is blocked by unresolved Critical calendar conflict.
- [ ] At least one required participant is required.
- [ ] Confirm meeting.
- [ ] Meeting confirmation queues participant notices.
- [ ] Start and complete meeting.
- [ ] Upload meeting document.
- [ ] Print meeting.

## F. Deadline Tracking
- [ ] Create DLN-YYYY-#### deadline.
- [ ] Link legislative item / agenda / calendar / meeting.
- [ ] Assign user, office or committee.
- [ ] Standard reminder policy creates default reminders.
- [ ] Urgent policy creates additional reminders.
- [ ] Manual reminder can be added.
- [ ] Editing due date regenerates scheduled reminders.
- [ ] Past-due open deadline becomes Overdue.
- [ ] Start work.
- [ ] Escalate urgent/overdue deadline.
- [ ] Escalation queues notification.
- [ ] Upload evidence.
- [ ] Complete with closure notes.
- [ ] Completed deadline cancels remaining scheduled reminders.
- [ ] Administrator can reopen.
- [ ] Print deadline.

## G. AI-assisted reminders and notifications
- [ ] Open AI-Assisted Reminder workspace.
- [ ] Generate content with Ollama running.
- [ ] Confirm AI request is recorded.
- [ ] Stop Ollama or use unavailable model.
- [ ] Confirm approved template fallback works.
- [ ] Review/edit reminder before queueing.
- [ ] Queue immediate internal notification.
- [ ] Process internal delivery.
- [ ] Shared notifications row appears for recipient.
- [ ] Future Scheduled notification is not delivered early.
- [ ] External participant email remains Pending when SMTP is not configured.
- [ ] Delivery attempt audit reflects external SMTP not configured.
- [ ] Run `cron/process_reminders.php` using PHP CLI.

## H. Executive-Legislative Synchronization
- [ ] Create SYN-YYYY-#### coordination record.
- [ ] Link legislative item, agenda and calendar context.
- [ ] Enter executive position.
- [ ] Enter legislative position.
- [ ] Start coordination.
- [ ] Move to Awaiting Executive.
- [ ] Move to Awaiting Legislative.
- [ ] Add action items with responsibility and due date.
- [ ] Mark action In Progress.
- [ ] Mark action Completed and confirm completed_by/completed_at are stored.
- [ ] Return a completed action to non-completed status and confirm completion metadata clears.
- [ ] Upload coordination document.
- [ ] Mark record Aligned/Resolved.
- [ ] Close is blocked while action remains Pending/In Progress.
- [ ] Complete/cancel all actions and Close.
- [ ] Print coordination record.

## I. Dashboard, reports and search
- [ ] Dashboard counts match module records.
- [ ] Today's Calendar is correct.
- [ ] Deadline Watch is correct.
- [ ] Meeting queue is correct.
- [ ] Charts load.
- [ ] Search respects current user's permissions.
- [ ] Agenda report opens.
- [ ] Calendar report opens.
- [ ] Meeting report opens.
- [ ] Deadline report opens.
- [ ] Synchronization report opens.
- [ ] Cross-Module Workflow trace is correct.
- [ ] CSV exports download.
- [ ] Print summary/workflow render correctly.

## J. Activity Logs and User Management
- [ ] Activity Log search works.
- [ ] User filter works.
- [ ] Date range filter works.
- [ ] CSV export works.
- [ ] Print view works.
- [ ] Create a test Legislative Staff account.
- [ ] Shared `users` record is created.
- [ ] `user_roles` primary role remains synchronized.
- [ ] Explicit LACMS `user_system_access` record exists.
- [ ] Grant/revoke LACMS access works.
- [ ] Shared account activation/deactivation works.
- [ ] Self-deactivation is blocked.
- [ ] Last active Administrator protection works.
- [ ] Last LACMS-accessible Administrator protection works.
- [ ] Administrative changes are present in activity/audit logs.

## K. System Health and final validation
- [ ] Open `/pages/system_health.php`.
- [ ] Required database objects pass.
- [ ] Migrations 001-004 pass.
- [ ] Fine-grained permission count is at least 17.
- [ ] Administrator permission mapping is at least 17.
- [ ] Legislative Staff permission mapping is at least 15.
- [ ] Committee Member permission mapping is at least 8.
- [ ] At least one active LACMS-accessible Administrator exists.
- [ ] Upload directory is writable.
- [ ] PHP log directory is writable.
- [ ] Run `database/final_validation.sql`.
- [ ] Every row under `INTEGRITY CHECKS - EXPECT ZERO PROBLEM ROWS` returns 0.
- [ ] External SMTP pending records are understood as integration status, not false failures.
- [ ] Before production, set `APP_DEBUG=false` and deploy over HTTPS.
