<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['child_id'])) { echo json_encode(['ok' => false]); exit; }
$childId = (int)$_SESSION['child_id'];
$progress = ensure_daily_progress($pdo, $childId);

$pdo->prepare("UPDATE daily_progress SET games_played = games_played + 1 WHERE id = ?")->execute([$progress['id']]);
$row = $pdo->prepare("SELECT games_played FROM daily_progress WHERE id = ?");
$row->execute([$progress['id']]);
$r = $row->fetch();

echo json_encode(['ok' => true, 'games_played' => (int)$r['games_played']]);
