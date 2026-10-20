<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requirePermission('orlms.activity_logs.view');

$pdo=db();$pageTitle='Activity Logs';$activeMenu='activity_logs';
$extraCss=[APP_URL.'/assets/css/orlms-final-ops.css'];

$search=clean($_GET['search']??'');
$userId=(int)($_GET['user_id']??0);
$action=clean($_GET['action']??'');
$scope=clean($_GET['scope']??'orlms');
$dateFrom=clean($_GET['date_from']??'');
$dateTo=clean($_GET['date_to']??'');
$page=max(1,(int)($_GET['page']??1));$perPage=30;

$where=[];$params=[];

if($scope==='orlms'){
    $where[]='al.system_id=:system';$params[':system']=orlmsSystemId();
}elseif($scope==='legacy'){
    $where[]='al.system_id IS NULL';
}

if($search!==''){
    $where[]='(al.action LIKE :s1 OR al.details LIKE :s2 OR u.full_name LIKE :s3 OR al.ip_address LIKE :s4)';
    $like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;$params[':s4']=$like;
}
if($userId>0){$where[]='al.user_id=:user';$params[':user']=$userId;}
if($action!==''){$where[]='al.action=:action';$params[':action']=$action;}
if($dateFrom!==''){$where[]='DATE(al.created_at)>=:df';$params[':df']=$dateFrom;}
if($dateTo!==''){$where[]='DATE(al.created_at)<=:dt';$params[':dt']=$dateTo;}

$whereSql=$where?'WHERE '.implode(' AND ',$where):'';

$c=$pdo->prepare("SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id {$whereSql}");
$c->execute($params);$total=(int)$c->fetchColumn();
$offset=($page-1)*$perPage;

$stmt=$pdo->prepare(
 "SELECT al.*,u.full_name,u.email,s.name system_name
  FROM activity_logs al
  LEFT JOIN users u ON u.id=al.user_id
  LEFT JOIN systems s ON s.id=al.system_id
  {$whereSql}
  ORDER BY al.created_at DESC,al.id DESC
  LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);$rows=$stmt->fetchAll();

$users=$pdo->query('SELECT id,full_name FROM users ORDER BY full_name')->fetchAll();
$actions=$pdo->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

$sum=$pdo->prepare(
 "SELECT COUNT(*) total,
         SUM(DATE(created_at)=CURDATE()) today_logs,
         SUM(action LIKE '%Login%' OR action LIKE '%Access%' OR action LIKE '%Denied%') auth_events,
         SUM(action LIKE '%Approve%' OR action LIKE '%Finalize%' OR action LIKE '%Publish%' OR action LIKE '%Apply%') control_events
  FROM activity_logs
  WHERE system_id=:system"
);
$sum->execute([':system'=>orlmsSystemId()]);$summary=$sum->fetch()?:[];

$query=$_GET;unset($query['page']);
include __DIR__.'/../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="orlms-main-content">

<div class="of-head"><div><div class="of-eyebrow"><i class="bi bi-clock-history"></i> Step 10 · Audit Administration</div><h1>ORLMS Activity Logs</h1><p>Review authentication, drafting, review, approval, publication, implementation, amendment, report export and administrative actions with user, system, IP, browser and timestamp context.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" target="_blank" href="activity_logs_print.php?<?= e(http_build_query($_GET)) ?>"><i class="bi bi-printer"></i> Print</a><a class="btn btn-primary" href="activity_logs_export.php?<?= e(http_build_query($_GET)) ?>"><i class="bi bi-filetype-csv"></i> CSV</a></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['ORLMS Events',$summary['total']??0,'bi-journal-text'],
 ['Today',$summary['today_logs']??0,'bi-calendar-day'],
 ['Auth / Access',$summary['auth_events']??0,'bi-shield-lock'],
 ['Lifecycle Controls',$summary['control_events']??0,'bi-patch-check'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="of-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card of-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-xl-3"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Action, details, user or IP"></div>
<div class="col-xl-2"><label class="form-label small">User</label><select class="form-select form-select-sm" name="user_id"><option value="">All</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $userId===(int)$u['id']?'selected':'' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><label class="form-label small">Action</label><select class="form-select form-select-sm" name="action"><option value="">All</option><?php foreach($actions as $a): ?><option <?= $action===$a?'selected':'' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-1"><label class="form-label small">Scope</label><select class="form-select form-select-sm" name="scope"><option value="orlms" <?= $scope==='orlms'?'selected':'' ?>>ORLMS</option><option value="legacy" <?= $scope==='legacy'?'selected':'' ?>>Legacy</option><option value="all" <?= $scope==='all'?'selected':'' ?>>All</option></select></div>
<div class="col-xl-1"><label class="form-label small">From</label><input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($dateFrom) ?>"></div>
<div class="col-xl-1"><label class="form-label small">To</label><input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($dateTo) ?>"></div>
<div class="col-xl-2"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i> Apply</button></div>
</form></div></div>

<div class="card of-card"><div class="card-header d-flex justify-content-between"><span>ORLMS Audit Register</span><span><?= $total ?> matching event(s)</span></div><div class="table-responsive"><table class="table table-hover of-table mb-0"><thead><tr><th>Date / Actor</th><th>Action</th><th>Details</th><th>System</th><th>Request Context</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="5" class="text-center text-muted py-5">No activity events match the filters.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><?= formatDateTime($r['created_at']) ?><div class="small text-muted"><?= e($r['full_name']?:'Anonymous / System') ?><?= $r['email']?' · '.e($r['email']):'' ?></div></td><td><span class="badge text-bg-light"><?= e($r['action']) ?></span></td><td><?= e($r['details']?:'—') ?></td><td><?= e($r['system_name']?:($r['system_id']?'System #'.$r['system_id']:'Legacy / Unassigned')) ?></td><td><div class="of-code"><?= e($r['ip_address']?:'No IP') ?></div><div class="of-agent" title="<?= e($r['user_agent']?:'') ?>"><?= e($r['user_agent']?:'No user-agent') ?></div></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php $pages=max(1,(int)ceil($total/$perPage)); if($pages>1): ?><div class="card-footer d-flex justify-content-between"><span class="small text-muted">Page <?= $page ?> of <?= $pages ?></span><div class="btn-group btn-group-sm"><?php if($page>1): $q=$query;$q['page']=$page-1; ?><a class="btn btn-outline-secondary" href="?<?= e(http_build_query($q)) ?>">Previous</a><?php endif; ?><?php if($page<$pages): $q=$query;$q['page']=$page+1; ?><a class="btn btn-outline-secondary" href="?<?= e(http_build_query($q)) ?>">Next</a><?php endif; ?></div></div><?php endif; ?>
</div>
</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
