<?php
declare(strict_types=1);

require_once __DIR__.'/lacms_notification_helpers.php';

function lacmsDashboardStats(PDO $pdo): array
{
    lacmsRefreshDeadlineStatuses($pdo);

    return [
        'agendas'=>(int)$pdo->query("SELECT COUNT(*) FROM lacms_agendas WHERE status NOT IN ('Archived','Cancelled')")->fetchColumn(),
        'finalized_agendas'=>(int)$pdo->query("SELECT COUNT(*) FROM lacms_agendas WHERE status='Finalized'")->fetchColumn(),
        'upcoming_events'=>(int)$pdo->query("SELECT COUNT(*) FROM lacms_calendar_events WHERE status NOT IN ('Completed','Cancelled') AND start_datetime>=NOW()")->fetchColumn(),
        'conflicts'=>(int)$pdo->query("SELECT COUNT(*) FROM lacms_calendar_conflicts WHERE status='Open'")->fetchColumn(),
        'meetings'=>(int)$pdo->query("SELECT COUNT(*) FROM lacms_meetings WHERE status NOT IN ('Completed','Cancelled')")->fetchColumn(),
        'confirmed_meetings'=>(int)$pdo->query("SELECT COUNT(*) FROM lacms_meetings WHERE status='Confirmed'")->fetchColumn(),
        'deadlines'=>(int)$pdo->query("SELECT COUNT(*) FROM lacms_deadlines WHERE status NOT IN ('Completed','Cancelled')")->fetchColumn(),
        'due_soon'=>(int)$pdo->query("SELECT COUNT(*) FROM lacms_deadlines WHERE status NOT IN ('Completed','Cancelled') AND due_datetime BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 7 DAY)")->fetchColumn(),
        'overdue'=>(int)$pdo->query("SELECT COUNT(*) FROM lacms_deadlines WHERE status='Overdue'")->fetchColumn(),
        'notifications'=>(int)$pdo->query("SELECT COUNT(*) FROM lacms_notifications WHERE status IN ('Ready','Scheduled','Partially Sent')")->fetchColumn(),
        'sync_open'=>(int)$pdo->query("SELECT COUNT(*) FROM lacms_sync_records WHERE status NOT IN ('Closed','Cancelled')")->fetchColumn(),
        'sync_attention'=>(int)$pdo->query("SELECT COUNT(*) FROM lacms_sync_records WHERE status IN ('Awaiting Executive','Awaiting Legislative') OR priority_level IN ('High','Urgent')")->fetchColumn(),
    ];
}

function lacmsWorkflowRows(PDO $pdo,?int $agendaId=null): array
{
    $where=$agendaId?'WHERE a.id=:agenda':'';
    $stmt=$pdo->prepare(
        "SELECT a.id agenda_id,a.agenda_reference,a.title agenda_title,
                a.agenda_date,a.status agenda_status,
                e.id event_id,e.event_reference,e.title event_title,
                e.start_datetime,e.status event_status,e.conflict_status,
                m.id meeting_id,m.meeting_reference,m.title meeting_title,
                m.status meeting_status,
                COUNT(DISTINCT d.id) deadline_count,
                SUM(d.status='Overdue') overdue_count,
                SUM(d.status='Completed') completed_deadline_count,
                COUNT(DISTINCT n.id) notification_count
         FROM lacms_agendas a
         LEFT JOIN lacms_calendar_events e
           ON e.id=(
             SELECT e2.id FROM lacms_calendar_events e2
             WHERE e2.agenda_id=a.id
             ORDER BY e2.start_datetime DESC,e2.id DESC LIMIT 1
           )
         LEFT JOIN lacms_meetings m
           ON m.id=(
             SELECT m2.id FROM lacms_meetings m2
             WHERE m2.agenda_id=a.id
             ORDER BY m2.start_datetime DESC,m2.id DESC LIMIT 1
           )
         LEFT JOIN lacms_deadlines d
           ON d.agenda_id=a.id
           OR (e.id IS NOT NULL AND d.calendar_event_id=e.id)
           OR (m.id IS NOT NULL AND d.meeting_id=m.id)
         LEFT JOIN lacms_notifications n
           ON (n.source_type='Meeting' AND n.source_id=m.id)
           OR (n.source_type='Deadline' AND n.source_id=d.id)
         {$where}
         GROUP BY a.id,a.agenda_reference,a.title,a.agenda_date,a.status,
                  e.id,e.event_reference,e.title,e.start_datetime,e.status,e.conflict_status,
                  m.id,m.meeting_reference,m.title,m.status
         ORDER BY COALESCE(a.agenda_date,a.created_at) DESC,a.id DESC"
    );
    $stmt->execute($agendaId?[':agenda'=>$agendaId]:[]);
    return $stmt->fetchAll();
}

function lacmsMonthlyActivity(PDO $pdo): array
{
    return $pdo->query(
        "SELECT DATE_FORMAT(months.month_start,'%b %Y') label,
                COALESCE(e.events,0) events,
                COALESCE(m.meetings,0) meetings,
                COALESCE(d.deadlines,0) deadlines
         FROM (
           SELECT DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL n MONTH),'%Y-%m-01') month_start
           FROM (
             SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
             UNION ALL SELECT 4 UNION ALL SELECT 5
           ) x
         ) months
         LEFT JOIN (
           SELECT DATE_FORMAT(start_datetime,'%Y-%m-01') k,COUNT(*) events
           FROM lacms_calendar_events GROUP BY k
         ) e ON e.k=months.month_start
         LEFT JOIN (
           SELECT DATE_FORMAT(start_datetime,'%Y-%m-01') k,COUNT(*) meetings
           FROM lacms_meetings GROUP BY k
         ) m ON m.k=months.month_start
         LEFT JOIN (
           SELECT DATE_FORMAT(due_datetime,'%Y-%m-01') k,COUNT(*) deadlines
           FROM lacms_deadlines GROUP BY k
         ) d ON d.k=months.month_start
         ORDER BY months.month_start"
    )->fetchAll();
}

function lacmsDeadlineStatusDistribution(PDO $pdo): array
{
    lacmsRefreshDeadlineStatuses($pdo);
    return $pdo->query(
        "SELECT status label,COUNT(*) total
         FROM lacms_deadlines
         GROUP BY status ORDER BY total DESC,status"
    )->fetchAll();
}
