<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// تحديد الشخصية النشطة لتلوين الخلفية والأيقونات (حتى قبل تسجيل الدخول: أول شخصية)
$__headerChild = null;
$__activeChar = null;
if (!empty($_SESSION['child_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM children WHERE id = ?");
    $stmt->execute([$_SESSION['child_id']]);
    $__headerChild = $stmt->fetch() ?: null;
    if ($__headerChild) $__activeChar = active_character($pdo, $__headerChild);
}
if (!$__activeChar) {
    $all = all_characters($pdo);
    $__activeChar = $all[0] ?? null;
}
$__pageTitle = $__pageTitle ?? 'Kidora — منصة الأطفال الذكية';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($__pageTitle); ?></title>
<meta name="description" content="منصة تفاعلية حديثة تعتمد على الأبطال والقصص التفاعلية لتعزيز سلوك الطفل وحمايته وتنميته إيجابياً.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/css/main.css">
<script src="<?php echo BASE_PATH; ?>/assets/js/theme-engine.js"></script>
<script src="<?php echo BASE_PATH; ?>/assets/js/sound-engine.js"></script>
<script src="<?php echo BASE_PATH; ?>/assets/js/story-player.js"></script>
<script src="<?php echo BASE_PATH; ?>/assets/js/games-engine.js"></script>
</head>
<body>

<!-- ============================================================
     خلفية متحركة حسب ثيم الشخصية النشطة (عامة لكل الصفحات)
     ============================================================ -->
<div id="animated-bg">
    <div class="bg-gradient" id="bgGradient"></div>
    <div class="bg-glow" id="bgGlow"></div>
    <div id="floating-icons" class="floating-icons-container"></div>
    <div class="wave wave1"></div>
    <div class="wave wave2"></div>
    <div class="wave wave3"></div>
</div>

<div class="app-wrapper">

<script>
  window.KIDAURA_ACTIVE_CHARACTER = <?php echo json_encode([
      'slug'  => $__activeChar['slug'] ?? 'mimo',
      'color' => $__activeChar['color'] ?? '#6C63FF',
      'icons' => $__activeChar ? character_icons($__activeChar) : ['✨','⭐','🌟'],
      'audio' => $__activeChar['audio_path'] ?? null,
      'name'  => $__activeChar['name'] ?? '',
  ], JSON_UNESCAPED_UNICODE); ?>;
  window.KIDAURA_BASE = "<?php echo BASE_PATH; ?>";
  document.addEventListener('DOMContentLoaded', function(){
      ThemeEngine.applyBackground(window.KIDAURA_ACTIVE_CHARACTER);
  });
</script>
