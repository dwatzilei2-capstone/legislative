<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orlms_report_helpers.php';
require_once __DIR__ . '/../includes/SimplePdf.php';
requirePermission('orlms.reports.view');

$pdo=db();$report=clean($_GET['report']??'legislative');
$pdf=new SimplePdf();

$pdf->addLine('ORDINANCE AND RESOLUTION LIFE CYCLE MANAGEMENT SYSTEM',14);
$pdf->addLine('Manila City Hall - Legislative Services Office',10);
$pdf->addBlank();

if($report==='workflow'){
    $pdf->addLine('Cross-Module Legislative Workflow Trace',13);
    $pdf->addLine('Generated: '.date('F j, Y g:i A'),9);
    $pdf->addBlank();

    $rows=orlmsWorkflowTraceRows($pdo,(int)($_GET['item_id']??0)?:null);
    foreach($rows as $r){
        $pdf->addLine($r['reference_number'].' - '.$r['title'],11);
        $pdf->addLine('Current status: '.$r['current_status']);
        $pdf->addLine('Review: '.($r['review_id']?('Round '.$r['review_round'].' / '.$r['review_status'].' / '.($r['endorsement_status']?:'No endorsement')):'None'));
        $pdf->addLine('Approval: '.($r['action_reference']?($r['action_reference'].' / '.$r['action_status']):'None'));
        $pdf->addLine('Enactment: '.($r['official_number']?:'None').' / '.($r['enactment_status']?:''));
        $pdf->addLine('Publication: '.($r['publication_reference']?($r['publication_reference'].' / '.$r['publication_status']):'None'));
        $pdf->addLine('Implementation: '.$r['implementation_completed'].' of '.$r['implementation_count'].' plan(s) completed');
        $pdf->addLine('Amendment: '.($r['amendment_reference']?($r['amendment_reference'].' / '.$r['amendment_status']):'None'));
        $pdf->addBlank();
    }
    $name='orlms_workflow_'.date('Ymd_His').'.pdf';
}else{
    $pdf->addLine('Legislative Registry Report',13);
    $pdf->addLine('Generated: '.date('F j, Y g:i A'),9);
    $pdf->addBlank();

    $rows=orlmsLegislativeReportRows($pdo,orlmsReportFilterValues());
    foreach($rows as $r){
        $pdf->addLine($r['reference_number'].' - '.$r['title'],11);
        $pdf->addLine(
            $r['item_type_name'].' | Status: '.$r['current_status'].
            ' | Official No.: '.($r['official_number']?:'N/A').
            ' | Revision: v'.(int)($r['revision_version']??0)
        );
        $pdf->addLine(
            'Reviews: '.(int)$r['review_rounds'].
            ' | Publications: '.(int)$r['published_count'].
            ' | Implementation plans: '.(int)$r['implementation_plans'].
            ' | Amendments: '.(int)$r['amendment_count']
        );
        $pdf->addBlank();
    }
    $name='orlms_legislative_registry_'.date('Ymd_His').'.pdf';
}

logActivity(currentUserId(),'Export ORLMS Report','PDF export: '.$report.'.');
$pdf->output($name);
