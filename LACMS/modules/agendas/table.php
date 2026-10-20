<?php
declare(strict_types=1);
$rows=db()->query("SELECT id,agenda_reference,title,agenda_date,status FROM lacms_agendas ORDER BY created_at DESC LIMIT 100")->fetchAll();
?>
<div class="table-responsive"><table class="table lo-table mb-0"><thead><tr><th>Agenda</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['agenda_reference'].' · '.$r['title']) ?></td><td><?= $r['agenda_date']?formatDate($r['agenda_date']):'—' ?></td><td><?= e($r['status']) ?></td><td><a href="view.php?id=<?= (int)$r['id'] ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div>
