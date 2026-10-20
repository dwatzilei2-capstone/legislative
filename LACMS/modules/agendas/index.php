<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.agendas.view');

$pdo=db();$pageTitle='Legislative Agenda Management';$activeMenu='agendas';
$extraCss=[appUrl('assets/css/lacms-operational.css')];

$search=clean($_GET['search']??'');$status=clean($_GET['status']??'');$committee=(int)($_GET['committee_id']??0);
$where=[];$params=[];
if($search!==''){$where[]='(a.agenda_reference LIKE :s1 OR a.title LIKE :s2 OR a.description LIKE :s3)';$like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;}
if($status!==''){$where[]='a.status=:status';$params[':status']=$status;}
if($committee>0){$where[]='a.committee_id=:committee';$params[':committee']=$committee;}
$sqlWhere=$where?'WHERE '.implode(' AND ',$where):'';

$stmt=$pdo->prepare(
 "SELECT a.*,c.name committee_name,o.name office_name,u.full_name created_name,
         (SELECT COUNT(*) FROM lacms_agenda_items i WHERE i.agenda_id=a.id AND i.item_status<>'Removed') item_count,
         (SELECT COUNT(*) FROM lacms_calendar_events e WHERE e.agenda_id=a.id AND e.status<>'Cancelled') linked_events,
         (SELECT COUNT(*) FROM lacms_meetings m WHERE m.agenda_id=a.id AND m.status<>'Cancelled') linked_meetings
  FROM lacms_agendas a
  LEFT JOIN committees c ON c.id=a.committee_id
  LEFT JOIN offices o ON o.id=a.office_id
  LEFT JOIN users u ON u.id=a.created_by
  {$sqlWhere}
  ORDER BY COALESCE(a.agenda_date,'9999-12-31'),a.created_at DESC"
);
$stmt->execute($params);$rows=$stmt->fetchAll();

$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();
$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();

$stats=$pdo->query(
 "SELECT COUNT(*) total,SUM(status='Draft') drafts,SUM(status='Under Review') review_count,
         SUM(status='Finalized') finalized,SUM(status='Cancelled') cancelled
  FROM lacms_agendas"
)->fetch()?:[];

include __DIR__.'/../../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="lacms-main-content">
<div class="lo-head"><div><div class="lo-eyebrow"><i class="bi bi-list-check"></i> Step 2 · Operational Backend</div><h1>Legislative Agenda Management</h1><p>Create structured legislative agendas, organize and prioritize agenda items, link legislative records, prepare final agenda sets, and preserve the full agenda history.</p></div><?php if(lacmsOperationalManager()): ?><button class="btn btn-primary" id="btnNewAgenda"><i class="bi bi-plus-circle"></i> New Agenda</button><?php endif; ?></div>

<div class="lo-flow"><div><strong>1. Create Agenda</strong><small>Session, committee, date</small></div><div><strong>2. Add Items</strong><small>Legislative references</small></div><div><strong>3. Organize</strong><small>Sections and sequence</small></div><div><strong>4. Review</strong><small>Validate completeness</small></div><div><strong>5. Finalize</strong><small>Lock official agenda</small></div><div><strong>6. Link Schedule</strong><small>Calendar / meetings</small></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Agendas',$stats['total']??0,'bi-inboxes'],['Draft',$stats['drafts']??0,'bi-pencil-square'],
 ['Under Review',$stats['review_count']??0,'bi-search'],['Finalized',$stats['finalized']??0,'bi-check2-circle'],
 ['Cancelled',$stats['cancelled']??0,'bi-x-circle']
] as [$l,$v,$i]): ?><div class="col-6 col-xl"><div class="lo-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card lo-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-xl-5"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Agenda reference, title or description"></div>
<div class="col-xl-3"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">All statuses</option><?php foreach(lacmsAgendaStatuses() as $s): ?><option <?= $status===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><label class="form-label small">Committee</label><select class="form-select form-select-sm" name="committee_id"><option value="">All committees</option><?php foreach($committees as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $committee===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i> Apply</button></div>
</form></div></div>

<div class="card lo-card"><div class="card-header d-flex justify-content-between"><span>Agenda Registry</span><a href="report.php" class="btn btn-sm btn-primary">Report</a></div><div class="table-responsive"><table class="table table-hover lo-table mb-0"><thead><tr><th>Agenda</th><th>Schedule</th><th>Committee / Office</th><th>Items</th><th>Linked Schedule</th><th>Status</th><th>Created By</th><th class="text-end">Open</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No legislative agendas found.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><span class="lo-code"><?= e($r['agenda_reference']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= e($r['agenda_type']) ?></div></td><td><?= $r['agenda_date']?formatDate($r['agenda_date']):'Unscheduled' ?><div class="small text-muted"><?= $r['start_time']?formatTime($r['start_time']):'—' ?><?= $r['end_time']?' - '.formatTime($r['end_time']):'' ?></div></td><td><?= e($r['committee_name']?:'No committee') ?><div class="small text-muted"><?= e($r['office_name']?:'No office') ?></div></td><td><?= (int)$r['item_count'] ?></td><td><?= (int)$r['linked_events'] ?> event(s)<div class="small text-muted"><?= (int)$r['linked_meetings'] ?> meeting(s)</div></td><td><span class="lo-status <?= $r['status']==='Finalized'?'good':($r['status']==='Cancelled'?'bad':($r['status']==='Under Review'?'warn':'')) ?>"><?= e($r['status']) ?></span></td><td><?= e($r['created_name']?:'System') ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>

<?php if(lacmsOperationalManager()): ?>
<div class="modal fade" id="agendaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 640px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="agendaForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="0">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-journal-text"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">New Legislative Agenda</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Agenda Title *</label>
              <input class="form-control form-control-sm" name="title" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Agenda Type</label>
              <select class="form-select form-select-sm" name="agenda_type">
                <option>Legislative Session</option>
                <option>Committee Meeting</option>
                <option>Special Session</option>
                <option>Public Deliberation</option>
                <option>Coordination Meeting</option>
                <option>Other</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Committee</label>
              <select class="form-select form-select-sm" name="committee_id">
                <option value="">None</option>
                <?php foreach($committees as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Responsible Office</label>
              <select class="form-select form-select-sm" name="office_id">
                <option value="">None</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Agenda Date</label>
              <input type="date" class="form-control form-control-sm" name="agenda_date">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Start Time</label>
              <input type="time" class="form-control form-control-sm" name="start_time">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">End Time</label>
              <input type="time" class="form-control form-control-sm" name="end_time">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Venue</label>
              <input class="form-control form-control-sm" name="venue">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Meeting Link</label>
              <input type="url" class="form-control form-control-sm" name="meeting_link">
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Description</label>
              <textarea class="form-control form-control-sm" name="description" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Administrative Notes</label>
              <textarea class="form-control form-control-sm" name="notes" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Create Agenda</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const form=document.getElementById('agendaForm'),modal=new bootstrap.Modal(document.getElementById('agendaModal'));
 document.getElementById('btnNewAgenda').onclick=()=>{form.reset();modal.show();};
 form.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(x=>x.json());if(r.success)location.href='view.php?id='+r.id;else Swal.fire('Agenda Error',r.message,'error');};
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
