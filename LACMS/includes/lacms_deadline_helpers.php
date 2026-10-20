<?php
declare(strict_types=1);

require_once __DIR__.'/lacms_operational_helpers.php';

function lacmsRefreshDeadlineStatuses(PDO $pdo): int
{
    $stmt=$pdo->prepare(
        "UPDATE lacms_deadlines
         SET status='Overdue',updated_at=NOW()
         WHERE status IN ('Pending','In Progress')
           AND due_datetime<NOW()"
    );
    $stmt->execute();
    return $stmt->rowCount();
}

function lacmsDeadlineHistory(
    PDO $pdo,int $deadlineId,string $action,?string $old,?string $new,string $details=''
): void {
    $pdo->prepare(
        'INSERT INTO lacms_deadline_history
         (deadline_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:deadline,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':deadline'=>$deadlineId,':action'=>$action,':old'=>$old,':new'=>$new,
        ':details'=>$details?:null,':user'=>currentUserId()
    ]);
}

function lacmsDeadlineRecipients(PDO $pdo,int $deadlineId): array
{
    $q=$pdo->prepare(
        "SELECT DISTINCT u.id user_id,u.full_name name,u.email
         FROM users u
         WHERE u.status='Active'
           AND u.deleted_at IS NULL
           AND (
             u.id IN (
                SELECT responsible_user_id
                FROM lacms_deadlines
                WHERE id=:deadline_responsible
                  AND responsible_user_id IS NOT NULL
             )
             OR u.id IN (
                SELECT user_id
                FROM lacms_deadline_assignments
                WHERE deadline_id=:deadline_user
                  AND user_id IS NOT NULL
             )
             OR u.office_id IN (
                SELECT office_id
                FROM lacms_deadline_assignments
                WHERE deadline_id=:deadline_office
                  AND office_id IS NOT NULL
                UNION
                SELECT office_id
                FROM lacms_deadlines
                WHERE id=:deadline_main_office
                  AND office_id IS NOT NULL
             )
             OR u.id IN (
                SELECT cm.user_id
                FROM committee_members cm
                WHERE cm.is_active=1
                  AND cm.committee_id IN (
                    SELECT committee_id
                    FROM lacms_deadline_assignments
                    WHERE deadline_id=:deadline_committee
                      AND committee_id IS NOT NULL
                    UNION
                    SELECT committee_id
                    FROM lacms_deadlines
                    WHERE id=:deadline_main_committee
                      AND committee_id IS NOT NULL
                  )
             )
           )
         ORDER BY u.full_name"
    );
    $q->execute([
        ':deadline_responsible'=>$deadlineId,
        ':deadline_user'=>$deadlineId,
        ':deadline_office'=>$deadlineId,
        ':deadline_main_office'=>$deadlineId,
        ':deadline_committee'=>$deadlineId,
        ':deadline_main_committee'=>$deadlineId,
    ]);

    return array_map(
        fn($x)=>[
            'user_id'=>(int)$x['user_id'],
            'name'=>$x['name'],
            'email'=>$x['email'],
            'channel'=>'System'
        ],
        $q->fetchAll()
    );
}

function lacmsCreateDefaultDeadlineReminders(PDO $pdo,int $deadlineId,string $policy): int
{
    $q=$pdo->prepare('SELECT due_datetime,title FROM lacms_deadlines WHERE id=:id');
    $q->execute([':id'=>$deadlineId]);$deadline=$q->fetch();
    if(!$deadline)return 0;

    $due=strtotime((string)$deadline['due_datetime']);
    if($due===false)return 0;

    $offsets=match($policy){
        'Minimal'=>[24*60],
        'Urgent'=>[3*24*60,24*60,4*60,60],
        'Extended'=>[14*24*60,7*24*60,3*24*60,24*60],
        'None'=>[],
        default=>[7*24*60,3*24*60,24*60],
    };

    $count=0;
    foreach($offsets as $minutes){
        $at=date('Y-m-d H:i:s',$due-($minutes*60));
        if(strtotime($at)<=time())continue;

        $subject='Reminder: '.$deadline['title'];
        $message='Automated reminder scheduled before the legislative deadline.';
        try{
            $pdo->prepare(
                'INSERT INTO lacms_deadline_reminders
                 (deadline_id,remind_at,reminder_type,status,subject,message,
                  generated_by_ai,created_by,created_at,updated_at)
                 VALUES(:deadline,:at,"System","Scheduled",:subject,:message,0,:user,NOW(),NOW())'
            )->execute([
                ':deadline'=>$deadlineId,':at'=>$at,':subject'=>$subject,
                ':message'=>$message,':user'=>currentUserId()
            ]);
            $count++;
        }catch(PDOException $e){
            if((int)($e->errorInfo[1]??0)!==1062)throw $e;
        }
    }

    return $count;
}

function lacmsDeadlineContext(PDO $pdo,int $deadlineId): ?array
{
    $q=$pdo->prepare(
        "SELECT d.*,u.full_name responsible_name,o.name office_name,c.name committee_name,
                li.reference_number legislative_reference,li.title legislative_title,
                a.agenda_reference,a.title agenda_title,
                e.event_reference,e.title event_title,
                m.meeting_reference,m.title meeting_title
         FROM lacms_deadlines d
         LEFT JOIN users u ON u.id=d.responsible_user_id
         LEFT JOIN offices o ON o.id=d.office_id
         LEFT JOIN committees c ON c.id=d.committee_id
         LEFT JOIN legislative_items li ON li.id=d.legislative_item_id
         LEFT JOIN lacms_agendas a ON a.id=d.agenda_id
         LEFT JOIN lacms_calendar_events e ON e.id=d.calendar_event_id
         LEFT JOIN lacms_meetings m ON m.id=d.meeting_id
         WHERE d.id=:id"
    );
    $q->execute([':id'=>$deadlineId]);
    $row=$q->fetch();
    return $row?:null;
}

function lacmsDeadlineStatusClass(string $status): string
{
    return match($status){
        'Completed'=>'good',
        'Overdue'=>'bad',
        'Cancelled'=>'bad',
        'In Progress'=>'warn',
        default=>'',
    };
}
