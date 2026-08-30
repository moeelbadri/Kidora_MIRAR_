<?php
// navbar.php - هيدر مع قائمة أساسية و Dropdown للمزيد (لللاب)، وسايد بار للجوال
$__navChild = $child ?? $__headerChild ?? null;
if (!$__navChild) return;

$__ringDays = min(30, (int)$__navChild['ring_days']);
$__circumference = 119;
$__offset = $__circumference - ($__circumference * $__ringDays / 30);
$__currentPage = basename($_SERVER['PHP_SELF']);

// ===== تقسيم الروابط إلى (أساسية) و (ثانوية) =====
$__primaryItems = [
    'dashboard.php' => ['label' => 'الرئيسية', 'icon' => '🏠'],
    'tasks.php' => ['label' => 'مهامي', 'icon' => '📋'],
    'story.php' => ['label' => 'قصتي اليومية', 'icon' => '📖'],
    'friends.php' => ['label' => 'قصص أصدقائي', 'icon' => '👫'],
    'games.php' => ['label' => 'ألعابي', 'icon' => '🎮'],
];

$__secondaryItems = [
    'assessment.php' => ['label' => 'أسئلة التحليل', 'icon' => '📊'],
    'culture.php' => ['label' => 'قصص ثقافية', 'icon' => '🌍'],
    'grand-story.php' => ['label' => 'مغامرتي الكبرى', 'icon' => '🏰'],
    'safety.php' => ['label' => 'الحماية', 'icon' => '🛡️'],
    'subscriptions.php' => ['label' => 'الاشتراك', 'icon' => '💎'],
    'games2.php' => ['label' => 'معرض الألعاب', 'icon' => '🎯'],
    'profile.php' => ['label' => 'ملفي الشخصي', 'icon' => '👤'],
];

// جميع العناصر (للسايد بار في الجوال)
$__allItems = array_merge($__primaryItems, $__secondaryItems);
?>

<!-- ===== الهيدر الثابت ===== -->
<header class="app-header" id="appHeader">
  <div class="header-inner">

    <!-- ===== الشعار (أفقي بسيط) ===== -->
    <a href="<?php echo BASE_PATH; ?>/dashboard.php" class="logo-link">
      <div class="logo-icon">
        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
          <circle cx="50" cy="50" r="46" fill="#3b82f6" />
          <path d="M30 20 L30 80 M30 50 L58 20 M30 50 L58 80"
                stroke="#fff" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M35 26 L35 74 M35 50 L55 26 M35 50 L55 74"
                stroke="#fcd34d" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
          <circle cx="18" cy="20" r="3" fill="#fbbf24" />
          <circle cx="78" cy="20" r="3" fill="#fbbf24" />
          <circle cx="18" cy="80" r="3" fill="#fbbf24" />
          <circle cx="78" cy="80" r="3" fill="#fbbf24" />
          <circle cx="50" cy="10" r="3" fill="#fbbf24" />
        </svg>
      </div>
      <span class="logo-name">Kidora</span>
    </a>

    <!-- ===== حلقة التقدم المصغرة ===== -->
    <div class="header-ring">
      <a class="story-ring" href="<?php echo BASE_PATH; ?>/grand-story.php">
        <svg viewBox="0 0 46 46">
          <circle class="ring-bg" cx="23" cy="23" r="19"></circle>
          <circle class="ring-fg" id="headerRingFg" cx="23" cy="23" r="19"
                  stroke-dasharray="<?php echo $__circumference; ?>"
                  stroke-dashoffset="<?php echo $__offset; ?>"></circle>
        </svg>
        <span class="ring-label"><?php echo $__ringDays; ?>/30</span>
      </a>
    </div>

    <!-- ===== القائمة الأفقية (لللاب توب فقط) ===== -->
    <nav class="header-nav">
      <ul class="nav-list">
        <!-- العناصر الأساسية -->
        <?php foreach ($__primaryItems as $file => $item): ?>
          <li>
            <a href="<?php echo BASE_PATH . '/' . $file; ?>" class="<?php echo $__currentPage === $file ? 'active' : ''; ?>">
              <span class="nav-icon"><?php echo $item['icon']; ?></span>
              <span class="nav-label"><?php echo $item['label']; ?></span>
            </a>
          </li>
        <?php endforeach; ?>

        <!-- زر المزيد + القائمة المنسدلة -->
        <li class="nav-dropdown">
          <button class="dropdown-toggle" id="dropdownToggle">
            <span class="nav-icon">📂</span>
            <span class="nav-label">المزيد</span>
            <span class="dropdown-arrow">▼</span>
          </button>
          <ul class="dropdown-menu" id="dropdownMenu">
            <?php foreach ($__secondaryItems as $file => $item): ?>
              <li>
                <a href="<?php echo BASE_PATH . '/' . $file; ?>" class="<?php echo $__currentPage === $file ? 'active' : ''; ?>">
                  <span class="nav-icon"><?php echo $item['icon']; ?></span>
                  <span class="nav-label"><?php echo $item['label']; ?></span>
                </a>
              </li>
            <?php endforeach; ?>
            <li class="dropdown-divider"></li>
            <li>
              <a href="<?php echo BASE_PATH; ?>/index.php?logout=1" class="logout-link">
                <span class="nav-icon">🚪</span>
                <span class="nav-label">خروج</span>
              </a>
            </li>
          </ul>
        </li>
      </ul>
    </nav>

    <!-- ===== أزرار الصوت وحالة الاشتراك (لللاب) ===== -->
    <div class="header-actions">
      <?php if (function_exists('is_premium_active') && is_premium_active($GLOBALS['pdo'], (int)$__navChild['id'])): ?>
        <span class="premium-badge">🌟 VIP</span>
      <?php else: ?>
        <span class="free-badge">🔓 مجاني</span>
      <?php endif; ?>
      <div class="header-voice">
        <button class="voice-btn on" id="voiceToggle">🗣️</button>
        <button class="music-btn" id="musicToggle">🔇</button>
      </div>
    </div>

    <!-- ===== زر فتح القائمة (للجوال فقط) ===== -->
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="تبديل القائمة">
      <span class="toggle-icon">
        <span></span><span></span><span></span>
      </span>
    </button>

  </div>
</header>

<!-- ===== السايد بار المنزلق (للجوال فقط - يحتوي على جميع الروابط) ===== -->
<aside class="app-sidebar" id="appSidebar">
  <div class="sidebar-header">
    <div class="sidebar-brand">
      <span class="brand-dot"></span>
      <span class="brand-name">Kidora</span>
    </div>
    <button class="sidebar-close-btn" id="sidebarCloseBtn">✕</button>
  </div>

  <ul class="sidebar-nav">
    <?php foreach ($__allItems as $file => $item): ?>
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

  <div class="sidebar-footer">
    <?php if (function_exists('is_premium_active') && is_premium_active($GLOBALS['pdo'], (int)$__navChild['id'])): ?>
      <span class="premium-badge">🌟 مشترك VIP</span>
    <?php else: ?>
      <span class="free-badge">🔓 حساب مجاني</span>
    <?php endif; ?>
    <div class="sidebar-voice">
      <button class="voice-btn on" id="voiceToggleSidebar">🗣️</button>
      <button class="music-btn" id="musicToggleSidebar">🔇</button>
    </div>
  </div>
</aside>

<!-- طبقة التغطية الخلفية (للجوال فقط) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
  // ===== التحكم بالسايد بار (الجوال) =====
  const sidebar = document.getElementById('appSidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const toggleBtn = document.getElementById('sidebarToggleBtn');
  const closeBtn = document.getElementById('sidebarCloseBtn');

  function isMobile() {
    return window.innerWidth < 1024;
  }

  function toggleSidebar() {
    if (!isMobile()) return;
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
    document.body.classList.toggle('sidebar-open');
  }

  function closeSidebar() {
    if (!isMobile()) return;
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    document.body.classList.remove('sidebar-open');
  }

  if (toggleBtn) toggleBtn.addEventListener('click', toggleSidebar);
  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeSidebar();
  });

  // ===== التحكم بالقائمة المنسدلة (Dropdown) في اللاب توب =====
  const dropdownToggle = document.getElementById('dropdownToggle');
  const dropdownMenu = document.getElementById('dropdownMenu');

  if (dropdownToggle && dropdownMenu) {
    dropdownToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      dropdownMenu.classList.toggle('open');
      this.classList.toggle('open');
    });

    // إغلاق القائمة عند الضغط في أي مكان آخر
    document.addEventListener('click', function() {
      dropdownMenu.classList.remove('open');
      if (dropdownToggle) dropdownToggle.classList.remove('open');
    });

    // منع إغلاق القائمة عند الضغط داخلها
    dropdownMenu.addEventListener('click', function(e) {
      e.stopPropagation();
    });
  }

  // ===== مزامنة أزرار الصوت =====
  const voiceBtns = document.querySelectorAll('.voice-btn');
  const musicBtns = document.querySelectorAll('.music-btn');

  voiceBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      this.classList.toggle('on');
      voiceBtns.forEach(b => {
        if (b !== this) b.classList.toggle('on');
      });
    });
  });

  musicBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      this.classList.toggle('on');
      musicBtns.forEach(b => {
        if (b !== this) b.classList.toggle('on');
      });
    });
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
     استيراد خط Tajawal الأنيق
     ====================================================== */
  @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

  /* ======================================================
     الأنماط العامة
     ====================================================== */
  .app-header,
  .app-sidebar,
  .app-header *,
  .app-sidebar * {
    font-family: 'Tajawal', sans-serif;
  }

  /* ===== الهيدر الثابت ===== */
  .app-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    background: rgba(10, 18, 35, 0.92);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
    padding: 8px 20px;
    height: 64px;
    display: flex;
    align-items: center;
  }

  .header-inner {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  /* ======================================================
     الشعار (أفقي الآن)
     ====================================================== */
  .logo-link {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: transform 0.3s ease;
    flex-shrink: 0;
  }

  .logo-link:hover {
    transform: scale(1.03);
  }

  .logo-icon {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    filter: drop-shadow(0 2px 10px rgba(0, 0, 0, 0.2));
  }

  .logo-icon svg {
    width: 100%;
    height: 100%;
    display: block;
  }

  .logo-name {
    font-size: 1.4rem;
    font-weight: 900;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.5px;
  }

  /* ===== حلقة التقدم ===== */
  .header-ring {
    display: flex;
    align-items: center;
    flex-shrink: 0;
  }

  .story-ring {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    text-decoration: none;
    transition: transform 0.3s ease;
  }

  .story-ring:hover {
    transform: scale(1.05);
  }

  .story-ring svg {
    width: 44px;
    height: 44px;
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
    font-size: 11px;
    font-weight: 800;
    color: #fff;
    white-space: nowrap;
  }

  /* ======================================================
     القائمة الأفقية و Dropdown (لللاب)
     ====================================================== */
  .header-nav {
    display: none;
    flex: 1;
    justify-content: center;
    min-width: 0;
  }

  .nav-list {
    display: flex;
    flex-wrap: nowrap;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 2px;
    align-items: center;
  }

  .nav-list li {
    margin: 0;
  }

  .nav-list a,
  .dropdown-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 40px;
    color: rgba(255, 255, 255, 0.5);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    white-space: nowrap;
    background: none;
    border: none;
    cursor: pointer;
    font-family: 'Tajawal', sans-serif;
  }

  .nav-list a:hover,
  .dropdown-toggle:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.05);
    transform: translateY(-2px);
  }

  .nav-list a.active {
    color: #fbbf24;
    background: rgba(251, 191, 36, 0.06);
    box-shadow: inset 0 2px 0 #fbbf24;
  }

  .nav-list .nav-icon {
    font-size: 1.1rem;
  }

  .dropdown-arrow {
    font-size: 0.6rem;
    margin-left: 2px;
    transition: transform 0.3s ease;
  }

  .dropdown-toggle.open .dropdown-arrow {
    transform: rotate(180deg);
  }

  /* ===== القائمة المنسدلة (شبكية) ===== */
  .nav-dropdown {
    position: relative;
  }

  .dropdown-menu {
    display: none;
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    min-width: 340px;
    background: rgba(10, 18, 35, 0.96);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 20px;
    padding: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    list-style: none;
    display: grid;
    grid-template-columns: 1fr 1fr; /* عمودين */
    gap: 4px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px) scale(0.95);
    transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
    z-index: 1000;
  }

  .dropdown-menu.open {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
  }

  .dropdown-menu li {
    margin: 0;
  }

  .dropdown-menu a {
    padding: 8px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    justify-content: flex-start;
    white-space: nowrap;
    width: 100%;
  }

  .dropdown-menu a .nav-icon {
    font-size: 1rem;
    width: 24px;
    text-align: center;
  }

  .dropdown-menu a.active {
    background: rgba(251, 191, 36, 0.08);
    color: #fbbf24;
    box-shadow: inset 0 0 0 1px rgba(251, 191, 36, 0.1);
  }

  .dropdown-menu a:hover {
    background: rgba(255, 255, 255, 0.04);
    transform: translateX(-4px) !important;
  }

  .dropdown-menu .dropdown-divider {
    grid-column: 1 / -1; /* يمتد على العمودين */
    height: 1px;
    background: rgba(255, 255, 255, 0.04);
    margin: 6px 0;
  }

  .dropdown-menu .logout-link {
    color: #f87171 !important;
  }

  .dropdown-menu .logout-link:hover {
    background: rgba(239, 68, 68, 0.06) !important;
    color: #fca5a5 !important;
  }

  /* ===== أزرار الصوت وحالة الاشتراك ===== */
  .header-actions {
    display: none;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
  }

  .premium-badge,
  .free-badge {
    padding: 2px 12px;
    border-radius: 40px;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    white-space: nowrap;
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

  .header-voice {
    display: flex;
    gap: 4px;
  }

  .header-voice button {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.4);
    padding: 4px 8px;
    border-radius: 30px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.3s ease;
  }

  .header-voice button:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #fff;
  }

  .header-voice button.on {
    background: rgba(251, 191, 36, 0.08);
    border-color: rgba(251, 191, 36, 0.15);
    color: #fbbf24;
  }

  /* ===== زر الهامبرغر (للجوال) ===== */
  .sidebar-toggle-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.04);
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    flex-shrink: 0;
  }

  .sidebar-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.08);
  }

  .sidebar-toggle-btn .toggle-icon {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    width: 20px;
    height: 17px;
  }

  .sidebar-toggle-btn .toggle-icon span {
    display: block;
    height: 2.5px;
    border-radius: 4px;
    background: #fff;
    transition: all 0.4s ease;
  }

  .sidebar-toggle-btn .toggle-icon span:nth-child(1) { width: 20px; }
  .sidebar-toggle-btn .toggle-icon span:nth-child(2) { width: 14px; }
  .sidebar-toggle-btn .toggle-icon span:nth-child(3) { width: 20px; }

  body.sidebar-open .sidebar-toggle-btn .toggle-icon span:nth-child(1) {
    transform: translateY(7px) rotate(45deg);
    width: 20px;
  }
  body.sidebar-open .sidebar-toggle-btn .toggle-icon span:nth-child(2) {
    opacity: 0;
    transform: scaleX(0);
  }
  body.sidebar-open .sidebar-toggle-btn .toggle-icon span:nth-child(3) {
    transform: translateY(-7px) rotate(-45deg);
    width: 20px;
  }

  /* ===== السايد بار (الجوال) ===== */
  .app-sidebar {
    position: fixed;
    top: 0;
    right: 0;
    width: 300px;
    height: 100vh;
    background: rgba(10, 18, 35, 0.95);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-left: 1px solid rgba(255, 255, 255, 0.06);
    padding: 24px 18px 20px;
    z-index: 9998;
    overflow-y: auto;
    transform: translateX(100%);
    transition: transform 0.5s cubic-bezier(0.23, 1, 0.32, 1), box-shadow 0.5s ease;
    display: flex;
    flex-direction: column;
    box-shadow: -20px 0 60px rgba(0, 0, 0, 0.6);
    border-radius: 30px 0 0 30px;
  }

  .app-sidebar.open {
    transform: translateX(0);
    box-shadow: -30px 0 80px rgba(0, 0, 0, 0.7);
  }

  .sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    margin-bottom: 16px;
  }

  .sidebar-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.4rem;
    font-weight: 900;
    color: #fff;
    white-space: nowrap;
  }

  .brand-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #fbbf24;
    display: inline-block;
    box-shadow: 0 0 30px rgba(251, 191, 36, 0.3);
    animation: pulse-dot 2s ease-in-out infinite;
    flex-shrink: 0;
  }

  @keyframes pulse-dot {
    0%, 100% { box-shadow: 0 0 20px rgba(251, 191, 36, 0.2); }
    50% { box-shadow: 0 0 40px rgba(251, 191, 36, 0.5); }
  }

  .sidebar-brand .brand-name {
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
    padding: 12px 16px;
    border-radius: 16px;
    color: rgba(255, 255, 255, 0.5);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
  }

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
    box-shadow: inset 4px 0 0 #fbbf24;
  }

  .sidebar-nav .nav-icon {
    font-size: 1.2rem;
    width: 28px;
    text-align: center;
    flex-shrink: 0;
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

  .sidebar-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.04);
    padding-top: 16px;
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: center;
  }

  .sidebar-footer .premium-badge,
  .sidebar-footer .free-badge {
    padding: 4px 20px;
    font-size: 0.75rem;
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

  body.sidebar-open {
    overflow: hidden;
  }

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

  /* ======================================================
     التجاوب: اللاب توب (≥ 1024px)
     ====================================================== */
  @media (min-width: 1024px) {
    .app-header {
      height: 72px;
      padding: 0 30px;
    }

    .header-nav {
      display: flex;
    }
    .header-actions {
      display: flex;
    }

    .sidebar-toggle-btn {
      display: none !important;
    }
    .app-sidebar {
      display: none !important;
    }
    .sidebar-overlay {
      display: none !important;
    }

    .story-ring {
      width: 48px;
      height: 48px;
    }
    .story-ring svg {
      width: 48px;
      height: 48px;
    }
    .ring-label {
      font-size: 12px;
    }

    .logo-icon {
      width: 40px;
      height: 40px;
    }
    .logo-name {
      font-size: 1.5rem;
    }
  }

  /* ======================================================
     التجاوب: الجوال (≤ 600px)
     ====================================================== */
  @media (max-width: 600px) {
    .app-header {
      height: 56px;
      padding: 0 12px;
    }

    .logo-icon {
      width: 30px;
      height: 30px;
    }
    .logo-name {
      font-size: 1.1rem;
    }

    .story-ring {
      width: 36px;
      height: 36px;
    }
    .story-ring svg {
      width: 36px;
      height: 36px;
    }
    .ring-label {
      font-size: 9px;
    }

    .sidebar-toggle-btn {
      width: 38px;
      height: 38px;
    }
    .sidebar-toggle-btn .toggle-icon {
      width: 18px;
      height: 15px;
    }
    .sidebar-toggle-btn .toggle-icon span {
      height: 2px;
    }
    .sidebar-toggle-btn .toggle-icon span:nth-child(1),
    .sidebar-toggle-btn .toggle-icon span:nth-child(3) {
      width: 18px;
    }
    body.sidebar-open .sidebar-toggle-btn .toggle-icon span:nth-child(1),
    body.sidebar-open .sidebar-toggle-btn .toggle-icon span:nth-child(3) {
      width: 18px;
    }

    .app-sidebar {
      width: 280px;
      padding: 20px 14px 16px;
    }
  }
</style>
