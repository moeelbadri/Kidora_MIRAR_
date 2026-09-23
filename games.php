<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
$progress = ensure_daily_progress($pdo, $child['id']);

$games = $pdo->query("SELECT * FROM games ORDER BY category, id")->fetchAll();

// القصص والألعاب تبقى كاملة بعد انتهاء التجربة؛ الاشتراك يخص بقية المنصة.
$categories = [];
foreach ($games as $g) { $categories[$g['category']][] = $g; }

// لعبة اليوم المقترحة من الرفيق (ثابتة طول اليوم). ?from=tasks: جاء من إنهاء الباكج
$gameOfDay = game_of_the_day(array_values($games), (int)$child['id']);
$fromTasks = isset($_GET['from']) && $_GET['from'] === 'tasks';

$CATEGORY_META = [
    'تربوي'  => ['icon'=>'📚','color'=>'#6C63FF'],
    'علمي'   => ['icon'=>'🔬','color'=>'#2EC4B6'],
    'اجتماعي'=> ['icon'=>'🤝','color'=>'#FF6FA5'],
    'سلوكي'  => ['icon'=>'🧠','color'=>'#FFC93C'],
    'ثقافي'  => ['icon'=>'🕌','color'=>'#FF7A50'],
    'صحي'    => ['icon'=>'🏃','color'=>'#4CAF6D'],
];

$__pageTitle = 'مكتبة الألعاب — Kidora';
$__pageLine = $fromTasks && $gameOfDay
    ? "أنجزت مهامك كلها يا {$child['name']}! لعبة اليوم المقترحة لك: {$gameOfDay['title']}. اضغط عليها ويلا نلعب!"
    : ($gameOfDay ? "يلا نلعب! لعبة اليوم المقترحة لك: {$gameOfDay['title']} 🎮" : "يلا نلعب شوي! 🎮");
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">مكتبة الألعاب</div>
    <h2 class="section-title">كل الألعاب</h2>
    <p class="section-sub">
      ألعاب متنوعة تربوية وعلمية واجتماعية وسلوكية وثقافية — المكتبة كاملة متاحة للجميع، والعب واحدة على الأقل لتفتح قصتك اليومية!
    </p>
    <p class="section-sub" style="color:var(--mint);">ألعابك بلا مؤقّت، وبتنقرأ عليك بصوت صاحبك 🔊</p>
  </div>

  <p style="text-align:center;font-weight:800;color:var(--gold);">ألعاب اليوم: <span id="gamesPlayedLabel"><?php echo (int)$progress['games_played']; ?></span> 🎮</p>

  <?php if ($gameOfDay): $gm = $CATEGORY_META[$gameOfDay['category']] ?? ['icon'=>'🎮','color'=>'#6C63FF']; ?>
    <div class="card gd-hero" id="gameOfDay" style="--gd-color:<?php echo h($gm['color']); ?>;">
      <div class="gd-badge">⭐ لعبة اليوم المقترحة</div>
      <div class="gd-icon"><?php echo $gm['icon']; ?></div>
      <div class="gd-body">
        <h3><?php echo h($gameOfDay['title']); ?></h3>
        <p><?php echo h($gameOfDay['category']); ?> · اقتراح <?php echo h($__activeChar['name'] ?? 'رفيقك'); ?> لهذا اليوم</p>
      </div>
      <button class="btn btn-primary gd-play" id="gdPlayBtn" onclick="playGame(this)"
              data-type="<?php echo h($gameOfDay['type']); ?>" data-title="<?php echo h($gameOfDay['title']); ?>"
              data-color="<?php echo h($gm['color']); ?>" data-category="<?php echo h($gameOfDay['category']); ?>">▶ العب لعبة اليوم</button>
    </div>
  <?php endif; ?>

  <a href="<?php echo BASE_PATH; ?>/draw.php" class="card" style="display:flex;align-items:center;gap:16px;padding:16px 20px;margin:0 auto 6px;max-width:620px;border-right:6px solid var(--pink);text-decoration:none;color:var(--ink);">
    <span style="font-size:40px;">🎨</span>
    <span style="flex:1;"><b style="font-size:17px;">لوحتي — ارسم ما تشعر به</b><br><small style="color:var(--ink-soft);">مساحة حرّة بلا قواعد. أول لوحة تحفظها اليوم تُحسب من ألعاب اليوم</small></span>
    <span class="btn btn-sm btn-primary">ارسم الآن 🖌️</span>
  </a>

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
    <button type="button" class="btn btn-ghost" onclick="openChoice(false)">ماذا بعد اللعب؟ 🤔</button>
  </div>
</main>
</div>

<!-- اختيار ما بعد اللعب: بطل الأمان أو قصتي اليومية — يقدّمه الرفيق -->
<div class="choice-modal" id="choiceModal" aria-hidden="true">
  <div class="choice-card">
    <div class="choice-title">يلا نختار المحطة التالية!</div>
    <div class="choice-grid">
      <a href="safety.php" class="choice-opt choice-safety">
        <span class="choice-emoji">🛡️</span>
        <b>بطل الأمان</b>
        <small>مهمة سريعة تصنع منك بطلاً يحمي نفسه</small>
      </a>
      <a href="story.php" class="choice-opt choice-story">
        <span class="choice-emoji">📖</span>
        <b>قصتي اليومية</b>
        <small>قصة مغامرتك الحقيقية اليوم</small>
      </a>
    </div>
    <button type="button" class="choice-close" onclick="closeChoice()">أكمل اللعب هنا 🎮</button>
  </div>
</div>
<style>
  .gd-hero{ position:relative; display:flex; align-items:center; gap:18px; flex-wrap:wrap; max-width:620px; margin:6px auto 22px; padding:22px 22px 22px 26px; border-inline-start:8px solid var(--gd-color); animation:gdGlow 2.4s ease-in-out infinite; }
  @keyframes gdGlow{ 0%,100%{ box-shadow:0 12px 30px rgba(0,0,0,.18); } 50%{ box-shadow:0 16px 40px color-mix(in srgb, var(--gd-color) 55%, transparent); } }
  .gd-badge{ position:absolute; top:-14px; inset-inline-start:18px; background:linear-gradient(135deg,#ffe99a,#ffc93c); color:#241645; font-weight:900; font-size:13px; padding:5px 12px; border-radius:999px; box-shadow:0 6px 14px rgba(0,0,0,.2); }
  .gd-icon{ width:74px; height:74px; border-radius:22px; display:grid; place-items:center; font-size:40px; background:var(--gd-color); color:#fff; flex:0 0 74px; animation:companionHop 2.2s ease-in-out infinite; }
  .gd-body{ flex:1; min-width:180px; } .gd-body h3{ margin:0; color:var(--ink); font-size:22px; } .gd-body p{ margin:4px 0 0; color:var(--ink-soft); font-size:14px; }
  .gd-play{ min-height:58px; font-size:18px; padding:14px 26px; }
  .choice-modal{ position:fixed; inset:0; z-index:420; display:none; align-items:center; justify-content:center; padding:18px; background:rgba(3,2,13,.78); backdrop-filter:blur(10px); }
  .choice-modal.open{ display:flex; }
  .choice-card{ width:min(640px,100%); padding:28px 22px; border-radius:30px; background:linear-gradient(160deg,#241645,#100920); border:1px solid rgba(255,255,255,.2); box-shadow:0 30px 80px rgba(0,0,0,.5); text-align:center; color:#fff; animation:bubblePop .4s ease; }
  .choice-title{ font-family:var(--font-display); font-size:clamp(24px,4vw,32px); margin-bottom:18px; }
  .choice-grid{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .choice-opt{ display:grid; gap:6px; justify-items:center; padding:22px 14px; border-radius:24px; text-decoration:none; color:#fff; border:2px solid rgba(255,255,255,.18); transition:transform .2s, box-shadow .2s; min-height:170px; }
  .choice-opt:hover{ transform:translateY(-6px) scale(1.02); box-shadow:0 18px 40px rgba(0,0,0,.35); }
  .choice-safety{ background:linear-gradient(160deg,#2EC4B6,#1B8A80); } .choice-story{ background:linear-gradient(160deg,#FF6FA5,#B455D6); }
  .choice-emoji{ font-size:54px; animation:companionFloat 3s ease-in-out infinite; } .choice-opt b{ font-size:22px; } .choice-opt small{ font-size:13px; opacity:.92; line-height:1.6; }
  .choice-close{ margin-top:16px; color:#c9bfe6; font-weight:800; background:none; text-decoration:underline; text-underline-offset:4px; min-height:44px; }
  @media(max-width:520px){ .choice-grid{ grid-template-columns:1fr; } .choice-opt{ min-height:130px; } }
</style>
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

        /* ✅ علّم أن الطفل لعب لعبة اليوم — عشان شاشة الوداع في الداشبورد */
        try {
          var _t = new Date().toISOString().slice(0,10);
          localStorage.setItem('kidora_done_game_' + _t, '1');
        } catch(e){}

        openChoice(true);
      });
  }, { category: btn.dataset.category });
}

/* الاختيار بعد اللعب — الرفيق يقدّمه بصوته */
function openChoice(afterGame){
  const m = document.getElementById('choiceModal');
  m.classList.add('open'); m.setAttribute('aria-hidden', 'false');
  const line = afterGame
    ? 'أحسنت! لعبة رائعة! والآن اختر: بطل الأمان، مهمة سريعة تحميك… أو قصتي اليومية، حكاية مغامرتك الحقيقية اليوم! أيهما تختار؟'
    : 'اختر المحطة التالية: بطل الأمان أو قصتي اليومية!';
  if (window.Companion) Companion.say(line, { mood: 'cheer', hold: 2500 });
}
function closeChoice(){ const m = document.getElementById('choiceModal'); m.classList.remove('open'); m.setAttribute('aria-hidden', 'true'); if (window.Companion) Companion.stop(); }

<?php if ($fromTasks && $gameOfDay): ?>
document.addEventListener('DOMContentLoaded', function(){
  const hero = document.getElementById('gameOfDay');
  if (hero) { hero.scrollIntoView({ behavior: 'smooth', block: 'center' }); if (window.Companion) Companion.mood('point', 6000); }
});
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
