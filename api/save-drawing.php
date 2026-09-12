<?php
/**
 * حفظ رسمة من لوحة الرسم (draw.php).
 *
 * يستقبل JSON: { image: "data:image/png;base64,...", title?: "..." }
 * يتحقق أن الحمولة PNG فعلية (رأس الملف + getimagesize) وبحجم معقول، ثم
 * يخزّنها في uploads/drawings/{child_id}/ ويسجّل صفاً في drawings.
 * أول رسمة محفوظة في اليوم تُحتسب لعبة من ألعاب اليوم (games_played + 1)،
 * فالرسم جزء من الحلقة اليومية لا نشاطاً جانبياً.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$fail = function (string $msg, int $code = 400) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE); exit; };

if (empty($_SESSION['child_id'])) $fail('غير مسجّل الدخول', 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $fail('طريقة غير مدعومة', 405);
$childId = (int)$_SESSION['child_id'];

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 3 * 1024 * 1024) $fail('الرسمة كبيرة جداً');
$in = json_decode($raw, true);
if (!is_array($in) || empty($in['image']) || !is_string($in['image'])) $fail('لا توجد صورة');

if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=\r\n]+)$#', $in['image'], $m)) $fail('صيغة الصورة غير مدعومة');
$bin = base64_decode($m[1], true);
if ($bin === false || strlen($bin) < 100 || strlen($bin) > 2 * 1024 * 1024) $fail('الصورة غير صالحة');
// رأس PNG الثابت: 89 50 4E 47 0D 0A 1A 0A
if (substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n") $fail('الصورة ليست PNG');

$tmp = tempnam(sys_get_temp_dir(), 'kd_');
file_put_contents($tmp, $bin);
$info = @getimagesize($tmp);
if ($info === false || $info[2] !== IMAGETYPE_PNG || $info[0] < 16 || $info[1] < 16 || $info[0] > 4096 || $info[1] > 4096) {
    @unlink($tmp); $fail('تعذّر قراءة الصورة');
}

$dir = __DIR__ . '/../uploads/drawings/' . $childId;
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) { @unlink($tmp); $fail('تعذّر الحفظ على الخادم', 500); }
$filename = 'd_' . bin2hex(random_bytes(10)) . '.png';
if (!rename($tmp, $dir . '/' . $filename)) {
    if (!copy($tmp, $dir . '/' . $filename)) { @unlink($tmp); $fail('تعذّر الحفظ على الخادم', 500); }
    @unlink($tmp);
}
@chmod($dir . '/' . $filename, 0644);
$rel = 'uploads/drawings/' . $childId . '/' . $filename;

$title = trim((string)($in['title'] ?? ''));
// بدون mbstring (غير مضمونة في كل بيئة تشغيل): قصّ آمن لـ UTF-8 عبر preg
$title = preg_replace('/\s+/u', ' ', $title);
if (preg_match('/^.{0,60}/us', $title, $tm)) $title = $tm[0];
if ($title === '') $title = 'رسمة ' . date('Y/m/d');

// created_at بتوقيت التطبيق (لا DEFAULT UTC) حتى يطابق DATE(created_at) قيمة today_key() قرب منتصف الليل
$pdo->prepare("INSERT INTO drawings (child_id, title, image_path, created_at) VALUES (?,?,?,?)")->execute([$childId, $title, $rel, date('Y-m-d H:i:s')]);
$drawingId = (int)$pdo->lastInsertId();

// أول رسمة اليوم = لعبة من ألعاب اليوم
$progress = ensure_daily_progress($pdo, $childId);
$cnt = $pdo->prepare("SELECT COUNT(*) c FROM drawings WHERE child_id = ? AND DATE(created_at) = ?");
$cnt->execute([$childId, today_key()]);
$countedAsGame = false;
if ((int)$cnt->fetch()['c'] === 1) {
    $pdo->prepare("UPDATE daily_progress SET games_played = games_played + 1 WHERE id = ?")->execute([$progress['id']]);
    $countedAsGame = true;
}

echo json_encode([
    'ok'    => true,
    'id'    => $drawingId,
    'url'   => BASE_PATH . '/' . $rel,
    'title' => $title,
    'counted_as_game' => $countedAsGame,
], JSON_UNESCAPED_UNICODE);
