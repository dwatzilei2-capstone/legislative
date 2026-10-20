<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';
requirePermission('orlms.implementation.view');

$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT p.*,li.reference_number,li.title,li.current_status,
         lit.name item_type_name,en.official_number,
         o.name office_name,u.full_name user_name
  FROM orlms_implementation_plans p
  JOIN legislative_items li ON li.id=p.legislative_item_id
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  LEFT JOIN offices o ON o.id=p.responsible_office_id
  LEFT JOIN users u ON u.id=p.responsible_user_id
  WHERE p.id=:id AND li.deleted_at IS NULL"
);$q->execute([':id'=>$id]);$plan=$q->fetch();
if(!$plan){setFlash('warning','Implementation plan not found.');redirect(APP_URL.'/modules/implementation/index.php');}

$updates=$pdo->prepare(
 "SELECT x.*,u.full_name updated_name
  FROM orlms_implementation_updates x
  LEFT JOIN users u ON u.id=x.updated_by
  WHERE x.implementation_plan_id=:id ORDER BY x.update_date DESC,x.id DESC"
);$updates->execute([':id'=>$id]);$updateRows=$updates->fetchAll();

$issues=$pdo->prepare(
 "SELECT x.*,o.name office_name,u.full_name resolved_name
  FROM orlms_implementation_issues x
  LEFT JOIN offices o ON o.id=x.responsible_office_id
  LEFT JOIN users u ON u.id=x.resolved_by
  WHERE x.implementation_plan_id=:id
  ORDER BY FIELD(x.severity,'Critical','High','Medium','Low'),x.created_at DESC"
);$issues->execute([':id'=>$id]);$issueRows=$issues->fetchAll();

$docs=$pdo->prepare(
 'SELECT d.*,u.full_name uploaded_name
  FROM orlms_implementation_documents d
  LEFT JOIN users u ON u.id=d.uploaded_by
  WHERE d.implementation_plan_id=:id ORDER BY d.uploaded_at DESC,d.id DESC'
);$docs->execute([':id'=>$id]);$documents=$docs->fetchAll();

$history=$pdo->prepare(
 "SELECT h.*,u.full_name changed_name
  FROM orlms_implementation_history h
  LEFT JOIN users u ON u.id=h.changed_by
  WHERE h.implementation_plan_id=:id ORDER BY h.created_at DESC,h.id DESC"
);$history->execute([':id'=>$id]);$historyRows=$history->fetchAll();

$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$users=$pdo->query(
 "SELECT u.id,u.full_name FROM users u JOIN roles r ON r.id=u.role_id
  WHERE u.status='Active' AND u.deleted_at IS NULL
    AND r.name IN ('Administrator','Legislative Staff','Committee Member')
  ORDER BY u.full_name"
)->fetchAll();

$pageTitle=$plan['plan_reference'];$activeMenu='implementation';
$extraCss=[APP_URL.'/assets/css/orlms-lifecycle-ops.css'];
include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content">
<div class="orl-head"><div><a href="index.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Implementation Monitoring</a><div class="orl-eyebrow mt-2"><?= e($plan['plan_reference']) ?></div><h1><?= e($plan['official_number']?:$plan['reference_number']) ?></h1><p><?= e($plan['title'].' · '.($plan['office_name']?:$plan['user_name']?:'Unassigned owner')) ?></p></div><div class="d-flex gap-2 flex-wrap"><span class="orl-status <?= $plan['status']==='Completed'?'good':($plan['status']==='On Hold'?'warn':'') ?>"><?= e($plan['status']) ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a></div></div>

<div class="row g-3 mb-3"><div class="col-md-4"><div class="orl-stat"><i class="bi bi-percent"></i><div><strong><?= round((float)$plan['compliance_percent'],1) ?>%</strong><small>Compliance</small></div></div></div><div class="col-md-4"><div class="orl-stat"><i class="bi bi-clock-history"></i><div><strong><?= count($updateRows) ?></strong><small>Progress Updates</small></div></div></div><div class="col-md-4"><div class="orl-stat"><i class="bi bi-exclamation-triangle"></i><div><strong><?= count(array_filter($issueRows,fn($x)=>!in_array($x['status'],['Resolved','Accepted Risk'],true))) ?></strong><small>Open Issues</small></div></div></div></div>

<div class="row g-3">
<div class="col-xl-8">
<div class="card orl-card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Implementation Plan</span><button class="btn btn-sm btn-primary" id="btnEditPlan"><i class="bi bi-pencil-square me-1"></i> Edit Plan</button></div><div class="card-body"><div class="orl-grid mb-3"><div><small>Responsible Office</small><strong><?= e($plan['office_name']?:'—') ?></strong></div><div><small>Responsible User</small><strong><?= e($plan['user_name']?:'—') ?></strong></div><div><small>Start Date</small><strong><?= $plan['start_date']?formatDate($plan['start_date']):'—' ?></strong></div><div><small>Due Date</small><strong><?= $plan['due_date']?formatDate($plan['due_date']):'—' ?></strong></div></div><div class="orl-alert"><strong>Objective</strong><br><?= nl2br(e($plan['objective'])) ?></div><div class="mt-3"><div class="d-flex justify-content-between small"><span>Implementation Compliance</span><strong><?= round((float)$plan['compliance_percent'],1) ?>%</strong></div><div class="orl-progress"><span style="width:<?= min(100,(float)$plan['compliance_percent']) ?>%"></span></div></div><?php if($plan['completion_notes']): ?><p class="small mt-3 mb-0"><?= nl2br(e($plan['completion_notes'])) ?></p><?php endif; ?></div></div>

<div class="card orl-card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Progress Updates</span><button class="btn btn-sm btn-primary" id="btnUpdate"><i class="bi bi-plus-lg me-1"></i> Add Progress Update</button></div><div class="card-body orl-timeline"><?php if(!$updateRows): ?><div class="text-muted small">No implementation updates yet.</div><?php endif; ?><?php foreach($updateRows as $u): ?><div><div class="d-flex justify-content-between"><strong><?= round((float)$u['progress_percent'],1) ?>% · <?= e($u['status']) ?></strong><small><?= formatDateTime($u['update_date']) ?></small></div><small><?= nl2br(e($u['update_text'])) ?><?= $u['issues_encountered']?'<br><strong>Issues:</strong> '.e($u['issues_encountered']):'' ?><?= $u['next_steps']?'<br><strong>Next:</strong> '.e($u['next_steps']):'' ?><br><?= e($u['updated_name']?:'System') ?></small></div><?php endforeach; ?></div></div>

<div class="card orl-card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Implementation Issues / Risks</span><button class="btn btn-sm btn-primary" id="btnIssue"><i class="bi bi-plus-lg me-1"></i> Add Issue</button></div><div class="card-body d-grid gap-2"><?php if(!$issueRows): ?><div class="text-muted small">No implementation issues recorded.</div><?php endif; ?><?php foreach($issueRows as $x): ?><div class="orl-issue <?= e(strtolower($x['severity'])) ?>"><div class="d-flex justify-content-between gap-2"><strong><?= e($x['issue_title']) ?></strong><span class="badge text-bg-light"><?= e($x['severity'].' · '.$x['status']) ?></span></div><div class="small text-muted mt-1"><?= nl2br(e($x['description']?:'')) ?></div><div class="small mt-2">Office: <?= e($x['office_name']?:'Unassigned') ?> · Due: <?= $x['due_date']?formatDate($x['due_date']):'—' ?></div><?php if($x['resolution']): ?><div class="small mt-2"><strong>Resolution:</strong> <?= e($x['resolution']) ?></div><?php endif; ?><?php if(hasPermission('orlms.implementation.manage')): ?><button class="btn btn-sm btn-outline-primary mt-2 edit-issue" data-row='<?= e(json_encode($x,JSON_HEX_APOS|JSON_HEX_QUOT)) ?>'>Update</button><?php endif; ?></div><?php endforeach; ?></div></div>

<div class="card orl-card"><div class="card-header">Evidence Documents</div><div class="card-body"><div class="list-group mb-3"><?php if(!$documents): ?><div class="list-group-item text-muted">No implementation evidence uploaded.</div><?php endif; ?><?php foreach($documents as $d): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(UPLOAD_URL.$d['file_path']) ?>"><strong><?= e($d['file_name']) ?></strong><div class="small text-muted"><?= e($d['document_type'].' · '.$d['visibility']) ?> · <?= formatDateTime($d['uploaded_at']) ?></div></a><?php endforeach; ?></div><?php if(hasPermission('orlms.implementation.manage')): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="implementation_plan_id" value="<?= $id ?>"><div class="col-md-5"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Implementation Evidence"></div><div class="col-md-2"><input class="form-control form-control-sm" name="description" placeholder="Description"></div><div class="col-md-2"><select class="form-select form-select-sm" name="visibility"><option>Internal</option><option>Restricted</option><option>Public</option></select></div><div class="col-12"><button class="btn btn-outline-primary btn-sm">Upload Evidence</button></div></form><?php endif; ?></div></div>
</div>

<div class="col-xl-4">
<div class="card orl-card"><div class="card-header">Implementation History</div><div class="card-body orl-history"><?php foreach($historyRows as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['changed_name']?:'System') ?></strong><small><?= e(($h['previous_status']?:'—').' → '.($h['new_status']?:'—')) ?><?= $h['details']?'<br>'.e($h['details']):'' ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div>
</div>
</main></div>

<div class="modal fade" id="editPlanModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 640px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="planForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="legislative_item_id" value="<?= (int)$plan['legislative_item_id'] ?>">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-pencil-square"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Edit Implementation Plan</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Objective</label>
              <textarea class="form-control form-control-sm" name="objective" rows="3" required style="resize: vertical; min-height: 65px;"><?= e($plan['objective']) ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Responsible Office</label>
              <select class="form-select form-select-sm" name="responsible_office_id">
                <option value="">Unassigned</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>" <?= (int)$plan['responsible_office_id']===(int)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Responsible User</label>
              <select class="form-select form-select-sm" name="responsible_user_id">
                <option value="">Unassigned</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>" <?= (int)$plan['responsible_user_id']===(int)$u['id']?'selected':'' ?>><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Start</label>
              <input type="date" class="form-control form-control-sm" name="start_date" value="<?= e($plan['start_date']?:'') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Due</label>
              <input type="date" class="form-control form-control-sm" name="due_date" value="<?= e($plan['due_date']?:'') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Status</label>
              <select class="form-select form-select-sm" name="status">
                <?php foreach(['Pending','In Progress','On Hold','Completed','Cancelled'] as $s): ?>
                  <option <?= $plan['status']===$s?'selected':'' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Compliance %</label>
              <input type="number" min="0" max="100" step="0.01" class="form-control form-control-sm" name="compliance_percent" value="<?= e((string)$plan['compliance_percent']) ?>">
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Completion / Administrative Notes</label>
              <textarea class="form-control form-control-sm" name="completion_notes" rows="2" style="resize: vertical; min-height: 50px;"><?= e($plan['completion_notes']?:'') ?></textarea>
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

<div class="modal fade" id="updateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 640px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="updateForm" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="implementation_plan_id" value="<?= $id ?>">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(16,185,129,0.25); color: #34d399; font-size: 12px;">
              <i class="bi bi-speedometer2"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Progress Update</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Progress %</label>
              <input type="number" min="0" max="100" step="0.01" class="form-control form-control-sm" name="progress_percent" value="<?= e((string)$plan['compliance_percent']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Status</label>
              <select class="form-select form-select-sm" name="status">
                <?php foreach(['Pending','In Progress','On Hold','Completed','Cancelled'] as $s): ?>
                  <option <?= $plan['status']===$s?'selected':'' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Update *</label>
              <textarea class="form-control form-control-sm" name="update_text" rows="3" required style="resize: vertical; min-height: 65px;"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Issues Encountered</label>
              <textarea class="form-control form-control-sm" name="issues_encountered" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Next Steps</label>
              <textarea class="form-control form-control-sm" name="next_steps" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Evidence Summary</label>
              <input class="form-control form-control-sm" name="evidence_summary">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Evidence Document</label>
              <input type="file" class="form-control form-control-sm" name="evidence_document">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Document Type</label>
              <input class="form-control form-control-sm" name="document_type" value="Implementation Evidence">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Visibility</label>
              <select class="form-select form-select-sm" name="visibility">
                <option>Internal</option>
                <option>Restricted</option>
                <option>Public</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Record Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="issueModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 640px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="issueForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="issue_id" value="0">
        <input type="hidden" name="implementation_plan_id" value="<?= $id ?>">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(239,68,68,0.25); color: #f87171; font-size: 12px;">
              <i class="bi bi-exclamation-triangle"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Implementation Issue / Risk</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Issue Title *</label>
              <input class="form-control form-control-sm" name="issue_title" id="issue_title" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Description</label>
              <textarea class="form-control form-control-sm" name="description" id="issue_description" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Severity</label>
              <select class="form-select form-select-sm" name="severity" id="issue_severity">
                <option>Low</option>
                <option selected>Medium</option>
                <option>High</option>
                <option>Critical</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Status</label>
              <select class="form-select form-select-sm" name="status" id="issue_status">
                <option>Open</option>
                <option>In Progress</option>
                <option>Resolved</option>
                <option>Accepted Risk</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Responsible Office</label>
              <select class="form-select form-select-sm" name="responsible_office_id" id="issue_office">
                <option value="">Unassigned</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Due Date</label>
              <input type="date" class="form-control form-control-sm" name="due_date" id="issue_due">
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Resolution</label>
              <textarea class="form-control form-control-sm" name="resolution" id="issue_resolution" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Save Issue</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const planForm=document.getElementById('planForm'),updateForm=document.getElementById('updateForm'),issueForm=document.getElementById('issueForm'),docForm=document.getElementById('docForm');
 const pm=new bootstrap.Modal(document.getElementById('editPlanModal')),um=new bootstrap.Modal(document.getElementById('updateModal')),im=new bootstrap.Modal(document.getElementById('issueModal'));
 const epb=document.getElementById('btnEditPlan');if(epb&&pm)epb.onclick=()=>pm.show();
 const upb=document.getElementById('btnUpdate');if(upb&&um)upb.onclick=()=>{if(updateForm)updateForm.reset();um.show();};
 const isb=document.getElementById('btnIssue');if(isb&&im)isb.onclick=()=>{if(issueForm)issueForm.reset();issue_id.value='0';issue_severity.value='Medium';issue_status.value='Open';im.show();};
 planForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_save_plan.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(planForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Plan Error',r.message,'error');};
 updateForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_add_update.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(updateForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Progress Error',r.message,'error');};
 issueForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_issue.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(issueForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Issue Error',r.message,'error');};
 document.querySelectorAll('.edit-issue').forEach(b=>b.onclick=function(){const x=JSON.parse(this.dataset.row);issue_id.value=x.id;issue_title.value=x.issue_title||'';issue_description.value=x.description||'';issue_severity.value=x.severity||'Medium';issue_status.value=x.status||'Open';issue_office.value=x.responsible_office_id||'';issue_due.value=x.due_date||'';issue_resolution.value=x.resolution||'';im.show();});
 docForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(docForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
