<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.meetings.view');

$pdo=db();$pageTitle='Meeting Coordination';$activeMenu='meetings';
$extraCss=[appUrl('assets/css/lacms-operational.css')];

$search=clean($_GET['search']??'');$status=clean($_GET['status']??'');
$calendarFilter=(int)($_GET['calendar_event_id']??0);$committeeFilter=(int)($_GET['committee_id']??0);
$where=[];$params=[];
if($search!==''){$where[]='(m.meeting_reference LIKE :s1 OR m.title LIKE :s2 OR m.venue LIKE :s3)';$like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;}
if($status!==''){$where[]='m.status=:status';$params[':status']=$status;}
if($calendarFilter>0){$where[]='m.calendar_event_id=:event';$params[':event']=$calendarFilter;}
if($committeeFilter>0){$where[]='m.committee_id=:committee';$params[':committee']=$committeeFilter;}
$sqlWhere=$where?'WHERE '.implode(' AND ',$where):'';

$stmt=$pdo->prepare(
 "SELECT m.*,e.event_reference,e.conflict_status,a.agenda_reference,c.name committee_name,
         o.name office_name,cu.full_name chair_name,su.full_name secretary_name,
         (SELECT COUNT(*) FROM lacms_meeting_participants p WHERE p.meeting_id=m.id) participant_count,
         (SELECT COUNT(*) FROM lacms_meeting_participants p WHERE p.meeting_id=m.id AND p.attendance_required=1) required_count
  FROM lacms_meetings m
  LEFT JOIN lacms_calendar_events e ON e.id=m.calendar_event_id
  LEFT JOIN lacms_agendas a ON a.id=m.agenda_id
  LEFT JOIN committees c ON c.id=m.committee_id
  LEFT JOIN offices o ON o.id=m.office_id
  LEFT JOIN users cu ON cu.id=m.chair_user_id
  LEFT JOIN users su ON su.id=m.secretary_user_id
  {$sqlWhere}
  ORDER BY m.start_datetime,m.id"
);
$stmt->execute($params);$rows=$stmt->fetchAll();

$events=$pdo->query("SELECT id,event_reference,title,start_datetime,status FROM lacms_calendar_events WHERE status NOT IN ('Completed','Cancelled') ORDER BY start_datetime")->fetchAll();
$agendas=$pdo->query("SELECT id,agenda_reference,title,agenda_date,status FROM lacms_agendas WHERE status IN ('Under Review','Finalized') ORDER BY agenda_date DESC,created_at DESC")->fetchAll();
$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();
$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$users=$pdo->query("SELECT u.id,u.full_name,u.email FROM users u JOIN roles r ON r.id=u.role_id WHERE u.status='Active' AND u.deleted_at IS NULL AND r.name IN ('Administrator','Legislative Staff','Committee Member') ORDER BY u.full_name")->fetchAll();

$stats=$pdo->query(
 "SELECT COUNT(*) total,SUM(status='Planned') planned,SUM(status='Confirmed') confirmed,
         SUM(status='In Progress') in_progress,SUM(status='Completed') completed,
         SUM(status='Postponed') postponed
  FROM lacms_meetings"
)->fetch()?:[];

include __DIR__.'/../../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="lacms-main-content">

<div class="lo-head"><div><div class="lo-eyebrow"><i class="bi bi-people"></i> Step 4 · Operational Backend</div><h1>Meeting Coordination</h1><p>Plan legislative meetings, synchronize them to the master calendar, manage internal/external participants, link finalized agendas, coordinate chair/secretary roles, issue notifications and preserve meeting changes.</p></div><?php if(lacmsOperationalManager()): ?><button class="btn btn-primary" id="btnNewMeeting"><i class="bi bi-plus-circle"></i> Coordinate Meeting</button><?php endif; ?></div>

<div class="lo-flow"><div><strong>1. Plan Meeting</strong><small>Purpose and schedule</small></div><div><strong>2. Link Agenda</strong><small>Finalized agenda context</small></div><div><strong>3. Add Participants</strong><small>Required / optional</small></div><div><strong>4. Sync Calendar</strong><small>Automatic event linkage</small></div><div><strong>5. Confirm & Notify</strong><small>System/email queue</small></div><div><strong>6. Track Outcome</strong><small>Complete / postpone / cancel</small></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Meetings',$stats['total']??0,'bi-people'],['Planned',$stats['planned']??0,'bi-pencil-square'],
 ['Confirmed',$stats['confirmed']??0,'bi-check-circle'],['In Progress',$stats['in_progress']??0,'bi-play-circle'],
 ['Completed',$stats['completed']??0,'bi-check2-all'],['Postponed',$stats['postponed']??0,'bi-arrow-repeat']
] as [$l,$v,$i]): ?><div class="col-6 col-xl-2"><div class="lo-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card lo-card mb-3"><div class="card-body"><form class="row g-2 align-items-end"><div class="col-xl-5"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Reference, title or venue"></div><div class="col-xl-2"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">All</option><?php foreach(lacmsMeetingStatuses() as $s): ?><option <?= $status===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div><div class="col-xl-3"><label class="form-label small">Committee</label><select class="form-select form-select-sm" name="committee_id"><option value="">All</option><?php foreach($committees as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $committeeFilter===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div><div class="col-xl-2"><button class="btn btn-outline-primary btn-sm w-100">Apply</button></div></form></div></div>

<div class="card lo-card"><div class="card-header d-flex justify-content-between"><span>Meeting Registry</span><a href="report.php" class="btn btn-sm btn-primary">Report</a></div><div class="table-responsive"><table class="table table-hover lo-table mb-0"><thead><tr><th>Meeting</th><th>Schedule</th><th>Agenda / Calendar</th><th>Committee</th><th>Chair / Secretary</th><th>Participants</th><th>Status</th><th class="text-end">Open</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No meetings found.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><span class="lo-code"><?= e($r['meeting_reference']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= e($r['meeting_type']) ?></div></td><td><?= formatDateTime($r['start_datetime']) ?><div class="small text-muted"><?= e($r['venue']?:'Venue TBA') ?></div></td><td><?= e($r['agenda_reference']?:'No agenda') ?><div class="small text-muted"><?= e($r['event_reference']?:'No calendar event') ?> · <?= e($r['conflict_status']?:'') ?></div></td><td><?= e($r['committee_name']?:'—') ?></td><td><?= e($r['chair_name']?:'—') ?><div class="small text-muted"><?= e($r['secretary_name']?:'—') ?></div></td><td><?= (int)$r['participant_count'] ?><div class="small text-muted"><?= (int)$r['required_count'] ?> required</div></td><td><span class="lo-status <?= $r['status']==='Confirmed'?'good':(in_array($r['status'],['Cancelled','Postponed'],true)?'bad':($r['status']==='Planned'?'warn':'')) ?>"><?= e($r['status']) ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>

<?php if(lacmsOperationalManager()): ?>
<div class="modal fade" id="meetingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 680px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="meetingForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="0">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-calendar2-week"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Coordinate Legislative Meeting</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Meeting Type</label>
              <select class="form-select form-select-sm" name="meeting_type">
                <option>Committee Meeting</option>
                <option>Regular Session</option>
                <option>Special Session</option>
                <option>Coordination Meeting</option>
                <option>Legislative Conference</option>
                <option>Executive-Legislative Meeting</option>
                <option>Other</option>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Title *</label>
              <input class="form-control form-control-sm" name="title" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Existing Calendar Event</label>
              <select class="form-select form-select-sm" name="calendar_event_id" id="m_event">
                <option value="">Create / synchronize automatically</option>
                <?php foreach($events as $e): ?>
                  <option value="<?= (int)$e['id'] ?>" <?= $calendarFilter===(int)$e['id']?'selected':'' ?>><?= e($e['event_reference'].' · '.$e['title'].' · '.formatDateTime($e['start_datetime'])) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Linked Agenda</label>
              <select class="form-select form-select-sm" name="agenda_id">
                <option value="">None</option>
                <?php foreach($agendas as $a): ?>
                  <option value="<?= (int)$a['id'] ?>"><?= e($a['agenda_reference'].' · '.$a['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Start *</label>
              <input type="datetime-local" class="form-control form-control-sm" name="start_datetime" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">End</label>
              <input type="datetime-local" class="form-control form-control-sm" name="end_datetime">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Venue</label>
              <input class="form-control form-control-sm" name="venue">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Meeting Link</label>
              <input type="url" class="form-control form-control-sm" name="meeting_link">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Committee</label>
              <select class="form-select form-select-sm" name="committee_id">
                <option value="">None</option>
                <?php foreach($committees as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Responsible Office</label>
              <select class="form-select form-select-sm" name="office_id">
                <option value="">None</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Chair / Presiding Officer</label>
              <select class="form-select form-select-sm" name="chair_user_id">
                <option value="">None</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Secretary / Clerk</label>
              <select class="form-select form-select-sm" name="secretary_user_id">
                <option value="">None</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Purpose</label>
              <textarea class="form-control form-control-sm" name="purpose" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Coordination Notes</label>
              <textarea class="form-control form-control-sm" name="coordination_notes" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
            <div class="col-12">
              <div class="alert alert-info py-2 px-3 mb-0 small" style="font-size: 0.78rem; border-left: 3px solid #0284c7;">
                <strong>Calendar synchronization:</strong> a meeting without an existing calendar event automatically creates one.
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Save Meeting</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const form=document.getElementById('meetingForm'),modal=new bootstrap.Modal(document.getElementById('meetingModal'));
 document.getElementById('btnNewMeeting').onclick=()=>{form.reset();<?php if($calendarFilter): ?>document.getElementById('m_event').value='<?= $calendarFilter ?>';<?php endif; ?>modal.show();};
 <?php if($calendarFilter): ?>document.getElementById('btnNewMeeting').click();<?php endif; ?>
 form.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(x=>x.json());if(r.success)location.href='view.php?id='+r.id;else Swal.fire('Meeting Error',r.message,'error');};
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
