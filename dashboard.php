<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
ensure_daily_progress($pdo, $child['id']);

$myChars = array_filter([get_character($pdo, $child['character_1']), get_character($pdo, $child['character_2'])]);
$activePlan = get_active_plan($pdo, $child['id']);
$subRec = get_subscription_record($pdo, $child['id']);

// =============================================================
// القصص الموصى بها حسب أضعف محور تحليل
// =============================================================
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

  <!-- ===== بطاقة الترحيب ===== -->
  <div class="welcome-banner">
    <div>
      <div class="eyebrow" style="color:#FFE9A8;">أهلاً بعودتك يا بطل!</div>
      <h2 class="section-title" style="margin:6px 0 4px;">مرحباً <?php echo h($child['name']); ?> 👋</h2>
      <p style="opacity:.92;">عمرك <?php echo (int)$child['age']; ?> سنوات · خطتك: <?php echo $activePlan ? h($activePlan['name']) : ($subRec ? h($subRec['name']).' (قيد المراجعة ⏳)' : '-'); ?></p>
    </div>
    <div class="welcome-points"><b><?php echo (int)$child['points']; ?></b><span>نقطة ⭐</span></div>
  </div>

  <!-- ===== السكشن الأول: انطلق في مغامرتك ===== -->
  <div class="section mission-section">
    <div class="section-head">
      <div class="eyebrow">⚔️ مغامرات اليوم</div>
      <h2 class="section-title">استعد لمغامرتك!</h2>
      <p class="section-sub">مغامرات جديدة تنتظرك كل يوم، انطلق الآن!</p>
    </div>

    <div class="mission-card">
      <!-- الخلفية الكرتونية المتحركة -->
      <div class="mission-bg">
        <div class="floating-shapes">
          <span class="shape star1">⭐</span>
          <span class="shape star2">🌟</span>
          <span class="shape star3">✨</span>
          <span class="shape cloud1">☁️</span>
          <span class="shape cloud2">☁️</span>
        </div>
      </div>

      <div class="mission-content-simple">
        <div class="mission-icon">🚀</div>
        <div class="mission-info-simple">
          <h3 class="mission-title">انطلق في مغامرتك اليومية!</h3>
          <p class="mission-desc">هناك مهام وقصص وألعاب في انتظارك. هل أنت مستعد؟</p>
        </div>
        <a href="<?php echo BASE_PATH; ?>/tasks.php" class="mission-btn btn-active">
          <span class="btn-text">⚡ انطلق في مغامرتك</span>
          <span class="btn-shine"></span>
        </a>
      </div>
    </div>
  </div>

  <!-- ===== السكشن الثاني: الشخصيات 3D ===== -->
  <div class="section characters-section">
    <div class="section-head">
      <div class="eyebrow">رفقاء البطل</div>
      <h2 class="section-title">شخصياتك المفضّلة</h2>
      <p class="section-sub">حرّك الفأرة أو المس البطاقة لتشاهد الشخصية بأبعادها الثلاثية</p>
    </div>

    <div class="characters-grid-3d">
      <?php foreach ($myChars as $c): ?>
        <div class="character-card-3d" style="--card-color:<?php echo h($c['color']); ?>;">
          <div class="card-inner">
            <div class="card-front">
              <?php if (!empty($c['image_path'])): ?>
                <img src="<?php echo h($c['image_path']); ?>" alt="<?php echo h($c['name']); ?>">
              <?php else: ?>
                <div class="fallback-3d" style="background:linear-gradient(150deg, <?php echo h($c['color']); ?>, #fff2);">
                  <?php echo character_icons($c)[0] ?? '✨'; ?>
                </div>
              <?php endif; ?>
              <div class="character-name-3d"><?php echo h($c['name']); ?></div>
            </div>
            <div class="card-back">
              <h3><?php echo h($c['name']); ?></h3>
              <p><?php echo h($c['title']); ?></p>
              <p class="quote">"<?php echo h($c['trait']); ?>"</p>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ===== السكشن الثالث: اكتشف عوالم جديدة ===== -->
  <div class="section">
    <div class="section-head">
      <div class="eyebrow">📚 مخصّصة لك</div>
      <h2 class="section-title">اكتشف عوالم جديدة</h2>
      <p class="section-sub">
        <?php echo $weakAxis ? 'لاحظنا أن محور "'.h($weakAxis['axis']).'" يحتاج تعزيزاً، اخترنا لك مسارات ممتعة 💜' : 'أجب عن أسئلة التحليل لنخصص لك رحلتك بدقة.'; ?>
      </p>
    </div>

    <div class="discovery-grid">
      <a href="<?php echo BASE_PATH; ?>/story.php" class="discovery-card daily">
        <div class="card-glow"></div>
        <div class="card-icon">📖</div>
        <div class="card-title">قصص يومية</div>
        <div class="card-desc">مغامرة جديدة تنتظرك كل يوم</div>
        <div class="card-btn">استكشف الآن ✨</div>
      </a>
      <a href="<?php echo BASE_PATH; ?>/culture.php" class="discovery-card cultural">
        <div class="card-glow"></div>
        <div class="card-icon">🌍</div>
        <div class="card-title">قصص ثقافية</div>
        <div class="card-desc">تعرّف على عوالم وحضارات</div>
        <div class="card-btn">استكشف الآن ✨</div>
      </a>
      <a href="<?php echo BASE_PATH; ?>/grand-story.php" class="discovery-card grand">
        <div class="card-glow"></div>
        <div class="card-icon">🏰</div>
        <div class="card-title">مغامرتي الكبرى</div>
        <div class="card-desc">قصة تفاعلية طويلة</div>
        <div class="card-btn">استكشف الآن ✨</div>
      </a>
    </div>
  </div>

</main>
</div>

<footer class="site-footer">Kidora © 2026 — منصة الأطفال الذكية للتعلّم والمغامرة الآمنة</footer>
<script>window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;</script>

<!-- =============================================================
     شاشة الوداع — تظهر لما الطفل ينجز مهامه اليومية كاملة
     ============================================================= -->
<div class="goodbye-modal" id="goodbyeModal" aria-hidden="true">
  <div class="goodbye-card">

    <!-- نجوم متلألئة -->
    <div class="goodbye-stars">
      <span>✨</span><span>⭐</span><span>✨</span>
      <span>🌟</span><span>💫</span>
    </div>

    <!-- الشخصيتان تودّعان -->
    <div class="goodbye-chars">
      <?php foreach ($myChars as $idx => $c): ?>
        <div class="goodbye-char" style="--c-color:<?php echo h($c['color']); ?>; animation-delay:<?php echo $idx * 0.3; ?>s;">
          <div class="char-bubble">باي باي يا بطل! 👋</div>
          <div class="char-visual">
            <?php if (!empty($c['image_path'])): ?>
              <img src="<?php echo h($c['image_path']); ?>" alt="<?php echo h($c['name']); ?>">
            <?php else: ?>
              <span class="char-emoji"><?php echo character_icons($c)[0] ?? '✨'; ?></span>
            <?php endif; ?>
            <div class="char-hand">👋</div>
          </div>
          <div class="char-name"><?php echo h($c['name']); ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- النص التحفيزي -->
    <h2 class="goodbye-title">خلص وقتك في المنصة يا بطل! 🌟</h2>
    <p class="goodbye-sub">لقد أنجزت كل شيء اليوم:</p>

    <!-- قائمة الإنجازات -->
    <div class="goodbye-checks">
      <div class="goodbye-check">
        <span class="chk-ico">✅</span>
        <span class="chk-txt">المهام اليومية</span>
      </div>
      <div class="goodbye-check">
        <span class="chk-ico">🎮</span>
        <span class="chk-txt">لعبة ممتعة</span>
      </div>
      <div class="goodbye-check">
        <span class="chk-ico">🛡️</span>
        <span class="chk-txt">درس الحماية</span>
      </div>
    </div>

    <!-- رسالة الوداع -->
    <p class="goodbye-msg">
      <strong>بنشوفك بكرا</strong><br>
      بمهام ومغامرات جديدة وأحلى! 🚀
    </p>

    <!-- أزرار -->
    <div class="goodbye-actions">
      <a href="<?php echo BASE_PATH; ?>/dashboard.php" class="goodbye-btn goodbye-btn-primary">
        🏠 العودة للرئيسية
      </a>
    </div>

    <p class="goodbye-note">
      💚 اذهب الآن وأكمل أنشطتك اليومية<br>
      رفقاؤك فخورون بك!
    </p>

  </div>
</div>

<style>
/* ============================================================= */
/*              شاشة الوداع — Goodbye Screen                     */
/* ============================================================= */

.goodbye-modal{
  position:fixed; inset:0; z-index:9999;
  display:none; align-items:center; justify-content:center;
  padding:1rem;
  background:radial-gradient(circle at 50% 40%, rgba(20,10,45,.94), rgba(5,2,20,.98));
  backdrop-filter:blur(16px);
  -webkit-backdrop-filter:blur(16px);
  animation:goodbyeFadeIn .5s ease;
}
.goodbye-modal.open{ display:flex; }
@keyframes goodbyeFadeIn{ from{opacity:0} to{opacity:1} }

.goodbye-card{
  position:relative;
  background:linear-gradient(160deg, rgba(35,25,70,.98), rgba(15,8,40,.98));
  border:2px solid rgba(251,191,36,.35);
  border-radius:32px;
  padding:2rem 1.6rem 1.8rem;
  max-width:520px; width:100%;
  text-align:center;
  box-shadow:
    0 30px 80px rgba(0,0,0,.6),
    0 0 80px rgba(251,191,36,.12) inset,
    0 0 100px rgba(139,92,246,.15);
  animation:goodbyePop .7s cubic-bezier(.34,1.56,.64,1);
  overflow:hidden;
}
@keyframes goodbyePop{
  0%{ transform:scale(.75) translateY(20px); opacity:0; }
  100%{ transform:scale(1) translateY(0); opacity:1; }
}

/* نجوم متلألئة في الخلفية */
.goodbye-stars{
  position:absolute; inset:0;
  pointer-events:none; overflow:hidden;
}
.goodbye-stars span{
  position:absolute;
  font-size:1.2rem;
  animation:goodbyeStarFloat 4s ease-in-out infinite;
  opacity:.7;
}
.goodbye-stars span:nth-child(1){ top:8%;  left:8%;  animation-delay:0s;   }
.goodbye-stars span:nth-child(2){ top:15%; right:10%; animation-delay:.6s; font-size:1.6rem;}
.goodbye-stars span:nth-child(3){ top:50%; left:5%;  animation-delay:1.2s; }
.goodbye-stars span:nth-child(4){ top:70%; right:8%; animation-delay:1.8s; font-size:1.4rem;}
.goodbye-stars span:nth-child(5){ top:30%; left:85%; animation-delay:2.4s; }
@keyframes goodbyeStarFloat{
  0%,100%{ transform:translateY(0) scale(1); opacity:.5; }
  50%    { transform:translateY(-15px) scale(1.3); opacity:1; }
}

/* ====== الشخصيتان ====== */
.goodbye-chars{
  position:relative; z-index:2;
  display:flex; justify-content:center; gap:2rem;
  margin-bottom:1.2rem;
}
.goodbye-char{
  position:relative;
  animation:charGoodbyeBounce 1.4s ease-in-out infinite;
}
@keyframes charGoodbyeBounce{
  0%,100%{ transform:translateY(0) rotate(0); }
  25%    { transform:translateY(-10px) rotate(-3deg); }
  75%    { transform:translateY(-10px) rotate(3deg); }
}

/* فقاعة الكلام */
.char-bubble{
  position:absolute;
  top:-38px; left:50%;
  transform:translateX(-50%);
  background:linear-gradient(135deg,#ffe99a,#ffc93c);
  color:#241645;
  font-weight:900;
  font-size:.7rem;
  padding:.35rem .7rem;
  border-radius:30px;
  white-space:nowrap;
  box-shadow:0 6px 16px rgba(255,201,60,.35);
  animation:charBubblePop 2s ease-in-out infinite;
}
.char-bubble::after{
  content:'';
  position:absolute;
  bottom:-6px; left:50%;
  transform:translateX(-50%);
  width:0; height:0;
  border-left:6px solid transparent;
  border-right:6px solid transparent;
  border-top:7px solid #ffc93c;
}
@keyframes charBubblePop{
  0%,100%{ transform:translateX(-50%) scale(1); }
  50%    { transform:translateX(-50%) scale(1.08); }
}

/* صورة الشخصية */
.char-visual{
  position:relative;
  width:90px; height:90px;
  border-radius:50%;
  background:radial-gradient(circle at 35% 30%, rgba(255,255,255,.35), transparent 45%),
             linear-gradient(145deg, var(--c-color,#6c63ff), rgba(10,6,26,.85));
  border:4px solid var(--c-color,#ffc93c);
  display:grid; place-items:center;
  box-shadow:
    0 10px 30px rgba(0,0,0,.4),
    0 0 40px color-mix(in srgb, var(--c-color,#6c63ff) 55%, transparent);
  overflow:hidden;
}
.char-visual img{
  width:100%; height:100%;
  object-fit:cover;
  border-radius:50%;
}
.char-emoji{
  font-size:2.6rem;
  filter:drop-shadow(0 4px 8px rgba(0,0,0,.4));
}

/* اليد تلوّح */
.char-hand{
  position:absolute;
  bottom:-8px; right:-12px;
  font-size:1.8rem;
  filter:drop-shadow(0 4px 8px rgba(0,0,0,.5));
  animation:handWave 0.9s ease-in-out infinite;
  transform-origin:70% 80%;
}
@keyframes handWave{
  0%,100%{ transform:rotate(0); }
  25%    { transform:rotate(-25deg); }
  75%    { transform:rotate(25deg); }
}

/* اسم الشخصية */
.char-name{
  margin-top:.6rem;
  font-size:.85rem;
  font-weight:900;
  color:var(--c-color,#ffc93c);
  text-shadow:0 2px 8px rgba(0,0,0,.5);
}

/* ====== العنوان والرسائل ====== */
.goodbye-title{
  position:relative; z-index:2;
  font-family:var(--font-display,inherit);
  font-size:1.7rem;
  margin:.4rem 0 .3rem;
  background:linear-gradient(135deg,#fff,#ffe99a,#ffc93c);
  -webkit-background-clip:text;
  background-clip:text;
  -webkit-text-fill-color:transparent;
  line-height:1.3;
}
.goodbye-sub{
  position:relative; z-index:2;
  color:#d9d0ff; font-size:.95rem;
  margin:0 0 .8rem;
}

/* ====== قائمة الإنجازات ====== */
.goodbye-checks{
  position:relative; z-index:2;
  display:flex; flex-wrap:wrap;
  gap:.5rem; justify-content:center;
  margin-bottom:1rem;
}
.goodbye-check{
  display:flex; align-items:center; gap:.4rem;
  background:rgba(46,196,182,.12);
  border:1px solid rgba(46,196,182,.35);
  border-radius:30px;
  padding:.45rem .9rem;
  font-size:.82rem;
  font-weight:800;
  color:#d9f7f3;
  animation:checkPop .5s ease backwards;
}
.goodbye-check:nth-child(1){ animation-delay:.3s; }
.goodbye-check:nth-child(2){ animation-delay:.5s; }
.goodbye-check:nth-child(3){ animation-delay:.7s; }
@keyframes checkPop{
  0%{ transform:scale(0); opacity:0; }
  70%{ transform:scale(1.15); }
  100%{ transform:scale(1); opacity:1; }
}
.goodbye-check .chk-ico{ font-size:1rem; }
.goodbye-check .chk-txt{ color:#fff; }

/* ====== رسالة الوداع ====== */
.goodbye-msg{
  position:relative; z-index:2;
  color:#e0d8f0;
  font-size:1rem;
  line-height:1.9;
  margin:.6rem 0 1.2rem;
  padding:.9rem 1rem;
  background:rgba(139,92,246,.12);
  border:1px dashed rgba(139,92,246,.35);
  border-radius:18px;
}
.goodbye-msg strong{
  color:#ffc93c;
  font-size:1.15rem;
  display:inline-block;
  margin-bottom:.2rem;
}

/* ====== الأزرار ====== */
.goodbye-actions{
  position:relative; z-index:2;
  display:flex; justify-content:center;
  gap:.6rem; flex-wrap:wrap;
  margin-bottom:.8rem;
}
.goodbye-btn{
  padding:.85rem 1.6rem;
  border-radius:50px;
  font-weight:900;
  font-size:.95rem;
  text-decoration:none;
  border:none;
  cursor:pointer;
  font-family:inherit;
  transition:.25s;
  display:inline-flex;
  align-items:center;
  gap:.4rem;
}
.goodbye-btn-primary{
  background:linear-gradient(135deg,#ffe99a,#ffc93c);
  color:#241645;
  box-shadow:0 10px 30px rgba(255,201,60,.4);
}
.goodbye-btn-primary:hover{
  transform:translateY(-3px);
  box-shadow:0 16px 40px rgba(255,201,60,.6);
}

/* ====== ملاحظة أخيرة ====== */
.goodbye-note{
  position:relative; z-index:2;
  color:#b9abd4;
  font-size:.8rem;
  line-height:1.7;
  margin:.4rem 0 0;
}

/* ====== الجوال ====== */
@media (max-width:520px){
  .goodbye-card{ padding:1.5rem 1.1rem; border-radius:24px; }
  .goodbye-title{ font-size:1.35rem; }
  .goodbye-chars{ gap:1.2rem; }
  .char-visual{ width:72px; height:72px; }
  .char-emoji{ font-size:2rem; }
  .char-hand{ font-size:1.4rem; }
  .char-bubble{ font-size:.62rem; padding:.3rem .6rem; top:-32px; }
  .goodbye-check{ font-size:.75rem; padding:.4rem .75rem; }
  .goodbye-msg{ font-size:.9rem; }
}

/* Confetti */
.kidora-confetti{
  position:fixed; inset:0; z-index:9998;
  pointer-events:none; overflow:hidden;
}
.kidora-confetti span{
  position:absolute; top:-60px;
  font-size:1.4rem;
  animation:kidoraConfettiFall 3.4s linear forwards;
}
@keyframes kidoraConfettiFall{
  0%  { transform:translateY(-60px) rotate(0);   opacity:1; }
  100%{ transform:translateY(110vh) rotate(720deg); opacity:0; }
}
</style>

<script>
/* =============================================================
   شاشة الوداع — تظهر لما الطفل ينجز:
   1) المهام اليومية
   2) لعبة من games.php
   3) درس حماية من safety.php
   ============================================================= */
(function(){

  const TODAY = new Date().toISOString().slice(0,10);
  const K = {
    tasks:  'kidora_done_tasks_'   + TODAY,
    game:   'kidora_done_game_'    + TODAY,
    safety: 'kidora_done_safety_'  + TODAY,
    shown:  'kidora_goodbye_shown_' + TODAY
  };

  /* ما نعرضها مرتين بنفس اليوم */
  if (localStorage.getItem(K.shown)) return;

  /* فحص الشروط الثلاثة */
  const allDone =
    localStorage.getItem(K.tasks)  === '1' &&
    localStorage.getItem(K.game)   === '1' &&
    localStorage.getItem(K.safety) === '1';

  if (!allDone) return;

  /* ننتظر شوي حتى تحمّل الصفحة */
  setTimeout(showGoodbye, 700);

  function showGoodbye(){
    /* Confetti */
    const conf = document.createElement('div');
    conf.className = 'kidora-confetti';
    const emojis = ['🎉','🎊','⭐','🌟','✨','💫','🏆','💜','🎈'];
    for (let i=0; i<70; i++){
      const s = document.createElement('span');
      s.textContent = emojis[Math.floor(Math.random()*emojis.length)];
      s.style.left = Math.random()*100 + '%';
      s.style.animationDelay = (Math.random()*2.5) + 's';
      s.style.fontSize = (16 + Math.random()*20) + 'px';
      conf.appendChild(s);
    }
    document.body.appendChild(conf);

    /* عرض المودال */
    const modal = document.getElementById('goodbyeModal');
    if (modal) modal.classList.add('open');

    /* نطق صوتي */
    speakGoodbye();

    /* علامة أنه اتعرّض */
    localStorage.setItem(K.shown, '1');

    /* إزالة الـ confetti بعد ما يخلص */
    setTimeout(() => conf.remove(), 6000);
  }

  /* صوت الوداع */
  function speakGoodbye(){
    if (!('speechSynthesis' in window)) return;
    window.speechSynthesis.cancel();

    const name = <?php echo json_encode($child['name'] ?? 'بطل', JSON_UNESCAPED_UNICODE); ?>;
    const text = `خلص وقتك في المنصة يا ${name}! بنشوفك بكرا بمهام ومغامرات جديدة. باي باي يا بطل!`;

    const u = new SpeechSynthesisUtterance(text);
    u.lang = 'ar-SA'; u.rate = 1; u.pitch = 1.15;
    const v = speechSynthesis.getVoices().find(x => x.lang.startsWith('ar'));
    if (v) u.voice = v;
    speechSynthesis.speak(u);
  }

  /* ============================================================
     دالة عامة للصفحات الأخرى عشان تعلّم الإنجاز
     استخدمها من tasks.php / games.php / safety.php
     ============================================================ */
  window.kidoraMarkDone = function(type){
    if (!['tasks','game','safety'].includes(type)) return;
    const today = new Date().toISOString().slice(0,10);
    localStorage.setItem('kidora_done_' + type + '_' + today, '1');
  };

})();
</script>


<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- ===== الأنماط ===== -->
<style>
/* ============================================================= */
/*              السكشن الأول: انطلق في مغامرتك                    */
/* ============================================================= */

.mission-section { margin-bottom:48px; }

.mission-card {
  position: relative;
  background: linear-gradient(145deg, rgba(251, 191, 36, 0.06), rgba(245, 158, 11, 0.02));
  border: 1px solid rgba(251, 191, 36, 0.1);
  border-radius: 32px;
  padding: 32px 28px;
  overflow: hidden;
  transition: all 0.4s ease;
}

.mission-card:hover {
  border-color: rgba(251, 191, 36, 0.2);
  box-shadow: 0 8px 40px rgba(251, 191, 36, 0.05);
}

/* الخلفية الكرتونية المتحركة */
.mission-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
}

.floating-shapes .shape {
  position: absolute;
  font-size: 2rem;
  opacity: 0.15;
  animation: floatShape 8s ease-in-out infinite;
}

.floating-shapes .star1 { top: 10%; left: 5%; animation-delay: 0s; font-size: 2.4rem; }
.floating-shapes .star2 { top: 70%; left: 85%; animation-delay: 2s; font-size: 2rem; }
.floating-shapes .star3 { top: 20%; left: 90%; animation-delay: 4s; font-size: 1.8rem; }
.floating-shapes .cloud1 { top: 80%; left: 10%; animation-delay: 1s; font-size: 3rem; }
.floating-shapes .cloud2 { top: 15%; left: 50%; animation-delay: 3s; font-size: 2.6rem; }

@keyframes floatShape {
  0%, 100% { transform: translateY(0) rotate(0deg); }
  50% { transform: translateY(-20px) rotate(10deg); }
}

/* المحتوى المبسط */
.mission-content-simple {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 24px;
  flex-wrap: wrap;
}

.mission-icon {
  font-size: 4rem;
  flex-shrink: 0;
  animation: bounceIcon 2s ease-in-out infinite;
}

@keyframes bounceIcon {
  0%, 100% { transform: scale(1) rotate(0deg); }
  50% { transform: scale(1.1) rotate(-5deg); }
}

.mission-info-simple {
  flex: 1;
  min-width: 180px;
}

.mission-title {
  font-size: 1.6rem;
  font-weight: 800;
  color: #fff;
  margin: 0 0 4px;
  background: linear-gradient(135deg, #fff, #fbbf24);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.mission-desc {
  color: rgba(255, 255, 255, 0.5);
  font-size: 1rem;
  margin: 0;
}

/* ===== الزر ثلاثي الأبعاد ===== */
.mission-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 16px 40px;
  border-radius: 60px;
  font-weight: 800;
  font-size: 1.1rem;
  text-decoration: none;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  transition: all 0.15s ease;
  transform-style: preserve-3d;
  perspective: 800px;
  flex-shrink: 0;
  min-width: 220px;
  border: none;
  cursor: pointer;
  font-family: 'Tajawal', sans-serif;
  background: linear-gradient(135deg, #fbbf24, #f59e0b);
  color: #0b1120 !important;
  box-shadow: 0 8px 0 #b45309, 0 12px 40px rgba(251, 191, 36, 0.35);
  transform: translateY(0);
  animation: pulseBtn 3s ease-in-out infinite;
}

.mission-btn .btn-text {
  position: relative;
  z-index: 2;
  color: inherit;
  font-weight: 800;
}

.mission-btn .btn-shine {
  position: absolute;
  inset: 0;
  border-radius: 60px;
  background: linear-gradient(135deg, rgba(255,255,255,0.25) 0%, transparent 50%);
  opacity: 0;
  transition: opacity 0.4s ease;
  pointer-events: none;
}

.mission-btn:hover .btn-shine {
  opacity: 1;
}

.mission-btn:hover {
  transform: translateY(-4px) scale(1.03);
  box-shadow: 0 12px 0 #b45309, 0 20px 50px rgba(251, 191, 36, 0.45);
  animation: none;
}

.mission-btn:active {
  transform: translateY(4px);
  box-shadow: 0 4px 0 #b45309, 0 12px 40px rgba(251, 191, 36, 0.2);
}

@keyframes pulseBtn {
  0%, 100% { box-shadow: 0 8px 0 #b45309, 0 12px 40px rgba(251, 191, 36, 0.35); }
  50% { box-shadow: 0 8px 0 #b45309, 0 12px 60px rgba(251, 191, 36, 0.55); }
}

/* ============================================================= */
/*              السكشن الثاني: الشخصيات 3D                        */
/* ============================================================= */

.characters-section { margin-bottom:48px; }
.characters-grid-3d {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 28px;
  perspective: 1200px;
}

.character-card-3d {
  width: 100%;
  aspect-ratio: 3/4;
  perspective: 800px;
  cursor: pointer;
  transition: all 0.4s ease;
}

.character-card-3d:hover { transform: translateY(-6px); }

.card-inner {
  position: relative;
  width: 100%;
  height: 100%;
  transition: transform 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  transform-style: preserve-3d;
  border-radius: 24px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.character-card-3d:hover .card-inner {
  transform: rotateY(180deg) scale(1.02);
}

.card-front, .card-back {
  position: absolute;
  width: 100%;
  height: 100%;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
  border-radius: 24px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 20px;
  text-align: center;
}

.card-front {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-color: var(--card-color, #fbbf24);
  box-shadow: inset 0 0 40px rgba(251, 191, 36, 0.03);
}

.card-front img {
  width: 100%;
  height: 70%;
  object-fit: cover;
  border-radius: 16px 16px 0 0;
}

.card-front .fallback-3d {
  width: 100%;
  height: 70%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 5rem;
  border-radius: 16px 16px 0 0;
}

.character-name-3d {
  font-size: 1.1rem;
  font-weight: 800;
  color: #fff;
  padding: 12px 0 4px;
  background: linear-gradient(135deg, #fff, var(--card-color, #fbbf24));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.card-back {
  transform: rotateY(180deg);
  background: linear-gradient(145deg, rgba(10, 18, 35, 0.95), rgba(20, 30, 51, 0.98));
  border: 1px solid var(--card-color, #fbbf24);
  box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
  padding: 20px;
}

.card-back h3 {
  font-size: 1.3rem;
  font-weight: 800;
  color: #fff;
  margin: 0 0 4px;
}

.card-back p {
  color: rgba(255, 255, 255, 0.6);
  font-size: 0.9rem;
  margin: 4px 0;
}

.card-back .quote {
  font-style: italic;
  color: var(--card-color, #fbbf24);
  font-size: 0.85rem;
  margin-top: 8px;
  opacity: 0.8;
}

.character-card-3d::before {
  content: '';
  position: absolute;
  inset: -2px;
  border-radius: 26px;
  background: linear-gradient(135deg, var(--card-color, #fbbf24), transparent, var(--card-color, #fbbf24));
  opacity: 0;
  transition: opacity 0.4s ease;
  z-index: -1;
  filter: blur(8px);
}

.character-card-3d:hover::before { opacity: 0.3; }

/* ============================================================= */
/*              السكشن الثالث: اكتشف عوالم جديدة                  */
/* ============================================================= */

.discovery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 24px;
  margin-top: 8px;
}

.discovery-card {
  position: relative;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 28px;
  padding: 30px 20px 24px;
  text-align: center;
  text-decoration: none;
  color: #fff;
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  overflow: hidden;
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  perspective: 800px;
  transform-style: preserve-3d;
  opacity: 0;
  animation: cardFadeIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

.discovery-card:nth-child(1) { animation-delay: 0.1s; }
.discovery-card:nth-child(2) { animation-delay: 0.25s; }
.discovery-card:nth-child(3) { animation-delay: 0.4s; }

@keyframes cardFadeIn {
  0% { opacity: 0; transform: translateY(30px) scale(0.95); }
  100% { opacity: 1; transform: translateY(0) scale(1); }
}

.discovery-card:hover {
  transform: translateY(-10px) rotateX(2deg) rotateY(2deg) scale(1.02);
  border-color: rgba(251, 191, 36, 0.25);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(251, 191, 36, 0.05);
}

.discovery-card .card-glow {
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 50% 0%, rgba(251, 191, 36, 0.06), transparent 70%);
  opacity: 0;
  transition: opacity 0.6s ease;
  pointer-events: none;
}

.discovery-card:hover .card-glow { opacity: 1; }

.discovery-card .card-icon {
  font-size: 3.6rem;
  margin-bottom: 6px;
  transition: transform 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  filter: drop-shadow(0 0 20px rgba(251, 191, 36, 0.1));
}

.discovery-card:hover .card-icon {
  transform: scale(1.15) rotate(-6deg);
}

.discovery-card .card-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.3px;
  margin-bottom: 2px;
}

.discovery-card .card-desc {
  font-size: 0.9rem;
  color: rgba(255, 255, 255, 0.4);
  margin-bottom: 12px;
}

.discovery-card .card-btn {
  display: inline-block;
  padding: 10px 28px;
  border-radius: 60px;
  font-weight: 700;
  font-size: 0.9rem;
  background: linear-gradient(135deg, #fbbf24, #f59e0b);
  color: #0b1120 !important;
  box-shadow: 0 6px 0 #b45309, 0 10px 30px rgba(251, 191, 36, 0.2);
  transition: all 0.15s ease;
  transform: translateY(0);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  animation: gentlePulse 3s ease-in-out infinite;
}

@keyframes gentlePulse {
  0%, 100% { box-shadow: 0 6px 0 #b45309, 0 10px 30px rgba(251, 191, 36, 0.2); }
  50% { box-shadow: 0 6px 0 #b45309, 0 10px 50px rgba(251, 191, 36, 0.4); }
}

.discovery-card:hover .card-btn {
  transform: translateY(-4px);
  box-shadow: 0 10px 0 #b45309, 0 20px 40px rgba(251, 191, 36, 0.3);
}

.discovery-card:active .card-btn {
  transform: translateY(4px);
  box-shadow: 0 2px 0 #b45309, 0 10px 30px rgba(251, 191, 36, 0.2);
}

.discovery-card.daily {
  background: linear-gradient(145deg, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.04));
  border-color: rgba(99, 102, 241, 0.12);
}
.discovery-card.daily:hover {
  border-color: rgba(99, 102, 241, 0.3);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(99, 102, 241, 0.08);
}

.discovery-card.cultural {
  background: linear-gradient(145deg, rgba(52, 211, 153, 0.08), rgba(16, 185, 129, 0.04));
  border-color: rgba(52, 211, 153, 0.12);
}
.discovery-card.cultural:hover {
  border-color: rgba(52, 211, 153, 0.3);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(52, 211, 153, 0.08);
}

.discovery-card.grand {
  background: linear-gradient(145deg, rgba(251, 191, 36, 0.08), rgba(245, 158, 11, 0.04));
  border-color: rgba(251, 191, 36, 0.12);
}
.discovery-card.grand:hover {
  border-color: rgba(251, 191, 36, 0.3);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(251, 191, 36, 0.08);
}

/* ============================================================= */
/*                    التجاوب للشاشات الصغيرة                     */
/* ============================================================= */

@media (max-width: 768px) {
  .mission-content-simple {
    flex-direction: column;
    text-align: center;
  }
  .mission-icon { font-size: 3rem; }
  .mission-btn {
    min-width: 100%;
    padding: 14px 24px;
    font-size: 1rem;
  }
  .mission-title { font-size: 1.3rem; }
  .characters-grid-3d {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
  }
  .discovery-grid { grid-template-columns: 1fr; gap: 16px; }
  .discovery-card { padding: 24px 16px 20px; }
  .discovery-card .card-icon { font-size: 2.8rem; }
  .discovery-card .card-title { font-size: 1.2rem; }
  .discovery-card .card-btn { padding: 8px 20px; font-size: 0.8rem; }
}

@media (max-width: 480px) {
  .mission-card { padding: 20px 16px; }
  .mission-btn {
    font-size: 0.85rem;
    padding: 12px 16px;
    min-width: 100%;
  }
  .mission-title { font-size: 1.1rem; }
  .mission-desc { font-size: 0.85rem; }
  .characters-grid-3d {
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
  }
  .character-card-3d { aspect-ratio: 3/4; }
}

/* ============================================================= */
/*                    الأنماط العامة                              */
/* ============================================================= */

.section-head { margin-bottom: 20px; }
.eyebrow {
  font-size: 0.8rem;
  font-weight: 600;
  color: #fbbf24;
  letter-spacing: 1px;
  text-transform: uppercase;
  opacity: 0.8;
}
.section-title {
  font-size: 1.8rem;
  font-weight: 800;
  color: #fff;
  margin: 4px 0 2px;
}
.section-sub {
  color: rgba(255, 255, 255, 0.4);
  font-size: 0.95rem;
  margin: 0;
}
.section { margin-bottom: 48px; }

.welcome-banner {
  display: flex;
  align-items: center;
  gap: 18px;
  background: rgba(255, 255, 255, 0.04);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 28px;
  padding: 20px 28px;
  margin-bottom: 28px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  flex-wrap: wrap;
}
.welcome-points {
  background: rgba(251, 191, 36, 0.08);
  border: 1px solid rgba(251, 191, 36, 0.12);
  border-radius: 40px;
  padding: 8px 18px;
  text-align: center;
  flex-shrink: 0;
}
.welcome-points b {
  font-size: 1.8rem;
  font-weight: 900;
  color: #fbbf24;
  display: block;
  line-height: 1.2;
}
.welcome-points span {
  font-size: 0.65rem;
  color: rgba(255, 255, 255, 0.5);
  letter-spacing: 0.5px;
}
.site-footer {
  text-align: center;
  padding: 30px 20px 20px;
  color: rgba(255, 255, 255, 0.15);
  font-size: 0.8rem;
  border-top: 1px solid rgba(255, 255, 255, 0.03);
  margin-top: 20px;
}

@media (max-width: 768px) {
  .welcome-banner {
    flex-direction: column;
    align-items: stretch;
    text-align: center;
    padding: 20px;
  }
  .welcome-points { align-self: center; }
  .section-title { font-size: 1.4rem; }
}
</style>
