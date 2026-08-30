<?php
// navbar.php - إصدار السايد بار الفاخر
$__navChild = $child ?? $__headerChild ?? null;
if (!$__navChild) return;

$__ringDays = min(30, (int)$__navChild['ring_days']);
$__circumference = 119;
$__offset = $__circumference - ($__circumference * $__ringDays / 30);
$__currentPage = basename($_SERVER['PHP_SELF']);

// قائمة الروابط الكاملة مع أيقونات
$__navItems = [
    'dashboard.php' => ['label' => 'الرئيسية', 'icon' => '🏠'],
    'tasks.php' => ['label' => 'مهامي', 'icon' => '📋'],
    'assessment.php' => ['label' => 'أسئلة التحليل', 'icon' => '📊'],
    'story.php' => ['label' => 'قصتي اليومية', 'icon' => '📖'],
    'friends.php' => ['label' => 'قصص أصدقائي', 'icon' => '👫'],
    'culture.php' => ['label' => 'قصص ثقافية', 'icon' => '🌍'],
    'grand-story.php' => ['label' => 'مغامرتي الكبرى', 'icon' => '🏰'],
    'safety.php' => ['label' => 'الحماية', 'icon' => '🛡️'],
    'subscriptions.php' => ['label' => 'الاشتراك', 'icon' => '💎'],
    'games.php' => ['label' => 'ألعابي', 'icon' => '🎮'],
    'games2.php' => ['label' => 'معرض الألعاب', 'icon' => '🎯'],
    'profile.php' => ['label' => 'ملفي الشخصي', 'icon' => '👤'],
];
?>

<!-- زر فتح/إغلاق السايد بار (موجود دائماً) -->
<button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="تبديل القائمة">
  <span class="toggle-icon"></span>
</button>

<!-- السايد بار الفاخر -->
<aside class="app-sidebar" id="appSidebar">
  <!-- رأس السايد بار مع الشعار -->
  <div class="sidebar-header">
    <div class="sidebar-brand">
      <span class="brand-dot"></span>
      <span class="brand-name">Kidora</span>
    </div>
    <button class="sidebar-close-btn" id="sidebarCloseBtn">✕</button>
  </div>

  <!-- حلقة التقدم -->
  <div class="sidebar-ring">
    <a class="story-ring" href="<?php echo BASE_PATH; ?>/grand-story.php">
      <svg viewBox="0 0 46 46">
        <circle class="ring-bg" cx="23" cy="23" r="19"></circle>
        <circle class="ring-fg" id="ringFg" cx="23" cy="23" r="19" stroke-dasharray="<?php echo $__circumference; ?>" stroke-dashoffset="<?php echo $__offset; ?>"></circle>
      </svg>
      <span class="ring-label"><?php echo $__ringDays; ?>/30</span>
    </a>
    <span class="ring-text">أيام التحدي</span>
  </div>

  <!-- قائمة الروابط -->
  <ul class="sidebar-nav">
    <?php foreach ($__navItems as $file => $item): ?>
      <li>
        <a href="<?php echo BASE_PATH . '/' . $file; ?>" class="<?php echo $__currentPage === $file ? 'active' : ''; ?>">
          <span class="nav-icon"><?php echo $item['icon']; ?></span>
          <span class="nav-label"><?php echo $item['label']; ?></span>
          <span class="nav-glow"></span>
        </a>
      </li>
    <?php endforeach; ?>
    <li class="nav-divider"></li>
    <li>
      <a href="<?php echo BASE_PATH; ?>/index.php?logout=1" class="logout-link">
        <span class="nav-icon">🚪</span>
        <span class="nav-label">خروج</span>
        <span class="nav-glow"></span>
      </a>
    </li>
  </ul>

  <!-- تذييل السايد بار -->
  <div class="sidebar-footer">
    <?php if (function_exists('is_premium_active') && is_premium_active($GLOBALS['pdo'], (int)$__navChild['id'])): ?>
      <span class="premium-badge">🌟 مشترك VIP</span>
    <?php else: ?>
      <span class="free-badge">🔓 حساب مجاني</span>
    <?php endif; ?>
    <div class="sidebar-voice">
      <button class="voice-btn on" id="voiceToggle">🗣️</button>
      <button class="music-btn" id="musicToggle">🔇</button>
    </div>
  </div>
</aside>

<!-- طبقة التغطية الخلفية -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
  // ===== التحكم بالسايد بار =====
  const sidebar = document.getElementById('appSidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const toggleBtn = document.getElementById('sidebarToggleBtn');
  const closeBtn = document.getElementById('sidebarCloseBtn');

  function toggleSidebar() {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
    document.body.classList.toggle('sidebar-open');
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    document.body.classList.remove('sidebar-open');
  }

  toggleBtn.addEventListener('click', toggleSidebar);
  closeBtn.addEventListener('click', closeSidebar);
  overlay.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeSidebar();
  });

  // ===== الصوت والموسيقى =====
  document.getElementById('voiceToggle')?.addEventListener('click', function() {
    this.classList.toggle('on');
  });
  document.getElementById('musicToggle')?.addEventListener('click', function() {
    this.classList.toggle('on');
  });

  // ===== بيانات الطفل =====
  window.KIDAURA_CHILD = <?php echo json_encode([
      'id' => (int)$__navChild['id'],
      'name' => $__navChild['name'],
      'points' => (int)$__navChild['points'],
  ], JSON_UNESCAPED_UNICODE); ?>;
</script>

<style>
  /* ======================================================
     السايد بار الفاخر - Glassmorphism مع أنيميشن وإضاءة
     ====================================================== */

  /* ===== زر التبديل (دائماً في أعلى اليمين) ===== */
  .sidebar-toggle-btn {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(251, 191, 36, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), 0 0 20px rgba(251, 191, 36, 0.05);
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .sidebar-toggle-btn:hover {
    transform: scale(1.08);
    border-color: #fbbf24;
    box-shadow: 0 8px 40px rgba(251, 191, 36, 0.15), 0 0 30px rgba(251, 191, 36, 0.05);
  }

  .sidebar-toggle-btn .toggle-icon {
    position: relative;
    width: 24px;
    height: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
  }

  .sidebar-toggle-btn .toggle-icon span {
    display: block;
    height: 2.5px;
    border-radius: 4px;
    background: #fff;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    transform-origin: center;
  }

  .sidebar-toggle-btn .toggle-icon span:nth-child(1) { width: 24px; }
  .sidebar-toggle-btn .toggle-icon span:nth-child(2) { width: 18px; }
  .sidebar-toggle-btn .toggle-icon span:nth-child(3) { width: 24px; }

  /* عندما يكون السايد بار مفتوحاً، الأيقونة تتحول إلى X */
  body.sidebar-open .sidebar-toggle-btn .toggle-icon span:nth-child(1) {
    transform: translateY(8.5px) rotate(45deg);
    width: 24px;
  }
  body.sidebar-open .sidebar-toggle-btn .toggle-icon span:nth-child(2) {
    opacity: 0;
    transform: scaleX(0);
  }
  body.sidebar-open .sidebar-toggle-btn .toggle-icon span:nth-child(3) {
    transform: translateY(-8.5px) rotate(-45deg);
    width: 24px;
  }

  /* ===== السايد بار نفسه ===== */
  .app-sidebar {
    position: fixed;
    top: 0;
    right: 0;
    width: 320px;
    height: 100vh;
    background: rgba(10, 18, 35, 0.92);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-left: 1px solid rgba(255, 255, 255, 0.06);
    padding: 28px 20px 24px;
    z-index: 9998;
    overflow-y: auto;
    transform: translateX(100%);
    transition: transform 0.6s cubic-bezier(0.23, 1, 0.32, 1), box-shadow 0.6s ease;
    display: flex;
    flex-direction: column;
    box-shadow: -20px 0 60px rgba(0, 0, 0, 0.6);
    /* شكل منحني في الزوايا اليمنى */
    border-radius: 40px 0 0 40px;
    /* إضاءة متوهجة على الحافة */
    box-shadow: -10px 0 50px rgba(251, 191, 36, 0.03), -20px 0 80px rgba(0, 0, 0, 0.5);
  }

  /* عند الفتح */
  .app-sidebar.open {
    transform: translateX(0);
    box-shadow: -30px 0 80px rgba(0, 0, 0, 0.7), -5px 0 40px rgba(251, 191, 36, 0.05);
  }

  /* ===== رأس السايد بار ===== */
  .sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    margin-bottom: 20px;
  }

  .sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.5rem;
    font-weight: 900;
    color: #fff;
  }

  .brand-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #fbbf24;
    display: inline-block;
    box-shadow: 0 0 30px rgba(251, 191, 36, 0.3);
    animation: pulse-dot 2s ease-in-out infinite;
  }

  @keyframes pulse-dot {
    0%, 100% { box-shadow: 0 0 20px rgba(251, 191, 36, 0.2); }
    50% { box-shadow: 0 0 40px rgba(251, 191, 36, 0.5); }
  }

  .brand-name {
    background: linear-gradient(135deg, #fff 60%, #fbbf24);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .sidebar-close-btn {
    background: rgba(255, 255, 255, 0.04);
    border: none;
    color: rgba(255, 255, 255, 0.5);
    font-size: 22px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .sidebar-close-btn:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    transform: rotate(90deg);
  }

  /* ===== حلقة التقدم ===== */
  .sidebar-ring {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px 0 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    margin-bottom: 16px;
  }

  .story-ring {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    text-decoration: none;
    transition: transform 0.3s ease;
  }

  .story-ring:hover {
    transform: scale(1.05);
  }

  .story-ring svg {
    width: 60px;
    height: 60px;
    transform: rotate(-90deg);
  }

  .ring-bg {
    fill: none;
    stroke: rgba(255, 255, 255, 0.06);
    stroke-width: 3;
  }

  .ring-fg {
    fill: none;
    stroke: #fbbf24;
    stroke-width: 3;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.8s ease;
    filter: drop-shadow(0 0 10px rgba(251, 191, 36, 0.3));
  }

  .ring-label {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 14px;
    font-weight: 800;
    color: #fff;
    white-space: nowrap;
  }

  .ring-text {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.35);
    margin-top: 6px;
    font-weight: 600;
    letter-spacing: 0.5px;
  }

  /* ===== قائمة الروابط ===== */
  .sidebar-nav {
    list-style: none;
    padding: 0;
    margin: 0;
    flex: 1;
    overflow-y: auto;
  }

  .sidebar-nav li {
    margin-bottom: 2px;
  }

  .sidebar-nav a {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 18px;
    border-radius: 16px;
    color: rgba(255, 255, 255, 0.5);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
    position: relative;
    overflow: hidden;
  }

  /* تأثير الإضاءة الخلفية للرابط */
  .sidebar-nav a .nav-glow {
    position: absolute;
    inset: 0;
    border-radius: 16px;
    background: radial-gradient(circle at 100% 50%, rgba(251, 191, 36, 0.08), transparent 70%);
    opacity: 0;
    transition: opacity 0.4s ease;
    pointer-events: none;
  }

  .sidebar-nav a:hover .nav-glow {
    opacity: 1;
  }

  .sidebar-nav a:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.04);
    transform: translateX(-6px);
  }

  .sidebar-nav a.active {
    color: #fbbf24;
    background: rgba(251, 191, 36, 0.06);
    box-shadow: inset 4px 0 0 #fbbf24, 0 0 20px rgba(251, 191, 36, 0.03);
  }

  .sidebar-nav a.active .nav-glow {
    opacity: 1;
    background: radial-gradient(circle at 100% 50%, rgba(251, 191, 36, 0.12), transparent 70%);
  }

  .sidebar-nav .nav-icon {
    font-size: 1.3rem;
    width: 30px;
    text-align: center;
    flex-shrink: 0;
  }

  .sidebar-nav .nav-label {
    font-size: 0.95rem;
  }

  .sidebar-nav .nav-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.04);
    margin: 12px 16px;
  }

  .sidebar-nav .logout-link {
    color: #f87171 !important;
  }

  .sidebar-nav .logout-link:hover {
    background: rgba(239, 68, 68, 0.06) !important;
    color: #fca5a5 !important;
  }

  /* ===== تذييل السايد بار ===== */
  .sidebar-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.04);
    padding-top: 16px;
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: center;
  }

  .premium-badge,
  .free-badge {
    padding: 4px 20px;
    border-radius: 40px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.3px;
  }

  .premium-badge {
    background: rgba(34, 197, 94, 0.12);
    color: #86efac;
    border: 1px solid rgba(34, 197, 94, 0.15);
  }

  .free-badge {
    background: rgba(251, 191, 36, 0.08);
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, 0.1);
  }

  .sidebar-voice {
    display: flex;
    gap: 8px;
  }

  .sidebar-voice button {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.4);
    padding: 6px 14px;
    border-radius: 30px;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.3s ease;
  }

  .sidebar-voice button:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #fff;
  }

  .sidebar-voice button.on {
    background: rgba(251, 191, 36, 0.08);
    border-color: rgba(251, 191, 36, 0.15);
    color: #fbbf24;
  }

  /* ===== طبقة التغطية ===== */
  .sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 9997;
    opacity: 0;
    visibility: hidden;
    transition: all 0.5s ease;
  }

  .sidebar-overlay.open {
    opacity: 1;
    visibility: visible;
  }

  /* ===== منع التمرير عند فتح السايد بار ===== */
  body.sidebar-open {
    overflow: hidden;
  }

  /* ===== سكرول السايد بار ===== */
  .app-sidebar::-webkit-scrollbar {
    width: 4px;
  }
  .app-sidebar::-webkit-scrollbar-track {
    background: transparent;
  }
  .app-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 10px;
  }
  .app-sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.15);
  }

  /* ===== التجاوب للشاشات الصغيرة ===== */
  @media (max-width: 600px) {
    .app-sidebar {
      width: 100%;
      max-width: 320px;
      border-radius: 30px 0 0 30px;
    }

    .sidebar-toggle-btn {
      top: 16px;
      right: 16px;
      width: 44px;
      height: 44px;
    }

    .sidebar-toggle-btn .toggle-icon {
      width: 20px;
      height: 17px;
    }

    .sidebar-toggle-btn .toggle-icon span {
      height: 2px;
    }

    .sidebar-toggle-btn .toggle-icon span:nth-child(1),
    .sidebar-toggle-btn .toggle-icon span:nth-child(3) {
      width: 20px;
    }

    body.sidebar-open .sidebar-toggle-btn .toggle-icon span:nth-child(1),
    body.sidebar-open .sidebar-toggle-btn .toggle-icon span:nth-child(3) {
      width: 20px;
    }
  }
</style>
