<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.calendar.view');

$pdo=db();$pageTitle='Calendar Scheduling';$activeMenu='calendar';
$extraCss=[appUrl('assets/css/lacms-operational.css')];

$search=clean($_GET['search']??'');$status=clean($_GET['status']??'');
$type=clean($_GET['event_type']??'');$agendaFilter=(int)($_GET['agenda_id']??0);
$where=[];$params=[];
if($search!==''){$where[]='(e.event_reference LIKE :s1 OR e.title LIKE :s2 OR e.venue LIKE :s3)';$like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;}
if($status!==''){$where[]='e.status=:status';$params[':status']=$status;}
if($type!==''){$where[]='e.event_type=:type';$params[':type']=$type;}
if($agendaFilter>0){$where[]='e.agenda_id=:agenda';$params[':agenda']=$agendaFilter;}
$sqlWhere=$where?'WHERE '.implode(' AND ',$where):'';

$stmt=$pdo->prepare(
 "SELECT e.*,a.agenda_reference,a.title agenda_title,c.name committee_name,o.name office_name,
         (SELECT COUNT(*) FROM lacms_calendar_event_participants p WHERE p.calendar_event_id=e.id) participant_count,
         (SELECT COUNT(*) FROM lacms_calendar_conflicts x WHERE x.calendar_event_id=e.id AND x.status='Open') open_conflicts
  FROM lacms_calendar_events e
  LEFT JOIN lacms_agendas a ON a.id=e.agenda_id
  LEFT JOIN committees c ON c.id=e.committee_id
  LEFT JOIN offices o ON o.id=e.office_id
  {$sqlWhere}
  ORDER BY e.start_datetime,e.id"
);
$stmt->execute($params);$rows=$stmt->fetchAll();

$agendas=$pdo->query("SELECT id,agenda_reference,title,agenda_date,status FROM lacms_agendas WHERE status IN ('Under Review','Finalized') ORDER BY agenda_date DESC,created_at DESC")->fetchAll();
$legislativeItems=$pdo->query("SELECT li.id,li.reference_number,li.title FROM legislative_items li JOIN legislative_item_types t ON t.id=li.item_type_id WHERE li.deleted_at IS NULL AND t.code IN ('ordinance','resolution','proposal','executive_request','policy_matter') ORDER BY li.updated_at DESC LIMIT 300")->fetchAll();
$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();
$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();

$stats=$pdo->query(
 "SELECT COUNT(*) total,
         SUM(status='Tentative') tentative,
         SUM(status='Confirmed') confirmed,
         SUM(status='In Progress') in_progress,
         SUM(conflict_status='Detected') conflicts,
         SUM(start_datetime>=NOW() AND start_datetime<=DATE_ADD(NOW(),INTERVAL 7 DAY) AND status NOT IN ('Completed','Cancelled')) week_count
  FROM lacms_calendar_events"
)->fetch()?:[];

include __DIR__.'/../../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="lacms-main-content">

<div class="lo-head"><div><div class="lo-eyebrow"><i class="bi bi-calendar-week"></i> Step 3 · Operational Backend</div><h1>Calendar Scheduling</h1><p>Centralized legislative scheduling with agenda linkage, participant coordination, venue/committee/participant conflict detection, confirmation controls, rescheduling and schedule history.</p></div><?php if(lacmsOperationalManager()): ?><button class="btn btn-primary" id="btnNewEvent"><i class="bi bi-calendar-plus"></i> Schedule Activity</button><?php endif; ?></div>

<div class="lo-flow"><div><strong>1. Create Event</strong><small>Type, schedule, venue</small></div><div><strong>2. Link Agenda</strong><small>Legislative context</small></div><div><strong>3. Add Participants</strong><small>Internal / external</small></div><div><strong>4. Check Conflicts</strong><small>Venue, committee, users</small></div><div><strong>5. Confirm</strong><small>Controlled schedule</small></div><div><strong>6. Track Changes</strong><small>Postpone / cancel</small></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Events',$stats['total']??0,'bi-calendar3'],['Tentative',$stats['tentative']??0,'bi-pencil-square'],
 ['Confirmed',$stats['confirmed']??0,'bi-check-circle'],['In Progress',$stats['in_progress']??0,'bi-play-circle'],
 ['Conflicts',$stats['conflicts']??0,'bi-exclamation-triangle'],['Next 7 Days',$stats['week_count']??0,'bi-calendar-event']
] as [$l,$v,$i]): ?><div class="col-6 col-xl-2"><div class="lo-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card lo-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-xl-4"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Reference, title or venue"></div>
<div class="col-xl-2"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">All</option><?php foreach(lacmsCalendarStatuses() as $s): ?><option <?= $status===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-3"><label class="form-label small">Event Type</label><select class="form-select form-select-sm" name="event_type"><option value="">All types</option><?php foreach(['Legislative Activity','Legislative Meeting','Regular Session','Special Session','Committee Hearing','Committee Meeting','Public Consultation','Deadline','Other'] as $x): ?><option <?= $type===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><label class="form-label small">Agenda</label><select class="form-select form-select-sm" name="agenda_id"><option value="">All agendas</option><?php foreach($agendas as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $agendaFilter===(int)$a['id']?'selected':'' ?>><?= e($a['agenda_reference'].' · '.$a['title']) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-1"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i></button></div>
</form></div></div>

<div class="card lo-card"><div class="card-header d-flex justify-content-between"><span>Master Legislative Calendar</span><a href="report.php" class="btn btn-sm btn-primary">Report</a></div><div class="table-responsive"><table class="table table-hover lo-table mb-0"><thead><tr><th>Schedule</th><th>Activity</th><th>Agenda / Committee</th><th>Venue</th><th>Participants</th><th>Conflict</th><th>Status</th><th class="text-end">Open</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No calendar activities found.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><div class="lo-calendar-date"><?= formatDate($r['start_datetime']) ?></div><div class="lo-calendar-time"><?= formatTime($r['start_datetime']) ?><?= $r['end_datetime']?' - '.formatTime($r['end_datetime']):'' ?></div></td><td><span class="lo-code"><?= e($r['event_reference']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= e($r['event_type']) ?></div></td><td><?= e($r['agenda_reference']?:'No agenda') ?><div class="small text-muted"><?= e($r['committee_name']?:'No committee') ?></div></td><td><?= e($r['venue']?:'TBA') ?></td><td><?= (int)$r['participant_count'] ?></td><td><span class="lo-status <?= $r['open_conflicts']?'bad':($r['conflict_status']==='Clear'?'good':'warn') ?>"><?= $r['open_conflicts']?(int)$r['open_conflicts'].' conflict(s)':e($r['conflict_status']) ?></span></td><td><span class="lo-status <?= $r['status']==='Confirmed'?'good':(in_array($r['status'],['Cancelled','Postponed'],true)?'bad':($r['status']==='Tentative'?'warn':'')) ?>"><?= e($r['status']) ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>

<?php if(lacmsOperationalManager()): ?>
<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 680px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="eventForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="0">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-calendar-check"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Schedule Legislative Activity</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Event Type</label>
              <select class="form-select form-select-sm" name="event_type">
                <?php foreach(['Legislative Activity','Regular Session','Special Session','Committee Hearing','Committee Meeting','Public Consultation','Deadline','Other'] as $x): ?>
                  <option><?= e($x) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Title *</label>
              <input class="form-control form-control-sm" name="title" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Linked Agenda</label>
              <select class="form-select form-select-sm" name="agenda_id" id="eventAgenda">
                <option value="">None</option>
                <?php foreach($agendas as $a): ?>
                  <option value="<?= (int)$a['id'] ?>" <?= $agendaFilter===(int)$a['id']?'selected':'' ?>><?= e($a['agenda_reference'].' · '.$a['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Linked Legislative Item</label>
              <select class="form-select form-select-sm" name="legislative_item_id">
                <option value="">None</option>
                <?php foreach($legislativeItems as $li): ?>
                  <option value="<?= (int)$li['id'] ?>"><?= e($li['reference_number'].' · '.$li['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Start *</label>
              <input type="datetime-local" class="form-control form-control-sm" name="start_datetime" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">End</label>
              <input type="datetime-local" class="form-control form-control-sm" name="end_datetime">
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="all_day" value="1" id="allDay">
                <label class="form-check-label small" for="allDay">All-day activity</label>
              </div>
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
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Description</label>
              <textarea class="form-control form-control-sm" name="description" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
            <div class="col-12">
              <div class="alert alert-info py-2 px-3 mb-0 small" style="font-size: 0.78rem; border-left: 3px solid #0284c7;">
                <strong>Conflict checking:</strong> after save, LACMS automatically checks overlapping venue, committee and participant schedules.
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Save & Check Conflicts</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const form=document.getElementById('eventForm'),modal=new bootstrap.Modal(document.getElementById('eventModal'));
 document.getElementById('btnNewEvent').onclick=()=>{form.reset();<?php if($agendaFilter): ?>document.getElementById('eventAgenda').value='<?= $agendaFilter ?>';<?php endif; ?>modal.show();};
 <?php if($agendaFilter): ?>document.getElementById('btnNewEvent').click();<?php endif; ?>
 form.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(x=>x.json());if(r.success){if(r.conflict_count>0)await Swal.fire('Saved with Conflict Review',r.conflict_count+' possible conflict(s) were detected.','warning');location.href='view.php?id='+r.id;}else Swal.fire('Calendar Error',r.message,'error');};
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
