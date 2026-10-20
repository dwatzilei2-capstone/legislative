<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/citizen_services.php';
requireLogin();

if($_SERVER['REQUEST_METHOD']!=='POST')redirect(appUrl('modules/engagement/index.php'));
requireCsrf();

$pdo=db();

try{
    $pdo->beginTransaction();

    $result=citizenCreateCefSubmission($pdo,currentUserId(),[
        'submission_type'=>$_POST['submission_type']??'Feedback',
        'category_id'=>(int)($_POST['category_id']??0),
        'priority_level'=>$_POST['priority_level']??'Normal',
        'title'=>$_POST['title']??'',
        'summary'=>$_POST['summary']??'',
        'details'=>$_POST['details']??'',

        'feedback_kind'=>$_POST['feedback_kind']??'General Feedback',
        'service_area'=>$_POST['service_area']??'',
        'desired_outcome'=>$_POST['desired_outcome']??'',
        'citizen_rating'=>(int)($_POST['citizen_rating']??0),

        'problem_statement'=>$_POST['problem_statement']??'',
        'proposed_solution'=>$_POST['proposed_solution']??'',
        'expected_public_benefit'=>$_POST['expected_public_benefit']??'',
        'estimated_scope'=>$_POST['estimated_scope']??'',

        'affected_service'=>$_POST['affected_service']??'',
        'incident_datetime'=>$_POST['incident_datetime']??'',
        'urgency_level'=>$_POST['urgency_level']??'Normal',
    ]);

    $pdo->commit();

    setFlash(
        'success',
        'Your submission was received. Reference: '.$result['reference_number']
    );

    redirect(appUrl('modules/engagement/view.php?id='.(int)$result['id']));
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    setFlash('danger',APP_DEBUG?$e->getMessage():'Unable to submit your record right now.');
    redirect(appUrl('modules/engagement/create.php'));
}
