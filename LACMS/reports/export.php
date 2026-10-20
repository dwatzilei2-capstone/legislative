<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/lacms_report_helpers.php';
requireLacmsPermission('lacms.reports.view');

$pdo=db();$report=clean($_GET['report']??'workflow');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="lacms_'.$report.'_'.date('Ymd_His').'.csv"');
$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");

if($report==='agendas'){
    fputcsv($out,['Reference','Title','Type','Date','Start','End','Committee','Office','Items','Status']);
    $rows=$pdo->query(
      "SELECT a.*,c.name committee_name,o.name office_name,
              (SELECT COUNT(*) FROM lacms_agenda_items i WHERE i.agenda_id=a.id AND i.item_status<>'Removed') items
       FROM lacms_agendas a
       LEFT JOIN committees c ON c.id=a.committee_id
       LEFT JOIN offices o ON o.id=a.office_id
       ORDER BY a.created_at DESC"
    );
    while($r=$rows->fetch())fputcsv($out,[$r['agenda_reference'],$r['title'],$r['agenda_type'],$r['agenda_date'],$r['start_time'],$r['end_time'],$r['committee_name'],$r['office_name'],$r['items'],$r['status']]);
}elseif($report==='calendar'){
    fputcsv($out,['Reference','Title','Type','Start','End','Venue','Committee','Conflict','Status']);
    $rows=$pdo->query("SELECT e.*,c.name committee_name FROM lacms_calendar_events e LEFT JOIN committees c ON c.id=e.committee_id ORDER BY e.start_datetime DESC");
    while($r=$rows->fetch())fputcsv($out,[$r['event_reference'],$r['title'],$r['event_type'],$r['start_datetime'],$r['end_datetime'],$r['venue'],$r['committee_name'],$r['conflict_status'],$r['status']]);
}elseif($report==='meetings'){
    fputcsv($out,['Reference','Title','Type','Start','End','Venue','Agenda','Calendar','Committee','Status']);
    $rows=$pdo->query("SELECT m.*,a.agenda_reference,e.event_reference,c.name committee_name FROM lacms_meetings m LEFT JOIN lacms_agendas a ON a.id=m.agenda_id LEFT JOIN lacms_calendar_events e ON e.id=m.calendar_event_id LEFT JOIN committees c ON c.id=m.committee_id ORDER BY m.start_datetime DESC");
    while($r=$rows->fetch())fputcsv($out,[$r['meeting_reference'],$r['title'],$r['meeting_type'],$r['start_datetime'],$r['end_datetime'],$r['venue'],$r['agenda_reference'],$r['event_reference'],$r['committee_name'],$r['status']]);
}elseif($report==='deadlines'){
    lacmsRefreshDeadlineStatuses($pdo);
    fputcsv($out,['Reference','Title','Type','Due','Priority','Responsible','Office','Committee','Reminder Policy','Status']);
    $rows=$pdo->query("SELECT d.*,u.full_name responsible_name,o.name office_name,c.name committee_name FROM lacms_deadlines d LEFT JOIN users u ON u.id=d.responsible_user_id LEFT JOIN offices o ON o.id=d.office_id LEFT JOIN committees c ON c.id=d.committee_id ORDER BY d.due_datetime DESC");
    while($r=$rows->fetch())fputcsv($out,[$r['deadline_reference'],$r['title'],$r['deadline_type'],$r['due_datetime'],$r['priority_level'],$r['responsible_name'],$r['office_name'],$r['committee_name'],$r['reminder_policy'],$r['status']]);
}elseif($report==='notifications'){
    fputcsv($out,['Reference','Type','Source','Source ID','Subject','Scheduled','AI','Status','Created']);
    $rows=$pdo->query("SELECT * FROM lacms_notifications ORDER BY created_at DESC");
    while($r=$rows->fetch())fputcsv($out,[$r['notification_reference'],$r['notification_type'],$r['source_type'],$r['source_id'],$r['subject'],$r['scheduled_at'],$r['generated_by_ai']?'Yes':'No',$r['status'],$r['created_at']]);
}else{
    fputcsv($out,['Agenda','Agenda Status','Calendar','Calendar Status','Conflict','Meeting','Meeting Status','Deadlines','Completed Deadlines','Overdue','Notifications']);
    foreach(lacmsWorkflowRows($pdo,(int)($_GET['agenda_id']??0)?:null) as $r){
        fputcsv($out,[
            $r['agenda_reference'],$r['agenda_status'],$r['event_reference'],$r['event_status'],
            $r['conflict_status'],$r['meeting_reference'],$r['meeting_status'],
            $r['deadline_count'],$r['completed_deadline_count'],$r['overdue_count'],$r['notification_count']
        ]);
    }
}

fclose($out);
lacmsLogActivity(currentUserId(),'LACMS Report Export','CSV export: '.$report.'.');
exit;
