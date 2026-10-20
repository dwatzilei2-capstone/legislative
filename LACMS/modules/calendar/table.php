<?php
declare(strict_types=1);
$rows=db()->query("SELECT id,event_reference,title,start_datetime,status,conflict_status FROM lacms_calendar_events ORDER BY start_datetime DESC LIMIT 100")->fetchAll();
?>
<div class="table-responsive"><table class="table lo-table mb-0"><thead><tr><th>Event</th><th>Start</th><th>Conflict</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['event_reference'].' · '.$r['title']) ?></td><td><?= formatDateTime($r['start_datetime']) ?></td><td><?= e($r['conflict_status']) ?></td><td><?= e($r['status']) ?></td><td><a href="view.php?id=<?= (int)$r['id'] ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div>
