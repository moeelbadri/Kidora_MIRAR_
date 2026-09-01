<?php
require_once __DIR__ . '/includes/guard.php';

$tab = $_GET['tab'] ?? 'overview';
$validTabs = ['overview','users','characters','tasks','games','gamecontent','assessment','history','subscriptions','institutions','settings'];
if (!in_array($tab, $validTabs, true)) $tab = 'overview';

$TAB_LABELS = [
    'overview' => '📊 نظرة عامة', 'users' => '👨‍👩‍👧 المستخدمون', 'characters' => '🐾 الشخصيات',
    'tasks' => '🎯 المهام', 'games' => '🎮 الألعاب', 'gamecontent' => '🧩 محتوى الألعاب',
    'assessment' => '📝 أسئلة التحليل', 'history' => '🕌 الشخصيات التاريخية',
    'subscriptions' => '💳 الاشتراكات', 'institutions' => '🏫 المؤسسات', 'settings' => '⚙️ الإعدادات',
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة تحكم Kidora</title>
<link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/main.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<nav class="app-nav">
  <div class="container">
    <div class="nav-brand"><span class="dot"></span> Kidora Admin</div>
    <a href="logout.php" class="btn btn-ghost btn-sm">↩️ تسجيل خروج للمنصة</a>
  </div>
</nav>

<div class="admin-shell">
  <aside class="admin-sidebar">
    <?php foreach ($TAB_LABELS as $key => $label): ?>
      <a class="admin-tab <?php echo $tab === $key ? 'active' : ''; ?>" href="?tab=<?php echo $key; ?>"><?php echo $label; ?></a>
    <?php endforeach; ?>
  </aside>
  <section class="admin-content">
    <?php require __DIR__ . '/tabs/' . $tab . '.php'; ?>
  </section>
</div>

<div class="toast-wrap" id="toastWrap"></div>
<?php if (!empty($_SESSION['admin_flash'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const wrap = document.getElementById('toastWrap');
  const el = document.createElement('div'); el.className='toast'; el.textContent = <?php echo json_encode($_SESSION['admin_flash'], JSON_UNESCAPED_UNICODE); ?>;
  wrap.appendChild(el); setTimeout(()=>el.remove(), 3500);
});
</script>
<?php unset($_SESSION['admin_flash']); endif; ?>
</body>
</html>
