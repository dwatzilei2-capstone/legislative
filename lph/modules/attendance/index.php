<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
if (!hasPermission('lph.attendance.manage')) redirect(APP_URL.'/dashboard.php');

$pdo=db();
$pageTitle='Attendance Tracking (Time In / Time Out)';
$activeMenu='attendance';
$hearingId=(int)($_GET['hearing_id']??0);

$hearings=$pdo->query(
 "SELECT id,reference_number,title,hearing_date,status
  FROM hearings
  WHERE status IN ('Upcoming','Ongoing','Completed')
  ORDER BY hearing_date DESC,hearing_time DESC"
)->fetchAll();

$rows=[];
$hearing=null;
$allStakeholders=[];

if($hearingId>0){
 $hs=$pdo->prepare('SELECT * FROM hearings WHERE id=:id');
 $hs->execute([':id'=>$hearingId]);
 $hearing=$hs->fetch();

 // Auto-sync: Guarantee that any stakeholder invited to this hearing is immediately enrolled in registrations roster
 $syncInvites = $pdo->prepare(
  "INSERT INTO registrations (stakeholder_id, hearing_id, registration_code, registration_status, attendance_type, approved_by, approved_at, registered_at)
   SELECT i.stakeholder_id, i.hearing_id, CONCAT('REG-', YEAR(NOW()), '-', UPPER(SUBSTRING(MD5(RAND()), 1, 10))), 'Approved', 'Invited', 1, NOW(), NOW()
   FROM invitations i
   WHERE i.hearing_id = :hid1 AND i.status NOT IN ('Declined', 'Cancelled')
     AND i.stakeholder_id NOT IN (
       SELECT r.stakeholder_id FROM registrations r WHERE r.hearing_id = :hid2
     )"
 );
 $syncInvites->execute([':hid1'=>$hearingId, ':hid2'=>$hearingId]);

 $stmt=$pdo->prepare(
  "SELECT r.id registration_id,r.registration_code,r.registration_status,r.attendance_type,
          s.id stakeholder_id,s.full_name,s.email,s.organization,
          a.id attendance_id,a.status attendance_status,a.checked_in_at,a.checked_out_at,
          a.check_in_method,a.remarks,
          COALESCE(q_stk.code_value, q_reg.code_value) AS code_value
   FROM registrations r
   JOIN stakeholders s ON s.id=r.stakeholder_id
   LEFT JOIN attendance a ON a.registration_id=r.id
   LEFT JOIN qr_codes q_reg ON q_reg.registration_id=r.id
   LEFT JOIN qr_codes q_stk ON (q_stk.stakeholder_id=s.id AND q_stk.registration_id IS NULL)
   WHERE r.hearing_id=:hid AND r.registration_status='Approved'
   ORDER BY s.full_name"
 );
 $stmt->execute([':hid'=>$hearingId]);
 $rows=$stmt->fetchAll();

 // Stakeholders not yet enrolled in this hearing (for fast walk-in enrollment)
 $allStakeholders = $pdo->prepare(
  "SELECT s.id, s.full_name, s.organization, s.email, q.code_value
   FROM stakeholders s
   LEFT JOIN qr_codes q ON (q.stakeholder_id=s.id AND q.registration_id IS NULL)
   WHERE s.id NOT IN (
     SELECT stakeholder_id FROM registrations WHERE hearing_id=:hid
   )
   ORDER BY s.full_name"
 );
 $allStakeholders->execute([':hid'=>$hearingId]);
 $unregisteredStakeholders = $allStakeholders->fetchAll();
}

$timedIn=0;
$timedOut=0;
$absent=0;
$excused=0;

foreach($rows as $r){
 if(!empty($r['checked_in_at'])) $timedIn++;
 if(!empty($r['checked_out_at'])) $timedOut++;
 if($r['attendance_status']==='Absent') $absent++;
 elseif($r['attendance_status']==='Excused') $excused++;
}

include __DIR__.'/../../layouts/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL.'/assets/css/lph-complete-modules.css') ?>">
<script src="<?= e(vendorAsset('html5-qrcode/html5-qrcode.min.js', 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js')) ?>"></script>
<style>
#cameraReader { width: 100% !important; border: none !important; }
#cameraReader video { width: 100% !important; max-height: 320px !important; object-fit: cover !important; border-radius: 8px !important; }
#cameraReader__scan_region { background: transparent !important; }
#cameraReader__dashboard_section_csr { display: none !important; }
</style>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><div class="main-content">
<?php include __DIR__ . '/../../layouts/top_controls.php'; ?>
<div class="lphx-head">
  <div>
    <div class="lphx-eyebrow"><i class="bi bi-qr-code-scan"></i> Step 4 · Session Attendance</div>
    <h1>Attendance Tracking &amp; QR Scanner</h1>
    <p>Scan stakeholder QR passes or registration codes for automatic session <strong>Time In</strong> and <strong>Time Out</strong> with real-time audit logging.</p>
  </div>
  <form method="get" class="d-flex align-items-center gap-2">
    <select name="hearing_id" class="form-select" onchange="this.form.submit()" style="min-width: 280px;">
      <option value="">-- Select hearing session --</option>
      <?php foreach($hearings as $h): ?>
        <option value="<?= (int)$h['id'] ?>" <?= $hearingId===(int)$h['id']?'selected':'' ?>>
          <?= e(($h['reference_number']?:'').' '.$h['title'].' · '.formatDate($h['hearing_date'])) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if($hearing): ?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="lphx-stat">
      <i class="bi bi-people text-secondary"></i>
      <div><strong><?= count($rows) ?></strong><small>Approved Roster</small></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="lphx-stat">
      <i class="bi bi-box-arrow-in-right text-success"></i>
      <div><strong><?= $timedIn ?></strong><small>Timed In (Present)</small></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="lphx-stat">
      <i class="bi bi-box-arrow-right text-primary"></i>
      <div><strong><?= $timedOut ?></strong><small>Timed Out (Departed)</small></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="lphx-stat">
      <i class="bi bi-person-x text-danger"></i>
      <div><strong><?= $absent + $excused ?></strong><small>Absent / Excused</small></div>
    </div>
  </div>
</div>

<!-- QR CODE SCANNER BOX -->
<div class="card shadow-sm border-0 mb-3" style="border-radius: 14px; background: linear-gradient(135deg, #0d2137 0%, #1e4a7a 100%); color: white;">
  <div class="card-body p-3.5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle d-flex align-items-center justify-content-center bg-white bg-opacity-20" style="width: 36px; height: 36px;">
          <i class="bi bi-qr-code-scan fs-5 text-warning"></i>
        </div>
        <div>
          <h6 class="fw-bold mb-0 text-white">QR Code Scanner (Time In / Time Out)</h6>
          <small class="text-white-50">Compatible with laptop webcam, 2D barcode scanners, or manual code entry</small>
        </div>
      </div>
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <button id="btnToggleCamera" class="btn btn-warning btn-sm fw-semibold shadow-sm text-dark px-3">
          <i class="bi bi-camera-video me-1"></i> Use Laptop Camera
        </button>
        <div class="form-check form-switch m-0 text-white">
          <input class="form-check-input" type="checkbox" id="autoScanToggle" checked>
          <label class="form-check-label small fw-semibold" for="autoScanToggle">Smart Auto Time In/Out</label>
        </div>
      </div>
    </div>

    <!-- LIVE LAPTOP CAMERA SCANNER VIEWPORT -->
    <div id="cameraViewportContainer" class="d-none my-3 p-3 bg-black bg-opacity-40 rounded-3 border border-white border-opacity-15">
      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
          <span class="spinner-grow spinner-grow-sm text-danger" role="status"></span>
          <span class="fw-semibold small text-white">Live Laptop Camera Scanner</span>
          <span class="badge bg-success bg-opacity-75 small">Active</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div id="cameraSelectWrap" class="d-none">
            <select id="cameraSelect" class="form-select form-select-sm bg-dark text-white border-secondary py-1" style="max-width: 220px; font-size: 0.8rem;"></select>
          </div>
          <button id="btnCloseCamera" class="btn btn-sm btn-outline-light py-0.5 px-2" style="font-size: 0.78rem;">
            <i class="bi bi-x-circle me-1"></i> Close Camera
          </button>
        </div>
      </div>
      <div class="position-relative d-flex justify-content-center align-items-center bg-black rounded overflow-hidden shadow-sm" style="min-height: 260px; max-width: 440px; margin: 0 auto; border: 2px solid rgba(255,255,255,0.2);">
        <div id="cameraReader" style="width: 100%;"></div>
      </div>
      <div class="text-center text-white-50 small mt-2">
        <i class="bi bi-lightbulb me-1"></i> Hold stakeholder QR pass in front of your laptop camera. Time In / Out triggers automatically upon detection.
      </div>
    </div>

    <div class="row g-2 align-items-center mt-1">
      <div class="col-lg-6">
        <div class="input-group">
          <span class="input-group-text bg-white border-0"><i class="bi bi-upc-scan text-muted"></i></span>
          <input id="scanCode" class="form-control border-0 py-2 font-monospace fw-bold" autofocus autocomplete="off" placeholder="Scan Stakeholder QR Pass (STK-...) or Registration Code">
        </div>
      </div>
      <div class="col-6 col-lg-2">
        <button id="btnFindCode" class="btn btn-light w-100 fw-semibold"><i class="bi bi-search me-1"></i> Find</button>
      </div>
      <div class="col-3 col-lg-2">
        <button id="btnScanCheckIn" class="btn btn-success w-100 fw-semibold shadow-sm" disabled>
          <i class="bi bi-box-arrow-in-right me-1"></i> Time In
        </button>
      </div>
      <div class="col-3 col-lg-2">
        <button id="btnScanCheckOut" class="btn btn-warning w-100 fw-semibold text-dark shadow-sm" disabled>
          <i class="bi bi-box-arrow-right me-1"></i> Time Out
        </button>
      </div>
    </div>

    <div id="scanResultBox" class="mt-2.5 p-2.5 rounded bg-black bg-opacity-25 border border-white border-opacity-10 small d-flex align-items-center justify-content-between flex-wrap gap-2" style="min-height: 42px;">
      <div id="scanResultText" class="text-white-50">
        <i class="bi bi-info-circle me-1"></i> Ready for scan. Aim scanner at attendee's Stakeholder QR Pass or type their code and press Enter.
      </div>
      <div id="scanBadges" class="d-flex align-items-center gap-2"></div>
    </div>
  </div>
</div>

<!-- ATTENDANCE ROSTER TABLE -->
<div class="card lphx-card shadow-sm border-0">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <span class="fw-bold"><i class="bi bi-list-check me-1 text-primary"></i> <?= e($hearing['title']) ?></span>
      <span class="badge bg-secondary ms-2"><?= count($rows) ?> registered attendee(s)</span>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <?php if(!empty($unregisteredStakeholders)): ?>
      <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#enrollModal">
        <i class="bi bi-person-plus me-1"></i> Enroll Stakeholder
      </button>
      <?php endif; ?>
      <a href="print.php?hearing_id=<?= $hearingId ?>" target="_blank" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-printer me-1"></i> Print Roster
      </a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="min-width: 220px;">Stakeholder</th>
          <th style="min-width: 140px;">QR Pass / Reg Code</th>
          <th style="min-width: 130px;">Status</th>
          <th style="min-width: 130px;">Time In</th>
          <th style="min-width: 130px;">Time Out</th>
          <th class="text-end" style="min-width: 200px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
        <tr>
          <td colspan="6" class="text-center py-5">
            <div class="text-muted">
              <i class="bi bi-people display-6 d-block mb-2 text-secondary opacity-50"></i>
              <strong>No attendees registered yet for this hearing session.</strong>
              <p class="small mb-3">You can scan any stakeholder's QR pass above to immediately admit and time them in, or click below to enroll stakeholders.</p>
              <?php if(!empty($unregisteredStakeholders)): ?>
              <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#enrollModal">
                <i class="bi bi-person-plus me-1"></i> Enroll Stakeholders Now
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endif; ?>

        <?php foreach($rows as $r): ?>
        <tr id="roster-row-<?= (int)$r['registration_id'] ?>" class="<?= !empty($r['checked_out_at']) ? 'table-light text-muted' : (!empty($r['checked_in_at']) ? 'bg-success bg-opacity-10' : '') ?>">
          <td>
            <strong class="text-dark d-block"><?= e($r['full_name']) ?></strong>
            <div class="small text-muted"><?= e($r['organization'] ?: $r['email']) ?></div>
          </td>
          <td>
            <?php if(!empty($r['code_value'])): ?>
              <a href="../stakeholders/qr.php?id=<?= (int)$r['stakeholder_id'] ?>" target="_blank" class="badge bg-light text-dark border text-decoration-none font-monospace d-inline-flex align-items-center gap-1" title="View & Print QR Pass">
                <i class="bi bi-qr-code text-primary"></i> <?= e($r['code_value']) ?>
              </a>
            <?php else: ?>
              <span class="badge bg-light text-muted border font-monospace"><?= e($r['registration_code']) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if(!empty($r['checked_out_at'])): ?>
              <span class="badge bg-primary-subtle text-primary border border-primary-subtle d-inline-flex align-items-center gap-1">
                <i class="bi bi-check2-all"></i> Completed
              </span>
            <?php elseif(!empty($r['checked_in_at'])): ?>
              <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1">
                <i class="bi bi-box-arrow-in-right"></i> In Session
              </span>
            <?php elseif($r['attendance_status']==='Absent'): ?>
              <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Absent</span>
            <?php elseif($r['attendance_status']==='Excused'): ?>
              <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Excused</span>
            <?php else: ?>
              <span class="badge bg-light text-muted border">Not Marked</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if(!empty($r['checked_in_at'])): ?>
              <span class="text-success fw-bold d-inline-flex align-items-center gap-1">
                <i class="bi bi-box-arrow-in-right"></i> <?= date('h:i A', strtotime($r['checked_in_at'])) ?>
              </span>
              <div class="small text-muted" style="font-size: 0.72rem;"><?= e($r['check_in_method'] ?: 'QR') ?></div>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if(!empty($r['checked_out_at'])): ?>
              <span class="text-primary fw-bold d-inline-flex align-items-center gap-1">
                <i class="bi bi-box-arrow-right"></i> <?= date('h:i A', strtotime($r['checked_out_at'])) ?>
              </span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <button class="btn <?= !empty($r['checked_in_at']) && empty($r['checked_out_at']) ? 'btn-success' : 'btn-outline-success' ?> att-action"
                data-id="<?= (int)$r['registration_id'] ?>" data-action="check_in" title="Record Time In">
                <i class="bi bi-box-arrow-in-right"></i> In
              </button>
              <button class="btn <?= !empty($r['checked_out_at']) ? 'btn-primary' : 'btn-outline-primary' ?> att-action"
                data-id="<?= (int)$r['registration_id'] ?>" data-action="check_out" title="Record Time Out">
                <i class="bi bi-box-arrow-right"></i> Out
              </button>
              <button class="btn <?= $r['attendance_status']==='Absent' ? 'btn-danger' : 'btn-outline-danger' ?> att-action"
                data-id="<?= (int)$r['registration_id'] ?>" data-action="absent" title="Mark Absent">
                <i class="bi bi-x"></i>
              </button>
              <button class="btn <?= $r['attendance_status']==='Excused' ? 'btn-secondary' : 'btn-outline-secondary' ?> att-action"
                data-id="<?= (int)$r['registration_id'] ?>" data-action="excused" title="Mark Excused">
                <i class="bi bi-dash"></i>
              </button>
              <a href="../stakeholders/qr.php?id=<?= (int)$r['stakeholder_id'] ?>" target="_blank" class="btn btn-outline-dark" title="View &amp; Print QR Pass">
                <i class="bi bi-qr-code"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- FAST WALK-IN ENROLLMENT MODAL -->
<?php if(!empty($unregisteredStakeholders)): ?>
<div class="modal fade" id="enrollModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-dark text-white py-2.5 px-3">
        <h6 class="modal-title fw-bold"><i class="bi bi-person-plus me-1 text-primary"></i> Enroll Stakeholder into Hearing</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3">
        <p class="small text-muted mb-2">Select any existing stakeholder to add them to this hearing session roster:</p>
        <div class="list-group list-group-flush border rounded" style="max-height: 280px; overflow-y: auto;">
          <?php foreach($unregisteredStakeholders as $us): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
            <div>
              <strong class="d-block" style="font-size: 0.9rem;"><?= e($us['full_name']) ?></strong>
              <small class="text-muted"><?= e($us['organization'] ?: $us['email']) ?>
                <?php if(!empty($us['code_value'])): ?>
                  · <span class="font-monospace text-primary"><?= e($us['code_value']) ?></span>
                <?php endif; ?>
              </small>
            </div>
            <button class="btn btn-sm btn-primary btn-enroll-now" data-code="<?= e($us['code_value'] ?: '') ?>" data-sid="<?= (int)$us['id'] ?>">
              Enroll &amp; In
            </button>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="lphx-empty py-5"><i class="bi bi-calendar-check display-4 mb-2"></i><h4>Select a hearing session</h4><p class="text-muted">Choose a hearing from the dropdown menu above to view its roster and scan QR codes.</p></div>
<?php endif; ?>

</div></div>

<?php if($hearing): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const hearingId = <?= (int)$hearingId ?>;
  const csrf = '<?= e(csrfToken()) ?>';
  const scanInput = document.getElementById('scanCode');
  const btnFind = document.getElementById('btnFindCode');
  const btnCheckIn = document.getElementById('btnScanCheckIn');
  const btnCheckOut = document.getElementById('btnScanCheckOut');
  const resultText = document.getElementById('scanResultText');
  const resultBadges = document.getElementById('scanBadges');
  const autoScanSwitch = document.getElementById('autoScanToggle');

  let currentFound = null;

  // Web Audio chime for scanner feedback
  function playScanSound(type = 'in'){
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.connect(gain);
      gain.connect(ctx.destination);
      const now = ctx.currentTime;
      if (type === 'out') {
        osc.frequency.setValueAtTime(587.33, now); // D5
        osc.frequency.setValueAtTime(440.00, now + 0.1); // A4
        gain.gain.setValueAtTime(0.2, now);
        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.25);
        osc.start(now);
        osc.stop(now + 0.25);
      } else {
        osc.frequency.setValueAtTime(523.25, now); // C5
        osc.frequency.setValueAtTime(659.25, now + 0.1); // E5
        gain.gain.setValueAtTime(0.2, now);
        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.25);
        osc.start(now);
        osc.stop(now + 0.25);
      }
    } catch(e){}
  }

  // Attendance Action Handler (Time In, Time Out, Absent, Excused)
  async function performAction(registrationId, action, method = 'Manual'){
    if (!registrationId) return;
    const fd = new FormData();
    fd.append('csrf_token', csrf);
    fd.append('registration_id', registrationId);
    fd.append('action', action);
    fd.append('check_in_method', method);

    try {
      const res = await fetch(APP_URL + '/modules/attendance/ajax_attendance_action.php', {
        method: 'POST',
        body: fd
      }).then(r => r.json());

      if (res.success) {
        playScanSound(action === 'check_out' ? 'out' : 'in');
        appToast('success', res.message);
        setTimeout(() => location.reload(), 450);
      } else {
        Swal.fire('Attendance Error', res.message, 'error');
      }
    } catch (err) {
      Swal.fire('Connection Error', 'Failed to communicate with server.', 'error');
    }
  }

  // Row button clicks
  document.querySelectorAll('.att-action').forEach(btn => {
    btn.onclick = () => performAction(btn.dataset.id, btn.dataset.action, 'Manual');
  });

  // Fast enroll buttons from modal
  document.querySelectorAll('.btn-enroll-now').forEach(btn => {
    btn.onclick = async () => {
      const code = btn.dataset.code;
      if (code) {
        scanInput.value = code;
        const modalEl = document.getElementById('enrollModal');
        if (modalEl) {
          const bsModal = bootstrap.Modal.getInstance(modalEl);
          if (bsModal) bsModal.hide();
        }
        await lookupCode(true); // scan & auto time-in
      }
    };
  });

  // Code lookup logic
  async function lookupCode(isAuto = false){
    const code = scanInput.value.trim();
    if (!code) {
      scanInput.focus();
      return;
    }

    resultText.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Looking up code <strong>' + code + '</strong>...';
    resultBadges.innerHTML = '';
    btnCheckIn.disabled = true;
    btnCheckOut.disabled = true;

    try {
      const res = await appGet(APP_URL + '/modules/attendance/ajax_scan.php?hearing_id=' + hearingId + '&code=' + encodeURIComponent(code));

      if (res.success) {
        currentFound = res.registration;

        let statusText = '';
        let statusBadgeClass = 'bg-secondary';
        if (currentFound.checked_out_at) {
          statusText = 'Timed Out at ' + currentFound.checked_out_at;
          statusBadgeClass = 'bg-primary';
        } else if (currentFound.checked_in_at) {
          statusText = 'Timed In at ' + currentFound.checked_in_at;
          statusBadgeClass = 'bg-success';
        } else {
          statusText = 'Ready for Time In';
          statusBadgeClass = 'bg-warning text-dark';
        }

        resultText.innerHTML = '<strong class="text-white fs-6">' + currentFound.full_name + '</strong>' +
          '<span class="text-white-50 ms-2">(' + (currentFound.organization || currentFound.email) + ')</span>' +
          '<span class="ms-2 font-monospace text-info small">[' + (currentFound.code_value || currentFound.registration_code) + ']</span>';

        resultBadges.innerHTML = '<span class="badge ' + statusBadgeClass + ' py-1 px-2">' + statusText + '</span>';

        btnCheckIn.disabled = false;
        btnCheckOut.disabled = false;

        // Smart auto-action when barcode/QR scanner hits Enter
        if (isAuto && autoScanSwitch.checked) {
          const recommended = currentFound.recommended_action || 'check_in';
          performAction(currentFound.registration_id, recommended, 'QR');
        }
      } else {
        currentFound = null;
        resultText.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i> ' + res.message + '</span>';
        resultBadges.innerHTML = '';
        btnCheckIn.disabled = true;
        btnCheckOut.disabled = true;
      }
    } catch (err) {
      resultText.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i> Error processing scan request.</span>';
    }
  }

  btnFind.onclick = () => lookupCode(false);

  scanInput.addEventListener('keydown', function(e){
    if (e.key === 'Enter') {
      e.preventDefault();
      lookupCode(true);
    }
  });

  btnCheckIn.onclick = () => {
    if (currentFound) performAction(currentFound.registration_id, 'check_in', 'QR');
  };

  btnCheckOut.onclick = () => {
    if (currentFound) performAction(currentFound.registration_id, 'check_out', 'QR');
  };

  /* ================= LAPTOP CAMERA SCANNER (html5-qrcode) ================= */
  let html5QrCode = null;
  let isCameraRunning = false;
  let lastCameraCode = null;
  let lastCameraTime = 0;

  const btnToggleCam = document.getElementById('btnToggleCamera');
  const btnCloseCam = document.getElementById('btnCloseCamera');
  const camContainer = document.getElementById('cameraViewportContainer');
  const camSelect = document.getElementById('cameraSelect');
  const camSelectWrap = document.getElementById('cameraSelectWrap');

  async function startCamera(preferredCameraId = null){
    if (typeof Html5Qrcode === 'undefined') {
      Swal.fire('Scanner Library Not Ready', 'The QR camera library is still loading. Please check connection or reload.', 'warning');
      return;
    }

    camContainer.classList.remove('d-none');
    btnToggleCam.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Starting Camera...';
    btnToggleCam.disabled = true;

    try {
      if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("cameraReader");
      }

      const devices = await Html5Qrcode.getCameras();
      if (!devices || devices.length === 0) {
        camContainer.classList.add('d-none');
        btnToggleCam.innerHTML = '<i class="bi bi-camera-video me-1"></i> Use Laptop Camera';
        btnToggleCam.disabled = false;
        Swal.fire('No Camera Detected', 'No webcam or camera device was found on this laptop/computer.', 'warning');
        return;
      }

      if (devices.length > 1) {
        camSelect.innerHTML = devices.map((d, i) =>
          `<option value="${d.id}" ${d.id === preferredCameraId || (!preferredCameraId && i === 0) ? 'selected' : ''}>${d.label || 'Camera ' + (i+1)}</option>`
        ).join('');
        camSelectWrap.classList.remove('d-none');
      } else {
        camSelectWrap.classList.add('d-none');
      }

      const selectedCamId = preferredCameraId || devices[0].id;

      await html5QrCode.start(
        selectedCamId,
        {
          fps: 10,
          qrbox: { width: 250, height: 250 }
        },
        (decodedText) => {
          const now = Date.now();
          // Debounce same QR code read within 3.5 seconds
          if (decodedText === lastCameraCode && (now - lastCameraTime) < 3500) {
            return;
          }
          lastCameraCode = decodedText;
          lastCameraTime = now;

          scanInput.value = decodedText;
          lookupCode(true); // Automatically performs Time In or Time Out!
        },
        () => {} // ignore frame noise
      );

      isCameraRunning = true;
      btnToggleCam.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Stop Camera';
      btnToggleCam.classList.remove('btn-warning');
      btnToggleCam.classList.add('btn-danger');
      btnToggleCam.disabled = false;

    } catch (err) {
      console.error('Camera startup error:', err);
      camContainer.classList.add('d-none');
      btnToggleCam.innerHTML = '<i class="bi bi-camera-video me-1"></i> Use Laptop Camera';
      btnToggleCam.classList.remove('btn-danger');
      btnToggleCam.classList.add('btn-warning');
      btnToggleCam.disabled = false;
      Swal.fire({
        icon: 'warning',
        title: 'Camera Access Needed',
        html: '<p class="mb-2">Could not access laptop webcam.</p>' +
              '<p class="small text-muted mb-0">Please ensure camera permissions are allowed in your browser address bar (lock / camera icon next to URL).</p>'
      });
    }
  }

  async function stopCamera(){
    if (html5QrCode && isCameraRunning) {
      try {
        await html5QrCode.stop();
      } catch (e) {}
    }
    isCameraRunning = false;
    camContainer.classList.add('d-none');
    btnToggleCam.innerHTML = '<i class="bi bi-camera-video me-1"></i> Use Laptop Camera';
    btnToggleCam.classList.remove('btn-danger');
    btnToggleCam.classList.add('btn-warning');
    btnToggleCam.disabled = false;
  }

  if (btnToggleCam) {
    btnToggleCam.onclick = async () => {
      if (isCameraRunning) {
        await stopCamera();
      } else {
        await startCamera();
      }
    };
  }

  if (btnCloseCam) {
    btnCloseCam.onclick = stopCamera;
  }

  if (camSelect) {
    camSelect.onchange = async () => {
      const newId = camSelect.value;
      if (isCameraRunning && html5QrCode) {
        await html5QrCode.stop();
        isCameraRunning = false;
        await startCamera(newId);
      }
    };
  }
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
