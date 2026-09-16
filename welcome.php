<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();

$hero  = active_character($pdo, $child) ?: get_character($pdo, $child['character_1']);
$theme = character_theme($hero);
$icons = $hero ? character_icons($hero) : ['✨', '⭐', '🌟'];
$next  = needs_assessment($child) ? 'assessment.php' : 'dashboard.php';
$photo = !empty($child['photo_path']) ? BASE_PATH . '/' . ltrim($child['photo_path'], '/') : null;

// الرفيق يقود الترحيب بصوته ثم ينتقل تلقائياً — بلا زر «انتقل» إلا كبديل
$lines = [
    "أهلاً وسهلاً يا {$child['name']}! أنا {$hero['name']}، من {$theme['world']}.",
    "من اليوم أنا رفيق مغامرتك: أقرأ لك المهام، ونلعب معاً، ونحكي القصص.",
    needs_assessment($child)
        ? "قبل أن نبدأ، عندي أسئلة قليلة وسريعة لأعرفك أكثر. اختر ما يشبهك، ولا توجد إجابة خاطئة!"
        : "يلا ننطلق إلى مهام اليوم!",
];

$__pageTitle = 'أهلاً بك — Kidora';
$__pageLine = $lines[0];
require_once __DIR__ . '/includes/header.php';
?>
<style>
  .wl-stage{ position:relative; z-index:2; min-height:100vh; display:grid; place-items:center; padding:24px 16px 120px; perspective:1200px; overflow:hidden; }
  .wl-card{ position:relative; width:min(720px,100%); padding:40px 28px 34px; border-radius:36px; text-align:center; color:#fff;
    background:linear-gradient(160deg, rgba(255,255,255,.14), rgba(255,255,255,.04)); border:1px solid rgba(255,255,255,.22);
    box-shadow:0 40px 90px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.35); backdrop-filter:blur(18px); transform-style:preserve-3d;
    animation:wlCardIn 1.1s cubic-bezier(.34,1.4,.64,1) both, wlCardFloat 7s ease-in-out 1.2s infinite; }
  @keyframes wlCardIn{ 0%{ opacity:0; transform:rotateX(18deg) translateY(60px) scale(.9); } 100%{ opacity:1; transform:rotateX(0) translateY(0) scale(1); } }
  @keyframes wlCardFloat{ 0%,100%{ transform:rotateX(0) rotateY(0) translateY(0); } 50%{ transform:rotateX(2deg) rotateY(-2deg) translateY(-8px); } }
  .wl-hero{ position:relative; width:220px; height:220px; margin:-110px auto 10px; border-radius:50%; display:grid; place-items:center; font-size:110px;
    background:radial-gradient(circle at 32% 26%, rgba(255,255,255,.55), transparent 28%), linear-gradient(150deg, var(--theme-accent), rgba(10,6,26,.75));
    border:7px solid rgba(255,255,255,.75); box-shadow:0 30px 60px rgba(0,0,0,.45), 0 0 90px color-mix(in srgb, var(--theme-accent) 55%, transparent);
    transform:translateZ(60px); animation:wlHero 3.6s ease-in-out infinite; }
  .wl-hero img{ width:100%; height:100%; object-fit:cover; border-radius:50%; }
  @keyframes wlHero{ 0%,100%{ transform:translateZ(60px) translateY(0) rotate(-3deg); } 50%{ transform:translateZ(60px) translateY(-16px) rotate(3deg); } }
  .wl-photo{ position:absolute; bottom:-6px; inset-inline-end:-14px; width:84px; height:84px; border-radius:50%; overflow:hidden; border:4px solid #fff; box-shadow:0 12px 26px rgba(0,0,0,.4); background:#fff; display:grid; place-items:center; font-size:40px; animation:wlPhoto 3s ease-in-out .4s infinite; }
  .wl-photo img{ width:100%; height:100%; object-fit:cover; }
  @keyframes wlPhoto{ 0%,100%{ transform:rotate(-6deg) translateY(0); } 50%{ transform:rotate(6deg) translateY(-6px); } }
  .wl-eyebrow{ display:inline-block; padding:6px 14px; border-radius:999px; background:rgba(255,255,255,.14); color:#ffe99a; font-weight:900; font-size:13px; letter-spacing:.3px; margin-top:8px; }
  .wl-title{ font-family:var(--font-display); font-size:clamp(34px,7vw,58px); line-height:1.1; margin:12px 0 6px; background-image:linear-gradient(135deg,#fff,#ffe99a 55%,#ffc93c); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; }
  .wl-world{ color:#e9e1ff; font-size:clamp(16px,2.4vw,20px); font-weight:800; margin:0 0 18px; }
  .wl-line{ min-height:64px; font-size:clamp(17px,2.6vw,22px); font-weight:800; line-height:1.8; color:#fff; padding:14px 18px; border-radius:20px; background:rgba(0,0,0,.22); border:1px solid rgba(255,255,255,.12); transition:opacity .35s; }
  .wl-line.is-swapping{ opacity:0; }
  .wl-progress{ display:flex; justify-content:center; gap:8px; margin:16px 0 22px; }
  .wl-progress span{ width:12px; height:12px; border-radius:50%; background:rgba(255,255,255,.25); transition:.3s; }
  .wl-progress span.on{ background:#ffc93c; box-shadow:0 0 14px #ffc93c; transform:scale(1.2); }
  .wl-go{ display:inline-flex; align-items:center; gap:10px; min-height:64px; padding:16px 38px; border-radius:999px; font-size:20px; font-weight:900; color:#241645;
    background:linear-gradient(135deg,#ffe99a,#ffc93c); box-shadow:0 16px 36px rgba(255,201,60,.35); transform:translateZ(40px); animation:wlPulse 2s ease-in-out infinite; }
  @keyframes wlPulse{ 0%,100%{ box-shadow:0 14px 30px rgba(255,201,60,.3); } 50%{ box-shadow:0 20px 48px rgba(255,201,60,.6); } }
  .wl-skip{ display:block; margin-top:14px; color:#c9bfe6; font-weight:700; font-size:14px; text-decoration:underline; text-underline-offset:4px; }
  /* أيقونات ترحيب متحركة حول البطاقة (ثلاثية الأبعاد بالمنظور) */
  .wl-orbit{ position:absolute; inset:0; pointer-events:none; transform-style:preserve-3d; }
  .wl-orbit span{ position:absolute; font-size:44px; filter:drop-shadow(0 12px 18px rgba(0,0,0,.4)); animation:wlOrbit var(--d) ease-in-out var(--delay) infinite; opacity:.95; }
  @keyframes wlOrbit{ 0%,100%{ transform:translate3d(0,0,0) rotate(-10deg) scale(1); } 50%{ transform:translate3d(var(--dx), var(--dy), 80px) rotate(10deg) scale(1.18); } }
  .wl-confetti{ position:fixed; inset:0; pointer-events:none; z-index:3; overflow:hidden; }
  .wl-confetti i{ position:absolute; top:-20px; width:10px; height:16px; border-radius:3px; animation:wlFall linear infinite; }
  @keyframes wlFall{ to{ transform:translateY(110vh) rotate(720deg); } }
  @media(max-width:600px){ .wl-hero{ width:160px; height:160px; font-size:80px; margin-top:-80px; } .wl-card{ padding-top:30px; } .wl-orbit span{ font-size:32px; } }
  @media (prefers-reduced-motion: reduce){ .wl-card, .wl-hero, .wl-orbit span, .wl-go, .wl-photo{ animation:none !important; } }
</style>

<div class="wl-confetti" id="wlConfetti" aria-hidden="true"></div>
<div class="wl-stage">
  <div class="wl-orbit" aria-hidden="true">
    <?php $pos = [[6,10],[86,8],[4,60],[88,58],[16,88],[74,90],[46,4],[50,94]];
    foreach (array_slice(array_merge($icons, ['🎉','🎈','⭐','🎊']), 0, 8) as $i => $ic): [$x,$y] = $pos[$i % count($pos)]; ?>
      <span style="left:<?php echo $x; ?>%;top:<?php echo $y; ?>%;--d:<?php echo 4 + ($i % 4); ?>s;--delay:<?php echo $i * .35; ?>s;--dx:<?php echo ($i % 2 ? 1 : -1) * (18 + $i * 4); ?>px;--dy:<?php echo -(20 + $i * 3); ?>px;"><?php echo h($ic); ?></span>
    <?php endforeach; ?>
  </div>

  <section class="wl-card" aria-live="polite">
    <div class="wl-hero">
      <?php if (!empty($hero['image_path'])): ?><img src="<?php echo BASE_PATH . '/' . h($hero['image_path']); ?>" alt="<?php echo h($hero['name']); ?>"><?php else: ?><?php echo h($icons[0]); ?><?php endif; ?>
      <div class="wl-photo" title="<?php echo h($child['name']); ?>"><?php if ($photo): ?><img src="<?php echo h($photo); ?>" alt=""><?php else: ?>👋<?php endif; ?></div>
    </div>
    <span class="wl-eyebrow">🎉 حسابك جاهز</span>
    <h1 class="wl-title">أهلاً وسهلاً <?php echo h($child['name']); ?>!</h1>
    <p class="wl-world"><?php echo h($hero['name']); ?> من <?php echo h($theme['world']); ?> رفيق مغامرتك <?php echo h($theme['sidekick']['icon']); ?></p>
    <div class="wl-line" id="wlLine"><?php echo h($lines[0]); ?></div>
    <div class="wl-progress" id="wlProgress"><?php foreach ($lines as $i => $_): ?><span class="<?php echo $i === 0 ? 'on' : ''; ?>"></span><?php endforeach; ?></div>
    <a class="wl-go" id="wlGo" href="<?php echo h($next); ?>"><?php echo needs_assessment($child) ? '🚀 يلا نبدأ' : '🚀 إلى مهام اليوم'; ?></a>
    <a class="wl-skip" href="<?php echo h($next); ?>">تخطّي الترحيب</a>
  </section>
</div>

<script>
window.KIDAURA_SILENT_PAGE = true; // الرفيق يقول التسلسل هنا بدل جملة الصفحة العامة
(function(){
  const lines = <?php echo json_encode($lines, JSON_UNESCAPED_UNICODE); ?>;
  const next = <?php echo json_encode($next); ?>;
  const lineEl = document.getElementById('wlLine');
  const dots = document.querySelectorAll('#wlProgress span');
  // كونفيتي خفيف
  const conf = document.getElementById('wlConfetti');
  const colors = ['#FFD93D','#FF6B6B','#4ECDC4','#A8E6CF','#FF8A5C','#B455D6','#5B8DEF'];
  for (let i = 0; i < 40; i++) {
    const el = document.createElement('i');
    el.style.left = Math.random()*100+'%'; el.style.background = colors[i%colors.length];
    el.style.animationDuration = (5+Math.random()*6)+'s'; el.style.animationDelay = (-Math.random()*8)+'s';
    el.style.transform = `rotate(${Math.random()*360}deg)`; conf.appendChild(el);
  }
  function show(i){
    lineEl.classList.add('is-swapping');
    setTimeout(() => { lineEl.textContent = lines[i]; lineEl.classList.remove('is-swapping'); }, 300);
    dots.forEach((d, k) => d.classList.toggle('on', k <= i));
  }
  document.addEventListener('DOMContentLoaded', async () => {
    if (!window.Companion) return;
    Companion.pin(true);
    let navigated = false;
    document.getElementById('wlGo').addEventListener('click', () => { navigated = true; Companion.stop(); });
    await new Promise(r => setTimeout(r, 900));
    for (let i = 0; i < lines.length && !navigated; i++) {
      show(i);
      await Companion.say(lines[i], { mood: i === 0 ? 'wave' : (i === lines.length - 1 ? 'point' : 'talk'), sidekick: i === 1, hold: 400 });
    }
    if (!navigated) { if (window.SoundEngine) SoundEngine.sfx('step'); setTimeout(() => location.href = next, 500); }
  });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
