<?php
/**
 * modules/priorities/view.php
 * ------------------------------------------------------------------
 * Legislative priority detail page.
 * ------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

requireRole([
    ROLE_ADMIN,
    ROLE_STAFF,
    ROLE_COMMITTEE,
]);

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    redirect(appUrl('modules/priorities/index.php'));
}

$pdo = db();

$stmt = $pdo->prepare(
    "SELECT
        li.id,
        li.reference_number,
        li.title,
        li.current_status,
        li.priority_level,
        li.created_at,
        li.updated_at,
        lit.name AS item_type_name,
        lit.code AS item_type_code,
        o.name AS originating_office

     FROM legislative_items li
     INNER JOIN legislative_item_types lit
        ON lit.id = li.item_type_id
     LEFT JOIN offices o
        ON o.id = li.originating_office_id

     WHERE li.id = :id
       AND li.deleted_at IS NULL
       AND lit.code IN ('ordinance', 'resolution')
     LIMIT 1"
);

$stmt->execute([
    ':id' => $id,
]);

$item = $stmt->fetch();

if (!$item) {
    setFlash(
        'warning',
        'Legislative record not found.'
    );

    redirect(appUrl('modules/priorities/index.php'));
}

$scoreMap = [
    'Urgent' => 90,
    'High'   => 75,
    'Normal' => 60,
    'Low'    => 40,
];

$initialScore = $scoreMap[$item['priority_level']] ?? 60;

$pageTitle  = 'Priority ' . $item['reference_number'];
$activeMenu = 'priorities';

$extraCss = [
    appUrl('assets/css/priorities.css'),
];

$extraJs = [
    appUrl('assets/js/priorities.js'),
];

include __DIR__ . '/../../layouts/header.php';
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../../layouts/sidebar.php'; ?>

    <main class="main-content">

        <section class="priority-detail-header">
            <div>
                <a
                    href="index.php"
                    class="priority-back-link"
                >
                    <i class="bi bi-arrow-left"></i>
                    Back to Priority Registry
                </a>

                <div class="priority-detail-reference">
                    <?= e($item['reference_number']) ?>
                </div>

                <h1><?= e($item['title']) ?></h1>

                <div class="priority-detail-meta">
                    <span>
                        <i class="bi bi-file-earmark-text"></i>
                        <?= e($item['item_type_name']) ?>
                    </span>

                    <span>
                        <i class="bi bi-building"></i>
                        <?= e(
                            $item['originating_office']
                            ?: 'Originating office not assigned'
                        ) ?>
                    </span>
                </div>
            </div>

            <div class="priority-detail-actions">
                <span
                    class="priority-level-badge
                           <?= e(strtolower(
                               $item['priority_level']
                           )) ?>"
                >
                    <?= e($item['priority_level']) ?>
                </span>

                <span class="priority-status-badge">
                    <?= e($item['current_status']) ?>
                </span>

                <button
                    type="button"
                    class="btn btn-primary"
                    data-set-priority="<?= (int)$item['id'] ?>"
                    data-reference="<?= e($item['reference_number']) ?>"
                    data-title="<?= e($item['title']) ?>"
                >
                    <i class="bi bi-list-stars"></i>
                    Evaluate Priority
                </button>
            </div>
        </section>

        <div class="row g-4 mb-4">
            <div class="col-xl-4">
                <section class="priority-detail-panel h-100">
                    <div class="priority-panel-heading">
                        <h2>
                            <i class="bi bi-speedometer2"></i>
                            Initial Priority Weight
                        </h2>
                    </div>

                    <div class="priority-score-overview">
                        <div
                            class="priority-score-ring"
                            style="--score:<?= $initialScore ?>"
                        >
                            <strong><?= $initialScore ?></strong>
                            <span>out of 100</span>
                        </div>

                        <div class="priority-score-copy">
                            <strong>
                                <?= e($item['priority_level']) ?>
                                Classification
                            </strong>

                            <span>
                                This initial weight is derived from the
                                current priority level. The final evaluation
                                score will be stored once the backend is
                                connected.
                            </span>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-8">
                <section class="priority-detail-panel h-100">
                    <div class="priority-panel-heading">
                        <h2>
                            <i class="bi bi-info-circle"></i>
                            Legislative Record Information
                        </h2>
                    </div>

                    <div class="priority-information-grid">
                        <div>
                            <span>Reference Number</span>
                            <strong>
                                <?= e($item['reference_number']) ?>
                            </strong>
                        </div>

                        <div>
                            <span>Record Type</span>
                            <strong>
                                <?= e($item['item_type_name']) ?>
                            </strong>
                        </div>

                        <div>
                            <span>Originating Office</span>
                            <strong>
                                <?= e(
                                    $item['originating_office']
                                    ?: 'Not assigned'
                                ) ?>
                            </strong>
                        </div>

                        <div>
                            <span>Workflow Status</span>
                            <strong>
                                <?= e($item['current_status']) ?>
                            </strong>
                        </div>

                        <div>
                            <span>Current Priority</span>
                            <strong>
                                <?= e($item['priority_level']) ?>
                            </strong>
                        </div>

                        <div>
                            <span>Last Updated</span>
                            <strong>
                                <?= e(formatDateTime($item['updated_at'])) ?>
                            </strong>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <section class="priority-detail-panel mb-4">
                    <div class="priority-panel-heading">
                        <h2>
                            <i class="bi bi-clipboard-data"></i>
                            Evaluation Criteria
                        </h2>
                    </div>

                    <div class="priority-detail-empty">
                        <i class="bi bi-bar-chart-steps"></i>

                        <strong>
                            No detailed evaluation saved yet
                        </strong>

                        <span>
                            Urgency, public impact, legal readiness,
                            feasibility, and strategic alignment scores
                            will appear here.
                        </span>
                    </div>
                </section>

                <section class="priority-detail-panel mb-4">
                    <div class="priority-panel-heading">
                        <h2>
                            <i class="bi bi-calendar-check"></i>
                            Agenda Placement and Schedule
                        </h2>
                    </div>

                    <div class="priority-detail-empty">
                        <i class="bi bi-calendar2-plus"></i>

                        <strong>No agenda placement recorded</strong>

                        <span>
                            Proposed rank, legislative cycle, target date,
                            and scheduling considerations will appear here.
                        </span>
                    </div>
                </section>

                <section class="priority-detail-panel">
                    <div class="priority-panel-heading">
                        <h2>
                            <i class="bi bi-clock-history"></i>
                            Priority History
                        </h2>
                    </div>

                    <div class="priority-history-list">
                        <div class="priority-history-item">
                            <span>
                                <i class="bi bi-file-earmark-text"></i>
                            </span>

                            <div>
                                <strong>
                                    Legislative record created
                                </strong>

                                <small>
                                    <?= e(formatDateTime($item['created_at'])) ?>
                                </small>
                            </div>
                        </div>

                        <div class="priority-history-item current">
                            <span>
                                <i class="bi bi-list-stars"></i>
                            </span>

                            <div>
                                <strong>
                                    Current priority:
                                    <?= e($item['priority_level']) ?>
                                </strong>

                                <small>
                                    Last record update:
                                    <?= e(formatDateTime($item['updated_at'])) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-4">
                <section class="priority-detail-panel mb-4">
                    <div class="priority-panel-heading">
                        <h2>
                            <i class="bi bi-patch-check"></i>
                            Recommendation
                        </h2>
                    </div>

                    <div class="priority-side-empty">
                        <i class="bi bi-clipboard-check"></i>

                        <span>
                            The agenda recommendation and justification
                            have not yet been recorded.
                        </span>

                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm"
                            data-set-priority="<?= (int)$item['id'] ?>"
                            data-reference="<?= e($item['reference_number']) ?>"
                            data-title="<?= e($item['title']) ?>"
                        >
                            Record Recommendation
                        </button>
                    </div>
                </section>

                <section class="priority-detail-panel mb-4">
                    <div class="priority-panel-heading">
                        <h2>
                            <i class="bi bi-people"></i>
                            Responsible Unit
                        </h2>
                    </div>

                    <div class="priority-side-empty">
                        <i class="bi bi-building-add"></i>

                        <span>
                            No committee, office, or responsible unit has
                            been assigned for agenda coordination.
                        </span>
                    </div>
                </section>

                <section class="priority-detail-panel">
                    <div class="priority-panel-heading">
                        <h2>
                            <i class="bi bi-paperclip"></i>
                            Supporting Documents
                        </h2>
                    </div>

                    <div class="priority-side-empty">
                        <i class="bi bi-file-earmark-arrow-up"></i>

                        <span>
                            No evaluation sheet, endorsement, or supporting
                            document has been uploaded.
                        </span>
                    </div>
                </section>
            </div>
        </div>

<?php include __DIR__ . '/../../layouts/footer.php'; ?>
