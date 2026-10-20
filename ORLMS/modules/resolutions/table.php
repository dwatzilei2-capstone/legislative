<?php
/**
 * modules/resolutions/table.php
 * ------------------------------------------------------------------
 * Resolution records table.
 * ------------------------------------------------------------------
 */

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
        li.originating_office_id,
        o.name AS originating_office

     FROM legislative_items li
     INNER JOIN legislative_item_types lit
        ON lit.id = li.item_type_id
     LEFT JOIN offices o
        ON o.id = li.originating_office_id

     WHERE lit.code = 'resolution'
       AND li.deleted_at IS NULL

     ORDER BY li.updated_at DESC
     LIMIT 50"
);

$resolutions = $stmt->fetchAll();
?>

<div class="table-responsive">
    <table
        class="table resolution-table align-middle mb-0"
        id="resolutionRecordsTable"
    >
        <thead>
            <tr>
                <th>Reference</th>
                <th>Resolution Title</th>
                <th>Originating Office</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Last Updated</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php if (empty($resolutions)): ?>
                <tr class="resolution-empty-row">
                    <td colspan="7">
                        <div class="resolution-empty-state">
                            <i class="bi bi-file-earmark-check"></i>

                            <strong>No resolution records found</strong>

                            <span>
                                Create the first resolution draft to begin
                                the submission and review process.
                            </span>

                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                data-create-resolution
                            >
                                <i class="bi bi-plus-circle"></i>
                                Create First Resolution
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($resolutions as $resolution): ?>
                <tr
                    class="resolution-data-row"
                    data-search="<?= e(strtolower(
                        $resolution['reference_number'] . ' ' .
                        $resolution['title'] . ' ' .
                        ($resolution['originating_office'] ?? '')
                    )) ?>"
                    data-status="<?= e($resolution['current_status']) ?>"
                    data-priority="<?= e($resolution['priority_level']) ?>"
                    data-office="<?= (int)($resolution['originating_office_id'] ?? 0) ?>"
                >
                    <td>
                        <span class="resolution-reference">
                            <?= e($resolution['reference_number']) ?>
                        </span>
                    </td>

                    <td>
                        <div class="resolution-title-cell">
                            <strong>
                                <?= e($resolution['title']) ?>
                            </strong>

                            <small>
                                Created
                                <?= e(formatDate(
                                    $resolution['created_at']
                                )) ?>
                            </small>
                        </div>
                    </td>

                    <td>
                        <?= e(
                            $resolution['originating_office']
                            ?: 'Not assigned'
                        ) ?>
                    </td>

                    <td>
                        <span class="resolution-priority-badge">
                            <?= e($resolution['priority_level']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="resolution-status-badge">
                            <?= e($resolution['current_status']) ?>
                        </span>
                    </td>

                    <td>
                        <?= e(formatDateTime(
                            $resolution['updated_at']
                        )) ?>
                    </td>

                    <td>
                        <div class="resolution-row-actions">
                            <a
                                href="view.php?id=<?= (int)$resolution['id'] ?>"
                                class="resolution-action-button"
                                title="View resolution"
                            >
                                <i class="bi bi-eye"></i>
                            </a>

                            <button
                                type="button"
                                class="resolution-action-button"
                                data-edit-resolution="<?= (int)$resolution['id'] ?>"
                                title="Edit resolution"
                            >
                                <i class="bi bi-pencil"></i>
                            </button>

                            <button
                                type="button"
                                class="resolution-action-button"
                                data-submit-resolution="<?= (int)$resolution['id'] ?>"
                                title="Submit for review"
                            >
                                <i class="bi bi-send"></i>
                            </button>

                            <button
                                type="button"
                                class="resolution-action-button danger"
                                data-archive-resolution="<?= (int)$resolution['id'] ?>"
                                title="Archive resolution"
                            >
                                <i class="bi bi-archive"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <tr
                id="resolutionNoFilterResult"
                class="d-none"
            >
                <td colspan="7">
                    <div class="resolution-empty-state">
                        <i class="bi bi-search"></i>

                        <strong>No matching resolutions</strong>

                        <span>
                            Adjust the search value or selected filters.
                        </span>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="resolution-table-footer">
    <span id="resolutionRecordCount">
        Showing <?= count($resolutions) ?> resolution record(s)
    </span>
</div>