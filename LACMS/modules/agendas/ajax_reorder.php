<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.agendas.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$agendaId=(int)($_POST['agenda_id']??0);
$order=json_decode((string)($_POST['order']??'[]'),true);
if(!is_array($order)||!$order)jsonResponse(false,'No item order was supplied.');

$q=$pdo->prepare('SELECT status FROM lacms_agendas WHERE id=:id');
$q->execute([':id'=>$agendaId]);$status=$q->fetchColumn();
if($status===false)jsonResponse(false,'Agenda not found.');
if($status==='Finalized')jsonResponse(false,'Finalized agenda items are locked.');

try{
    $pdo->beginTransaction();
    $update=$pdo->prepare(
        'UPDATE lacms_agenda_items
         SET sequence_number=:seq,updated_at=NOW()
         WHERE id=:id AND agenda_id=:agenda'
    );
    $seq=1;
    foreach($order as $id){
        $update->execute([':seq'=>$seq++,':id'=>(int)$id,':agenda'=>$agendaId]);
    }
    lacmsAgendaHistory($pdo,$agendaId,'Reorder',$status,$status,'Agenda item sequence updated.');
    $pdo->commit();
    jsonResponse(true,'Agenda order updated.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to reorder agenda.');
}
