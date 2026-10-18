<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// تحديد الشخصية النشطة لتلوين الخلفية والأيقونات (حتى قبل تسجيل الدخول: أول شخصية)
$__headerChild = null;
$__activeChar = null;
if (!empty($_SESSION['child_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM children WHERE id = ?");
    $stmt->execute([$_SESSION['child_id']]);
    $__headerChild = $stmt->fetch() ?: null;
    if ($__headerChild) $__activeChar = effective_character($pdo, $__headerChild);
}
if (!$__activeChar) {
    $all = all_characters($pdo);
    $__activeChar = $all[0] ?? null;
}
$__pageTitle = $__pageTitle ?? 'Kidora — منصة الأطفال الذكية';
// Version every shared local asset from its deployed contents' modification time.
$__assetUrl = static function (string $path): string {
    $version = filemtime(__DIR__ . '/..' . $path);
    return h(BASE_PATH . $path . '?v=' . $version);
};
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<script src="<?= $__assetUrl('/assets/js/security.js') ?>"></script>
<title><?php echo h($__pageTitle); ?></title>
<link rel="icon" type="image/png" href="<?php echo h(KIDORA_FAVICON); ?>">
<link rel="shortcut icon" type="image/png" href="<?php echo h(KIDORA_FAVICON); ?>">
<link rel="apple-touch-icon" href="<?php echo h(KIDORA_FAVICON); ?>">
<meta name="description" content="منصة تفاعلية حديثة تعتمد على الأبطال والقصص التفاعلية لتعزيز سلوك الطفل وحمايته وتنميته إيجابياً.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo $__assetUrl('/assets/css/main.css'); ?>">
<?php $scriptLoading = !empty($__landingPage) ? ' defer' : ''; ?>
<script<?php echo $scriptLoading; ?> src="<?php echo $__assetUrl('/assets/js/video-player.js'); ?>"></script>
<script<?php echo $scriptLoading; ?> src="<?php echo $__assetUrl('/assets/js/theme-engine.js'); ?>"></script>
<script<?php echo $scriptLoading; ?> src="<?php echo $__assetUrl('/assets/js/sound-engine.js'); ?>"></script>
<script<?php echo $scriptLoading; ?> src="<?php echo $__assetUrl('/assets/js/companion.js'); ?>"></script>
<?php // Keep synchronous: page-inline scripts below require these globals immediately. ?>
<?php if (!empty($__needsStoryPlayer)): ?>
<script src="<?php echo $__assetUrl('/assets/js/story-player.js'); ?>"></script>
<?php endif; ?>
<?php if (!empty($__needsGamesEngine)): ?>
<script src="<?php echo $__assetUrl('/assets/js/games-engine.js'); ?>"></script>
<?php endif; ?>
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
      'slug'  => $__activeChar['slug'] ?? 'spongebob',
      'color' => $__activeChar['color'] ?? '#6C63FF',
      'move'  => $__activeChar['move_type'] ?? 'wiggle',
      'icons' => $__activeChar ? character_icons($__activeChar) : ['✨','⭐','🌟'],
      'theme' => character_theme($__activeChar),
      'audio' => $__activeChar['audio_path'] ?? null,
      'image' => $__activeChar['image_path'] ?? null,
      'name'  => $__activeChar['name'] ?? '',
    ], JSON_UNESCAPED_UNICODE); ?>;
  window.KIDAURA_CHILD_PHOTO = <?php echo json_encode(private_media_url($__headerChild['photo_path'] ?? null)); ?>;
  window.KIDAURA_BASE = "<?php echo BASE_PATH; ?>";
  document.addEventListener('DOMContentLoaded', function(){
      ThemeEngine.applyBackground(window.KIDAURA_ACTIVE_CHARACTER);
  });
</script>
