<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lph_module_helpers.php';

requireLogin();

$pdo = db();
$pageTitle = 'Stakeholder Registrations';
$activeMenu = 'stakeholders';

$hearingFilter = (int)($_GET['hearing_id'] ?? 0);

// Active hearings for staff quick-enroll form
$activeHearings = $pdo->query(
    "SELECT id, reference_number, title, hearing_date, status 
     FROM hearings 
     WHERE status IN ('Upcoming','Ongoing') 
     ORDER BY hearing_date DESC, title ASC"
)->fetchAll();
if (empty($activeHearings)) {
    $activeHearings = $pdo->query(
        "SELECT id, reference_number, title, hearing_date, status 
         FROM hearings 
         ORDER BY hearing_date DESC, title ASC LIMIT 30"
    )->fetchAll();
}

// All hearings available for filtering (that have registrations or are active)
$filterHearings = $pdo->query(
    "SELECT DISTINCT h.id, h.reference_number, h.title, h.hearing_date, h.status,
            (SELECT COUNT(*) FROM registrations r WHERE r.hearing_id = h.id) AS reg_count
     FROM hearings h
     WHERE h.status IN ('Upcoming','Ongoing') 
        OR h.id IN (SELECT DISTINCT hearing_id FROM registrations WHERE hearing_id IS NOT NULL)
     ORDER BY h.hearing_date DESC, h.title ASC"
)->fetchAll();

// Stakeholders for manual registration
$stakeholders = $pdo->query(
    "SELECT id, full_name, email, organization, status 
     FROM stakeholders 
     WHERE status IN ('Pending','Verified') 
     ORDER BY full_name ASC"
)->fetchAll();

// Fetch registrations with associated hearing and QR credentials
$sql = "SELECT r.*, s.full_name, s.email, s.organization,
               h.id AS hearing_id, h.title AS hearing_title, h.reference_number, h.hearing_date, h.status AS hearing_status,
               COALESCE(q.code_value, q_stk.code_value) AS code_value,
               COALESCE(q.status, q_stk.status) AS qr_status
        FROM registrations r
        JOIN stakeholders s ON s.id = r.stakeholder_id
        LEFT JOIN hearings h ON h.id = r.hearing_id
        LEFT JOIN qr_codes q ON q.registration_id = r.id
        LEFT JOIN qr_codes q_stk ON (q_stk.stakeholder_id = s.id AND q_stk.registration_id IS NULL)
        " . ($hearingFilter > 0 ? "WHERE r.hearing_id = :hid " : "") . "
        ORDER BY 
           CASE WHEN h.id IS NULL THEN 1 ELSE 0 END,
           h.hearing_date DESC,
           h.id DESC,
           r.registered_at DESC,
           r.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($hearingFilter > 0 ? [':hid' => $hearingFilter] : []);
$rows = $stmt->fetchAll();

// Group registrations by Hearing
$hearingsGrouped = [];
foreach ($rows as $r) {
    $hid = (int)($r['hearing_id'] ?? 0);
    if (!isset($hearingsGrouped[$hid])) {
        $hearingsGrouped[$hid] = [
            'hearing_id'       => $hid,
            'hearing_title'    => $r['hearing_title'] ?: ($hid === 0 ? 'General / Unassigned Registrations' : 'Hearing #' . $hid),
            'reference_number' => $r['reference_number'] ?: '',
            'hearing_date'     => $r['hearing_date'] ?? null,
            'hearing_status'   => $r['hearing_status'] ?: '',
            'rows'             => []
        ];
    }
    $hearingsGrouped[$hid]['rows'][] = $r;
}

// If specific hearing filtered but has 0 registrations, display its empty card
if ($hearingFilter > 0 && !isset($hearingsGrouped[$hearingFilter])) {
    $hStmt = $pdo->prepare("SELECT id, title, reference_number, hearing_date, status FROM hearings WHERE id = :id");
    $hStmt->execute([':id' => $hearingFilter]);
    $hInfo = $hStmt->fetch();
    if ($hInfo) {
        $hearingsGrouped[$hearingFilter] = [
            'hearing_id'       => (int)$hInfo['id'],
            'hearing_title'    => $hInfo['title'],
            'reference_number' => $hInfo['reference_number'] ?: '',
            'hearing_date'     => $hInfo['hearing_date'] ?? null,
            'hearing_status'   => $hInfo['status'] ?: '',
            'rows'             => []
        ];
    }
}

// Calculate summary stats
$totalRegistrations = count($rows);
$totalApproved = 0;
$totalPending = 0;
$totalRejected = 0;
foreach ($rows as $r) {
    if ($r['registration_status'] === 'Approved') $totalApproved++;
    elseif ($r['registration_status'] === 'Pending') $totalPending++;
    elseif ($r['registration_status'] === 'Rejected') $totalRejected++;
}

include __DIR__ . '/../../layouts/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL . '/assets/css/lph-complete-modules.css') ?>">
<style>
.table-highlight-flash {
    animation: hearingTableFlash 1.6s ease-out;
}
@keyframes hearingTableFlash {
    0% { box-shadow: 0 0 0 4px rgba(169, 121, 0, 0.7); transform: scale(1.002); }
    50% { box-shadow: 0 0 0 6px rgba(169, 121, 0, 0.3); }
    100% { box-shadow: 0 4px 16px rgba(10, 22, 40, 0.04); transform: scale(1); }
}
.hearing-jump-link {
    transition: all 0.2s ease;
}
.hearing-jump-link:hover {
    background: #fff7d6 !important;
    border-color: #f6df8a !important;
    color: #a97900 !important;
    transform: translateY(-1px);
}
.hearing-header-card {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border-bottom: 2px solid #e2e8f0;
}
.lphx-hearing-avatar {
    width: 40px;
    height: 40px;
    border-radius: 9px;
    background: #fff7d6;
    border: 1px solid #f6df8a;
    color: #a97900;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
/* Match Hearings Schedule Table sizing & button styling */
.lphx-table thead th {
    background: linear-gradient(135deg, #0A1628, #1A3A5C);
    color: #fff;
    border-bottom: 3px solid #a97900;
    font-size: 0.67rem;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    white-space: nowrap;
    padding: 0.75rem;
}
.lphx-table tbody td {
    padding: 0.75rem;
    font-size: 0.78rem;
    vertical-align: middle;
}
.lphx-table .btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.825rem;
    line-height: 1.5;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 31px;
    border-radius: 0.25rem;
}
.lphx-table .btn-group-sm > .btn i {
    font-size: 0.875rem;
}
</style>

<div class="app-wrapper">
    <?php include __DIR__ . '/../../layouts/sidebar.php'; ?>
    <div class="main-content">
        <?php if (file_exists(__DIR__ . '/../../layouts/top_controls.php')): ?>
            <?php include __DIR__ . '/../../layouts/top_controls.php'; ?>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="lphx-head">
            <div>
                <div class="lphx-eyebrow"><i class="bi bi-clipboard-check"></i> Step 3 · Registration</div>
                <h1>Stakeholder Registrations</h1>
                <p>Manage participant registrations per hearing session, approve or reject applications, and issue QR-ready attendance credentials.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-nowrap no-print mt-2 mt-md-0">
                <a href="index.php" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" style="width: 140px; height: 38px; font-size: 0.825rem; font-weight: 650; box-sizing: border-box; flex-shrink: 0;">
                    <i class="bi bi-arrow-left"></i> Stakeholders
                </a>
                <a href="invitations.php" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" style="width: 140px; height: 38px; font-size: 0.825rem; font-weight: 650; box-sizing: border-box; flex-shrink: 0;">
                    <i class="bi bi-envelope-paper"></i> Invitations
                </a>
                <a href="<?= e(APP_URL . '/modules/attendance/index.php') ?>" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" style="width: 175px; height: 38px; font-size: 0.825rem; font-weight: 650; box-sizing: border-box; flex-shrink: 0;">
                    <i class="bi bi-qr-code-scan"></i> Attendance Scanner
                </a>
            </div>
        </div>

        <!-- High-level Summary Stats -->
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="lphx-stat">
                    <i class="bi bi-calendar-range"></i>
                    <div>
                        <strong><?= count($hearingsGrouped) ?></strong>
                        <small>Hearings Listed</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="lphx-stat">
                    <i class="bi bi-people"></i>
                    <div>
                        <strong><?= $totalRegistrations ?></strong>
                        <small>Total Registrations</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="lphx-stat">
                    <i class="bi bi-check2-circle text-success"></i>
                    <div>
                        <strong class="text-success"><?= $totalApproved ?></strong>
                        <small>Approved</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="lphx-stat">
                    <i class="bi bi-hourglass-split text-warning"></i>
                    <div>
                        <strong class="text-warning"><?= $totalPending ?></strong>
                        <small>Pending Review</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Staff Manual Registration Form -->
        <?php if (canManage()): ?>
            <div class="card lphx-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span><i class="bi bi-person-plus text-primary me-1"></i> Quick Staff Registration</span>
                    <button class="btn btn-sm btn-link text-decoration-none p-0 text-muted" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStaffReg" aria-expanded="true">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                </div>
                <div class="collapse show" id="collapseStaffReg">
                    <div class="card-body">
                        <form id="registrationForm" class="row g-2">
                            <?= csrfField() ?>
                            <div class="col-lg-5">
                                <label class="form-label small text-muted mb-1">Stakeholder *</label>
                                <select name="stakeholder_id" class="form-select" required>
                                    <option value="">Select stakeholder...</option>
                                    <?php foreach ($stakeholders as $s): ?>
                                        <option value="<?= (int)$s['id'] ?>">
                                            <?= e($s['full_name']) ?> · <?= e($s['email']) ?><?= !empty($s['organization']) ? ' (' . e($s['organization']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label small text-muted mb-1">Hearing *</label>
                                <select name="hearing_id" class="form-select" required>
                                    <option value="">Select hearing...</option>
                                    <?php foreach ($activeHearings as $h): ?>
                                        <option value="<?= (int)$h['id'] ?>" <?= $hearingFilter === (int)$h['id'] ? 'selected' : '' ?>>
                                            <?= e(($h['reference_number'] ? '[' . $h['reference_number'] . '] ' : '') . $h['title'] . ' · ' . formatDate($h['hearing_date'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label small text-muted mb-1">Attendance Mode</label>
                                <select name="attendance_type" class="form-select">
                                    <option value="On-site">On-site</option>
                                    <option value="Online">Online</option>
                                    <option value="Hybrid">Hybrid</option>
                                </select>
                            </div>
                            <div class="col-lg-1 d-flex align-items-end">
                                <button class="btn btn-primary w-100" title="Register Stakeholder">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Toolbar: Hearing Filter & Search -->
        <div class="card lphx-card mb-4">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <!-- Dropdown Hearing Filter -->
                    <form method="get" class="d-flex align-items-center gap-2 flex-wrap">
                        <label for="hearingFilterSelect" class="form-label mb-0 fw-bold small text-dark">
                            <i class="bi bi-funnel text-primary"></i> Filter Hearing:
                        </label>
                        <select id="hearingFilterSelect" name="hearing_id" class="form-select form-select-sm" style="min-width: 280px;" onchange="this.form.submit()">
                            <option value="0">All Hearings (Show Separate Tables)</option>
                            <?php foreach ($filterHearings as $fh): ?>
                                <option value="<?= (int)$fh['id'] ?>" <?= $hearingFilter === (int)$fh['id'] ? 'selected' : '' ?>>
                                    <?= e($fh['title']) ?> (<?= (int)$fh['reg_count'] ?> registered) · <?= !empty($fh['hearing_date']) ? formatDate($fh['hearing_date']) : 'TBD' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($hearingFilter > 0): ?>
                            <a href="registrations.php" class="btn btn-sm btn-outline-secondary text-nowrap">
                                <i class="bi bi-x-circle"></i> Show All Tables
                            </a>
                        <?php endif; ?>
                    </form>

                    <!-- Instant Search across all tables -->
                    <div class="ms-auto" style="min-width: 260px; max-width: 360px; width: 100%;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="stakeholderSearchInput" class="form-control" placeholder="Search participant, email, or code...">
                        </div>
                    </div>
                </div>

                <!-- Jump pills for quick navigation when multiple tables exist -->
                <?php if ($hearingFilter === 0 && count($hearingsGrouped) > 1): ?>
                    <div class="d-flex align-items-center gap-1 flex-wrap pt-2 mt-2 border-top">
                        <span class="small text-muted me-2"><i class="bi bi-arrow-down-circle"></i> Jump to Hearing:</span>
                        <?php foreach ($hearingsGrouped as $ghid => $grp): ?>
                            <a href="#hearing-section-<?= $ghid ?>" class="badge rounded-pill bg-light text-dark border text-decoration-none py-1 px-2 hearing-jump-link">
                                <?= e($grp['hearing_title']) ?> <span class="text-primary fw-bold ms-1">(<?= count($grp['rows']) ?>)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Separate Tables Depending on Hearing -->
        <?php if (empty($hearingsGrouped)): ?>
            <div class="card lphx-card">
                <div class="card-body py-5 text-center text-muted">
                    <i class="bi bi-person-check fs-1 text-warning d-block mb-2"></i>
                    <h5 class="fw-bold">No Registrations Found</h5>
                    <p class="mb-0 text-secondary">There are currently no stakeholder registrations matching your selected criteria.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($hearingsGrouped as $hid => $group): ?>
                <?php
                    $hCount = count($group['rows']);
                    $hApproved = 0;
                    foreach ($group['rows'] as $gr) {
                        if ($gr['registration_status'] === 'Approved') $hApproved++;
                    }
                ?>
                <div class="card lphx-card mb-4 hearing-table-container" id="hearing-section-<?= $hid ?>">
                    <!-- Hearing Table Header -->
                    <div class="card-header py-3 hearing-header-card d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="lphx-hearing-avatar">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <h5 class="mb-0 fw-bold text-dark" style="font-size: 1.05rem;">
                                        <?= e($group['hearing_title']) ?>
                                    </h5>
                                    <?php if (!empty($group['reference_number'])): ?>
                                        <span class="badge bg-light text-secondary border font-monospace"><?= e($group['reference_number']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($group['hearing_status'])): ?>
                                        <?php
                                            $stClass = match($group['hearing_status']) {
                                                'Upcoming' => 'primary',
                                                'Ongoing' => 'warning text-dark',
                                                'Completed' => 'success',
                                                'Cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>
                                        <span class="badge bg-<?= $stClass ?>"><?= e($group['hearing_status']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted mt-1 d-flex align-items-center gap-3 flex-wrap">
                                    <span>
                                        <i class="bi bi-calendar3 me-1 text-secondary"></i>
                                        <?= !empty($group['hearing_date']) ? formatDate($group['hearing_date']) : 'Date to be determined' ?>
                                    </span>
                                    <span class="text-success">
                                        <i class="bi bi-check-circle me-1"></i><?= $hApproved ?> Approved
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill bg-dark px-3 py-2 hearing-count-badge" data-original-count="<?= $hCount ?>">
                                <i class="bi bi-people-fill me-1"></i> <?= $hCount ?> <?= $hCount === 1 ? 'Participant' : 'Participants' ?>
                            </span>
                            <?php if ($hid > 0): ?>
                                <a href="<?= APP_URL ?>/modules/attendance/index.php?hearing_id=<?= $hid ?>" class="btn btn-sm btn-outline-primary" title="Open QR Attendance Tracker for this hearing">
                                    <i class="bi bi-qr-code-scan me-1"></i> Attendance
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Table for this specific Hearing -->
                    <div class="table-responsive">
                        <table class="table table-hover lphx-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 180px;"><i class="bi bi-hash"></i> Code</th>
                                    <th><i class="bi bi-person"></i> Stakeholder</th>
                                    <th style="width: 140px;"><i class="bi bi-geo-alt"></i> Attendance</th>
                                    <th style="width: 140px;"><i class="bi bi-shield-check"></i> Status</th>
                                    <th style="width: 200px;"><i class="bi bi-qr-code"></i> QR Credential</th>
                                    <th class="text-end no-print" style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($group['rows'])): ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="lphx-empty py-4">
                                                <i class="bi bi-person-x"></i>
                                                No registrations recorded for this hearing.
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($group['rows'] as $r): ?>
                                        <tr class="reg-row">
                                            <td>
                                                <span class="lphx-code"><?= e($r['registration_code'] ?: '—') ?></span>
                                            </td>
                                            <td>
                                                <strong><?= e($r['full_name']) ?></strong>
                                                <div class="small text-muted"><?= e($r['email']) ?></div>
                                                <?php if (!empty($r['organization'])): ?>
                                                    <div class="small text-muted"><i class="bi bi-building me-1"></i><?= e($r['organization']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                    $att = $r['attendance_type'] ?: 'On-site';
                                                    $attIcon = match($att) {
                                                        'On-site' => 'bi-geo-alt-fill text-danger',
                                                        'Online' => 'bi-camera-video-fill text-primary',
                                                        'Hybrid' => 'bi-broadcast text-info',
                                                        'Invited' => 'bi-envelope-check-fill text-secondary',
                                                        default => 'bi-person-check'
                                                    };
                                                ?>
                                                <span class="badge bg-light text-dark border">
                                                    <i class="bi <?= $attIcon ?> me-1"></i><?= e($att) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge text-bg-<?= $r['registration_status'] === 'Approved' ? 'success' : ($r['registration_status'] === 'Rejected' ? 'danger' : ($r['registration_status'] === 'Pending' ? 'warning' : 'secondary')) ?>">
                                                    <?= e($r['registration_status']) ?>
                                                </span>
                                                <?php if (!empty($r['rejection_reason'])): ?>
                                                    <div class="small text-danger mt-1">
                                                        <i class="bi bi-exclamation-circle me-1"></i><?= e($r['rejection_reason']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($r['code_value'])): ?>
                                                    <span class="lphx-code text-primary">
                                                        <i class="bi bi-qr-code me-1"></i><?= e($r['code_value']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">
                                                        <i class="bi bi-clock-history me-1"></i>Issued after approval
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end no-print">
                                                <?php if (canManage()): ?>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-success btn-reg-status" data-id="<?= (int)$r['id'] ?>" data-status="Approved" title="Approve">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger btn-reg-status" data-id="<?= (int)$r['id'] ?>" data-status="Rejected" title="Reject">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary btn-reg-status" data-id="<?= (int)$r['id'] ?>" data-status="Cancelled" title="Cancel">
                                                            <i class="bi bi-slash-circle"></i>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="reg-empty-search text-center py-3 text-muted" style="display: none;">
                                        <td colspan="6" class="py-3">
                                            <i class="bi bi-search me-1"></i>No matching participants found in this hearing.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quick staff registration submit handler
    const form = document.getElementById('registrationForm');
    if (form) {
        form.onsubmit = async function(e) {
            e.preventDefault();
            const r = await appPostForm(APP_URL + '/modules/stakeholders/ajax_registration_create.php', form);
            if (r.success) {
                appToast('success', r.message);
                setTimeout(() => location.reload(), 400);
            } else if (!r.session_expired) {
                Swal.fire('Registration Error', r.message, 'error');
            }
        };
    }

    // Status change buttons (Approve, Reject with reason modal, Cancel)
    document.querySelectorAll('.btn-reg-status').forEach(btn => {
        btn.onclick = async function() {
            let reason = '';
            if (this.dataset.status === 'Rejected') {
                const p = await Swal.fire({
                    title: 'Rejection reason',
                    input: 'textarea',
                    placeholder: 'Enter reason for rejecting this registration...',
                    showCancelButton: true,
                    inputValidator: v => !v ? 'Reason is required' : undefined
                });
                if (!p.isConfirmed) return;
                reason = p.value;
            }

            const fd = new FormData();
            fd.append('csrf_token', document.querySelector('[name=csrf_token]')?.value || '');
            fd.append('id', this.dataset.id);
            fd.append('status', this.dataset.status);
            fd.append('rejection_reason', reason);

            const r = await fetch(APP_URL + '/modules/stakeholders/ajax_registration_status.php', {
                method: 'POST',
                body: fd
            }).then(x => x.json());

            if (r.success) {
                location.reload();
            } else {
                Swal.fire('Update Failed', r.message, 'error');
            }
        };
    });

    // Real-time client-side search across all hearing tables
    const searchInput = document.getElementById('stakeholderSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('.hearing-table-container').forEach(container => {
                let visibleCount = 0;
                container.querySelectorAll('tbody tr.reg-row').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (!q || text.includes(q)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                const emptySearch = container.querySelector('.reg-empty-search');
                if (emptySearch) {
                    emptySearch.style.display = (visibleCount === 0 && q) ? '' : 'none';
                }

                const badge = container.querySelector('.hearing-count-badge');
                if (badge) {
                    if (q) {
                        badge.innerHTML = '<i class="bi bi-funnel me-1"></i> ' + visibleCount + ' match' + (visibleCount === 1 ? '' : 'es');
                    } else {
                        const orig = badge.dataset.originalCount;
                        badge.innerHTML = '<i class="bi bi-people-fill me-1"></i> ' + orig + ' ' + (orig === '1' ? 'Participant' : 'Participants');
                    }
                }
            });
        });
    }

    // Smooth scroll and visual flash for jump-to pills
    document.querySelectorAll('.hearing-jump-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const target = document.querySelector(targetId);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                target.classList.add('table-highlight-flash');
                setTimeout(() => target.classList.remove('table-highlight-flash'), 1700);
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../../layouts/footer.php'; ?>
