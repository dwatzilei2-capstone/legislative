<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/citizen_services.php';
requireLogin();

$pdo=db();
$pageTitle='New Citizen Submission';
$activeMenu='engagement';
$extraCss=[appUrl('assets/css/public-modules.css')];

$categories=$pdo->query(
    'SELECT id,name,category_type
     FROM cef_categories
     WHERE is_active=1
     ORDER BY category_type,name'
)->fetchAll();

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<a class="small text-decoration-none" href="<?= e(appUrl('modules/engagement/index.php')) ?>"><i class="bi bi-arrow-left"></i> My Engagement</a>
<h1>New Citizen Submission</h1>
<p>Send public feedback, a proposal/suggestion or a complaint using your signed-in account.</p>
</div>
</div>

<div class="card portal-card">
<div class="card-header">Submission Form</div>
<div class="card-body">
<form method="post" action="<?= e(appUrl('modules/engagement/save.php')) ?>" id="engagementForm">
<?= csrfField() ?>
<div class="row g-3">
<div class="col-md-4">
<label class="form-label">Submission Type</label>
<select class="form-select" name="submission_type" id="submissionType" required>
<option>Feedback</option><option>Proposal</option><option>Complaint</option>
</select>
</div>
<div class="col-md-4">
<label class="form-label">Category</label>
<select class="form-select" name="category_id" id="categorySelect">
<option value="">Select category</option>
<?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" data-type="<?= e($c['category_type']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
</select>
</div>
<div class="col-md-4">
<label class="form-label">Priority</label>
<select class="form-select" name="priority_level"><option>Low</option><option selected>Normal</option><option>High</option><option>Urgent</option></select>
</div>
<div class="col-12"><label class="form-label">Title</label><input class="form-control" name="title" maxlength="255" required></div>
<div class="col-12"><label class="form-label">Short Summary</label><textarea class="form-control" name="summary" rows="2"></textarea></div>
<div class="col-12"><label class="form-label">Full Details</label><textarea class="form-control" name="details" rows="6" required></textarea></div>

<div class="col-12 type-fields feedback-fields">
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Feedback Kind</label><input class="form-control" name="feedback_kind" value="General Feedback"></div>
<div class="col-md-6"><label class="form-label">Service Area</label><input class="form-control" name="service_area"></div>
<div class="col-md-8"><label class="form-label">Desired Outcome</label><textarea class="form-control" name="desired_outcome" rows="2"></textarea></div>
<div class="col-md-4"><label class="form-label">Rating (optional)</label><select class="form-select" name="citizen_rating"><option value="">No rating</option><?php for($i=1;$i<=5;$i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></div>
</div>
</div>

<div class="col-12 type-fields proposal-fields d-none">
<div class="row g-3">
<div class="col-12"><label class="form-label">Problem / Opportunity</label><textarea class="form-control" name="problem_statement" rows="2"></textarea></div>
<div class="col-12"><label class="form-label">Proposed Solution</label><textarea class="form-control" name="proposed_solution" rows="3"></textarea></div>
<div class="col-md-8"><label class="form-label">Expected Public Benefit</label><textarea class="form-control" name="expected_public_benefit" rows="2"></textarea></div>
<div class="col-md-4"><label class="form-label">Estimated Scope</label><input class="form-control" name="estimated_scope"></div>
</div>
</div>

<div class="col-12 type-fields complaint-fields d-none">
<div class="row g-3">
<div class="col-md-5"><label class="form-label">Affected Service</label><input class="form-control" name="affected_service"></div>
<div class="col-md-4"><label class="form-label">Incident Date/Time</label><input type="datetime-local" class="form-control" name="incident_datetime"></div>
<div class="col-md-3"><label class="form-label">Urgency</label><select class="form-select" name="urgency_level"><option>Low</option><option selected>Normal</option><option>High</option><option>Urgent</option></select></div>
</div>
</div>

<div class="col-12">
<div class="safe-note"><i class="bi bi-shield-check"></i><div><strong>Privacy consent</strong><span>Your registered citizen account is linked to this submission so you can follow its status securely. Staff-only moderation notes are not displayed here.</span></div></div>
<input type="hidden" name="privacy_consent" value="1">
</div>

<div class="col-12 d-flex justify-content-end gap-2">
<a class="btn btn-outline-secondary" href="<?= e(appUrl('modules/engagement/index.php')) ?>">Cancel</a>
<button class="btn btn-primary"><i class="bi bi-send"></i> Submit</button>
</div>
</div>
</form>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const type=document.getElementById('submissionType');
 const category=document.getElementById('categorySelect');
 const sync=()=>{
   const value=type.value;
   document.querySelector('.feedback-fields').classList.toggle('d-none',value!=='Feedback');
   document.querySelector('.proposal-fields').classList.toggle('d-none',value!=='Proposal');
   document.querySelector('.complaint-fields').classList.toggle('d-none',value!=='Complaint');
   [...category.options].forEach((o,i)=>{
     if(i===0)return;
     const t=o.dataset.type||'All';
     o.hidden=t!=='All'&&t!==value;
     o.disabled=o.hidden;
   });
   if(category.selectedOptions[0]?.disabled)category.value='';
 };
 type.addEventListener('change',sync);sync();
});
</script>

</main>
</div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
