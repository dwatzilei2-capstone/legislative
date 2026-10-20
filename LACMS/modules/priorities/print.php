<?php
/**
 * modules/priorities/print.php
 * ------------------------------------------------------------------
 * Print-friendly legislative priority list.
 * ------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

requireRole([
    ROLE_ADMIN,
    ROLE_STAFF,
    ROLE_COMMITTEE,
]);

$pdo = db();

$stmt = $pdo->query(
    "SELECT
        li.reference_number,
        li.title,
        li.current_status,
        li.priority_level,
        li.updated_at,
        lit.name AS item_type_name,
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
        li.updated_at DESC"
);

$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LACMS Legislative Priority List</title>

    <link
        rel="stylesheet"
        href="<?= e(vendorAsset(
            'bootstrap/bootstrap.min.css',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'
        )) ?>"
    >

    <style>
        body {
            padding: 28px;
            color: #0f172a;
            font-family: Arial, sans-serif;
            font-size: 11px;
        }

        .print-header {
            margin-bottom: 18px;
            padding-bottom: 12px;
            text-align: center;
            border-bottom: 3px solid #0b3d6e;
        }

        .print-header h3 {
            margin: 0;
            color: #0b3d6e;
            font-weight: 700;
        }

        table th {
            color: #ffffff !important;
            background: #0b3d6e !important;
            font-size: 9px;
            text-transform: uppercase;
        }

        table td {
            font-size: 10px;
            vertical-align: top;
        }

        @page {
            size: landscape;
            margin: 10mm;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                padding: 0;
            }

            thead {
                display: table-header-group;
            }

            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body onload="window.print()">

<div class="no-print text-end mb-3">
    <button
        type="button"
        class="btn btn-primary btn-sm"
        onclick="window.print()"
    >
        Print
    </button>
</div>

<div class="print-header">
    <h3><?= e(APP_NAME) ?></h3>
    <div>Legislative Priority List</div>

    <small>
        Generated on <?= e(date('F j, Y g:i A')) ?>
    </small>
</div>

<table class="table table-bordered table-sm">
    <thead>
        <tr>
            <th>Rank</th>
            <th>Reference</th>
            <th>Type</th>
            <th>Title</th>
            <th>Originating Office</th>
            <th>Workflow Status</th>
            <th>Priority</th>
            <th>Last Updated</th>
        </tr>
    </thead>

    <tbody>
        <?php if (empty($rows)): ?>
            <tr>
                <td colspan="8" class="text-center text-muted">
                    No legislative records are available.
                </td>
            </tr>
        <?php endif; ?>

        <?php foreach ($rows as $index => $row): ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td><?= e($row['reference_number']) ?></td>
                <td><?= e($row['item_type_name']) ?></td>
                <td><?= e($row['title']) ?></td>
                <td>
                    <?= e(
                        $row['originating_office']
                        ?: 'Not assigned'
                    ) ?>
                </td>
                <td><?= e($row['current_status']) ?></td>
                <td><?= e($row['priority_level']) ?></td>
                <td><?= e(formatDateTime($row['updated_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p class="text-muted">
    Total: <?= count($rows) ?> legislative record(s)
</p>

</body>
</html>
