<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['child_id'])) { echo json_encode(['ok' => false]); exit; }

$stmt = $pdo->prepare("SELECT * FROM children WHERE id = ?");
$stmt->execute([$_SESSION['child_id']]);
$child = $stmt->fetch();
if (!$child) { echo json_encode(['ok' => false]); exit; }

$fullAccess = has_full_access($pdo, $child);
$saved = active_character($pdo, $child);
if (!$fullAccess && $saved && !empty($saved['is_premium'])) {
    // لا نستبدل الاختيار المحفوظ لمجرد انتهاء التجربة؛ سيعود تلقائياً بعد الاشتراك.
    echo json_encode(['ok' => false, 'msg' => 'رفيقك المحفوظ يعود مع الاشتراك']); exit;
}
$allowed = selectable_characters($pdo, $fullAccess);
if (!$allowed) { echo json_encode(['ok' => false, 'msg' => 'لا توجد شخصية متاحة']); exit; }
$allowedIds = array_map(fn($c) => (int)$c['id'], $allowed);
$effective = effective_character($pdo, $child);
$current = (int)($effective['id'] ?? 0);
$idx = array_search($current, $allowedIds, true);
$other = $allowedIds[$idx === false ? 0 : (($idx + 1) % count($allowedIds))];

$upd = $pdo->prepare("UPDATE children SET active_character = ? WHERE id = ?");
$upd->execute([$other, $child['id']]);

echo json_encode(['ok' => true, 'active_character' => $other]);
