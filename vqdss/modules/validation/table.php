<?php
declare(strict_types=1);

$candidateRows = $candidateRows ?? [];

$validationReadyStatuses = [
    'Approved',
    'Enacted',
    'Committee Endorsed',
    'For Approval',
];
?>

<div class="table-responsive">
    <table class="table validation-table align-middle mb-0">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Type</th>
                <th>Legislative Measure</th>
                <th>Originating Office</th>
                <th>Priority</th>
                <th>Workflow Status</th>
                <th>Validation Readiness</th>
                <th>Last Updated</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($candidateRows as $row): ?>
                <?php
                $search = strtolower(
                    ($row['reference_number'] ?? '') . ' ' .
                    ($row['title'] ?? '') . ' ' .
                    ($row['originating_office'] ?? '')
                );

                $isReady = in_array(
                    $row['current_status'],
                    $validationReadyStatuses,
                    true
                );
                ?>

                <tr
                    class="validation-data-row"
                    data-search="<?= e($search) ?>"
                    data-type="<?= e($row['item_type_code']) ?>"
                    data-priority="<?= e($row['priority_level']) ?>"
                    data-status="<?= e($row['current_status']) ?>"
                >
                    <td>
                        <span class="validation-reference">
                            <?= e($row['reference_number']) ?>
                        </span>
                    </td>

                    <td>
                        <span
                            class="validation-type-badge
                            <?= $row['item_type_code'] === 'ordinance'
                                ? 'ordinance'
                                : 'resolution'
                            ?>"
                        >
                            <?= e($row['item_type_name']) ?>
                        </span>
                    </td>

                    <td>
                        <div class="validation-title-cell">
                            <strong><?= e($row['title']) ?></strong>

                            <small>
                                Available for result validation and reporting
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
                            class="validation-priority-badge
                            <?= e(strtolower(
                                $row['priority_level']
                            )) ?>"
                        >
                            <?= e($row['priority_level']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="validation-status-badge">
                            <?= e($row['current_status']) ?>
                        </span>
                    </td>

                    <td>
                        <span
                            class="validation-readiness-badge
                            <?= $isReady ? 'ready' : 'pending' ?>"
                        >
                            <i
                                class="bi
                                <?= $isReady
                                    ? 'bi-check-circle'
                                    : 'bi-clock'
                                ?>"
                            ></i>

                            <?= $isReady
                                ? 'Ready'
                                : 'Pending'
                            ?>
                        </span>
                    </td>

                    <td>
                        <?= e(formatDateTime(
                            $row['updated_at']
                        )) ?>
                    </td>

                    <td>
                        <div class="validation-row-actions">
                            <a
                                href="view.php?id=<?= (int)$row['id'] ?>"
                                class="validation-action-button"
                                title="View validation details"
                            >
                                <i class="bi bi-eye"></i>
                            </a>

                            <button
                                type="button"
                                class="validation-action-button"
                                data-create-validation="<?= (int)$row['id'] ?>"
                                data-reference="<?= e($row['reference_number']) ?>"
                                data-title="<?= e($row['title']) ?>"
                                title="Create validation review"
                            >
                                <i class="bi bi-patch-check"></i>
                            </button>

                            <button
                                type="button"
                                class="validation-action-button"
                                data-validation-history="<?= (int)$row['id'] ?>"
                                title="View validation history"
                            >
                                <i class="bi bi-clock-history"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$candidateRows): ?>
                <tr>
                    <td colspan="9">
                        <div class="validation-empty-state">
                            <i class="bi bi-inboxes"></i>

                            <strong>
                                No validation candidates available
                            </strong>

                            <span>
                                Shared ordinance and resolution records
                                will appear here.
                            </span>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <tr
                id="validationNoFilterResult"
                class="d-none"
            >
                <td colspan="9">
                    <div class="validation-empty-state compact">
                        <i class="bi bi-search"></i>

                        <strong>
                            No matching validation candidates
                        </strong>

                        <span>
                            Adjust the search or selected filters.
                        </span>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="validation-table-footer">
    <span id="validationRecordCount">
        Showing <?= count($candidateRows) ?>
        candidate record(s)
    </span>

    <span>
        Validation records will be stored during backend implementation.
    </span>
</div>
