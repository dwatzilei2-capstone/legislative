<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';
requirePermission('orlms.approvals.view');

$pdo=db();$pageTitle='Approval & Enactment';$activeMenu='approvals';
$extraCss=[APP_URL.'/assets/css/orlms-lifecycle-ops.css'];

$search=clean($_GET['search']??'');$status=clean($_GET['status']??'');$type=clean($_GET['type']??'');
$where=['li.deleted_at IS NULL','lit.code IN ("ordinance","resolution")'];
$params=[];
if($search!==''){$where[]='(li.reference_number LIKE :s1 OR li.title LIKE :s2 OR en.official_number LIKE :s3)';$like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;}
if($status!==''){$where[]='li.current_status=:status';$params[':status']=$status;}
if($type!==''){$where[]='lit.code=:type';$params[':type']=$type;}

$stmt=$pdo->prepare(
 "SELECT li.id,li.reference_number,li.title,li.current_status,li.priority_level,
         lit.code item_type_code,lit.name item_type_name,
         e.endorsement_status,e.vote_summary,e.endorsed_at,
         c.name committee_name,
         a.id latest_approval_id,a.action_reference,a.action_type,a.action_status,
         en.id enactment_id,en.enactment_reference,en.official_number,en.enactment_status,
         en.enactment_date
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  LEFT JOIN orlms_endorsements e
    ON e.id=(SELECT MAX(e2.id) FROM orlms_endorsements e2 WHERE e2.legislative_item_id=li.id)
  LEFT JOIN committees c ON c.id=e.committee_id
  LEFT JOIN orlms_approval_actions a
    ON a.id=(SELECT MAX(a2.id) FROM orlms_approval_actions a2 WHERE a2.legislative_item_id=li.id)
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  WHERE ".implode(' AND ',$where)."
    AND (
      e.endorsement_status='Endorsed'
      OR li.current_status IN ('Pending Approval','Approved','Vetoed','Rejected','Revision Required','Enacted')
    )
  ORDER BY li.updated_at DESC,li.id DESC"
);
$stmt->execute($params);$rows=$stmt->fetchAll();

$stats=$pdo->query(
 "SELECT
   SUM(li.current_status='Endorsed') ready,
   SUM(li.current_status='Pending Approval') pending,
   SUM(li.current_status='Approved') approved,
   SUM(li.current_status='Enacted') enacted
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  WHERE li.deleted_at IS NULL AND lit.code IN ('ordinance','resolution')"
)->fetch()?:[];

include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content">
<div class="orl-head"><div><div class="orl-eyebrow"><i class="bi bi-patch-check"></i> Step 5 · Operational Backend</div><h1>Approval & Enactment</h1><p>Receive committee-endorsed ordinances and resolutions, record the formal approving authority action, handle returns/rejection/veto/override where applicable, then assign the official number and finalize enactment or adoption.</p></div><a class="btn btn-outline-secondary" href="report.php"><i class="bi bi-file-earmark-bar-graph"></i> Approval Report</a></div>

<div class="orl-flow"><div><strong>1. Endorsed</strong><small>Committee handoff</small></div><div><strong>2. Approval Action</strong><small>Authority / office review</small></div><div><strong>3. Decision</strong><small>Approve, return, reject or veto</small></div><div><strong>4. Official Number</strong><small>Record enactment/adoption details</small></div><div><strong>5. Enacted</strong><small>Ready for publication</small></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Ready for Approval',$stats['ready']??0,'bi-inbox'],['Pending Approval',$stats['pending']??0,'bi-hourglass'],
 ['Approved',$stats['approved']??0,'bi-check-circle'],['Enacted',$stats['enacted']??0,'bi-patch-check'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="orl-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card orl-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-lg-5"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Reference, title or official number"></div>
<div class="col-lg-3"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">All statuses</option><?php foreach(['Endorsed','Pending Approval','Approved','Revision Required','Vetoed','Rejected','Enacted'] as $s): ?><option <?= $status===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
<div class="col-lg-2"><label class="form-label small">Type</label><select class="form-select form-select-sm" name="type"><option value="">Both</option><option value="ordinance" <?= $type==='ordinance'?'selected':'' ?>>Ordinance</option><option value="resolution" <?= $type==='resolution'?'selected':'' ?>>Resolution</option></select></div>
<div class="col-lg-2"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i> Apply</button></div>
</form></div></div>

<div class="card orl-card"><div class="card-header">Approval & Enactment Register</div><div class="table-responsive"><table class="table table-hover orl-table mb-0"><thead><tr><th>Item</th><th>Committee Handoff</th><th>Latest Approval</th><th>Official Number</th><th>Enactment</th><th>Status</th><th class="text-end">Open</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="7" class="text-center text-muted py-5">No endorsed/approval records match the filters.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><span class="orl-code"><?= e($r['reference_number'] ?? 'NO-REF') ?></span><div><strong><?= e($r['title'] ?? 'Untitled Item') ?></strong></div><div class="small text-muted"><?= e(($r['item_type_name'] ?? 'Legislative Item').' · '.($r['priority_level'] ?? 'Normal')) ?></div></td><td><?= e($r['committee_name']?:'—') ?><div class="small text-muted"><?= e($r['endorsement_status']?:'—') ?><?= !empty($r['vote_summary'])?' · '.e($r['vote_summary']):'' ?></div></td><td><?= e($r['action_reference']?:'None') ?><div class="small text-muted"><?= e(($r['action_type']?:'').' '.($r['action_status']?:'')) ?></div></td><td><?= e($r['official_number']?:'—') ?></td><td><?= e($r['enactment_status']?:'Not created') ?><div class="small text-muted"><?= !empty($r['enactment_date'])?formatDate($r['enactment_date']):'' ?></div></td><td><span class="orl-status <?= in_array($r['current_status'] ?? '',['Approved','Enacted'],true)?'good':(in_array($r['current_status'] ?? '',['Rejected','Vetoed','Revision Required'],true)?'bad':'') ?>"><?= e($r['current_status'] ?? 'Pending Approval') ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
