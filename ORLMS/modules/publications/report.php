<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('orlms.publications.view');

$rows=db()->query(
 "SELECT p.publication_reference,p.publication_channel,p.publication_date,
         p.publication_status,p.release_classification,li.reference_number,li.title,
         en.official_number,
         (SELECT COUNT(*) FROM orlms_dissemination_recipients r WHERE r.publication_id=p.id) recipients,
         (SELECT COUNT(*) FROM orlms_dissemination_recipients r WHERE r.publication_id=p.id AND r.delivery_status='Sent') sent,
         (SELECT COUNT(*) FROM orlms_dissemination_recipients r WHERE r.publication_id=p.id AND r.delivery_status='Acknowledged') acknowledged
  FROM orlms_publications p
  JOIN legislative_items li ON li.id=p.legislative_item_id
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  WHERE li.deleted_at IS NULL ORDER BY p.created_at DESC"
)->fetchAll();

$pageTitle='Publication & Dissemination Report';$activeMenu='publications';
$extraCss=[APP_URL.'/assets/css/orlms-lifecycle-ops.css'];
include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content"><div class="orl-head"><div><div class="orl-eyebrow">Operational Report</div><h1>Publication & Dissemination Report</h1><p>Official publication channels, release dates, classifications and recipient delivery/acknowledgment tracking.</p></div><a class="btn btn-outline-secondary" href="index.php">Publication Register</a></div><div class="card orl-card"><div class="table-responsive"><table class="table orl-table mb-0"><thead><tr><th>Publication</th><th>Item</th><th>Official No.</th><th>Channel</th><th>Date</th><th>Classification</th><th>Recipients</th><th>Sent</th><th>Acknowledged</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['publication_reference']) ?></td><td><?= e($r['reference_number']) ?><div class="small text-muted"><?= e(mb_strimwidth($r['title'],0,70,'…')) ?></div></td><td><?= e($r['official_number']?:'—') ?></td><td><?= e($r['publication_channel']) ?></td><td><?= $r['publication_date']?formatDateTime($r['publication_date']):'—' ?></td><td><?= e($r['release_classification']) ?></td><td><?= (int)$r['recipients'] ?></td><td><?= (int)$r['sent'] ?></td><td><?= (int)$r['acknowledged'] ?></td><td><?= e($r['publication_status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
