<?php
declare(strict_types=1);

/**
 * pages/ai_health.php
 * Ollama AI Health Diagnostics and Test Bench for VQDSS.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/vqdss_operational_helpers.php';
requirePermission('vqd.system_health.view');

$pdo = db();
$pageTitle = 'Ollama AI Health Diagnostics';
$activeMenu = 'ai_health';
$extraCss = [
    appUrl('assets/css/vqdss-operational.css'),
    appUrl('assets/css/vqdss-final-ops.css'),
];

$ai = vqdssAiService();
$health = $ai->healthCheck();

$testOutput = null;
$testError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_type'])) {
    requireCsrf();
    $testType = clean($_POST['test_type']);
    $title = trim((string)($_POST['test_title'] ?? 'Ordinance Regulating Traffic in City of Manila'));

    try {
        if ($testType === 'decision') {
            $context = [
                'legislative_title' => $title,
                'motion_text' => 'Moved for 3rd and final reading: ' . $title,
                'legislative_reference' => 'PO-2026-0088',
                'decision_record_number' => 'ORD-2026-0042',
                'official_outcome' => 'Approved',
                'decision_threshold' => 'Simple Majority',
                'affirmative_votes' => 32,
                'negative_votes' => 2,
                'abstain_votes' => 1,
                'required_votes' => 18,
            ];
            $res = $ai->draftDecisionStatement($context);
            if ($res['success']) $testOutput = $res;
            else $testError = $res['error'] ?? 'Draft generation failed.';
        } elseif ($testType === 'actions') {
            $context = [
                'legislative_title' => $title,
                'decision_statement' => 'An Ordinance Enacting Enhanced Digital Traffic Enforcement within the City of Manila.',
                'official_outcome' => 'Approved',
                'reference_number' => 'ORD-2026-0042',
            ];
            $res = $ai->suggestImplementationActions($context);
            if ($res['success']) $testOutput = $res;
            else $testError = $res['error'] ?? 'Action suggestions failed.';
        } elseif ($testType === 'threshold') {
            $context = [
                'title' => $title,
                'quorum_status' => 'Quorum Met (35/38 Present)',
                'eligible_members' => 38,
            ];
            $res = $ai->adviseVotingThreshold($context);
            if ($res['success']) $testOutput = $res;
            else $testError = $res['error'] ?? 'Threshold advice failed.';
        }
    } catch (Throwable $e) {
        $testError = $e->getMessage();
    }
}

// AI Audit History Counts
$totalAiRecords = (int)$pdo->query('SELECT COUNT(*) FROM vqd_ai_analysis')->fetchColumn();
$recentAi = $pdo->query(
    'SELECT a.*, u.full_name
     FROM vqd_ai_analysis a
     LEFT JOIN users u ON u.id = a.created_by
     ORDER BY a.id DESC LIMIT 10'
)->fetchAll();

include __DIR__ . '/../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__ . '/../layouts/sidebar.php'; ?><main class="main-content">

<div class="vqdf-head mb-4">
  <div>
    <div class="vqdf-eyebrow"><i class="bi bi-robot"></i> Subsystem #5 · Local Intelligent Parliamentary Operations</div>
    <h1>Ollama AI Health & Diagnostics</h1>
    <p>Real-time telemetry, model readiness verification, and test bench for local Ollama AI copilot services.</p>
  </div>
  <div>
    <a class="btn btn-outline-secondary btn-sm" href="system_health.php"><i class="bi bi-arrow-left"></i> System Health</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="vqdf-stat">
      <i class="bi bi-hdd-network text-primary"></i>
      <div>
        <strong><?= $health['available'] ? 'ONLINE' : 'OFFLINE' ?></strong>
        <small>Daemon Status</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="vqdf-stat">
      <i class="bi bi-cpu text-warning"></i>
      <div>
        <strong><?= e(OLLAMA_MODEL) ?></strong>
        <small>Configured Model</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="vqdf-stat">
      <i class="bi bi-patch-check text-success"></i>
      <div>
        <strong><?= $health['model_installed'] ? 'READY' : 'NOT FOUND' ?></strong>
        <small>Model Verification</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="vqdf-stat">
      <i class="bi bi-journal-code text-info"></i>
      <div>
        <strong><?= $totalAiRecords ?></strong>
        <small>AI Analysis Records</small>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card vqdf-card">
      <div class="card-header">Service Telemetry & Connectivity</div>
      <div class="card-body">
        <table class="table table-sm vqdf-table mb-3">
          <tbody>
            <tr><th>Status Message</th><td><?= e($health['message']) ?></td></tr>
            <tr><th>Base Endpoint</th><td><code><?= e($health['base_url'] ?? OLLAMA_BASE_URL) ?></code></td></tr>
            <tr><th>Configured Model</th><td><span class="badge bg-dark"><?= e(OLLAMA_MODEL) ?></span></td></tr>
            <tr><th>Installed Models</th>
              <td>
                <?php if (!empty($health['models'])): ?>
                  <?php foreach ($health['models'] as $m): ?>
                    <span class="badge bg-secondary me-1"><?= e($m) ?></span>
                  <?php endforeach; ?>
                <?php else: ?>
                  <span class="text-muted">None detected</span>
                <?php endif; ?>
              </td>
            </tr>
            <tr><th>Privacy & Data Law</th><td><span class="badge bg-success"><i class="bi bi-shield-check"></i> On-Premise (RA 10173 DPA Compliant)</span></td></tr>
          </tbody>
        </table>
        <div class="small text-muted">
          All inference occurs within your local workstation or on-premise server environment. No legislative votes or council resolutions leave this infrastructure.
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card vqdf-card">
      <div class="card-header"><i class="bi bi-stars text-warning me-1"></i> Interactive Parliamentary AI Test Bench</div>
      <div class="card-body">
        <form method="POST" class="mb-3">
          <?= csrfField() ?>
          <div class="row g-2 mb-2">
            <div class="col-md-8">
              <label class="form-label small">Sample Legislative Measure</label>
              <input class="form-control form-control-sm" name="test_title" value="An Ordinance Enacting Comprehensive Traffic Reforms in the City of Manila" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small">Test Copilot Action</label>
              <select class="form-select form-select-sm" name="test_type">
                <option value="decision">Draft Decision & Legal Basis</option>
                <option value="actions">Suggest Implementation Plan</option>
                <option value="threshold">Advise Statutory Threshold</option>
              </select>
            </div>
          </div>
          <button type="submit" class="btn btn-sm btn-primary" <?= !$health['available'] ? 'disabled' : '' ?>>
            <i class="bi bi-play-circle me-1"></i> Run Test Inference
          </button>
        </form>

        <?php if ($testError): ?>
          <div class="alert alert-danger py-2 small mb-0"><i class="bi bi-exclamation-octagon me-1"></i><strong>Inference Error:</strong> <?= e($testError) ?></div>
        <?php endif; ?>

        <?php if ($testOutput): ?>
          <div class="border rounded p-3 bg-light">
            <h6 class="text-success mb-2"><i class="bi bi-check-circle me-1"></i> Live Inference Output:</h6>
            <pre class="small bg-white p-2 border rounded mb-0" style="max-height: 240px; overflow-y: auto;"><code><?= e(json_encode($testOutput, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card vqdf-card">
  <div class="card-header">Recent AI Operations Log (Audit Trail: <code>vqd_ai_analysis</code>)</div>
  <div class="table-responsive">
    <table class="table vqdf-table mb-0">
      <thead>
        <tr>
          <th>ID</th>
          <th>Module</th>
          <th>Reference</th>
          <th>Analysis Type</th>
          <th>Model</th>
          <th>Initiator</th>
          <th>Timestamp</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$recentAi): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No AI transactions recorded yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($recentAi as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><span class="badge bg-secondary"><?= e($r['module']) ?></span></td>
            <td><strong><?= e($r['reference_number'] ?: 'Ref #' . $r['reference_id']) ?></strong></td>
            <td><?= e($r['analysis_type']) ?></td>
            <td><span class="badge bg-dark"><?= e($r['model_used']) ?></span></td>
            <td><?= e($r['full_name'] ?: 'System') ?></td>
            <td><?= formatDateTime($r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</main></div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
