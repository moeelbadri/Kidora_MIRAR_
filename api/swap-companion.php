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

$current = $child['active_character'] ?: $child['character_1'];
$other = ($current == $child['character_1']) ? $child['character_2'] : $child['character_1'];
if (!$other) { echo json_encode(['ok' => false, 'msg' => 'شخصية واحدة فقط']); exit; }

$upd = $pdo->prepare("UPDATE children SET active_character = ? WHERE id = ?");
$upd->execute([$other, $child['id']]);

echo json_encode(['ok' => true, 'active_character' => $other]);
