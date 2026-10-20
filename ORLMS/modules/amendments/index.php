<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_amendment_helpers.php';
requirePermission('orlms.amendments.view');

$pdo=db();$pageTitle='Amendment & Revision Tracking';$activeMenu='amendments';
$extraCss=[APP_URL.'/assets/css/orlms-final-ops.css', APP_URL.'/assets/css/orlms-drafting-review.css'];

$search=clean($_GET['search']??'');
$status=clean($_GET['status']??'');
$type=clean($_GET['type']??'');

$where=['li.deleted_at IS NULL'];
$params=[];

if($search!==''){
    $where[]='(a.amendment_reference LIKE :s1 OR li.reference_number LIKE :s2 OR li.title LIKE :s3 OR a.title LIKE :s4)';
    $like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;$params[':s4']=$like;
}
if($status!==''){$where[]='a.status=:status';$params[':status']=$status;}
if($type!==''){$where[]='a.amendment_type=:type';$params[':type']=$type;}

$stmt=$pdo->prepare(
 "SELECT a.id,a.amendment_reference,a.amendment_type,a.title amendment_title,
         a.status,a.source_revision_version,a.effective_date,a.created_at,a.updated_at,
         li.id item_id,li.reference_number,li.title legislative_title,li.current_status,
         lit.name item_type_name,en.official_number,
         u.full_name proposed_name,
         (SELECT COUNT(*) FROM orlms_amendment_changes c WHERE c.amendment_id=a.id) change_count,
         (SELECT COUNT(*) FROM orlms_amendment_documents d WHERE d.amendment_id=a.id) document_count
  FROM orlms_amendments a
  JOIN legislative_items li ON li.id=a.legislative_item_id
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  LEFT JOIN users u ON u.id=a.proposed_by
  WHERE ".implode(' AND ',$where)."
  ORDER BY a.updated_at DESC,a.id DESC"
);
$stmt->execute($params);$rows=$stmt->fetchAll();

$eligible=$pdo->query(
 "SELECT li.id,li.reference_number,li.title,li.current_status,lit.name item_type_name,
         en.official_number,
         (SELECT rh.version_number FROM orlms_revision_history rh
          WHERE rh.legislative_item_id=li.id
          ORDER BY rh.is_current DESC,rh.version_number DESC LIMIT 1) current_revision
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  JOIN orlms_enactments en
    ON en.legislative_item_id=li.id AND en.enactment_status='Finalized'
  WHERE li.deleted_at IS NULL
    AND lit.code IN ('ordinance','resolution')
  ORDER BY li.updated_at DESC"
)->fetchAll();

$relatedItems=$pdo->query(
 "SELECT li.id,li.reference_number,li.title
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  WHERE li.deleted_at IS NULL AND lit.code IN ('ordinance','resolution')
  ORDER BY li.reference_number"
)->fetchAll();

$stats=$pdo->query(
 "SELECT COUNT(*) total,
   SUM(status='Draft') drafts,
   SUM(status IN ('Under Review','Revision Required')) active_review,
   SUM(status='Approved') approved,
   SUM(status='Applied') applied
  FROM orlms_amendments"
)->fetch()?:[];

include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content">

<div class="of-head"><div><div class="of-eyebrow"><i class="bi bi-file-earmark-diff"></i> Step 8 · Operational Backend</div><h1>Amendment & Revision Tracking</h1><p>Create amendments against an enacted ordinance/resolution, preserve the exact source revision, record affected provisions, review the proposed revision, and apply an approved amendment as a new official document version without losing prior text.</p></div><?php if(hasPermission('orlms.amendments.manage')): ?><button class="btn btn-primary" id="btnNewAmendment"><i class="bi bi-plus-circle"></i> New Amendment</button><?php endif; ?></div>

<div class="orx-flow">
<div class="orx-step-item active"><div class="orx-step-badge"><i class="bi bi-file-earmark-check"></i></div><div class="orx-step-content"><strong>1. Select Enacted Item</strong><small>Lock source revision</small></div></div>
<div class="orx-step-divider"><i class="bi bi-chevron-right"></i></div>
<div class="orx-step-item"><div class="orx-step-badge"><i class="bi bi-pencil-square"></i></div><div class="orx-step-content"><strong>2. Draft Amendment</strong><small>Type, rationale, relation</small></div></div>
<div class="orx-step-divider"><i class="bi bi-chevron-right"></i></div>
<div class="orx-step-item"><div class="orx-step-badge"><i class="bi bi-list-check"></i></div><div class="orx-step-content"><strong>3. Identify Changes</strong><small>Sections & revised text</small></div></div>
<div class="orx-step-divider"><i class="bi bi-chevron-right"></i></div>
<div class="orx-step-item"><div class="orx-step-badge"><i class="bi bi-shield-check"></i></div><div class="orx-step-content"><strong>4. Review</strong><small>Approve or reject</small></div></div>
<div class="orx-step-divider"><i class="bi bi-chevron-right"></i></div>
<div class="orx-step-item"><div class="orx-step-badge"><i class="bi bi-check2-circle"></i></div><div class="orx-step-content"><strong>5. Apply</strong><small>Create official revision</small></div></div>
<div class="orx-step-divider"><i class="bi bi-chevron-right"></i></div>
<div class="orx-step-item"><div class="orx-step-badge"><i class="bi bi-clock-history"></i></div><div class="orx-step-content"><strong>6. Preserve History</strong><small>Compare version history</small></div></div>
</div>

<div class="row g-3 mb-3"><?php foreach([
 ['Amendments',$stats['total']??0,'bi-files','#071426'],['Draft',$stats['drafts']??0,'bi-pencil-square','#0284c7'],
 ['Review / Revision',$stats['active_review']??0,'bi-search','#d97706'],['Approved',$stats['approved']??0,'bi-patch-check','#059669'],
 ['Applied',$stats['applied']??0,'bi-clock-history','#b8860b'],
] as [$l,$v,$i,$c]): ?><div class="col-6 col-xl"><div class="orx-stat-card" style="border-left-color: <?= $c ?>;"><div class="orx-stat-icon" style="background: <?= $c ?>15; color: <?= $c ?>;"><i class="bi <?= e($i) ?>"></i></div><div class="orx-stat-info"><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card of-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-xl-5"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Amendment, legislative reference or title"></div>
<div class="col-xl-3"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">All statuses</option><?php foreach(['Draft','Under Review','Revision Required','Approved','Rejected','Withdrawn','Applied'] as $s): ?><option <?= $status===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><label class="form-label small">Type</label><select class="form-select form-select-sm" name="type"><option value="">All types</option><?php foreach(['Amendment','Revision','Technical Correction','Repeal','Superseding Measure'] as $x): ?><option <?= $type===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i> Apply</button></div>
</form></div></div>

<div class="card of-card"><div class="card-header d-flex justify-content-between"><span>Amendment Registry</span><span><?= count($rows) ?> record(s)</span></div><div class="table-responsive"><table class="table table-hover of-table mb-0"><thead><tr><th>Amendment</th><th>Original Legislation</th><th>Source Revision</th><th>Changes</th><th>Proposed By</th><th>Effective</th><th>Status</th><th class="text-end">Open</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No amendment records found.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr>
<td><span class="of-code"><?= e($r['amendment_reference']) ?></span><div><strong><?= e($r['amendment_title']) ?></strong></div><div class="small text-muted"><?= e($r['amendment_type']) ?></div></td>
<td><?= e($r['reference_number']) ?><div><strong><?= e($r['official_number']?:'—') ?></strong></div><div class="small text-muted"><?= e(mb_strimwidth($r['legislative_title'],0,70,'…')) ?></div></td>
<td>v<?= (int)$r['source_revision_version'] ?></td>
<td><?= (int)$r['change_count'] ?> change(s)<div class="small text-muted"><?= (int)$r['document_count'] ?> document(s)</div></td>
<td><?= e($r['proposed_name']?:'—') ?></td>
<td><?= $r['effective_date']?formatDate($r['effective_date']):'—' ?></td>
<td><span class="of-status <?= $r['status']==='Applied'?'good':(in_array($r['status'],['Rejected','Withdrawn'],true)?'bad':(in_array($r['status'],['Approved','Under Review'],true)?'warn':'')) ?>"><?= e($r['status']) ?></span></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>

<?php if(hasPermission('orlms.amendments.manage')): ?>
<div class="modal fade" id="amendmentModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 680px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <form id="amendmentForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="0">
        <div class="modal-header py-2.5 px-3.5" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 2px solid #3b82f6;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-25 text-info" style="width: 32px; height: 32px;">
              <i class="bi bi-pencil-square fs-6"></i>
            </div>
            <div>
              <h6 class="modal-title text-white fw-bold mb-0" style="font-size: 0.95rem;">New Amendment / Revision</h6>
              <small class="text-white-50" style="font-size: 0.72rem;">Propose legislative revision, repeal, or technical correction</small>
            </div>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3.5">
          <div class="row g-2">
            <div class="col-12"><label class="form-label small fw-semibold text-secondary mb-1">Original Legislative Item *</label><select class="form-select form-select-sm" name="legislative_item_id" id="a_item" required><option value="">Select enacted/adopted item</option><?php foreach($eligible as $e): ?><option value="<?= (int)$e['id'] ?>" data-revision="<?= (int)($e['current_revision']??1) ?>"><?= e($e['reference_number'].' · '.($e['official_number']?:'No official number').' · '.$e['title']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label small fw-semibold text-secondary mb-1">Amendment Type</label><select class="form-select form-select-sm" name="amendment_type"><?php foreach(['Amendment','Revision','Technical Correction','Repeal','Superseding Measure'] as $x): ?><option><?= e($x) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label small fw-semibold text-secondary mb-1">Related Legislative Item</label><select class="form-select form-select-sm" name="related_legislative_item_id"><option value="">None</option><?php foreach($relatedItems as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e($x['reference_number'].' · '.$x['title']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label small fw-semibold text-secondary mb-1">Relationship Type</label><select class="form-select form-select-sm" name="relationship_type"><option value="">Not specified</option><option>Amends</option><option>Repeals</option><option>Supersedes</option><option>Related Measure</option><option>Technical Correction</option></select></div>
            <div class="col-12"><label class="form-label small fw-semibold text-secondary mb-1">Amendment Title *</label><input class="form-control form-control-sm" name="title" required></div>
            <div class="col-md-6"><label class="form-label small fw-semibold text-secondary mb-1">Summary</label><textarea class="form-control form-control-sm" name="summary" rows="2"></textarea></div>
            <div class="col-md-6"><label class="form-label small fw-semibold text-secondary mb-1">Rationale / Legal Reason</label><textarea class="form-control form-control-sm" name="rationale" rows="2"></textarea></div>
            <div class="col-md-4"><label class="form-label small fw-semibold text-secondary mb-1">Proposed Effectivity Date</label><input type="date" class="form-control form-control-sm" name="effective_date"></div>
            <div class="col-12"><label class="form-label small fw-semibold text-secondary mb-1">Complete Revised Legislative Text</label><textarea class="form-control form-control-sm font-monospace" name="revised_body_text" rows="6" style="resize: vertical; min-height: 110px; max-height: 240px;" placeholder="Paste the complete proposed revised text. This becomes the official next revision only after approval and Apply."></textarea></div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3.5 bg-light border-top d-flex justify-content-end gap-2">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-3 fw-semibold shadow-sm">Create Amendment Draft</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const form=document.getElementById('amendmentForm'),modal=new bootstrap.Modal(document.getElementById('amendmentModal'));
 document.getElementById('btnNewAmendment').onclick=()=>{form.reset();modal.show();};
 form.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(x=>x.json());if(r.success)location.href='view.php?id='+r.id;else Swal.fire('Amendment Error',r.message,'error');};
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
