<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/lacms_notification_helpers.php';
requireLacmsPermission('lacms.notifications.view');

$pdo=db();lacmsRefreshDeadlineStatuses($pdo);
$pageTitle='AI-Assisted Email Reminders';$activeMenu='ai_email_reminders';
$extraCss=[appUrl('assets/css/lacms-operational.css')];

$selected=(int)($_GET['deadline_id']??0);
$deadlines=$pdo->query(
 "SELECT d.id,d.deadline_reference,d.title,d.due_datetime,d.priority_level,d.status,
         u.full_name responsible_name,
         (SELECT COUNT(*) FROM lacms_deadline_reminders r
          WHERE r.deadline_id=d.id AND r.status='Scheduled') scheduled_reminders
  FROM lacms_deadlines d
  LEFT JOIN users u ON u.id=d.responsible_user_id
  WHERE d.status NOT IN ('Completed','Cancelled')
  ORDER BY FIELD(d.status,'Overdue','In Progress','Pending'),d.due_datetime
  LIMIT 200"
)->fetchAll();

$aiRecent=$pdo->query(
 "SELECT a.*,u.full_name
  FROM lacms_ai_request_logs a
  LEFT JOIN users u ON u.id=a.created_by
  ORDER BY a.created_at DESC,a.id DESC LIMIT 10"
)->fetchAll();

include __DIR__.'/../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="lacms-main-content">

<div class="lo-head"><div><div class="lo-eyebrow"><i class="bi bi-stars"></i> Step 6 · Intelligent Reminder Workspace</div><h1>AI-Assisted Reminder Generation</h1><p>Generate professional deadline reminder content using local Ollama when available, with a deterministic legislative reminder template as a safe fallback. Review content before placing it in the notification queue.</p></div><span class="lo-status <?= LACMS_OLLAMA_ENABLED?'good':'warn' ?>"><?= LACMS_OLLAMA_ENABLED?'Ollama Enabled':'Template Mode' ?></span></div>

<div class="row g-3">
<div class="col-xl-5">
<div class="card lo-card"><div class="card-header">Open Deadlines</div><div class="card-body p-0"><div class="list-group list-group-flush">
<?php if(!$deadlines): ?><div class="list-group-item text-muted">No open deadlines.</div><?php endif; ?>
<?php foreach($deadlines as $d): ?><a class="list-group-item list-group-item-action <?= $selected===(int)$d['id']?'active':'' ?>" href="?deadline_id=<?= (int)$d['id'] ?>"><div class="d-flex justify-content-between gap-2"><div><strong><?= e($d['deadline_reference'].' · '.$d['title']) ?></strong><div class="small"><?= e($d['responsible_name']?:'Unassigned') ?> · <?= e($d['priority_level']) ?></div></div><div class="text-end small"><?= formatDateTime($d['due_datetime']) ?><br><?= e($d['status']) ?></div></div></a><?php endforeach; ?>
</div></div></div>
</div>

<div class="col-xl-7">
<div class="card lo-card mb-3"><div class="card-header">Reminder Composer</div><div class="card-body">
<?php if(!$selected): ?><div class="lo-alert">Select a deadline to review its reminder context.</div>
<?php elseif(!lacmsOperationalManager()): ?><div class="lo-alert">Reminder generation and queuing are available to Administrators and Legislative Staff. Committee Members have read-only access to this workspace.</div>
<?php else: ?>
<form id="reminderComposer"><?= csrfField() ?><input type="hidden" name="deadline_id" value="<?= $selected ?>"><input type="hidden" name="mode" value="preview">
<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="prefer_ai" value="1" id="preferAi" checked><label class="form-check-label" for="preferAi">Prefer local Ollama generation; use approved template if unavailable</label></div>
<button class="btn btn-warning mb-3" type="button" id="btnGenerate"><i class="bi bi-stars"></i> Generate Preview</button>
<label class="form-label">Subject</label><input class="form-control mb-3" name="subject" id="reminderSubject">
<label class="form-label">Message</label><textarea class="form-control mb-3" rows="7" name="message" id="reminderMessage"></textarea>
<div id="aiStatus" class="small text-muted mb-3"></div>
<label class="form-label">Optional Queue Schedule</label><input type="datetime-local" class="form-control mb-3" name="scheduled_at">
<button class="btn btn-primary" type="button" id="btnQueue"><i class="bi bi-send"></i> Queue Reminder</button>
</form>
<?php endif; ?>
</div></div>

<div class="card lo-card"><div class="card-header">Recent AI Attempts</div><div class="table-responsive"><table class="table lo-table mb-0"><thead><tr><th>Time</th><th>Model</th><th>Status</th><th>Duration</th><th>Requested By</th></tr></thead><tbody><?php if(!$aiRecent): ?><tr><td colspan="5" class="text-center text-muted py-4">No AI requests yet.</td></tr><?php endif; ?><?php foreach($aiRecent as $a): ?><tr><td><?= formatDateTime($a['created_at']) ?></td><td><?= e($a['model_used']?:'—') ?></td><td><?= $a['success']?'Generated':'Fallback' ?><?php if($a['error_message']): ?><div class="small text-muted"><?= e(mb_strimwidth($a['error_message'],0,90,'…')) ?></div><?php endif; ?></td><td><?= (int)$a['duration_ms'] ?> ms</td><td><?= e($a['full_name']?:'System') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
</div>

</main></div>
<?php if($selected&&lacmsOperationalManager()): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const form=document.getElementById('reminderComposer'),subject=document.getElementById('reminderSubject'),message=document.getElementById('reminderMessage'),status=document.getElementById('aiStatus');
 async function generate(){
   const fd=new FormData(form);fd.set('mode','preview');
   const r=await fetch('ajax_generate_reminder.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());
   if(!r.success){Swal.fire('Reminder Error',r.message,'error');return false;}
   subject.value=r.subject||'';message.value=r.message_text||'';
   status.textContent='Content source: '+(r.ai_status||'Template')+(r.ai_error?' · '+r.ai_error:'');
   return true;
 }
 document.getElementById('btnGenerate').onclick=generate;
 document.getElementById('btnQueue').onclick=async()=>{
   if(!subject.value.trim()||!message.value.trim()){const ok=await generate();if(!ok)return;}
   const fd=new FormData(form);fd.set('mode','queue');fd.set('subject',subject.value);fd.set('message',message.value);
   const r=await fetch('ajax_generate_reminder.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());
   if(r.success){await Swal.fire('Queued',r.message,'success');location.href='meeting_notifications.php';}
   else Swal.fire('Reminder Error',r.message,'error');
 };
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../layouts/footer.php'; ?>
