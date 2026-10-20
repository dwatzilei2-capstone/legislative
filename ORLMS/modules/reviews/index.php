<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_drafting_helpers.php';

requirePermission('orlms.reviews.view');

$pdo=db();$pageTitle='Review & Committee Endorsement';$activeMenu='reviews';
$extraCss=[APP_URL.'/assets/css/orlms-drafting-review.css'];

$search=clean($_GET['search']??'');
$status=clean($_GET['status']??'');
$type=clean($_GET['type']??'');
$committeeId=(int)($_GET['committee_id']??0);

$where=['li.deleted_at IS NULL'];
$params=[];

if($search!==''){
    $where[]='(li.reference_number LIKE :s1 OR li.title LIKE :s2 OR d.subject LIKE :s3)';
    $like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;
}
if($status!==''){$where[]='r.status=:status';$params[':status']=$status;}
if($type!==''){$where[]='lit.code=:type';$params[':type']=$type;}
if($committeeId>0){$where[]='r.committee_id=:committee';$params[':committee']=$committeeId;}

$stmt=$pdo->prepare(
    'SELECT r.id review_id,r.review_round,r.review_type,r.recommendation,r.status review_status,
            r.started_at,r.completed_at,r.created_at,
            li.id item_id,li.reference_number,li.title,li.current_status,li.priority_level,
            lit.code item_type_code,lit.name item_type_name,d.subject,
            c.name committee_name,u.full_name reviewer_name,
            e.endorsement_status,e.recommendation endorsement_recommendation,e.endorsed_at,
            (SELECT COUNT(*) FROM orlms_review_checklist rc WHERE rc.review_id=r.id AND rc.check_status="Pending") pending_checks,
            (SELECT COUNT(*) FROM orlms_review_comments cm WHERE cm.review_id=r.id AND cm.status="Open") open_comments
     FROM orlms_reviews r
     JOIN legislative_items li ON li.id=r.legislative_item_id
     JOIN legislative_item_types lit ON lit.id=li.item_type_id
     LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
     LEFT JOIN committees c ON c.id=r.committee_id
     LEFT JOIN users u ON u.id=r.reviewer_id
     LEFT JOIN orlms_endorsements e ON e.review_id=r.id
     WHERE '.implode(' AND ',$where).'
     ORDER BY
       CASE r.status WHEN "Pending" THEN 1 WHEN "In Progress" THEN 2 ELSE 3 END,
       r.created_at DESC,r.id DESC'
);
$stmt->execute($params);$reviews=$stmt->fetchAll();

$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();

$stats=$pdo->query(
    "SELECT COUNT(*) total,
      SUM(status='Pending') pending,
      SUM(status='In Progress') in_progress,
      SUM(status='Completed') completed
     FROM orlms_reviews"
)->fetch()?:[];

$endorsed=(int)$pdo->query("SELECT COUNT(*) FROM orlms_endorsements WHERE endorsement_status='Endorsed'")->fetchColumn();

include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content">

<div class="orx-head"><div><div class="orx-eyebrow"><i class="bi bi-clipboard-check"></i> Step 4 · Operational Backend</div><h1>Review & Committee Endorsement</h1><p>Receive submitted ordinance and resolution drafts, perform structured committee review, record checklist findings and revision comments, then formally endorse, defer, or decline endorsement.</p></div><a class="btn btn-outline-secondary" href="report.php"><i class="bi bi-file-earmark-bar-graph"></i> Review Report</a></div>

<div class="orx-flow">
<div class="orx-step-item active"><div class="orx-step-badge"><i class="bi bi-inbox"></i></div><div class="orx-step-content"><strong>1. Submission Queue</strong><small>Draft enters review</small></div></div>
<div class="orx-step-divider"><i class="bi bi-chevron-right"></i></div>
<div class="orx-step-item"><div class="orx-step-badge"><i class="bi bi-play-circle"></i></div><div class="orx-step-content"><strong>2. Start Review</strong><small>Assign reviewer & round</small></div></div>
<div class="orx-step-divider"><i class="bi bi-chevron-right"></i></div>
<div class="orx-step-item"><div class="orx-step-badge"><i class="bi bi-card-checklist"></i></div><div class="orx-step-content"><strong>3. Checklist & Notes</strong><small>Quality & legal check</small></div></div>
<div class="orx-step-divider"><i class="bi bi-chevron-right"></i></div>
<div class="orx-step-item"><div class="orx-step-badge"><i class="bi bi-hand-thumbs-up"></i></div><div class="orx-step-content"><strong>4. Recommendation</strong><small>Endorse, revise or decline</small></div></div>
<div class="orx-step-divider"><i class="bi bi-chevron-right"></i></div>
<div class="orx-step-item"><div class="orx-step-badge"><i class="bi bi-patch-check"></i></div><div class="orx-step-content"><strong>5. Endorsement</strong><small>Route to approval</small></div></div>
</div>

<div class="row g-3 mb-3">
<?php foreach([
 ['All Reviews',$stats['total']??0,'bi-files','#071426'],
 ['Pending',$stats['pending']??0,'bi-hourglass-split','#64748b'],
 ['In Progress',$stats['in_progress']??0,'bi-search','#0284c7'],
 ['Completed',$stats['completed']??0,'bi-check-circle','#059669'],
 ['Endorsed',$endorsed,'bi-patch-check','#b8860b'],
] as [$l,$v,$i,$c]): ?>
<div class="col-6 col-xl"><div class="orx-stat-card" style="border-left-color: <?= $c ?>;"><div class="orx-stat-icon" style="background: <?= $c ?>15; color: <?= $c ?>;"><i class="bi <?= e($i) ?>"></i></div><div class="orx-stat-info"><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div>
<?php endforeach; ?>
</div>

<div class="card orx-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-xl-4"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Reference, title or subject"></div>
<div class="col-xl-2"><label class="form-label small">Review Status</label><select class="form-select form-select-sm" name="status"><option value="">All</option><?php foreach(['Pending','In Progress','Completed'] as $s): ?><option <?= $status===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><label class="form-label small">Type</label><select class="form-select form-select-sm" name="type"><option value="">Ordinance & Resolution</option><option value="ordinance" <?= $type==='ordinance'?'selected':'' ?>>Ordinance</option><option value="resolution" <?= $type==='resolution'?'selected':'' ?>>Resolution</option></select></div>
<div class="col-xl-2"><label class="form-label small">Committee</label><select class="form-select form-select-sm" name="committee_id"><option value="">All committees</option><?php foreach($committees as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $committeeId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i> Apply</button></div>
</form></div></div>

<div class="card orx-card"><div class="card-header d-flex justify-content-between"><span>Committee Review Queue</span><span><?= count($reviews) ?> review(s)</span></div><div class="table-responsive"><table class="table table-hover orx-table mb-0">
<thead><tr><th>Item</th><th>Committee / Reviewer</th><th>Round</th><th>Checklist</th><th>Comments</th><th>Recommendation</th><th>Endorsement</th><th>Status</th><th class="text-end">Open</th></tr></thead><tbody>
<?php if(!$reviews): ?><tr><td colspan="9" class="text-center text-muted py-5">No review records match the filters.</td></tr><?php endif; ?>
<?php foreach($reviews as $r): ?><tr>
<td><span class="orx-code"><?= e($r['reference_number']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= e($r['item_type_name'].' · '.($r['subject']?:'No subject')) ?></div></td>
<td><?= e($r['committee_name']?:'Unassigned') ?><div class="small text-muted"><?= e($r['reviewer_name']?:'Committee queue') ?></div></td>
<td><?= (int)$r['review_round'] ?></td>
<td><?= (int)$r['pending_checks'] ?> pending</td>
<td><?= (int)$r['open_comments'] ?> open</td>
<td><?= e($r['recommendation']?:'Pending') ?></td>
<td><?= e($r['endorsement_status']?:'Pending') ?></td>
<td><span class="orx-status <?= $r['review_status']==='Completed'?'good':'' ?>"><?= e($r['review_status']) ?></span></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['review_id'] ?>"><i class="bi bi-eye"></i></a></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
