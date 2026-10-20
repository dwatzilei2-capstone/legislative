<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/lacms_report_helpers.php';
requireLacmsPermission('lacms.reports.view');

header('Content-Type: application/json; charset=UTF-8');

$type=clean($_GET['type']??'dashboard');
$pdo=db();

$data=match($type){
    'monthly'=>lacmsMonthlyActivity($pdo),
    'deadline_status'=>lacmsDeadlineStatusDistribution($pdo),
    'workflow'=>lacmsWorkflowRows($pdo,(int)($_GET['agenda_id']??0)?:null),
    default=>lacmsDashboardStats($pdo),
};

echo json_encode(['success'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
