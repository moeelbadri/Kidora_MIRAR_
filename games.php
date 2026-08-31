<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
$progress = ensure_daily_progress($pdo, $child['id']);

$stmt = $pdo->prepare("SELECT * FROM games WHERE age_min <= ? AND age_max >= ? ORDER BY category, id");
$stmt->execute([$child['age'], $child['age']]);
$games = $stmt->fetchAll();
if (!$games) $games = $pdo->query("SELECT * FROM games ORDER BY category, id")->fetchAll();

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
    <p class="section-sub">ألعاب متنوعة تربوية وعلمية واجتماعية وسلوكية وثقافية — العب واحدة على الأقل لتفتح قصتك اليومية لاحقاً!</p>
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
