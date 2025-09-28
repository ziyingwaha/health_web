<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$pdo = get_pdo();

$schemaPath = __DIR__ . '/../sql/schema.sql';
if (!file_exists($schemaPath)) {
    fwrite(STDERR, "Schema file not found: $schemaPath\n");
    exit(1);
}

$sql = file_get_contents($schemaPath);
$pdo->exec($sql);

echo "Database schema setup completed.\n";

