<?php
declare(strict_types=1);

$candidateRows = $candidateRows ?? [];

$votingReadyStatuses = [
    'Committee Endorsed',
    'For Approval',
    'Approved',
    'Enacted',
];
?>

<div class="table-responsive">
    <table class="table voting-table align-middle mb-0">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Type</th>
                <th>Legislative Measure</th>
                <th>Originating Office</th>
                <th>Priority</th>
                <th>Workflow Status</th>
                <th>Voting Readiness</th>
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
                    $votingReadyStatuses,
                    true
                );
                ?>

                <tr
                    class="voting-data-row"
                    data-search="<?= e($search) ?>"
                    data-type="<?= e($row['item_type_code']) ?>"
                    data-priority="<?= e($row['priority_level']) ?>"
                    data-status="<?= e($row['current_status']) ?>"
                >
                    <td>
                        <span class="voting-reference">
                            <?= e($row['reference_number']) ?>
                        </span>
                    </td>

                    <td>
                        <span
                            class="voting-type-badge
                            <?= $row['item_type_code'] === 'ordinance'
                                ? 'ordinance'
                                : 'resolution'
                            ?>"
                        >
                            <?= e($row['item_type_name']) ?>
                        </span>
                    </td>

                    <td>
                        <div class="voting-title-cell">
                            <strong><?= e($row['title']) ?></strong>

                            <small>
                                Available for ballot and session preparation
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
                            class="voting-priority-badge
                            <?= e(strtolower(
                                $row['priority_level']
                            )) ?>"
                        >
                            <?= e($row['priority_level']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="voting-status-badge">
                            <?= e($row['current_status']) ?>
                        </span>
                    </td>

                    <td>
                        <span
                            class="voting-readiness-badge
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
                        <div class="voting-row-actions">
                            <a
                                href="view.php?id=<?= (int)$row['id'] ?>"
                                class="voting-action-button"
                                title="View voting details"
                            >
                                <i class="bi bi-eye"></i>
                            </a>

                            <button
                                type="button"
                                class="voting-action-button"
                                data-create-voting="<?= (int)$row['id'] ?>"
                                data-reference="<?= e($row['reference_number']) ?>"
                                data-title="<?= e($row['title']) ?>"
                                title="Create voting session"
                            >
                                <i class="bi bi-ui-checks"></i>
                            </button>

                            <button
                                type="button"
                                class="voting-action-button"
                                data-voting-history="<?= (int)$row['id'] ?>"
                                title="View voting history"
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
                        <div class="voting-empty-state">
                            <i class="bi bi-inboxes"></i>

                            <strong>No voting candidates available</strong>

                            <span>
                                Shared ordinance and resolution records
                                will appear here.
                            </span>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <tr
                id="votingNoFilterResult"
                class="d-none"
            >
                <td colspan="9">
                    <div class="voting-empty-state compact">
                        <i class="bi bi-search"></i>

                        <strong>No matching voting candidates</strong>

                        <span>
                            Adjust the search or selected filters.
                        </span>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="voting-table-footer">
    <span id="votingRecordCount">
        Showing <?= count($candidateRows) ?>
        candidate record(s)
    </span>

    <span>
        Voting sessions will be stored during backend implementation.
    </span>
</div>
