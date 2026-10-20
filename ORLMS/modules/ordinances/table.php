<?php
/**
 * modules/ordinances/table.php
 * ------------------------------------------------------------------
 * Ordinance records table.
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
        o.name AS originating_office

     FROM legislative_items li
     INNER JOIN legislative_item_types lit
        ON lit.id = li.item_type_id
     LEFT JOIN offices o
        ON o.id = li.originating_office_id

     WHERE lit.code = 'ordinance'
       AND li.deleted_at IS NULL

     ORDER BY li.updated_at DESC
     LIMIT 50"
);

$ordinances = $stmt->fetchAll();
?>

<div class="table-responsive">
    <table class="table ordinance-table align-middle mb-0">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Ordinance Title</th>
                <th>Originating Office</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Last Updated</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php if (empty($ordinances)): ?>
                <tr>
                    <td colspan="7">
                        <div class="ordinance-empty-state">
                            <i class="bi bi-file-earmark-plus"></i>

                            <strong>No ordinance drafts found</strong>

                            <span>
                                Create a new ordinance draft to begin
                                the legislative life-cycle process.
                            </span>

                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                data-create-ordinance
                            >
                                <i class="bi bi-plus-circle"></i>
                                Create First Ordinance
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($ordinances as $ordinance): ?>
                <tr>
                    <td>
                        <span class="ordinance-reference">
                            <?= e($ordinance['reference_number']) ?>
                        </span>
                    </td>

                    <td>
                        <div class="ordinance-title-cell">
                            <strong>
                                <?= e($ordinance['title']) ?>
                            </strong>

                            <small>
                                Created
                                <?= e(formatDate(
                                    $ordinance['created_at']
                                )) ?>
                            </small>
                        </div>
                    </td>

                    <td>
                        <?= e(
                            $ordinance['originating_office']
                            ?: 'Not assigned'
                        ) ?>
                    </td>

                    <td>
                        <span class="priority-badge">
                            <?= e($ordinance['priority_level']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="ordinance-status-badge">
                            <?= e($ordinance['current_status']) ?>
                        </span>
                    </td>

                    <td>
                        <?= e(formatDateTime(
                            $ordinance['updated_at']
                        )) ?>
                    </td>

                    <td>
                        <div class="ordinance-row-actions">
                            <a
                                href="view.php?id=<?= (int)$ordinance['id'] ?>"
                                class="row-action-button"
                                title="View ordinance"
                            >
                                <i class="bi bi-eye"></i>
                            </a>

                            <button
                                type="button"
                                class="row-action-button"
                                data-edit-ordinance="<?= (int)$ordinance['id'] ?>"
                                title="Edit ordinance"
                            >
                                <i class="bi bi-pencil"></i>
                            </button>

                            <button
                                type="button"
                                class="row-action-button"
                                data-submit-ordinance="<?= (int)$ordinance['id'] ?>"
                                title="Submit for review"
                            >
                                <i class="bi bi-send"></i>
                            </button>

                            <button
                                type="button"
                                class="row-action-button danger"
                                data-archive-ordinance="<?= (int)$ordinance['id'] ?>"
                                title="Archive ordinance"
                            >
                                <i class="bi bi-archive"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="ordinance-table-footer">
    <span>
        Showing <?= count($ordinances) ?> ordinance record(s)
    </span>
</div>