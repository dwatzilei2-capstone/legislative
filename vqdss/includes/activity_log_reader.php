<?php
declare(strict_types=1);

/**
 * VQDSS activity-log compatibility reader.
 *
 * The shared legislative database may contain different activity-log
 * schemas. These helpers detect available columns and normalize records
 * from both the shared activity_logs table and the future
 * vqd_activity_logs table.
 */

function vqdssAuditColumns(
    PDO $pdo,
    string $table
): array {
    static $cache = [];

    $cacheKey =
        spl_object_id($pdo) .
        ':' .
        strtolower($table);

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $rows = $pdo
            ->query("SHOW COLUMNS FROM `{$table}`")
            ->fetchAll();

        $columns = [];

        foreach ($rows as $row) {
            $field = trim(
                (string)($row['Field'] ?? '')
            );

            if ($field !== '') {
                $columns[strtolower($field)] =
                    $field;
            }
        }

        $cache[$cacheKey] = $columns;
    } catch (Throwable $exception) {
        error_log(
            '[VQDSS Audit Columns] ' .
            $exception->getMessage()
        );

        $cache[$cacheKey] = [];
    }

    return $cache[$cacheKey];
}

function vqdssAuditColumn(
    array $columns,
    array $candidates
): ?string {
    foreach ($candidates as $candidate) {
        $key = strtolower($candidate);

        if (isset($columns[$key])) {
            return $columns[$key];
        }
    }

    return null;
}

function vqdssAuditValue(
    array $row,
    array $candidates,
    mixed $default = ''
): mixed {
    foreach ($candidates as $candidate) {
        if (
            array_key_exists($candidate, $row) &&
            $row[$candidate] !== null &&
            $row[$candidate] !== ''
        ) {
            return $row[$candidate];
        }
    }

    return $default;
}

function vqdssAuditDetailsText(
    mixed $value
): string {
    if ($value === null || $value === '') {
        return '';
    }

    if (is_array($value) || is_object($value)) {
        return json_encode(
            $value,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?: '';
    }

    $text = trim((string)$value);

    if ($text === '') {
        return '';
    }

    $decoded = json_decode(
        $text,
        true
    );

    if (
        json_last_error() === JSON_ERROR_NONE &&
        is_array($decoded)
    ) {
        return json_encode(
            $decoded,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?: $text;
    }

    return $text;
}

function vqdssAuditModule(
    string $module,
    string $entityType,
    string $action,
    string $description
): string {
    $haystack = strtolower(
        implode(
            ' ',
            [
                $module,
                $entityType,
                $action,
                $description,
            ]
        )
    );

    $mapping = [
        'quorum' => [
            'quorum',
            'attendance',
            'present member',
        ],
        'voting' => [
            'voting',
            'vote session',
            'ballot',
            'voter',
        ],
        'tallying' => [
            'tally',
            'recount',
            'vote count',
        ],
        'decisions' => [
            'decision',
            'official outcome',
            'resolution result',
        ],
        'validation' => [
            'validation',
            'integrity review',
            'finding',
            'audit report',
        ],
        'reports' => [
            'report',
            'export',
            'analytics',
        ],
        'users' => [
            'user',
            'role',
            'account',
            'permission',
        ],
        'authentication' => [
            'login',
            'logout',
            'sign in',
            'authentication',
            'session',
        ],
        'system' => [
            'system',
            'configuration',
            'setting',
            'migration',
        ],
    ];

    foreach ($mapping as $label => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return $label;
            }
        }
    }

    $clean = strtolower(
        trim($module)
    );

    $clean = preg_replace(
        '/[^a-z0-9]+/',
        ' ',
        $clean
    ) ?? '';

    return trim($clean) !== ''
        ? trim($clean)
        : 'general';
}

function vqdssAuditActionCategory(
    string $action,
    string $description
): string {
    $haystack = strtolower(
        $action . ' ' . $description
    );

    $categories = [
        'Login' => [
            'login',
            'logged in',
            'sign in',
            'authenticated',
        ],
        'Logout' => [
            'logout',
            'logged out',
            'sign out',
        ],
        'Create' => [
            'create',
            'created',
            'add',
            'added',
            'insert',
        ],
        'Update' => [
            'update',
            'updated',
            'edit',
            'edited',
            'change',
            'modified',
        ],
        'Delete' => [
            'delete',
            'deleted',
            'remove',
            'removed',
            'trash',
        ],
        'Approve' => [
            'approve',
            'approved',
            'adopted',
            'enacted',
        ],
        'Reject' => [
            'reject',
            'rejected',
            'denied',
            'declined',
        ],
        'Certify' => [
            'certify',
            'certified',
            'lock result',
        ],
        'Validate' => [
            'validate',
            'validated',
            'integrity check',
        ],
        'Vote' => [
            'vote',
            'ballot',
        ],
        'Tally' => [
            'tally',
            'recount',
            'counted',
        ],
        'Export' => [
            'export',
            'download',
            'print',
            'report generated',
        ],
        'View' => [
            'view',
            'opened',
            'read',
        ],
    ];

    foreach ($categories as $label => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return $label;
            }
        }
    }

    $clean = trim($action);

    return $clean !== ''
        ? ucwords(
            str_replace(
                ['_', '-'],
                ' ',
                $clean
            )
        )
        : 'Other';
}

function vqdssAuditSeverity(
    string $severity,
    string $action,
    string $status,
    string $description
): string {
    $explicit = strtolower(
        trim($severity)
    );

    if (
        in_array(
            $explicit,
            [
                'critical',
                'high',
                'warning',
                'medium',
                'low',
                'info',
                'success',
            ],
            true
        )
    ) {
        return match ($explicit) {
            'medium' => 'warning',
            'low' => 'info',
            default => $explicit,
        };
    }

    $haystack = strtolower(
        implode(
            ' ',
            [
                $action,
                $status,
                $description,
            ]
        )
    );

    foreach (
        [
            'security breach',
            'unauthorized',
            'tamper',
            'critical',
            'failed validation',
            'duplicate vote',
            'integrity failure',
        ]
        as $keyword
    ) {
        if (str_contains($haystack, $keyword)) {
            return 'critical';
        }
    }

    foreach (
        [
            'failed',
            'failure',
            'rejected',
            'deleted',
            'cancelled',
            'locked out',
            'denied',
            'error',
            'mismatch',
        ]
        as $keyword
    ) {
        if (str_contains($haystack, $keyword)) {
            return 'high';
        }
    }

    foreach (
        [
            'warning',
            'updated',
            'modified',
            'challenge',
            'recount',
            'review required',
            'pending',
        ]
        as $keyword
    ) {
        if (str_contains($haystack, $keyword)) {
            return 'warning';
        }
    }

    foreach (
        [
            'success',
            'approved',
            'certified',
            'validated',
            'completed',
        ]
        as $keyword
    ) {
        if (str_contains($haystack, $keyword)) {
            return 'success';
        }
    }

    return 'info';
}

function vqdssAuditStatus(
    string $status,
    string $severity
): string {
    $clean = trim($status);

    if ($clean !== '') {
        return ucwords(
            str_replace(
                ['_', '-'],
                ' ',
                $clean
            )
        );
    }

    return match ($severity) {
        'critical',
        'high' => 'Attention Required',
        'warning' => 'Review',
        'success' => 'Successful',
        default => 'Recorded',
    };
}

function vqdssAuditLoadSource(
    PDO $pdo,
    string $table,
    string $sourceLabel,
    int $limit = 750
): array {
    if (!tableExists($table)) {
        return [];
    }

    $logColumns = vqdssAuditColumns(
        $pdo,
        $table
    );

    if (!$logColumns) {
        return [];
    }

    $userColumns = tableExists('users')
        ? vqdssAuditColumns(
            $pdo,
            'users'
        )
        : [];

    $systemColumns = tableExists('systems')
        ? vqdssAuditColumns(
            $pdo,
            'systems'
        )
        : [];

    $logUserIdColumn = vqdssAuditColumn(
        $logColumns,
        [
            'user_id',
            'actor_id',
            'performed_by',
            'created_by',
            'updated_by',
        ]
    );

    $logSystemIdColumn = vqdssAuditColumn(
        $logColumns,
        [
            'system_id',
            'application_id',
        ]
    );

    $createdColumn = vqdssAuditColumn(
        $logColumns,
        [
            'created_at',
            'logged_at',
            'activity_date',
            'event_time',
            'timestamp',
            'occurred_at',
        ]
    );

    $idColumn = vqdssAuditColumn(
        $logColumns,
        [
            'id',
            'activity_log_id',
            'log_id',
            'audit_id',
        ]
    );

    $userIdColumn = vqdssAuditColumn(
        $userColumns,
        ['id', 'user_id']
    );

    $userNameColumn = vqdssAuditColumn(
        $userColumns,
        [
            'full_name',
            'name',
            'display_name',
            'username',
        ]
    );

    $userEmailColumn = vqdssAuditColumn(
        $userColumns,
        [
            'email',
            'email_address',
        ]
    );

    $roleIdColumn = vqdssAuditColumn(
        $userColumns,
        [
            'role_id',
            'user_role_id',
        ]
    );

    $selectParts = ['al.*'];
    $joinParts = [];

    if (
        $logUserIdColumn &&
        $userIdColumn &&
        (
            $userNameColumn ||
            $userEmailColumn
        )
    ) {
        $joinParts[] =
            "LEFT JOIN users u
             ON u.`{$userIdColumn}` =
                al.`{$logUserIdColumn}`";

        if ($userNameColumn) {
            $selectParts[] =
                "u.`{$userNameColumn}` AS
                 vqdss_actor_name";
        }

        if ($userEmailColumn) {
            $selectParts[] =
                "u.`{$userEmailColumn}` AS
                 vqdss_actor_email";
        }

        if (
            $roleIdColumn &&
            tableExists('roles')
        ) {
            $roleColumns =
                vqdssAuditColumns(
                    $pdo,
                    'roles'
                );

            $rolePrimaryKey =
                vqdssAuditColumn(
                    $roleColumns,
                    ['id', 'role_id']
                );

            $roleNameColumn =
                vqdssAuditColumn(
                    $roleColumns,
                    [
                        'name',
                        'role_name',
                        'title',
                    ]
                );

            if (
                $rolePrimaryKey &&
                $roleNameColumn
            ) {
                $joinParts[] =
                    "LEFT JOIN roles r
                     ON r.`{$rolePrimaryKey}` =
                        u.`{$roleIdColumn}`";

                $selectParts[] =
                    "r.`{$roleNameColumn}` AS
                     vqdss_actor_role";
            }
        }
    }

    $systemIdColumn = vqdssAuditColumn(
        $systemColumns,
        ['id', 'system_id']
    );

    $systemNameColumn = vqdssAuditColumn(
        $systemColumns,
        [
            'name',
            'system_name',
            'title',
            'code',
        ]
    );

    if (
        $logSystemIdColumn &&
        $systemIdColumn &&
        $systemNameColumn
    ) {
        $joinParts[] =
            "LEFT JOIN systems s
             ON s.`{$systemIdColumn}` =
                al.`{$logSystemIdColumn}`";

        $selectParts[] =
            "s.`{$systemNameColumn}` AS
             vqdss_system_name";
    }

    $orderColumn =
        $createdColumn
        ?: $idColumn;

    $orderSql = $orderColumn
        ? "ORDER BY al.`{$orderColumn}` DESC"
        : '';

    $safeLimit = max(
        1,
        min(
            $limit,
            5000
        )
    );

    $sql = "
        SELECT
            " .
            implode(
                ",\n            ",
                $selectParts
            ) .
            "
        FROM `{$table}` al
        " .
        implode(
            "\n",
            $joinParts
        ) .
        "
        {$orderSql}
        LIMIT {$safeLimit}
    ";

    try {
        $rows = $pdo
            ->query($sql)
            ->fetchAll();
    } catch (Throwable $exception) {
        error_log(
            '[VQDSS Audit Load ' .
            $table .
            '] ' .
            $exception->getMessage()
        );

        return [];
    }

    $normalized = [];

    foreach ($rows as $position => $row) {
        $id = (string)vqdssAuditValue(
            $row,
            [
                'id',
                'activity_log_id',
                'log_id',
                'audit_id',
            ],
            (string)($position + 1)
        );

        $actorName = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'vqdss_actor_name',
                    'user_name',
                    'actor_name',
                    'performed_by_name',
                    'created_by_name',
                    'full_name',
                    'username',
                ],
                'System'
            )
        );

        $actorEmail = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'vqdss_actor_email',
                    'user_email',
                    'actor_email',
                    'email',
                ],
                ''
            )
        );

        $actorRole = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'vqdss_actor_role',
                    'role_name',
                    'actor_role',
                    'role',
                ],
                ''
            )
        );

        $moduleRaw = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'module',
                    'module_name',
                    'system_module',
                    'section',
                    'category',
                    'vqdss_system_name',
                    'system_name',
                    'application',
                ],
                ''
            )
        );

        $entityType = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'entity_type',
                    'record_type',
                    'resource_type',
                    'object_type',
                    'table_name',
                ],
                ''
            )
        );

        $entityId = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'entity_id',
                    'record_id',
                    'resource_id',
                    'object_id',
                    'target_id',
                ],
                ''
            )
        );

        $actionRaw = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'action',
                    'action_name',
                    'event',
                    'activity',
                    'operation',
                    'event_type',
                ],
                ''
            )
        );

        $description = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'description',
                    'details',
                    'message',
                    'activity_description',
                    'event_description',
                    'remarks',
                ],
                ''
            )
        );

        if ($description === '') {
            $description =
                $actionRaw !== ''
                    ? $actionRaw
                    : 'Activity recorded';
        }

        $statusRaw = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'status',
                    'result',
                    'outcome',
                    'event_status',
                ],
                ''
            )
        );

        $severityRaw = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'severity',
                    'level',
                    'risk_level',
                    'event_level',
                ],
                ''
            )
        );

        $module = vqdssAuditModule(
            $moduleRaw,
            $entityType,
            $actionRaw,
            $description
        );

        $action =
            vqdssAuditActionCategory(
                $actionRaw,
                $description
            );

        $severity = vqdssAuditSeverity(
            $severityRaw,
            $actionRaw,
            $statusRaw,
            $description
        );

        $status = vqdssAuditStatus(
            $statusRaw,
            $severity
        );

        $createdAt = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'created_at',
                    'logged_at',
                    'activity_date',
                    'event_time',
                    'timestamp',
                    'occurred_at',
                    'date_created',
                ],
                ''
            )
        );

        $ipAddress = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'ip_address',
                    'ip',
                    'remote_address',
                    'client_ip',
                ],
                ''
            )
        );

        $userAgent = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'user_agent',
                    'browser',
                    'client_agent',
                    'device',
                ],
                ''
            )
        );

        $requestMethod = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'request_method',
                    'http_method',
                    'method',
                ],
                ''
            )
        );

        $requestUrl = trim(
            (string)vqdssAuditValue(
                $row,
                [
                    'request_url',
                    'url',
                    'route',
                    'endpoint',
                    'path',
                ],
                ''
            )
        );

        $oldValues = vqdssAuditDetailsText(
            vqdssAuditValue(
                $row,
                [
                    'old_values',
                    'old_value',
                    'before_data',
                    'previous_data',
                ],
                ''
            )
        );

        $newValues = vqdssAuditDetailsText(
            vqdssAuditValue(
                $row,
                [
                    'new_values',
                    'new_value',
                    'after_data',
                    'current_data',
                ],
                ''
            )
        );

        $metadata = vqdssAuditDetailsText(
            vqdssAuditValue(
                $row,
                [
                    'metadata',
                    'meta',
                    'payload',
                    'context',
                    'extra_data',
                ],
                ''
            )
        );

        $search = strtolower(
            implode(
                ' ',
                [
                    $actorName,
                    $actorEmail,
                    $actorRole,
                    $module,
                    $action,
                    $description,
                    $entityType,
                    $entityId,
                    $severity,
                    $status,
                    $ipAddress,
                    $sourceLabel,
                ]
            )
        );

        $normalized[] = [
            'key' =>
                strtolower($table) .
                ':' .
                $id,
            'id' => $id,
            'source_table' => $table,
            'source_label' => $sourceLabel,
            'actor_name' =>
                $actorName !== ''
                    ? $actorName
                    : 'System',
            'actor_email' => $actorEmail,
            'actor_role' => $actorRole,
            'module' => $module,
            'module_label' => ucwords(
                str_replace(
                    ['_', '-'],
                    ' ',
                    $module
                )
            ),
            'action' => $action,
            'description' => $description,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'severity' => $severity,
            'status' => $status,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'request_method' => $requestMethod,
            'request_url' => $requestUrl,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'created_at' => $createdAt,
            'timestamp' =>
                $createdAt !== ''
                    ? (
                        strtotime($createdAt)
                        ?: 0
                    )
                    : 0,
            'search' => $search,
        ];
    }

    return $normalized;
}

function vqdssLoadAuditLogs(
    PDO $pdo,
    int $limitPerSource = 750
): array {
    $logs = array_merge(
        vqdssAuditLoadSource(
            $pdo,
            'vqd_activity_logs',
            'VQDSS',
            $limitPerSource
        ),
        vqdssAuditLoadSource(
            $pdo,
            'activity_logs',
            'Shared Platform',
            $limitPerSource
        )
    );

    usort(
        $logs,
        static function (
            array $left,
            array $right
        ): int {
            $timestampCompare =
                ($right['timestamp'] ?? 0)
                <=>
                ($left['timestamp'] ?? 0);

            if ($timestampCompare !== 0) {
                return $timestampCompare;
            }

            return strcmp(
                (string)($right['key'] ?? ''),
                (string)($left['key'] ?? '')
            );
        }
    );

    return $logs;
}
