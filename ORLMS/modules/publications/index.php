<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';
requirePermission('orlms.publications.view');

$pdo=db();$pageTitle='Publication & Dissemination';$activeMenu='publications';
$extraCss=[APP_URL.'/assets/css/orlms-lifecycle-ops.css'];

$eligible=$pdo->query(
 "SELECT li.id,li.reference_number,li.title,lit.name item_type_name,
         en.official_number,en.enactment_reference,en.enactment_date,
         (SELECT COUNT(*) FROM orlms_publications p WHERE p.legislative_item_id=li.id) publication_count
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  JOIN orlms_enactments en ON en.legislative_item_id=li.id AND en.enactment_status='Finalized'
  WHERE li.deleted_at IS NULL
    AND lit.code IN ('ordinance','resolution')
  ORDER BY en.enactment_date DESC,li.id DESC"
)->fetchAll();

$rows=$pdo->query(
 "SELECT p.id,p.publication_reference,p.publication_channel,p.publication_date,
         p.publication_status,p.release_classification,p.public_url,p.created_at,
         li.reference_number,li.title,lit.name item_type_name,en.official_number,
         (SELECT COUNT(*) FROM orlms_dissemination_recipients r WHERE r.publication_id=p.id) recipient_count,
         (SELECT COUNT(*) FROM orlms_dissemination_recipients r WHERE r.publication_id=p.id AND r.delivery_status='Acknowledged') acknowledged_count
  FROM orlms_publications p
  JOIN legislative_items li ON li.id=p.legislative_item_id
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  WHERE li.deleted_at IS NULL
  ORDER BY p.created_at DESC,p.id DESC"
)->fetchAll();

$stats=$pdo->query(
 "SELECT COUNT(*) total,
   SUM(publication_status='Draft') drafts,
   SUM(publication_status='Scheduled') scheduled,
   SUM(publication_status='Published') published
  FROM orlms_publications"
)->fetch()?:[];

include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content">
<div class="orl-head"><div><div class="orl-eyebrow"><i class="bi bi-megaphone"></i> Step 6 · Operational Backend</div><h1>Publication & Dissemination</h1><p>Prepare official publication records for enacted ordinances and resolutions, schedule or release them, attach public evidence, identify intended recipients, and track delivery and acknowledgment.</p></div><?php if(hasPermission('orlms.publications.manage')): ?><button class="btn btn-primary" id="btnNewPublication"><i class="bi bi-plus-circle"></i> New Publication</button><?php endif; ?></div>

<div class="orl-flow"><div><strong>1. Enacted Item</strong><small>Final official number</small></div><div><strong>2. Publication Draft</strong><small>Channel, text, classification</small></div><div><strong>3. Schedule / Publish</strong><small>Official release date</small></div><div><strong>4. Disseminate</strong><small>Office, user, external recipients</small></div><div><strong>5. Acknowledge</strong><small>Delivery tracking</small></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Publication Records',$stats['total']??0,'bi-files'],['Draft',$stats['drafts']??0,'bi-pencil-square'],
 ['Scheduled',$stats['scheduled']??0,'bi-calendar-event'],['Published',$stats['published']??0,'bi-megaphone'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="orl-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card orl-card mb-3"><div class="card-header">Enacted Items Available for Publication</div><div class="table-responsive"><table class="table orl-table mb-0"><thead><tr><th>Item</th><th>Official Number</th><th>Enactment Date</th><th>Existing Publications</th><th class="text-end">Create</th></tr></thead><tbody>
<?php if(!$eligible): ?><tr><td colspan="5" class="text-center text-muted py-4">No finalized enactments are available.</td></tr><?php endif; ?>
<?php foreach($eligible as $e): ?><tr><td><span class="orl-code"><?= e($e['reference_number']) ?></span><div><strong><?= e($e['title']) ?></strong></div><div class="small text-muted"><?= e($e['item_type_name']) ?></div></td><td><?= e($e['official_number']?:'—') ?></td><td><?= $e['enactment_date']?formatDate($e['enactment_date']):'—' ?></td><td><?= (int)$e['publication_count'] ?></td><td class="text-end"><?php if(hasPermission('orlms.publications.manage')): ?><button class="btn btn-sm btn-outline-primary create-for-item" data-id="<?= (int)$e['id'] ?>" data-label="<?= e($e['reference_number'].' · '.$e['official_number']) ?>">Create Publication</button><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>

<div class="card orl-card"><div class="card-header d-flex justify-content-between"><span>Publication Register</span><a class="btn btn-sm btn-outline-primary" href="report.php">Report</a></div><div class="table-responsive"><table class="table table-hover orl-table mb-0"><thead><tr><th>Publication</th><th>Item</th><th>Channel</th><th>Release</th><th>Classification</th><th>Recipients</th><th>Status</th><th class="text-end">Open</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No publication records yet.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><span class="orl-code"><?= e($r['publication_reference']) ?></span></td><td><?= e($r['reference_number']) ?><div><strong><?= e($r['official_number']?:'—') ?></strong></div><div class="small text-muted"><?= e(mb_strimwidth($r['title'],0,75,'…')) ?></div></td><td><?= e($r['publication_channel']) ?></td><td><?= $r['publication_date']?formatDateTime($r['publication_date']):'Not scheduled' ?></td><td><?= e($r['release_classification']) ?></td><td><?= (int)$r['acknowledged_count'] ?> / <?= (int)$r['recipient_count'] ?> acknowledged</td><td><span class="orl-status <?= $r['publication_status']==='Published'?'good':($r['publication_status']==='Withdrawn'?'bad':'') ?>"><?= e($r['publication_status']) ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>

<?php if(hasPermission('orlms.publications.manage')): ?>
<div class="modal fade" id="publicationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 640px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="publicationForm">
        <?= csrfField() ?>
        <input type="hidden" name="publication_id" value="0">
        <input type="hidden" name="mode" value="save">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-megaphone"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Publication Record</h6>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div id="publicationSource" class="alert alert-info py-2 px-3 mb-2 small" style="font-size: 0.8rem; border-left: 3px solid #0284c7;">Select an enacted item.</div>
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Legislative Item *</label>
              <select class="form-select form-select-sm" name="legislative_item_id" id="p_item" required>
                <option value="">Select enacted item</option>
                <?php foreach($eligible as $e): ?>
                  <option value="<?= (int)$e['id'] ?>"><?= e($e['reference_number'].' · '.($e['official_number']?:'No official number').' · '.$e['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Publication Channel</label>
              <select class="form-select form-select-sm" name="publication_channel">
                <option>Official Publication</option>
                <option>Official Gazette</option>
                <option>City Website</option>
                <option>Bulletin / Notice Board</option>
                <option>Newspaper</option>
                <option>Administrative Circular</option>
                <option>Other</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Publication Date</label>
              <input type="datetime-local" class="form-control form-control-sm" name="publication_date">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Release Classification</label>
              <select class="form-select form-select-sm" name="release_classification">
                <option>Public</option>
                <option>Internal</option>
                <option>Restricted</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Public URL</label>
              <input type="url" class="form-control form-control-sm" name="public_url" placeholder="https://...">
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Publication Text / Notice</label>
              <textarea class="form-control form-control-sm" name="publication_text" rows="3" style="resize: vertical; min-height: 65px;"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Remarks</label>
              <textarea class="form-control form-control-sm" name="remarks" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Save Publication</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const form=document.getElementById('publicationForm'),item=document.getElementById('p_item'),source=document.getElementById('publicationSource'),modal=new bootstrap.Modal(document.getElementById('publicationModal'));
 const open=id=>{form.reset();item.value=id||'';source.textContent=id?'Creating publication for selected enacted item.':'Select an enacted item.';modal.show();};
 document.getElementById('btnNewPublication').onclick=()=>open('');
 document.querySelectorAll('.create-for-item').forEach(b=>b.onclick=function(){open(this.dataset.id);source.textContent=this.dataset.label;});
 <?php if((int)($_GET['item_id']??0)>0): ?>open('<?= (int)$_GET['item_id'] ?>');<?php endif; ?>
 form.onsubmit=async e=>{e.preventDefault();const fd=new FormData(form);const dt=fd.get('publication_date');if(dt)fd.set('publication_date',dt.replace('T',' ')+':00');const r=await fetch('ajax_publish.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.href='view.php?id='+r.id;else Swal.fire('Publication Error',r.message,'error');};
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
