<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/lacms_sync_helpers.php';
requireLacmsPermission('lacms.sync.view');
$pdo=db();$pageTitle='Executive-Legislative Synchronization';$activeMenu='synchronization';$extraCss=[appUrl('assets/css/lacms-operational.css')];
$search=clean($_GET['search']??'');$status=clean($_GET['status']??'');$priority=clean($_GET['priority']??'');$where=[];$params=[];
if($search!==''){$where[]='(s.sync_reference LIKE :s1 OR s.title LIKE :s2 OR s.executive_position LIKE :s3 OR s.legislative_position LIKE :s4)';$like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;$params[':s4']=$like;}
if($status!==''){$where[]='s.status=:status';$params[':status']=$status;}if($priority!==''){$where[]='s.priority_level=:priority';$params[':priority']=$priority;}$sqlWhere=$where?'WHERE '.implode(' AND ',$where):'';
$q=$pdo->prepare("SELECT s.*,li.reference_number legislative_reference,a.agenda_reference,e.event_reference,o.name source_office_name,c.name target_committee_name,(SELECT COUNT(*) FROM lacms_sync_actions x WHERE x.sync_record_id=s.id) action_count,(SELECT COUNT(*) FROM lacms_sync_actions x WHERE x.sync_record_id=s.id AND x.status IN ('Pending','In Progress')) open_actions FROM lacms_sync_records s LEFT JOIN legislative_items li ON li.id=s.legislative_item_id LEFT JOIN lacms_agendas a ON a.id=s.agenda_id LEFT JOIN lacms_calendar_events e ON e.id=s.calendar_event_id LEFT JOIN offices o ON o.id=s.source_office_id LEFT JOIN committees c ON c.id=s.target_committee_id {$sqlWhere} ORDER BY FIELD(s.status,'Coordinating','Awaiting Executive','Awaiting Legislative','Draft','Aligned','Resolved','Closed','Cancelled'),FIELD(s.priority_level,'Urgent','High','Normal','Low'),s.updated_at DESC");$q->execute($params);$rows=$q->fetchAll();
$legislative=$pdo->query("SELECT id,reference_number,title FROM legislative_items WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 300")->fetchAll();$agendas=$pdo->query("SELECT id,agenda_reference,title FROM lacms_agendas ORDER BY created_at DESC LIMIT 250")->fetchAll();$events=$pdo->query("SELECT id,event_reference,title FROM lacms_calendar_events ORDER BY start_datetime DESC LIMIT 250")->fetchAll();$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();
$stats=$pdo->query("SELECT COUNT(*) total,SUM(status='Coordinating') coordinating,SUM(status='Awaiting Executive') awaiting_exec,SUM(status='Awaiting Legislative') awaiting_leg,SUM(status='Aligned') aligned,SUM(status='Resolved') resolved FROM lacms_sync_records")->fetch()?:[];
include __DIR__.'/../../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="lacms-main-content">
<div class="lo-head"><div><div class="lo-eyebrow"><i class="bi bi-arrow-left-right"></i> Step 8 · Executive-Legislative Synchronization</div><h1>Executive-Legislative Synchronization</h1><p>Track policy coordination, executive and legislative positions, linked agenda/calendar context, action items, evidence and final alignment/resolution.</p></div><?php if(lacmsHasPermission('lacms.sync.manage')): ?><button class="btn btn-primary" id="btnNewSync"><i class="bi bi-plus-circle"></i> New Coordination</button><?php endif; ?></div>
<div class="lo-flow"><div><strong>1. Register</strong><small>Issue / policy matter</small></div><div><strong>2. Capture Positions</strong><small>Executive vs legislative</small></div><div><strong>3. Coordinate</strong><small>Office / committee</small></div><div><strong>4. Assign Actions</strong><small>Owners and due dates</small></div><div><strong>5. Align / Resolve</strong><small>Document outcome</small></div><div><strong>6. Close</strong><small>Preserve evidence</small></div></div>
<div class="row g-3 mb-3"><?php foreach([['Records',$stats['total']??0,'bi-diagram-3'],['Coordinating',$stats['coordinating']??0,'bi-arrow-repeat'],['Awaiting Executive',$stats['awaiting_exec']??0,'bi-building'],['Awaiting Legislative',$stats['awaiting_leg']??0,'bi-bank'],['Aligned',$stats['aligned']??0,'bi-check2-circle'],['Resolved',$stats['resolved']??0,'bi-check2-all']] as [$l,$v,$i]): ?><div class="col-6 col-xl-2"><div class="lo-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>
<div class="card lo-card mb-3"><div class="card-body"><form class="row g-2 align-items-end"><div class="col-xl-6"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Reference, title or position"></div><div class="col-xl-2"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">All</option><?php foreach(lacmsSyncStatuses() as $x): ?><option <?= $status===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div><div class="col-xl-2"><label class="form-label small">Priority</label><select class="form-select form-select-sm" name="priority"><option value="">All</option><?php foreach(['Low','Normal','High','Urgent'] as $x): ?><option <?= $priority===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div><div class="col-xl-2"><button class="btn btn-outline-primary btn-sm w-100">Apply</button></div></form></div></div>
<div class="card lo-card"><div class="card-header d-flex justify-content-between"><span>Synchronization Registry</span><a class="btn btn-sm btn-outline-primary" href="report.php">Report</a></div><div class="table-responsive"><table class="table table-hover lo-table mb-0"><thead><tr><th>Coordination</th><th>Context</th><th>Executive Office</th><th>Legislative Committee</th><th>Actions</th><th>Priority</th><th>Status</th><th></th></tr></thead><tbody><?php if(!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No synchronization records found.</td></tr><?php endif; ?><?php foreach($rows as $r): ?><tr><td><span class="lo-code"><?= e($r['sync_reference']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= e($r['sync_type']) ?></div></td><td><?= e($r['legislative_reference']?:$r['agenda_reference']?:$r['event_reference']?:'General') ?></td><td><?= e($r['source_office_name']?:'—') ?></td><td><?= e($r['target_committee_name']?:'—') ?></td><td><?= (int)$r['action_count'] ?><div class="small text-muted"><?= (int)$r['open_actions'] ?> open</div></td><td><?= e($r['priority_level']) ?></td><td><span class="lo-status <?= in_array($r['status'],['Aligned','Resolved','Closed'],true)?'good':($r['status']==='Cancelled'?'bad':(str_starts_with($r['status'],'Awaiting')?'warn':'')) ?>"><?= e($r['status']) ?></span></td><td><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach; ?></tbody></table></div></div>
</main></div>
<?php if(lacmsHasPermission('lacms.sync.manage')): ?>
<div class="modal fade" id="syncModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 680px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="syncForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="0">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-arrow-repeat"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">New Executive-Legislative Coordination</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Title *</label>
              <input class="form-control form-control-sm" name="title" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Type</label>
              <select class="form-select form-select-sm" name="sync_type">
                <option>Policy Coordination</option>
                <option>Executive Request</option>
                <option>Legislative Clarification</option>
                <option>Budget Coordination</option>
                <option>Implementation Coordination</option>
                <option>Priority Alignment</option>
                <option>Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Legislative Item</label>
              <select class="form-select form-select-sm" name="legislative_item_id">
                <option value="">None</option>
                <?php foreach($legislative as $x): ?>
                  <option value="<?= (int)$x['id'] ?>"><?= e($x['reference_number'].' · '.$x['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Agenda</label>
              <select class="form-select form-select-sm" name="agenda_id">
                <option value="">None</option>
                <?php foreach($agendas as $x): ?>
                  <option value="<?= (int)$x['id'] ?>"><?= e($x['agenda_reference'].' · '.$x['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Calendar Event</label>
              <select class="form-select form-select-sm" name="calendar_event_id">
                <option value="">None</option>
                <?php foreach($events as $x): ?>
                  <option value="<?= (int)$x['id'] ?>"><?= e($x['event_reference'].' · '.$x['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Executive Office</label>
              <select class="form-select form-select-sm" name="source_office_id">
                <option value="">None</option>
                <?php foreach($offices as $x): ?>
                  <option value="<?= (int)$x['id'] ?>"><?= e($x['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Legislative Committee</label>
              <select class="form-select form-select-sm" name="target_committee_id">
                <option value="">None</option>
                <?php foreach($committees as $x): ?>
                  <option value="<?= (int)$x['id'] ?>"><?= e($x['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Priority</label>
              <select class="form-select form-select-sm" name="priority_level">
                <option>Low</option>
                <option selected>Normal</option>
                <option>High</option>
                <option>Urgent</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Executive Position</label>
              <textarea class="form-control form-control-sm" name="executive_position" rows="3" style="resize: vertical; min-height: 65px;"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Legislative Position</label>
              <textarea class="form-control form-control-sm" name="legislative_position" rows="3" style="resize: vertical; min-height: 65px;"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Initial Coordination Notes</label>
              <textarea class="form-control form-control-sm" name="resolution_notes" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Create Coordination</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const f=document.getElementById('syncForm'),m=new bootstrap.Modal(document.getElementById('syncModal'));
 const btn=document.getElementById('btnNewSync');
 if(btn) btn.onclick=()=>{f.reset();m.show()};
 if(f) f.onsubmit=async e=>{
   e.preventDefault();
   const r=await fetch('ajax_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(f)}).then(x=>x.json());
   if(r.success)location.href='view.php?id='+r.id;
   else Swal.fire('Coordination Error',r.message,'error')
 };
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
