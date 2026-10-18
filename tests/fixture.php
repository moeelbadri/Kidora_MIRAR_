<?php
// CLI-only SQL bridge. The runner supplies a disposable database, never production.
if (PHP_SAPI !== 'cli' || getenv('KIDORA_TEST_ISOLATED') !== '1') exit(1);
require __DIR__ . '/../config/db.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$stmt = $pdo->prepare($input['sql']);
$stmt->execute($input['params'] ?? []);
echo json_encode($stmt->columnCount() ? $stmt->fetchAll() : $stmt->rowCount(), JSON_THROW_ON_ERROR);
