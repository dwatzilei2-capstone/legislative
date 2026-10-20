<?php
declare(strict_types=1);

if(!isset($pdo,$s) || !is_array($s)){
    return;
}

$ticketChatPermission=$ticketChatPermission??cefTicketManagePermission((string)$s['submission_type']);
$ticketChatClosed=cefTicketChatClosed((string)$s['status']);
$ticketChatCanSend=cefHasPermission($ticketChatPermission)&&!$ticketChatClosed;

$ticketChatQ=$pdo->prepare(
    'SELECT f.id,f.direction,f.sender_user_id,f.sender_name,f.message,f.created_at,
            u.full_name sender_account_name
     FROM cef_followups f
     LEFT JOIN users u ON u.id=f.sender_user_id
     WHERE f.submission_id=:submission
       AND f.public_visible=1
     ORDER BY f.created_at,f.id'
);
$ticketChatQ->execute([':submission'=>(int)$s['id']]);
$ticketChatMessages=$ticketChatQ->fetchAll();
?>
<div class="card cef-card mb-3" id="ticketConversationCard">
<div class="card-header d-flex justify-content-between align-items-center">
<span><i class="bi bi-chat-dots me-2"></i>Ticket Conversation</span>
<span class="badge <?= $ticketChatClosed?'text-bg-secondary':'text-bg-success' ?>"><?= $ticketChatClosed?'Chat Closed':'Chat Open' ?></span>
</div>
<div class="card-body">
<div class="cef-ticket-chat" id="ticketConversationMessages">
<?php if(!$ticketChatMessages): ?>
<div class="cef-ticket-chat-empty">No citizen/staff messages yet.</div>
<?php endif; ?>
<?php foreach($ticketChatMessages as $chat):
    $fromCitizen=$chat['direction']==='Citizen to Council';
    $sender=$fromCitizen
        ? ($chat['sender_account_name']?:$chat['sender_name']?:'Citizen')
        : ($chat['sender_account_name']?:$chat['sender_name']?:'Council Staff');
?>
<div class="cef-chat-row <?= $fromCitizen?'citizen':'staff' ?>">
<div class="cef-chat-bubble">
<div class="cef-chat-sender"><?= e($sender) ?> <span><?= $fromCitizen?'Citizen':'CEPFMS Staff' ?></span></div>
<div class="cef-chat-message"><?= nl2br(e($chat['message'])) ?></div>
<div class="cef-chat-time"><?= formatDateTime($chat['created_at']) ?></div>
</div>
</div>
<?php endforeach; ?>
</div>

<?php if($ticketChatClosed): ?>
<div class="alert alert-secondary small mt-3 mb-0">
<i class="bi bi-lock me-1"></i>
This ticket is <?= e($s['status']) ?>. The conversation is read-only and no new message can be sent.
</div>
<?php elseif($ticketChatCanSend): ?>
<form id="ticketChatForm" class="mt-3">
<?= csrfField() ?>
<input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
<label class="form-label">Reply to Citizen</label>
<div class="input-group">
<textarea class="form-control" name="message" rows="2" maxlength="5000" required placeholder="Type a message for this ticket..."></textarea>
<button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> Send</button>
</div>
<div class="form-text">This message is visible to the citizen inside this ticket.</div>
</form>
<?php elseif(cefHasPermission($ticketChatPermission)): ?>
<div class="text-muted small mt-3">Chat is not available for this ticket.</div>
<?php endif; ?>
</div>
</div>

<?php if($ticketChatCanSend): ?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const box=document.getElementById('ticketConversationMessages');
 if(box)box.scrollTop=box.scrollHeight;
 const form=document.getElementById('ticketChatForm');
 if(!form)return;
 form.addEventListener('submit',async e=>{
   e.preventDefault();
   const button=form.querySelector('button[type="submit"]');
   if(button)button.disabled=true;
   try{
     const response=await fetch('<?= e(appUrl('modules/shared/ajax_ticket_chat.php')) ?>',{
       method:'POST',
       headers:{'X-Requested-With':'XMLHttpRequest'},
       body:new FormData(form)
     });
     const data=await response.json();
     if(data.success){location.reload();return;}
     Swal.fire('Ticket Conversation',data.message||'Unable to send message.','error');
   }catch(err){
     Swal.fire('Ticket Conversation','Unable to send message.','error');
   }finally{
     if(button)button.disabled=false;
   }
 });
});
</script>
<?php endif; ?>
