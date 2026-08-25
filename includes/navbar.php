<?php
// يُستخدم هذا الملف فقط بعد require_once header.php وبعد require_login()
// يعتمد على $child (مصفوفة الطفل الحالي) المعرّفة في الصفحة المستدعية
$__navChild = $child ?? $__headerChild ?? null;
if (!$__navChild) return;
$__ringDays = min(30, (int)$__navChild['ring_days']);
$__circumference = 119;
$__offset = $__circumference - ($__circumference * $__ringDays / 30);
$__currentPage = basename($_SERVER['PHP_SELF']);
$__navItems = [
    'dashboard.php' => 'الرئيسية',
    'tasks.php'      => 'مهامي',
    'assessment.php' => 'أسئلة التحليل',
    'story.php'       => 'قصتي اليومية',
    'friends.php'      => 'قصص أصدقائي',
    'culture.php'        => 'قصص ثقافية',
    'grand-story.php'     => 'مغامرتي الكبرى',
    'safety.php'           => 'الحماية',
    'subscriptions.php'     => 'الاشتراك',
    'profile.php'             => 'ملفي الشخصي',
];
?>
<nav class="app-nav">
  <div class="container">
    <div class="nav-brand"><span class="dot"></span> Kidora</div>

    <ul class="nav-links" id="navLinks">
      <?php foreach ($__navItems as $file => $label): ?>
        <li><a href="<?php echo BASE_PATH . '/' . $file; ?>" class="<?php echo $__currentPage === $file ? 'active' : ''; ?>"><?php echo $label; ?></a></li>
      <?php endforeach; ?>
      <li><a href="<?php echo BASE_PATH; ?>/index.php?logout=1" style="color:#FFB4A6;">خروج</a></li>
    </ul>

    <div class="nav-right">
      <button class="music-toggle on" id="voiceToggle" title="صوت الرفيق">🗣️</button>
      <button class="music-toggle" id="musicToggle" title="الموسيقى">🔇</button>
      <a class="story-ring-btn" href="<?php echo BASE_PATH; ?>/grand-story.php" title="مغامرتي الكبرى">
        <svg viewBox="0 0 46 46">
          <circle class="ring-bg" cx="23" cy="23" r="19"></circle>
          <circle class="ring-fg" id="ringFg" cx="23" cy="23" r="19" stroke-dasharray="<?php echo $__circumference; ?>" stroke-dashoffset="<?php echo $__offset; ?>"></circle>
        </svg>
        <span class="ring-label"><?php echo $__ringDays; ?>/30</span>
      </a>
      <?php if (function_exists('is_premium_active') && is_premium_active($GLOBALS['pdo'], (int)$__navChild['id'])): ?>
        <span title="حساب مشترك موثّق" style="background:#2D6CDF;color:#fff;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:13px;">✔️</span>
      <?php endif; ?>
      <button class="nav-toggle" id="navToggle">☰</button>
    </div>
  </div>
</nav>

<script>
  window.KIDAURA_CHILD = <?php echo json_encode([
      'id' => (int)$__navChild['id'],
      'name' => $__navChild['name'],
      'points' => (int)$__navChild['points'],
  ], JSON_UNESCAPED_UNICODE); ?>;
</script>
