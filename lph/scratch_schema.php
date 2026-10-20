<?php
require_once __DIR__ . '/../../includes/auth.php';
$pdo = db();

function showCols($pdo, $table) {
    echo "=== Table: $table ===\n";
    try {
        $cols = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo "{$c['Field']} - {$c['Type']} (Null: {$c['Null']}, Key: {$c['Key']}, Default: {$c['Default']})\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

showCols($pdo, 'stakeholders');
showCols($pdo, 'qr_codes');
showCols($pdo, 'registrations');
showCols($pdo, 'attendance');
