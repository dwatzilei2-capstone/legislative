<?php
declare(strict_types=1);

$candidateRows = $candidateRows ?? [];

$readinessStatuses = [
    'Under Review',
    'Committee Endorsed',
    'For Approval',
    'Approved',
    'Enacted',
    'For Publication',
    'Published',
    'Under Implementation',
];
?>

<div class="table-responsive">
    <table class="table sync-table align-middle mb-0">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Type</th>
                <th>Legislative Measure</th>
                <th>Originating Office</th>
                <th>Priority</th>
                <th>Workflow Status</th>
                <th>Sync Readiness</th>
                <th>Last Updated</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php if (empty($candidateRows)): ?>
                <tr>
                    <td colspan="9">
                        <div class="sync-empty-state">
                            <i class="bi bi-arrow-left-right"></i>

                            <strong>No synchronization candidates available</strong>

                            <span>
                                Shared legislative records will appear here.
                            </span>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($candidateRows as $row): ?>
                <?php
                $search = strtolower(
                    ($row['reference_number'] ?? '') . ' ' .
                    ($row['title'] ?? '') . ' ' .
                    ($row['originating_office'] ?? '')
                );

                $isReady = in_array(
                    $row['current_status'],
                    $readinessStatuses,
                    true
                );
                ?>

                <tr
                    class="sync-data-row"
                    data-search="<?= e($search) ?>"
                    data-type="<?= e($row['item_type_code']) ?>"
                    data-priority="<?= e($row['priority_level']) ?>"
                    data-status="<?= e($row['current_status']) ?>"
                >
                    <td>
                        <span class="sync-reference">
                            <?= e($row['reference_number']) ?>
                        </span>
                    </td>

                    <td>
                        <span
                            class="sync-type-badge
                            <?= $row['item_type_code'] === 'ordinance'
                                ? 'ordinance'
                                : 'resolution'
                            ?>"
                        >
                            <?= e($row['item_type_name']) ?>
                        </span>
                    </td>

                    <td>
                        <div class="sync-title-cell">
                            <strong><?= e($row['title']) ?></strong>

                            <small>
                                Available for executive-legislative alignment
                            </small>
                        </div>
                    </td>

                    <td>
                        <?= e(
                            $row['originating_office']
                            ?: 'Not assigned'
                        ) ?>
                    </td>

                    <td>
                        <span
                            class="sync-priority-badge
                            <?= e(strtolower(
                                $row['priority_level']
                            )) ?>"
                        >
                            <?= e($row['priority_level']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="sync-status-badge">
                            <?= e($row['current_status']) ?>
                        </span>
                    </td>

                    <td>
                        <span
                            class="sync-readiness-badge
                            <?= $isReady ? 'ready' : 'pending' ?>"
                        >
                            <i
                                class="bi
                                <?= $isReady
                                    ? 'bi-check-circle'
                                    : 'bi-clock'
                                ?>"
                            ></i>

                            <?= $isReady ? 'Ready' : 'Pending' ?>
                        </span>
                    </td>

                    <td>
                        <?= e(formatDateTime(
                            $row['updated_at']
                        )) ?>
                    </td>

                    <td>
                        <div class="sync-row-actions">
                            <a
                                href="view.php?id=<?= (int)$row['id'] ?>"
                                class="sync-action-button"
                                title="View synchronization details"
                            >
                                <i class="bi bi-eye"></i>
                            </a>

                            <button
                                type="button"
                                class="sync-action-button"
                                data-create-sync="<?= (int)$row['id'] ?>"
                                data-reference="<?= e($row['reference_number']) ?>"
                                data-title="<?= e($row['title']) ?>"
                                title="Create synchronization record"
                            >
                                <i class="bi bi-arrow-left-right"></i>
                            </button>

                            <button
                                type="button"
                                class="sync-action-button"
                                data-sync-history="<?= (int)$row['id'] ?>"
                                title="View synchronization history"
                            >
                                <i class="bi bi-clock-history"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <tr
                id="syncNoFilterResult"
                class="d-none"
            >
                <td colspan="9">
                    <div class="sync-empty-state compact">
                        <i class="bi bi-search"></i>
                        <strong>No matching synchronization records</strong>
                        <span>Adjust the search or selected filters.</span>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="sync-table-footer">
    <span id="syncRecordCount">
        Showing <?= count($candidateRows) ?> candidate record(s)
    </span>

    <span>
        Synchronization records will be stored during backend implementation.
    </span>
</div>
