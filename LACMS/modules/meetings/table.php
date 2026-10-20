<?php
declare(strict_types=1);
$rows=db()->query("SELECT id,meeting_reference,title,start_datetime,status FROM lacms_meetings ORDER BY start_datetime DESC LIMIT 100")->fetchAll();
?>
<div class="table-responsive"><table class="table lo-table mb-0"><thead><tr><th>Meeting</th><th>Start</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['meeting_reference'].' · '.$r['title']) ?></td><td><?= formatDateTime($r['start_datetime']) ?></td><td><?= e($r['status']) ?></td><td><a href="view.php?id=<?= (int)$r['id'] ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div>
