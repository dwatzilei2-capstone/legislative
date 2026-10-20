<?php
declare(strict_types=1);

require_once __DIR__.'/auth.php';
require_once __DIR__.'/lacms_helpers.php';

function lacmsOperationalManager(): bool
{
    if(function_exists('lacmsFinalRbacInstalled') && lacmsFinalRbacInstalled()){
        foreach([
            'lacms.agendas.manage','lacms.calendar.manage','lacms.meetings.manage',
            'lacms.deadlines.manage','lacms.notifications.manage','lacms.sync.manage'
        ] as $permission){
            if(lacmsHasPermission($permission))return true;
        }
        return false;
    }

    return in_array(
        normalizeRole(currentRole()),
        [normalizeRole(ROLE_ADMIN),normalizeRole(ROLE_STAFF)],
        true
    );
}

function lacmsAgendaHistory(
    PDO $pdo,int $agendaId,string $action,?string $old,?string $new,string $details=''
): void {
    $pdo->prepare(
        'INSERT INTO lacms_agenda_history
         (agenda_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:agenda,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':agenda'=>$agendaId,':action'=>$action,':old'=>$old,':new'=>$new,
        ':details'=>$details?:null,':user'=>currentUserId()
    ]);
}

function lacmsAgendaItemHistory(
    PDO $pdo,int $itemId,int $agendaId,string $action,string $details=''
): void {
    if(!lacmsTableExists('lacms_agenda_item_history'))return;
    $pdo->prepare(
        'INSERT INTO lacms_agenda_item_history
         (agenda_item_id,agenda_id,action,details,changed_by,created_at)
         VALUES(:item,:agenda,:action,:details,:user,NOW())'
    )->execute([
        ':item'=>$itemId,':agenda'=>$agendaId,':action'=>$action,
        ':details'=>$details?:null,':user'=>currentUserId()
    ]);
}

function lacmsCalendarHistory(
    PDO $pdo,int $eventId,string $action,?string $old,?string $new,string $details=''
): void {
    $pdo->prepare(
        'INSERT INTO lacms_calendar_history
         (calendar_event_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:event,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':event'=>$eventId,':action'=>$action,':old'=>$old,':new'=>$new,
        ':details'=>$details?:null,':user'=>currentUserId()
    ]);
}

function lacmsMeetingHistory(
    PDO $pdo,int $meetingId,string $action,?string $old,?string $new,string $details=''
): void {
    $pdo->prepare(
        'INSERT INTO lacms_meeting_history
         (meeting_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:meeting,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':meeting'=>$meetingId,':action'=>$action,':old'=>$old,':new'=>$new,
        ':details'=>$details?:null,':user'=>currentUserId()
    ]);
}

function lacmsUploadDocument(
    PDO $pdo,string $module,int $parentId,array $file,string $type,string $description,string $visibility,
    ?int $agendaItemId=null
): int {
    if(!in_array($visibility,['Public','Internal','Restricted'],true))$visibility='Internal';

    $map=[
        'agenda'=>['table'=>'lacms_agenda_documents','parent'=>'agenda_id','subdir'=>'lacms/agendas'],
        'calendar'=>['table'=>'lacms_calendar_event_documents','parent'=>'calendar_event_id','subdir'=>'lacms/calendar'],
        'meeting'=>['table'=>'lacms_meeting_documents','parent'=>'meeting_id','subdir'=>'lacms/meetings'],
    ];
    if(!isset($map[$module]))throw new InvalidArgumentException('Invalid LACMS upload target.');

    $up=handleUpload($file,$map[$module]['subdir']);
    if(!$up['success'])throw new RuntimeException($up['message']);

    if($module==='agenda'){
        $pdo->prepare(
            'INSERT INTO lacms_agenda_documents
             (agenda_id,agenda_item_id,file_name,file_path,document_type,description,
              visibility,uploaded_by,uploaded_at)
             VALUES(:parent,:item,:name,:path,:type,:description,:visibility,:user,NOW())'
        )->execute([
            ':parent'=>$parentId,':item'=>$agendaItemId,':name'=>$up['file_name'],
            ':path'=>$up['file_path'],':type'=>$type?:'Agenda Document',
            ':description'=>$description?:null,':visibility'=>$visibility,
            ':user'=>currentUserId()
        ]);
    }else{
        $table=$map[$module]['table'];$parent=$map[$module]['parent'];
        $pdo->prepare(
            "INSERT INTO {$table}
             ({$parent},file_name,file_path,document_type,description,
              visibility,uploaded_by,uploaded_at)
             VALUES(:parent,:name,:path,:type,:description,:visibility,:user,NOW())"
        )->execute([
            ':parent'=>$parentId,':name'=>$up['file_name'],':path'=>$up['file_path'],
            ':type'=>$type?:ucfirst($module).' Document',
            ':description'=>$description?:null,':visibility'=>$visibility,
            ':user'=>currentUserId()
        ]);
    }

    return (int)$pdo->lastInsertId();
}

function lacmsEventConflicts(PDO $pdo,array $event,array $participantUserIds=[]): array
{
    $eventId=(int)($event['id']??0);
    $start=(string)$event['start_datetime'];
    $end=(string)($event['end_datetime']?:$event['start_datetime']);
    $venue=trim((string)($event['venue']??''));
    $committeeId=(int)($event['committee_id']??0);

    $q=$pdo->prepare(
        "SELECT e.id,e.event_reference,e.title,e.start_datetime,e.end_datetime,
                e.venue,e.committee_id,e.status
         FROM lacms_calendar_events e
         WHERE e.id<>:id
           AND e.status NOT IN ('Completed','Cancelled')
           AND e.start_datetime < :end_time
           AND COALESCE(e.end_datetime,e.start_datetime) > :start_time
         ORDER BY e.start_datetime"
    );
    $q->execute([':id'=>$eventId,':end_time'=>$end,':start_time'=>$start]);
    $overlaps=$q->fetchAll();

    $conflicts=[];

    foreach($overlaps as $other){
        if($venue!=='' && trim((string)$other['venue'])!=='' &&
           mb_strtolower($venue)===mb_strtolower(trim((string)$other['venue']))){
            $conflicts[]=[
                'event_id'=>(int)$other['id'],
                'type'=>'Venue',
                'severity'=>'Critical',
                'details'=>"Venue '{$venue}' overlaps with {$other['event_reference']}."
            ];
        }

        if($committeeId>0 && (int)$other['committee_id']===$committeeId){
            $conflicts[]=[
                'event_id'=>(int)$other['id'],
                'type'=>'Committee',
                'severity'=>'Critical',
                'details'=>"The same committee has an overlapping activity ({$other['event_reference']})."
            ];
        }
    }

    $participantUserIds=array_values(array_unique(array_filter(array_map('intval',$participantUserIds))));
    if($participantUserIds){
        $marks=implode(',',array_fill(0,count($participantUserIds),'?'));
        foreach($overlaps as $other){
            $sql="SELECT p.user_id,u.full_name
                  FROM lacms_calendar_event_participants p
                  LEFT JOIN users u ON u.id=p.user_id
                  WHERE p.calendar_event_id=?
                    AND p.user_id IN ({$marks})";
            $stmt=$pdo->prepare($sql);
            $stmt->execute(array_merge([(int)$other['id']],$participantUserIds));
            foreach($stmt->fetchAll() as $p){
                $conflicts[]=[
                    'event_id'=>(int)$other['id'],
                    'type'=>'Participant',
                    'severity'=>'Warning',
                    'details'=>($p['full_name']?:'A participant')." is also scheduled in {$other['event_reference']}."
                ];
            }
        }
    }

    return $conflicts;
}

function lacmsRefreshEventConflicts(PDO $pdo,int $eventId): array
{
    $q=$pdo->prepare('SELECT * FROM lacms_calendar_events WHERE id=:id');
    $q->execute([':id'=>$eventId]);$event=$q->fetch();
    if(!$event)return [];

    $p=$pdo->prepare(
        'SELECT user_id FROM lacms_calendar_event_participants
         WHERE calendar_event_id=:event AND user_id IS NOT NULL'
    );
    $p->execute([':event'=>$eventId]);
    $participantIds=$p->fetchAll(PDO::FETCH_COLUMN);

    $conflicts=lacmsEventConflicts($pdo,$event,$participantIds);

    if(lacmsTableExists('lacms_calendar_conflicts')){
        $pdo->prepare(
            'DELETE FROM lacms_calendar_conflicts
             WHERE calendar_event_id=:event AND status="Open"'
        )->execute([':event'=>$eventId]);

        foreach($conflicts as $c){
            $pdo->prepare(
                'INSERT INTO lacms_calendar_conflicts
                 (calendar_event_id,conflicting_event_id,conflict_type,severity,details,
                  status,created_at,updated_at)
                 VALUES(:event,:other,:type,:severity,:details,"Open",NOW(),NOW())
                 ON DUPLICATE KEY UPDATE
                    severity=VALUES(severity),details=VALUES(details),
                    status="Open",resolved_by=NULL,resolved_at=NULL,
                    resolution_notes=NULL,updated_at=NOW()'
            )->execute([
                ':event'=>$eventId,':other'=>$c['event_id'],':type'=>$c['type'],
                ':severity'=>$c['severity'],':details'=>$c['details']
            ]);
        }
    }

    $status=$conflicts?'Detected':'Clear';
    $notes=$conflicts
        ? implode("\n",array_map(fn($c)=>$c['details'],$conflicts))
        : null;

    $pdo->prepare(
        'UPDATE lacms_calendar_events
         SET conflict_status=:status,conflict_notes=:notes,updated_at=NOW()
         WHERE id=:id'
    )->execute([':status'=>$status,':notes'=>$notes,':id'=>$eventId]);

    return $conflicts;
}

function lacmsQueueNotification(
    PDO $pdo,string $sourceType,int $sourceId,string $type,string $subject,string $message,
    array $recipients,?string $scheduledAt=null,bool $generatedByAi=false
): int {
    $ref=lacmsGenerateReference($pdo,'lacms_notifications','notification_reference','NTF',$scheduledAt);
    $status=$scheduledAt && strtotime($scheduledAt)>time()?'Scheduled':'Ready';
    $actor=currentUserId()?:null;

    $pdo->prepare(
        'INSERT INTO lacms_notifications
         (notification_reference,notification_type,source_type,source_id,subject,message,
          scheduled_at,status,generated_by_ai,created_by,created_at,updated_at)
         VALUES(:ref,:type,:source_type,:source_id,:subject,:message,:scheduled,:status,
          :ai,:user,NOW(),NOW())'
    )->execute([
        ':ref'=>$ref,':type'=>$type,':source_type'=>$sourceType,':source_id'=>$sourceId,
        ':subject'=>$subject,':message'=>$message,':scheduled'=>$scheduledAt,
        ':status'=>$status,':ai'=>$generatedByAi?1:0,':user'=>$actor
    ]);
    $notificationId=(int)$pdo->lastInsertId();

    foreach($recipients as $r){
        $userId=(int)($r['user_id']??0)?:null;
        $name=trim((string)($r['name']??''));
        $email=trim((string)($r['email']??''));
        $channel=clean($r['channel']??($userId?'System':'Email'));

        $pdo->prepare(
            'INSERT INTO lacms_notification_recipients
             (notification_id,user_id,recipient_name,recipient_email,delivery_channel,
              delivery_status,created_at)
             VALUES(:notification,:user,:name,:email,:channel,"Pending",NOW())'
        )->execute([
            ':notification'=>$notificationId,':user'=>$userId,':name'=>$name?:null,
            ':email'=>$email?:null,':channel'=>$channel
        ]);
    }

    $pdo->prepare(
        'INSERT INTO lacms_notification_history
         (notification_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:notification,"Queue",NULL,:status,:details,:user,NOW())'
    )->execute([
        ':notification'=>$notificationId,':status'=>$status,
        ':details'=>'Notification '.$ref.' queued with '.count($recipients).' recipient(s).',
        ':user'=>$actor
    ]);

    return $notificationId;
}

function lacmsSyncMeetingCalendarEvent(PDO $pdo,int $meetingId): int
{
    $q=$pdo->prepare('SELECT * FROM lacms_meetings WHERE id=:id');
    $q->execute([':id'=>$meetingId]);$meeting=$q->fetch();
    if(!$meeting)throw new RuntimeException('Meeting not found.');

    $eventId=(int)($meeting['calendar_event_id']??0);

    if($eventId>0){
        $pdo->prepare(
            'UPDATE lacms_calendar_events
             SET agenda_id=:agenda,event_type="Legislative Meeting",title=:title,
                 description=:description,start_datetime=:start,end_datetime=:end,
                 venue=:venue,meeting_link=:link,committee_id=:committee,
                 office_id=:office,status=:status,updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':agenda'=>$meeting['agenda_id'],':title'=>$meeting['title'],
            ':description'=>$meeting['purpose'],':start'=>$meeting['start_datetime'],
            ':end'=>$meeting['end_datetime'],':venue'=>$meeting['venue'],
            ':link'=>$meeting['meeting_link'],':committee'=>$meeting['committee_id'],
            ':office'=>$meeting['office_id'],
            ':status'=>match($meeting['status']){
                'Confirmed'=>'Confirmed','In Progress'=>'In Progress',
                'Completed'=>'Completed','Postponed'=>'Postponed',
                'Cancelled'=>'Cancelled',default=>'Tentative'
            },
            ':id'=>$eventId
        ]);
    }else{
        $ref=lacmsGenerateReference($pdo,'lacms_calendar_events','event_reference','CAL',$meeting['start_datetime']);
        $pdo->prepare(
            'INSERT INTO lacms_calendar_events
             (event_reference,agenda_id,event_type,title,description,start_datetime,
              end_datetime,venue,meeting_link,committee_id,office_id,status,
              conflict_status,created_by,created_at,updated_at)
             VALUES(:ref,:agenda,"Legislative Meeting",:title,:description,:start,:end,
              :venue,:link,:committee,:office,"Tentative","Unchecked",:user,NOW(),NOW())'
        )->execute([
            ':ref'=>$ref,':agenda'=>$meeting['agenda_id'],':title'=>$meeting['title'],
            ':description'=>$meeting['purpose'],':start'=>$meeting['start_datetime'],
            ':end'=>$meeting['end_datetime'],':venue'=>$meeting['venue'],
            ':link'=>$meeting['meeting_link'],':committee'=>$meeting['committee_id'],
            ':office'=>$meeting['office_id'],':user'=>currentUserId()
        ]);
        $eventId=(int)$pdo->lastInsertId();
        $pdo->prepare(
            'UPDATE lacms_meetings SET calendar_event_id=:event,updated_at=NOW() WHERE id=:meeting'
        )->execute([':event'=>$eventId,':meeting'=>$meetingId]);
        lacmsCalendarHistory($pdo,$eventId,'Create from Meeting',null,'Tentative',
            'Calendar activity automatically created from '.$meeting['meeting_reference'].'.');
    }

    return $eventId;
}

function lacmsSyncMeetingParticipantsToCalendar(PDO $pdo,int $meetingId): void
{
    $q=$pdo->prepare('SELECT calendar_event_id FROM lacms_meetings WHERE id=:id');
    $q->execute([':id'=>$meetingId]);$eventId=(int)$q->fetchColumn();
    if(!$eventId)return;

    $pdo->prepare('DELETE FROM lacms_calendar_event_participants WHERE calendar_event_id=:event')
        ->execute([':event'=>$eventId]);

    $q=$pdo->prepare(
        'SELECT user_id,external_name,external_email,participant_role,attendance_required,response_status,notes
         FROM lacms_meeting_participants WHERE meeting_id=:meeting ORDER BY id'
    );
    $q->execute([':meeting'=>$meetingId]);

    foreach($q->fetchAll() as $p){
        $pdo->prepare(
            'INSERT INTO lacms_calendar_event_participants
             (calendar_event_id,user_id,external_name,external_email,participant_role,
              attendance_required,response_status,notes,created_at)
             VALUES(:event,:user,:name,:email,:role,:required,:response,:notes,NOW())'
        )->execute([
            ':event'=>$eventId,':user'=>$p['user_id'],':name'=>$p['external_name'],
            ':email'=>$p['external_email'],':role'=>$p['participant_role'],
            ':required'=>$p['attendance_required'],':response'=>$p['response_status'],
            ':notes'=>$p['notes']
        ]);
    }

    lacmsRefreshEventConflicts($pdo,$eventId);
}
