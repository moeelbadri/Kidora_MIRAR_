<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
$childId = (int)($_SESSION['child_id'] ?? 0);
if (!$childId) {
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$type = trim($_POST['type'] ?? $_GET['type'] ?? 'analysis_report');
$msg = trim($_POST['message'] ?? $_GET['message'] ?? 'analysis_report_sent');

log_wa($pdo, $childId, $type, $msg);

echo json_encode(['ok' => true]);
