<?php
/**
 * modules/priorities/table.php
 * ------------------------------------------------------------------
 * Legislative priority registry table.
 * ------------------------------------------------------------------
 */

declare(strict_types=1);

$pdo = $pdo ?? db();

$stmt = $pdo->query(
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

     WHERE li.deleted_at IS NULL
       AND lit.code IN ('ordinance', 'resolution')

     ORDER BY
        FIELD(
            li.priority_level,
            'Urgent',
            'High',
            'Normal',
            'Low'
        ),
        li.updated_at DESC
     LIMIT 300"
);

$priorityItems = $stmt->fetchAll();

$scoreMap = [
    'Urgent' => 90,
    'High'   => 75,
    'Normal' => 60,
    'Low'    => 40,
];
?>

<div class="table-responsive">
    <table class="table priority-table align-middle mb-0">
        <thead>
            <tr>
                <th>Rank</th>
                <th>Reference</th>
                <th>Type</th>
                <th>Legislative Measure</th>
                <th>Originating Office</th>
                <th>Workflow Status</th>
                <th>Priority</th>
                <th>Initial Weight</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php if (empty($priorityItems)): ?>
                <tr class="priority-empty-source">
                    <td colspan="9">
                        <div class="priority-empty-state">
                            <i class="bi bi-list-stars"></i>

                            <strong>No legislative records available</strong>

                            <span>
                                Ordinances and resolutions from the shared
                                database will appear here for priority
                                evaluation.
                            </span>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($priorityItems as $index => $item): ?>
                <?php
                $searchValue = strtolower(
                    ($item['reference_number'] ?? '') . ' ' .
                    ($item['title'] ?? '') . ' ' .
                    ($item['originating_office'] ?? '')
                );

                $initialScore =
                    $scoreMap[$item['priority_level']] ?? 60;
                ?>

                <tr
                    class="priority-data-row"
                    data-search="<?= e($searchValue) ?>"
                    data-type="<?= e($item['item_type_code']) ?>"
                    data-level="<?= e($item['priority_level']) ?>"
                    data-status="<?= e($item['current_status']) ?>"
                >
                    <td>
                        <span class="priority-rank">
                            <?= $index + 1 ?>
                        </span>
                    </td>

                    <td>
                        <span class="priority-reference">
                            <?= e($item['reference_number']) ?>
                        </span>
                    </td>

                    <td>
                        <span
                            class="priority-type-badge
                            <?= $item['item_type_code'] === 'ordinance'
                                ? 'ordinance'
                                : 'resolution'
                            ?>"
                        >
                            <?= e($item['item_type_name']) ?>
                        </span>
                    </td>

                    <td>
                        <div class="priority-title-cell">
                            <strong><?= e($item['title']) ?></strong>

                            <small>
                                Updated
                                <?= e(formatDateTime($item['updated_at'])) ?>
                            </small>
                        </div>
                    </td>

                    <td>
                        <?= e(
                            $item['originating_office']
                            ?: 'Not assigned'
                        ) ?>
                    </td>

                    <td>
                        <span class="priority-status-badge">
                            <?= e($item['current_status']) ?>
                        </span>
                    </td>

                    <td>
                        <span
                            class="priority-level-badge
                                   <?= e(strtolower(
                                       $item['priority_level']
                                   )) ?>"
                        >
                            <?= e($item['priority_level']) ?>
                        </span>
                    </td>

                    <td>
                        <div class="priority-score-cell">
                            <strong><?= $initialScore ?></strong>

                            <div class="priority-mini-meter">
                                <span
                                    style="width:<?= $initialScore ?>%"
                                ></span>
                            </div>
                        </div>
                    </td>

                    <td>
                        <div class="priority-row-actions">
                            <a
                                href="view.php?id=<?= (int)$item['id'] ?>"
                                class="priority-action-button"
                                title="Open priority details"
                            >
                                <i class="bi bi-eye"></i>
                            </a>

                            <button
                                type="button"
                                class="priority-action-button"
                                data-set-priority="<?= (int)$item['id'] ?>"
                                data-reference="<?= e($item['reference_number']) ?>"
                                data-title="<?= e($item['title']) ?>"
                                title="Evaluate priority"
                            >
                                <i class="bi bi-list-stars"></i>
                            </button>

                            <button
                                type="button"
                                class="priority-action-button"
                                data-view-history="<?= (int)$item['id'] ?>"
                                title="View priority history"
                            >
                                <i class="bi bi-clock-history"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <tr
                id="priorityNoFilterResult"
                class="d-none"
            >
                <td colspan="9">
                    <div class="priority-empty-state compact">
                        <i class="bi bi-search"></i>
                        <strong>No matching priority records</strong>
                        <span>
                            Adjust the search or selected filters.
                        </span>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="priority-table-footer">
    <span id="priorityRecordCount">
        Showing <?= count($priorityItems) ?> legislative record(s)
    </span>

    <span>
        Initial weight is based on the current priority classification.
    </span>
</div>
