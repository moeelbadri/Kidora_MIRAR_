<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
ensure_daily_progress($pdo, $child['id']);

$myChars = array_filter([get_character($pdo, $child['character_1']), get_character($pdo, $child['character_2'])]);
$activePlan = get_active_plan($pdo, $child['id']);
$subRec = get_subscription_record($pdo, $child['id']);

// ---------------- القصص الموصى بها حسب أضعف محور تحليل ----------------
$axisStmt = $pdo->prepare("SELECT axis, AVG(value) avg_v, COUNT(*) c FROM quiz_history WHERE child_id = ? GROUP BY axis ORDER BY avg_v ASC LIMIT 1");
$axisStmt->execute([$child['id']]);
$weakAxis = $axisStmt->fetch();

$RECO_MAP = [
    'الثقة بالنفس' => ['icon'=>'🦸','title'=>'بطل يواجه خوفه','desc'=>'قصة عن بطل يتعلّم كيف يثق بصوته وقراراته.'],
    'المهارات الاجتماعية' => ['icon'=>'🤝','title'=>'صديق للجميع','desc'=>'قصة عن أهمية مشاركة المشاعر ومساعدة الأصدقاء.'],
    'الذكاء العاطفي' => ['icon'=>'🌈','title'=>'رحلة تهدئة القلب','desc'=>'قصة تعلّم كيف نتعرّف على مشاعرنا ونتعامل معها بهدوء.'],
    'الإبداع' => ['icon'=>'🎨','title'=>'عالم الألوان السحري','desc'=>'قصة تشجّع الخيال والابتكار في كل يوم.'],
    'التركيز' => ['icon'=>'🧩','title'=>'سرّ التركيز الخارق','desc'=>'قصة عن بطل يكتشف قوة التركيز خطوة بخطوة.'],
    'الأمان الشخصي' => ['icon'=>'🛡️','title'=>'درع الأمان','desc'=>'قصة تُرسّخ قواعد الحماية بأسلوب مغامرة شيّقة.'],
];
$recoList = $weakAxis ? [$RECO_MAP[$weakAxis['axis']] ?? reset($RECO_MAP)] : [];
foreach ($RECO_MAP as $k => $v) { if (count($recoList) >= 4) break; if (!in_array($v, $recoList, true)) $recoList[] = $v; }

$__pageTitle = 'الرئيسية — Kidora';
$__pageLine = "أهلاً {$child['name']}! أنا هنا أرافقك اليوم بكل حماس 🎉";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="page-body">
<main class="container" style="padding-top:26px;">

  <div class="welcome-banner">
    <div>
      <div class="eyebrow" style="color:#FFE9A8;">أهلاً بعودتك يا بطل!</div>
      <h2 class="section-title" style="margin:6px 0 4px;">مرحباً <?php echo h($child['name']); ?> 👋</h2>
      <p style="opacity:.92;">عمرك <?php echo (int)$child['age']; ?> سنوات · خطتك: <?php echo $activePlan ? h($activePlan['name']) : ($subRec ? h($subRec['name']).' (قيد المراجعة ⏳)' : '-'); ?></p>
    </div>
    <div class="welcome-points"><b><?php echo (int)$child['points']; ?></b><span>نقطة ⭐</span></div>
  </div>

  <div class="section">
    <div class="section-head">
      <div class="eyebrow">رفقاء البطل</div>
      <h2 class="section-title">شخصياتك المفضّلة</h2>
      <p class="section-sub">مرّر الفأرة أو المس البطاقة لتتعرّف أكثر على كل شخصية وصفاتها.</p>
    </div>
    <div class="characters-grid">
      <?php foreach ($myChars as $c): ?>
        <div class="character-card" style="--card-color:<?php echo h($c['color']); ?>;">
          <div class="character-media">
            <?php if (!empty($c['image_path'])): ?>
              <img src="<?php echo h($c['image_path']); ?>" alt="<?php echo h($c['name']); ?>">
            <?php else: ?>
              <div class="fallback-emoji" style="background:linear-gradient(150deg, <?php echo h($c['color']); ?>, #fff2);"><?php echo character_icons($c)[0] ?? '✨'; ?></div>
            <?php endif; ?>
            <div class="character-info-overlay">
              <h3><?php echo h($c['name']); ?></h3>
              <p><?php echo h($c['title']); ?></p>
              <p class="quote">"<?php echo h($c['trait']); ?>"</p>
            </div>
          </div>
          <div class="name"><?php echo h($c['name']); ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section">
    <div class="section-head">
      <div class="eyebrow">مخصّصة لك</div>
      <h2 class="section-title">قصص موصى بها حسب تحليل سلوك طفلك</h2>
      <p class="section-sub"><?php echo $weakAxis ? 'لاحظنا أن محور "'.h($weakAxis['axis']).'" يحتاج تعزيزاً بسيطاً، لذلك اخترنا لك هذه القصص 💜' : 'أجب عن أسئلة التحليل ليتم تخصيص القصص بدقة أكبر.'; ?></p>
    </div>
    <div class="reco-strip">
      <?php foreach ($recoList as $r): ?>
        <div class="reco-card">
          <div class="reco-cover"><?php echo $r['icon']; ?></div>
          <div class="reco-body">
            <b><?php echo h($r['title']); ?></b>
            <p style="font-size:13px;color:var(--ink-soft);margin:6px 0 10px;"><?php echo h($r['desc']); ?></p>
            <a class="btn btn-sm btn-primary" href="tasks.php">ابدأ المغامرة</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</main>
</div>
<footer class="site-footer">Kidora © 2026 — منصة الأطفال الذكية للتعلّم والمغامرة الآمنة</footer>
<script>window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
