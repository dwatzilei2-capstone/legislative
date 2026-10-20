<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';
requirePermission('orlms.implementation.view');

$pdo=db();$pageTitle='Implementation Monitoring';$activeMenu='implementation';
$extraCss=[APP_URL.'/assets/css/orlms-lifecycle-ops.css'];

$eligible=$pdo->query(
 "SELECT DISTINCT li.id,li.reference_number,li.title,lit.name item_type_name,
         en.official_number,
         (SELECT COUNT(*) FROM orlms_implementation_plans ip WHERE ip.legislative_item_id=li.id) plan_count
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  JOIN orlms_enactments en ON en.legislative_item_id=li.id AND en.enactment_status='Finalized'
  WHERE li.deleted_at IS NULL
    AND lit.code IN ('ordinance','resolution')
    AND EXISTS(
      SELECT 1 FROM orlms_publications p
      WHERE p.legislative_item_id=li.id AND p.publication_status='Published'
    )
  ORDER BY li.updated_at DESC"
)->fetchAll();

$rows=$pdo->query(
 "SELECT p.id,p.plan_reference,p.objective,p.start_date,p.due_date,p.status,
         p.compliance_percent,p.completed_at,
         li.reference_number,li.title,li.current_status,
         en.official_number,o.name office_name,u.full_name user_name,
         (SELECT COUNT(*) FROM orlms_implementation_updates x WHERE x.implementation_plan_id=p.id) update_count,
         (SELECT COUNT(*) FROM orlms_implementation_issues x WHERE x.implementation_plan_id=p.id AND x.status NOT IN ('Resolved','Accepted Risk')) open_issues
  FROM orlms_implementation_plans p
  JOIN legislative_items li ON li.id=p.legislative_item_id
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  LEFT JOIN offices o ON o.id=p.responsible_office_id
  LEFT JOIN users u ON u.id=p.responsible_user_id
  WHERE li.deleted_at IS NULL
  ORDER BY
    CASE WHEN p.due_date<CURDATE() AND p.status NOT IN ('Completed','Cancelled') THEN 0 ELSE 1 END,
    p.due_date,p.created_at DESC"
)->fetchAll();

$stats=$pdo->query(
 "SELECT COUNT(*) total,
   SUM(status='Pending') pending,
   SUM(status='In Progress') in_progress,
   SUM(status='Completed') completed,
   SUM(due_date<CURDATE() AND status NOT IN ('Completed','Cancelled')) overdue
  FROM orlms_implementation_plans"
)->fetch()?:[];

$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$users=$pdo->query(
 "SELECT u.id,u.full_name FROM users u JOIN roles r ON r.id=u.role_id
  WHERE u.status='Active' AND u.deleted_at IS NULL
    AND r.name IN ('Administrator','Legislative Staff','Committee Member')
  ORDER BY u.full_name"
)->fetchAll();

include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content">
<div class="orl-head"><div><div class="orl-eyebrow"><i class="bi bi-activity"></i> Step 7 · Operational Backend</div><h1>Implementation Monitoring</h1><p>Create implementation plans for published ordinances and resolutions, assign accountable offices/users, track progress and compliance, record implementation issues and corrective actions, and retain supporting evidence.</p></div><?php if(hasPermission('orlms.implementation.manage')): ?><button class="btn btn-primary" id="btnNewPlan"><i class="bi bi-plus-circle"></i> New Implementation Plan</button><?php endif; ?></div>

<div class="orl-flow"><div><strong>1. Published Item</strong><small>Official publication exists</small></div><div><strong>2. Implementation Plan</strong><small>Objective, owner, dates</small></div><div><strong>3. Progress Updates</strong><small>Compliance percentage</small></div><div><strong>4. Issues & Evidence</strong><small>Risks, corrective action, documents</small></div><div><strong>5. Completion</strong><small>Implemented status</small></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Plans',$stats['total']??0,'bi-list-task'],['Pending',$stats['pending']??0,'bi-hourglass'],
 ['In Progress',$stats['in_progress']??0,'bi-activity'],['Completed',$stats['completed']??0,'bi-check-circle'],
 ['Overdue',$stats['overdue']??0,'bi-exclamation-triangle'],
] as [$l,$v,$i]): ?><div class="col-6 col-xl"><div class="orl-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card orl-card mb-3"><div class="card-header">Published Items Available for Monitoring</div><div class="table-responsive"><table class="table orl-table mb-0"><thead><tr><th>Item</th><th>Official Number</th><th>Existing Plans</th><th class="text-end">Create</th></tr></thead><tbody>
<?php if(!$eligible): ?><tr><td colspan="4" class="text-center text-muted py-4">No published legislative items are available.</td></tr><?php endif; ?>
<?php foreach($eligible as $e): ?><tr><td><span class="orl-code"><?= e($e['reference_number']) ?></span><div><strong><?= e($e['title']) ?></strong></div><div class="small text-muted"><?= e($e['item_type_name']) ?></div></td><td><?= e($e['official_number']?:'—') ?></td><td><?= (int)$e['plan_count'] ?></td><td class="text-end"><?php if(hasPermission('orlms.implementation.manage')): ?><button class="btn btn-sm btn-outline-primary create-plan" data-id="<?= (int)$e['id'] ?>" data-label="<?= e($e['reference_number'].' · '.($e['official_number']?:'')) ?>">Create Plan</button><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>

<div class="card orl-card"><div class="card-header d-flex justify-content-between"><span>Implementation Plan Register</span><a class="btn btn-sm btn-outline-primary" href="report.php">Report</a></div><div class="table-responsive"><table class="table table-hover orl-table mb-0"><thead><tr><th>Plan</th><th>Legislative Item</th><th>Responsible</th><th>Timeline</th><th>Compliance</th><th>Issues</th><th>Status</th><th class="text-end">Open</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No implementation plans yet.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><span class="orl-code"><?= e($r['plan_reference']) ?></span><div class="small text-muted"><?= e(mb_strimwidth($r['objective'],0,80,'…')) ?></div></td><td><?= e($r['reference_number']) ?><div><strong><?= e($r['official_number']?:'—') ?></strong></div><div class="small text-muted"><?= e(mb_strimwidth($r['title'],0,70,'…')) ?></div></td><td><?= e($r['office_name']?:$r['user_name']?:'Unassigned') ?></td><td><?= $r['start_date']?formatDate($r['start_date']):'—' ?> → <?= $r['due_date']?formatDate($r['due_date']):'—' ?><?php if($r['due_date']&&strtotime($r['due_date'])<strtotime(date('Y-m-d'))&&!in_array($r['status'],['Completed','Cancelled'],true)): ?><div class="small text-danger">Overdue</div><?php endif; ?></td><td><div class="d-flex justify-content-between small"><span><?= round((float)$r['compliance_percent'],1) ?>%</span><span><?= (int)$r['update_count'] ?> updates</span></div><div class="orl-progress"><span style="width:<?= min(100,(float)$r['compliance_percent']) ?>%"></span></div></td><td><?= (int)$r['open_issues'] ?> open</td><td><span class="orl-status <?= $r['status']==='Completed'?'good':($r['status']==='On Hold'?'warn':'') ?>"><?= e($r['status']) ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>

<?php if(hasPermission('orlms.implementation.manage')): ?>
<div class="modal fade" id="planModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 640px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="planForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="0">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-kanban"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Implementation Plan</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div id="planSource" class="alert alert-info py-2 px-3 mb-2 small" style="font-size: 0.8rem; border-left: 3px solid #0284c7;">Select a published legislative item.</div>
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Legislative Item *</label>
              <select class="form-select form-select-sm" name="legislative_item_id" id="i_item" required>
                <option value="">Select published item</option>
                <?php foreach($eligible as $e): ?>
                  <option value="<?= (int)$e['id'] ?>"><?= e($e['reference_number'].' · '.($e['official_number']?:'').' · '.$e['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Implementation Objective *</label>
              <textarea class="form-control form-control-sm" name="objective" rows="3" required style="resize: vertical; min-height: 65px;"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Responsible Office</label>
              <select class="form-select form-select-sm" name="responsible_office_id">
                <option value="">Unassigned</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Responsible User</label>
              <select class="form-select form-select-sm" name="responsible_user_id">
                <option value="">Unassigned</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Start Date</label>
              <input type="date" class="form-control form-control-sm" name="start_date">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Due Date</label>
              <input type="date" class="form-control form-control-sm" name="due_date">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Status</label>
              <select class="form-select form-select-sm" name="status">
                <option>Pending</option>
                <option>In Progress</option>
                <option>On Hold</option>
                <option>Completed</option>
                <option>Cancelled</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Compliance %</label>
              <input type="number" min="0" max="100" step="0.01" class="form-control form-control-sm" name="compliance_percent" value="0">
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Completion / Administrative Notes</label>
              <textarea class="form-control form-control-sm" name="completion_notes" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Save Plan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const form=document.getElementById('planForm'),item=document.getElementById('i_item'),source=document.getElementById('planSource'),modal=new bootstrap.Modal(document.getElementById('planModal'));
 const open=id=>{form.reset();item.value=id||'';source.textContent=id?'Creating monitoring plan for selected published item.':'Select a published legislative item.';modal.show();};
 document.getElementById('btnNewPlan').onclick=()=>open('');
 document.querySelectorAll('.create-plan').forEach(b=>b.onclick=function(){open(this.dataset.id);source.textContent=this.dataset.label;});
 <?php if((int)($_GET['item_id']??0)>0): ?>open('<?= (int)$_GET['item_id'] ?>');<?php endif; ?>
 form.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_save_plan.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(x=>x.json());if(r.success)location.href='view.php?id='+r.id;else Swal.fire('Implementation Error',r.message,'error');};
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
