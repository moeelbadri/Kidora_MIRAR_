<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
$childId = (int)($_SESSION['child_id'] ?? 0);
if (!$childId) {
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$type = substr(trim($_POST['type'] ?? 'analysis_report'), 0, 80);
$msg = substr(trim($_POST['message'] ?? 'analysis_report_sent'), 0, 4000);

log_wa($pdo, $childId, $type, $msg);

echo json_encode(['ok' => true]);
