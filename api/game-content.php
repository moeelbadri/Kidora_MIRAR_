<?php
/**
 * محتوى لعبة واحدة (أيقونات + بنك صح/خطأ + سيناريوهات مغامرة) حسب تصنيف
 * المهمة أو اللعبة. كان هذا المحتوى ثوابت داخل games-engine.js.
 *
 * العمر يُقرأ من سجل الطفل لا من الرابط — العميل لا يُصدَّق في تحديد
 * النسخة الهادئة (بلا مؤقّت) لأنها قرار حماية لا تفضيل واجهة.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['child_id'])) { echo json_encode(['ok' => false]); exit; }

$stmt = $pdo->prepare("SELECT age FROM children WHERE id = ?");
$stmt->execute([(int)$_SESSION['child_id']]);
$child = $stmt->fetch();
if (!$child) { echo json_encode(['ok' => false]); exit; }

$age     = (int)$child['age'];
$content = game_content_for($pdo, $_GET['category'] ?? null, $age);

echo json_encode([
    'ok'   => true,
    'age'  => $age,
    'calm' => game_is_calm_age($age),
] + $content, JSON_UNESCAPED_UNICODE);
