<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orlms_report_helpers.php';
requirePermission('orlms.reports.view');

$pdo=db();
$report=clean($_GET['report']??'legislative');

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="orlms_'.$report.'_'.date('Ymd_His').'.xls"');
echo "\xEF\xBB\xBF";

if($report==='workflow'){
    $rows=orlmsWorkflowTraceRows($pdo,(int)($_GET['item_id']??0)?:null);
    echo '<table border="1"><tr><th>Reference</th><th>Title</th><th>Current Status</th><th>Review</th><th>Endorsement</th><th>Approval</th><th>Official Number</th><th>Publication</th><th>Implementation</th><th>Amendment</th></tr>';
    foreach($rows as $r){
        echo '<tr>';
        foreach([
            $r['reference_number'],$r['title'],$r['current_status'],
            ($r['review_id']?'Round '.$r['review_round'].' / '.$r['review_status']:''),
            $r['endorsement_status']?:'',
            $r['action_reference']?($r['action_reference'].' / '.$r['action_status']):'',
            $r['official_number']?:'',
            $r['publication_reference']?($r['publication_reference'].' / '.$r['publication_status']):'',
            $r['implementation_count'].' plan(s), '.$r['implementation_completed'].' completed',
            $r['amendment_reference']?($r['amendment_reference'].' / '.$r['amendment_status']):''
        ] as $v)echo '<td>'.e((string)$v).'</td>';
        echo '</tr>';
    }
    echo '</table>';
}else{
    $f=orlmsReportFilterValues();
    $rows=orlmsLegislativeReportRows($pdo,$f);
    echo '<table border="1"><tr><th>Reference</th><th>Title</th><th>Type</th><th>Office</th><th>Official Number</th><th>Status</th><th>Priority</th><th>Visibility</th><th>Review Rounds</th><th>Published Records</th><th>Implementation Plans</th><th>Amendments</th><th>Revision</th><th>Created</th><th>Updated</th></tr>';
    foreach($rows as $r){
        foreach([
            'reference_number','title','item_type_name','office_name','official_number',
            'current_status','priority_level','visibility','review_rounds','published_count',
            'implementation_plans','amendment_count','revision_version','created_at','updated_at'
        ] as $k)$r[$k]=$r[$k]??'';

        echo '<tr>';
        foreach([
            $r['reference_number'],$r['title'],$r['item_type_name'],$r['office_name'],
            $r['official_number'],$r['current_status'],$r['priority_level'],$r['visibility'],
            $r['review_rounds'],$r['published_count'],$r['implementation_plans'],
            $r['amendment_count'],'v'.$r['revision_version'],$r['created_at'],$r['updated_at']
        ] as $v)echo '<td>'.e((string)$v).'</td>';
        echo '</tr>';
    }
    echo '</table>';
}
logActivity(currentUserId(),'Export ORLMS Report','Excel export: '.$report.'.');
exit;
