<?php
/**
 * modules/priorities/report.php
 * ------------------------------------------------------------------
 * Legislative priority summary report.
 * ------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

requireRole([
    ROLE_ADMIN,
    ROLE_STAFF,
    ROLE_COMMITTEE,
]);

$pageTitle  = 'Priority Setting Report';
$activeMenu = 'priorities';

$extraCss = [
    appUrl('assets/css/priorities.css'),
];

$pdo = db();

$byPriority = $pdo->query(
    "SELECT
        li.priority_level,
        COUNT(*) AS total

     FROM legislative_items li
     INNER JOIN legislative_item_types lit
        ON lit.id = li.item_type_id

     WHERE li.deleted_at IS NULL
       AND lit.code IN ('ordinance', 'resolution')

     GROUP BY li.priority_level

     ORDER BY FIELD(
        li.priority_level,
        'Urgent',
        'High',
        'Normal',
        'Low'
     )"
)->fetchAll();

$byType = $pdo->query(
    "SELECT
        lit.name,
        COUNT(*) AS total

     FROM legislative_items li
     INNER JOIN legislative_item_types lit
        ON lit.id = li.item_type_id

     WHERE li.deleted_at IS NULL
       AND lit.code IN ('ordinance', 'resolution')

     GROUP BY lit.id, lit.name
     ORDER BY lit.name"
)->fetchAll();

$total = array_sum(array_map(
    'intval',
    array_column($byPriority, 'total')
));

include __DIR__ . '/../../layouts/header.php';
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../../layouts/sidebar.php'; ?>

    <main class="main-content">

        <section class="priority-page-header">
            <div>
                <a
                    href="index.php"
                    class="priority-back-link"
                >
                    <i class="bi bi-arrow-left"></i>
                    Back to Priority Registry
                </a>

                <div class="priority-eyebrow">
                    <i class="bi bi-bar-chart"></i>
                    Legislative Agenda Analytics
                </div>

                <h1>Priority Setting Report</h1>

                <p>
                    Summary of legislative records by priority
                    classification and record type.
                </p>
            </div>

            <div class="priority-header-actions">
                <button
                    type="button"
                    class="btn btn-primary"
                    onclick="window.print()"
                >
                    <i class="bi bi-printer"></i>
                    Print Report
                </button>
            </div>
        </section>

        <section class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="priority-summary-card">
                    <span class="summary-icon">
                        <i class="bi bi-files"></i>
                    </span>

                    <strong><?= (int)$total ?></strong>
                    <span>Total Priority Records</span>
                </div>
            </div>

            <?php foreach ($byType as $row): ?>
                <div class="col-md-4">
                    <div class="priority-summary-card">
                        <span class="summary-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </span>

                        <strong><?= (int)$row['total'] ?></strong>
                        <span><?= e($row['name']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="row g-4">
            <div class="col-xl-7">
                <section class="priority-detail-panel h-100">
                    <div class="priority-panel-heading">
                        <h2>
                            <i class="bi bi-bar-chart"></i>
                            Records by Priority Level
                        </h2>
                    </div>

                    <div class="priority-report-chart">
                        <canvas id="priorityLevelChart"></canvas>
                    </div>
                </section>
            </div>

            <div class="col-xl-5">
                <section class="priority-detail-panel h-100">
                    <div class="priority-panel-heading">
                        <h2>
                            <i class="bi bi-list-check"></i>
                            Priority Summary
                        </h2>
                    </div>

                    <div class="priority-report-list">
                        <?php if (empty($byPriority)): ?>
                            <div class="priority-empty-state compact">
                                <i class="bi bi-bar-chart"></i>
                                <strong>No priority data available</strong>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($byPriority as $row): ?>
                            <div class="priority-report-list-item">
                                <span>
                                    <?= e($row['priority_level']) ?>
                                </span>

                                <strong>
                                    <?= (int)$row['total'] ?>
                                </strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('priorityLevelChart');

    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: <?= json_encode(
                array_column($byPriority, 'priority_level'),
                JSON_UNESCAPED_UNICODE
            ) ?>,
            datasets: [{
                label: 'Records',
                data: <?= json_encode(array_map(
                    'intval',
                    array_column($byPriority, 'total')
                )) ?>,
                backgroundColor: [
                    '#ef4444',
                    '#f59e0b',
                    '#1d6fb8',
                    '#64748b'
                ],
                borderRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../../layouts/footer.php'; ?>
