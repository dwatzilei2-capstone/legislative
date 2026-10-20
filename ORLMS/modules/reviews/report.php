<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('orlms.reviews.view');

$pdo=db();$pageTitle='Committee Review Report';$activeMenu='reviews';
$extraCss=[APP_URL.'/assets/css/orlms-drafting-review.css'];

$rows=$pdo->query(
 "SELECT li.reference_number,li.title,lit.name item_type_name,r.review_round,
         c.name committee_name,u.full_name reviewer_name,r.status review_status,
         r.recommendation,e.endorsement_status,e.vote_summary,r.created_at,r.completed_at
  FROM orlms_reviews r
  JOIN legislative_items li ON li.id=r.legislative_item_id
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  LEFT JOIN committees c ON c.id=r.committee_id
  LEFT JOIN users u ON u.id=r.reviewer_id
  LEFT JOIN orlms_endorsements e ON e.review_id=r.id
  WHERE li.deleted_at IS NULL
  ORDER BY r.created_at DESC,r.id DESC"
)->fetchAll();

include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content"><div class="orx-head"><div><div class="orx-eyebrow">Operational Report</div><h1>Committee Review & Endorsement Report</h1><p>Review rounds, recommendations, committee assignments and endorsement results for submitted ordinances and resolutions.</p></div><a class="btn btn-outline-secondary" href="index.php">Review Queue</a></div>
<div class="card orx-card"><div class="table-responsive"><table class="table orx-table mb-0"><thead><tr><th>Item</th><th>Type</th><th>Round</th><th>Committee</th><th>Reviewer</th><th>Review</th><th>Recommendation</th><th>Endorsement</th><th>Completed</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['reference_number']) ?><div class="small text-muted"><?= e(mb_strimwidth($r['title'],0,85,'…')) ?></div></td><td><?= e($r['item_type_name']) ?></td><td><?= (int)$r['review_round'] ?></td><td><?= e($r['committee_name']?:'—') ?></td><td><?= e($r['reviewer_name']?:'—') ?></td><td><?= e($r['review_status']) ?></td><td><?= e($r['recommendation']?:'Pending') ?></td><td><?= e($r['endorsement_status']?:'Pending') ?><?= $r['vote_summary']?' · '.e($r['vote_summary']):'' ?></td><td><?= $r['completed_at']?formatDateTime($r['completed_at']):'—' ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
