<?php
declare(strict_types=1);

$candidateRows = $candidateRows ?? [];

$readyStatuses = [
    'Committee Endorsed',
    'For Approval',
    'Approved',
    'Enacted',
];
?>

<div class="table-responsive">
    <table class="table tally-table align-middle mb-0">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Type</th>
                <th>Legislative Measure</th>
                <th>Originating Office</th>
                <th>Priority</th>
                <th>Workflow Status</th>
                <th>Tally Readiness</th>
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
                    $readyStatuses,
                    true
                );
                ?>

                <tr
                    class="tally-data-row"
                    data-search="<?= e($search) ?>"
                    data-type="<?= e($row['item_type_code']) ?>"
                    data-priority="<?= e($row['priority_level']) ?>"
                    data-status="<?= e($row['current_status']) ?>"
                >
                    <td>
                        <span class="tally-reference">
                            <?= e($row['reference_number']) ?>
                        </span>
                    </td>

                    <td>
                        <span
                            class="tally-type-badge
                            <?= $row['item_type_code'] === 'ordinance'
                                ? 'ordinance'
                                : 'resolution'
                            ?>"
                        >
                            <?= e($row['item_type_name']) ?>
                        </span>
                    </td>

                    <td>
                        <div class="tally-title-cell">
                            <strong><?= e($row['title']) ?></strong>

                            <small>
                                Available for tally and result preparation
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
                            class="tally-priority-badge
                            <?= e(strtolower(
                                $row['priority_level']
                            )) ?>"
                        >
                            <?= e($row['priority_level']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="tally-status-badge">
                            <?= e($row['current_status']) ?>
                        </span>
                    </td>

                    <td>
                        <span
                            class="tally-readiness-badge
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
                        <div class="tally-row-actions">
                            <a
                                href="view.php?id=<?= (int)$row['id'] ?>"
                                class="tally-action-button"
                                title="View tally details"
                            >
                                <i class="bi bi-eye"></i>
                            </a>

                            <button
                                type="button"
                                class="tally-action-button"
                                data-create-tally="<?= (int)$row['id'] ?>"
                                data-reference="<?= e($row['reference_number']) ?>"
                                data-title="<?= e($row['title']) ?>"
                                title="Create vote tally"
                            >
                                <i class="bi bi-bar-chart"></i>
                            </button>

                            <button
                                type="button"
                                class="tally-action-button"
                                data-tally-history="<?= (int)$row['id'] ?>"
                                title="View tally history"
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
                        <div class="tally-empty-state">
                            <i class="bi bi-inboxes"></i>

                            <strong>No tally candidates available</strong>

                            <span>
                                Shared ordinance and resolution records
                                will appear here.
                            </span>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <tr
                id="tallyNoFilterResult"
                class="d-none"
            >
                <td colspan="9">
                    <div class="tally-empty-state compact">
                        <i class="bi bi-search"></i>

                        <strong>No matching tally candidates</strong>

                        <span>
                            Adjust the search or selected filters.
                        </span>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="tally-table-footer">
    <span id="tallyRecordCount">
        Showing <?= count($candidateRows) ?>
        candidate record(s)
    </span>

    <span>
        Vote tallies will be stored during backend implementation.
    </span>
</div>
