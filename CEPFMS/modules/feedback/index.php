<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.feedback.view');

$pdo=db();$pageTitle='Public Feedback Submission';$activeMenu='feedback';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];

$search=clean($_GET['search']??'');$status=clean($_GET['status']??'');$category=(int)($_GET['category_id']??0);
$where=["s.submission_type='Feedback'","s.deleted_at IS NULL"];$params=[];
if($search!==''){$where[]='(s.reference_number LIKE :s1 OR s.title LIKE :s2 OR s.summary LIKE :s3)';$like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;}
if($status!==''){$where[]='s.status=:status';$params[':status']=$status;}
if($category>0){$where[]='s.category_id=:category';$params[':category']=$category;}

$q=$pdo->prepare(
 "SELECT s.*,c.name category_name,f.feedback_kind,f.service_area,f.citizen_rating,
         (SELECT COUNT(*) FROM cef_submission_documents d WHERE d.submission_id=s.id) document_count
  FROM cef_submissions s
  LEFT JOIN cef_categories c ON c.id=s.category_id
  JOIN cef_feedback_submissions f ON f.submission_id=s.id
  WHERE ".implode(' AND ',$where)."
  ORDER BY s.created_at DESC,s.id DESC"
);$q->execute($params);$rows=$q->fetchAll();

$categories=$pdo->query("SELECT id,name FROM cef_categories WHERE is_active=1 ORDER BY name")->fetchAll();
$stats=$pdo->query(
 "SELECT COUNT(*) total,SUM(status='Submitted') submitted,SUM(status='Under Moderation') moderation,
         SUM(status='Validated') validated,SUM(status IN ('Responded','Resolved','Closed')) completed
  FROM cef_submissions WHERE submission_type='Feedback' AND deleted_at IS NULL"
)->fetch()?:[];

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="cef-head"><div><div class="cef-eyebrow"><i class="bi bi-chat-square-text"></i> Step 2 · Operational Backend</div><h1>Public Feedback Submission</h1><p>Centralized registry for citizen comments and service feedback received through the public portal, with classification, internal review, supporting evidence and trackable public status.</p></div><a class="btn btn-outline-secondary" target="_blank" href="<?= e(appUrl('public/index.php')) ?>"><i class="bi bi-globe2"></i> Citizen Portal</a></div>

<div class="cef-flow"><div><strong>1. Citizen Submits</strong><small>Reference + private token</small></div><div><strong>2. Intake</strong><small>Category and priority</small></div><div><strong>3. Review</strong><small>Moderation queue</small></div><div><strong>4. Validate</strong><small>Accept civic record</small></div><div><strong>5. Route</strong><small>Later response workflow</small></div><div><strong>6. Track</strong><small>Public-visible history</small></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Feedback',$stats['total']??0,'bi-chat-square-text'],['Submitted',$stats['submitted']??0,'bi-inbox'],
 ['Under Review',$stats['moderation']??0,'bi-shield-check'],['Validated',$stats['validated']??0,'bi-check-circle'],
 ['Responded/Closed',$stats['completed']??0,'bi-reply']
] as [$l,$v,$i]): ?><div class="col-6 col-xl"><div class="cef-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card cef-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-xl-5"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Reference, title or summary"></div>
<div class="col-xl-3"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">All statuses</option><?php foreach(cefSubmissionStatuses() as $x): ?><option <?= $status===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-3"><label class="form-label small">Category</label><select class="form-select form-select-sm" name="category_id"><option value="">All categories</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $category===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-1"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i></button></div>
</form></div></div>

<div class="card cef-card"><div class="card-header d-flex justify-content-between"><span>Feedback Registry</span><a class="btn btn-sm btn-outline-primary" href="report.php">Report</a></div><div class="table-responsive"><table class="table table-hover cef-table mb-0"><thead><tr><th>Feedback</th><th>Citizen</th><th>Category</th><th>Kind / Service</th><th>Priority</th><th>Evidence</th><th>Status</th><th class="text-end">Open</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No feedback submissions found.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><span class="cef-code"><?= e($r['reference_number']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= formatDateTime($r['created_at']) ?></div></td><td><?= $r['anonymous_flag']?'Anonymous':e($r['citizen_name']?:'Not provided') ?><div class="small text-muted"><?= $r['anonymous_flag']?'Private':e($r['citizen_email']?:'') ?></div></td><td><?= e($r['category_name']?:'Unclassified') ?></td><td><?= e($r['feedback_kind']) ?><div class="small text-muted"><?= e($r['service_area']?:'') ?><?= $r['citizen_rating']?' · '.$r['citizen_rating'].'/5':'' ?></div></td><td><?= e($r['priority_level']) ?></td><td><?= (int)$r['document_count'] ?></td><td><span class="cef-status <?= e(cefStatusClass($r['status'])) ?>"><?= e($r['status']) ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
