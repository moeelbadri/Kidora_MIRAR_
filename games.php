<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
$progress = ensure_daily_progress($pdo, $child['id']);

$stmt = $pdo->prepare("SELECT * FROM games WHERE age_min <= ? AND age_max >= ? ORDER BY category, id");
$stmt->execute([$child['age'], $child['age']]);
$games = $stmt->fetchAll();
if (!$games) $games = $pdo->query("SELECT * FROM games ORDER BY category, id")->fetchAll();

// غير المشترك يرى عيّنة من المكتبة فقط. اللعبة التالية لكل مهمة تبقى مجانية.
$isPremium = is_premium_active($pdo, (int)$child['id']);
$totalGames = count($games);
$games = visible_library_games($games, $isPremium);
$lockedCount = $totalGames - count($games);

$categories = [];
foreach ($games as $g) { $categories[$g['category']][] = $g; }

$CATEGORY_META = [
    'تربوي'  => ['icon'=>'📚','color'=>'#6C63FF'],
    'علمي'   => ['icon'=>'🔬','color'=>'#2EC4B6'],
    'اجتماعي'=> ['icon'=>'🤝','color'=>'#FF6FA5'],
    'سلوكي'  => ['icon'=>'🧠','color'=>'#FFC93C'],
    'ثقافي'  => ['icon'=>'🕌','color'=>'#FF7A50'],
    'صحي'    => ['icon'=>'🏃','color'=>'#4CAF6D'],
];

$__pageTitle = 'مكتبة الألعاب — Kidora';
$__pageLine = "يلا نلعب شوي! اخترلك ألعاب حلوة بكل المجالات 🎮";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">مكتبة الألعاب</div>
    <h2 class="section-title">ألعاب مناسبة لعمرك (<?php echo (int)$child['age']; ?> سنوات)</h2>
    <p class="section-sub">
      <?php if ($isPremium): ?>
        ألعاب متنوعة تربوية وعلمية واجتماعية وسلوكية وثقافية — العب واحدة على الأقل لتفتح قصتك اليومية لاحقاً!
      <?php else: ?>
        هاتان لعبتاك المجانيتان لليوم. مع الاشتراك تُفتح المكتبة كاملة 🎮
      <?php endif; ?>
    </p>
    <?php if (game_is_calm_age((int)$child['age'])): ?>
      <p class="section-sub" style="color:var(--mint);">ألعابك بلا مؤقّت، وبتنقرأ عليك بصوت صاحبك 🔊</p>
    <?php endif; ?>
  </div>

  <p style="text-align:center;font-weight:800;color:var(--gold);">ألعاب اليوم: <span id="gamesPlayedLabel"><?php echo (int)$progress['games_played']; ?></span> 🎮</p>

  <?php foreach ($categories as $catName => $catGames): $meta = $CATEGORY_META[$catName] ?? ['icon'=>'🎮','color'=>'#6C63FF']; ?>
    <div class="section" style="padding:20px 0;">
      <h3 style="color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.35);"><?php echo $meta['icon']; ?> <?php echo h($catName); ?></h3>
      <div class="friend-grid">
        <?php foreach ($catGames as $g): ?>
          <div class="friend-card card" style="border-top:5px solid <?php echo h($meta['color']); ?>;">
            <div class="fchar"><div class="fe" style="background:<?php echo h($meta['color']); ?>;"><?php echo $meta['icon']; ?></div><div><b><?php echo h($g['title']); ?></b><div style="font-size:12px;color:var(--ink-soft);"><?php echo h($catName); ?></div></div></div>
            <button class="btn btn-sm btn-primary" onclick="playGame(this)"
                    data-type="<?php echo h($g['type']); ?>"
                    data-title="<?php echo h($g['title']); ?>"
                    data-color="<?php echo h($meta['color']); ?>"
                    data-category="<?php echo h($catName); ?>">▶ العب الآن</button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if ($lockedCount > 0): ?>
    <div class="card" style="max-width:520px;margin:24px auto 0;padding:26px;text-align:center;">
      <div style="font-size:40px;">🔓</div>
      <h3 style="color:var(--ink);">في <?php echo (int)$lockedCount; ?> لعبة كمان مستنيّاك!</h3>
      <p style="color:var(--ink-soft);">مع الاشتراك تُفتح المكتبة كاملة، وكذلك قصتك اليومية المتحركة.</p>
      <a class="btn btn-primary" href="<?php echo BASE_PATH; ?>/subscriptions.php">شوف الاشتراكات 💳</a>
    </div>
  <?php endif; ?>

  <div id="gameHost" style="margin-top:20px;"></div>

  <div style="text-align:center;margin:30px 0;">
    <a href="safety.php" class="btn btn-mint">تعال بعدها لقسم الحماية معي 🛡️</a>
    <a href="story.php" class="btn btn-primary">اذهب لقصتي اليومية ✨</a>
  </div>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
/* 6 آليات لعب مختلفة فعلياً موحّدة عبر assets/js/games-engine.js */

function playGame(btn){
  const host = document.getElementById('gameHost');
  GamesEngine.run(btn.dataset.type, host, btn.dataset.title, btn.dataset.color, () => {
    fetch(window.KIDAURA_BASE + '/api/play-game.php', {method:'POST'})
      .then(r=>r.json()).then(data => {
        if (data.ok) document.getElementById('gamesPlayedLabel').textContent = data.games_played;
        window.companionSay('أحسنت! لعبة رائعة 🎮');
      });
  }, { category: btn.dataset.category });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
