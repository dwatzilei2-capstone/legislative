<?php
declare(strict_types=1);
lacmsRefreshDeadlineStatuses(db());
$rows=db()->query("SELECT id,deadline_reference,title,due_datetime,priority_level,status FROM lacms_deadlines ORDER BY due_datetime LIMIT 100")->fetchAll();
?>
<div class="table-responsive"><table class="table lo-table mb-0"><thead><tr><th>Deadline</th><th>Due</th><th>Priority</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['deadline_reference'].' · '.$r['title']) ?></td><td><?= formatDateTime($r['due_datetime']) ?></td><td><?= e($r['priority_level']) ?></td><td><?= e($r['status']) ?></td><td><a href="view.php?id=<?= (int)$r['id'] ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div>
