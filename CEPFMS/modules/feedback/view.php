<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.feedback.view');

$pdo=db();$id=(int)($_GET['id']??0);$s=cefSubmissionRow($pdo,$id);
if(!$s||$s['submission_type']!=='Feedback'){setFlash('warning','Feedback record not found.');redirect(appUrl('modules/feedback/index.php'));}

$q=$pdo->prepare('SELECT * FROM cef_feedback_submissions WHERE submission_id=:id');$q->execute([':id'=>$id]);$f=$q->fetch()?:[];
$h=$pdo->prepare("SELECT h.*,u.full_name changed_name FROM cef_submission_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.submission_id=:id ORDER BY h.created_at DESC,h.id DESC");$h->execute([':id'=>$id]);$history=$h->fetchAll();
$d=$pdo->prepare("SELECT d.*,u.full_name uploaded_name FROM cef_submission_documents d LEFT JOIN users u ON u.id=d.uploaded_by WHERE d.submission_id=:id ORDER BY d.uploaded_at DESC,d.id DESC");$d->execute([':id'=>$id]);$docs=$d->fetchAll();
$categories=$pdo->query("SELECT id,name FROM cef_categories WHERE is_active=1 ORDER BY name")->fetchAll();

$pageTitle=$s['reference_number'];$activeMenu='feedback';$extraCss=[appUrl('assets/css/cepfms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="cef-head"><div><a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> Feedback Registry</a><div class="cef-eyebrow mt-2"><?= e($s['reference_number']) ?> · Public Feedback</div><h1><?= e($s['title']) ?></h1><p>Received <?= formatDateTime($s['created_at']) ?> · <?= e($s['category_name']?:'Unclassified') ?></p></div><div class="d-flex gap-2"><span class="cef-status <?= e(cefStatusClass($s['status'])) ?>"><?= e($s['status']) ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a></div></div>

<div class="row g-3">
<div class="col-xl-8">
<div class="card cef-card mb-3"><div class="card-header">Citizen Feedback Record</div><div class="card-body"><div class="cef-grid"><div><small>Citizen</small><strong><?= $s['anonymous_flag']?'Anonymous':e($s['citizen_name']?:'Not provided') ?></strong></div><div><small>Contact</small><strong><?= $s['anonymous_flag']?'Not retained':e($s['citizen_email']?:$s['citizen_phone']?:'Not provided') ?></strong></div><div><small>Priority</small><strong><?= e($s['priority_level']) ?></strong></div><div><small>Feedback Kind</small><strong><?= e($f['feedback_kind']??'General Feedback') ?></strong></div><div><small>Service Area</small><strong><?= e($f['service_area']??'—') ?></strong></div><div><small>Rating</small><strong><?= !empty($f['citizen_rating'])?(int)$f['citizen_rating'].' / 5':'—' ?></strong></div></div><div class="cef-alert mt-3"><strong>Details</strong><br><?= nl2br(e($s['details'])) ?></div><?php if(!empty($f['desired_outcome'])): ?><div class="cef-alert mt-3"><strong>Desired Outcome</strong><br><?= nl2br(e($f['desired_outcome'])) ?></div><?php endif; ?></div></div>

<div class="card cef-card mb-3"><div class="card-header">Documents</div><div class="card-body"><div class="list-group mb-3"><?php if(!$docs): ?><div class="list-group-item text-muted">No documents.</div><?php endif; ?><?php foreach($docs as $doc): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(UPLOAD_URL.$doc['file_path']) ?>"><strong><?= e($doc['file_name']) ?></strong><div class="small text-muted"><?= e($doc['document_type'].' · '.$doc['visibility']) ?> · <?= formatDateTime($doc['uploaded_at']) ?></div></a><?php if(cefHasPermission('cepfms.feedback.manage')): ?><div class="d-flex justify-content-end mt-1 mb-2"><button type="button" class="btn btn-link btn-sm p-0 doc-visibility-btn" data-id="<?= (int)$doc['id'] ?>" data-scope="submission" data-next="<?= $doc['visibility']==='Public'?'Internal':'Public' ?>"><?= $doc['visibility']==='Public'?'Make Internal Only':'Make Citizen Visible' ?></button></div><?php endif; ?><?php endforeach; ?></div><?php if(cefHasPermission('cepfms.feedback.manage')): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><div class="col-md-4"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Internal Feedback Document"></div><div class="col-md-3"><select class="form-select form-select-sm" name="visibility"><option value="Internal">Internal Only</option><option value="Public">Citizen Visible</option></select></div><div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100">Upload</button></div></form><?php endif; ?></div></div>
<?php $ticketChatPermission='cepfms.feedback.manage'; include __DIR__.'/../../includes/cepfms_ticket_chat.php'; ?>
</div>

<div class="col-xl-4">
<?php if(cefHasPermission('cepfms.feedback.manage')): ?><div class="card cef-card mb-3"><div class="card-header">Intake / Classification</div><div class="card-body"><form id="updateForm"><?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><label class="form-label">Category</label><select class="form-select mb-2" name="category_id"><option value="">Unclassified</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$s['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><label class="form-label">Priority</label><select class="form-select mb-2" name="priority_level"><?php foreach(cefPriorityLevels() as $x): ?><option <?= $s['priority_level']===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select><label class="form-label">Status</label><select class="form-select mb-2" name="status"><?php foreach(['Submitted','Under Moderation','Withdrawn'] as $x): ?><option <?= $s['status']===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select><label class="form-label">Update Note</label><textarea class="form-control mb-2" name="note" rows="3"></textarea><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="public_visible" value="1" id="pub"><label class="form-check-label" for="pub">Show this update in citizen tracking</label></div><button class="btn btn-primary w-100">Save Intake Update</button></form></div></div><?php endif; ?>

<div class="card cef-card"><div class="card-header">Public / Internal History</div><div class="card-body cef-timeline"><?php foreach($history as $x): ?><div><strong><?= e($x['action']) ?> · <?= e($x['changed_name']?:'Public/System') ?><?= $x['public_visible']?' · Public':'' ?></strong><small><?= e(($x['previous_status']?:'—').' → '.($x['new_status']?:'—')) ?><?= $x['details']?'<br>'.e($x['details']):'' ?><br><?= formatDateTime($x['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div>
</div>
</main></div>
<?php if(cefHasPermission('cepfms.feedback.manage')): ?><script>
document.addEventListener('DOMContentLoaded',()=>{
 const update=document.getElementById('updateForm'),doc=document.getElementById('docForm');
 update.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_update.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(update)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Feedback Error',r.message,'error');};
 if(doc)doc.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(doc)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};

 document.querySelectorAll('.doc-visibility-btn').forEach(btn=>btn.addEventListener('click',async()=>{
   const fd=new FormData();fd.append('csrf_token',document.querySelector('input[name=csrf_token]').value);fd.append('scope',btn.dataset.scope);fd.append('document_id',btn.dataset.id);fd.append('visibility',btn.dataset.next);
   const x=await fetch('../shared/ajax_document_visibility.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(z=>z.json());
   if(x.success)location.reload();else Swal.fire('Attachment Visibility',x.message,'error');
 }));
});
</script><?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
