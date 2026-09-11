<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();

/* json_encode آمن */
function safe_json($d){
    $j = json_encode($d,
        JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|
        JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE);
    return $j === false ? '[]' : $j;
}

/* جلب الدروس المناسبة */
$stmt = $pdo->prepare("
  SELECT id,type,title,description,youtube_id,game_type,age_min,age_max
  FROM safety_content
  WHERE age_min <= ? AND age_max >= ? AND (is_premium=0 OR is_premium IS NULL)
  ORDER BY id ASC
");
$stmt->execute([$child['age'], $child['age']]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if (!$lessons) {
  $lessons = [[
    'id'=>0,'type'=>'video','title'=>'جسدي ملكي',
    'description'=>'جسمك لك وحدك، وهناك مناطق خاصة لا يجوز لأحد لمسها.',
    'youtube_id'=>null,'game_type'=>'body'
  ]];
}

$__pageTitle = 'قسم الحماية — Kidora';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
/* =========================================================
   قسم الحماية — يندمج مع ثيم الموقع (بدون خلفية خاصة)
   ========================================================= */
.sf-wrap{max-width:1100px;margin:0 auto;padding:3rem 1rem 3rem;direction:rtl}

    @media(min-width:900px){
  .sf-card{max-width:900px;margin-left:auto;margin-right:auto}
  .sf-guide{max-width:900px;margin-left:auto;margin-right:auto}
}
    /* المرشدة */
.sf-guide{
  display:flex;align-items:center;gap:1rem;
  background:var(--k-card,rgba(255,255,255,.07));
  border:1px solid var(--k-line,rgba(255,255,255,.14));
  border-radius:24px;padding:1rem 1.4rem;margin-bottom:1.5rem;
  backdrop-filter:blur(10px);
}
.sf-guide-av{
  width:70px;height:70px;border-radius:50%;
  background:linear-gradient(145deg,#f5a623,#ffc93c);
  display:grid;place-items:center;font-size:40px;flex-shrink:0;
  box-shadow:0 8px 24px rgba(255,201,60,.3);
  animation:sfFloat 3s ease-in-out infinite;
}
@keyframes sfFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
.sf-guide-name{color:#ffc93c;font-weight:900;display:block;margin-bottom:.2rem;font-size:.95rem}
.sf-guide-msg{line-height:1.7;font-size:1rem}

/* الكارت الرئيسي */
.sf-card{
  background:var(--k-card,rgba(255,255,255,.06));
  border:1px solid var(--k-line,rgba(255,255,255,.12));
  border-radius:26px;padding:1.5rem;margin-bottom:1.2rem;
  backdrop-filter:blur(6px);
}
.sf-kicker{
  display:inline-block;
  background:linear-gradient(135deg,#ffe99a,#ffc93c);
  color:#241645;font-weight:900;font-size:.75rem;
  padding:.25rem .8rem;border-radius:30px;margin-bottom:.6rem;
}
.sf-title{
  font-family:var(--font-display,inherit);
  font-size:1.5rem;margin:.2rem 0 .5rem;color:#fff;
}
.sf-desc{color:#d9d0ff;line-height:1.8;margin:0 0 1rem}

/* بطاقة الفيديو */
.sf-video{
  background:rgba(0,0,0,.22);border-radius:18px;padding:1rem;
  border-right:4px solid #ffc93c;display:flex;align-items:center;
  gap:1rem;flex-wrap:wrap;margin-bottom:1rem;
}
.sf-video-icon{font-size:2.2rem}
.sf-video-info{flex:1;min-width:180px}
.sf-video-info h4{color:#ffc93c;margin:0 0 .2rem;font-size:1rem}
.sf-video-info p{color:#e0d8f0;line-height:1.6;margin:0;font-size:.9rem}

/* أزرار */
.sf-btn{
  background:linear-gradient(135deg,#ffc93c,#f5a623);
  border:none;color:#241645;font-weight:900;
  padding:.7rem 1.5rem;border-radius:50px;cursor:pointer;
  transition:.2s;font-family:inherit;font-size:.9rem;
  box-shadow:0 6px 20px rgba(255,201,60,.25);
}
.sf-btn:hover{transform:translateY(-2px);box-shadow:0 10px 25px rgba(255,201,60,.4)}
.sf-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}
.sf-btn.ready{animation:sfPulse 1.6s ease infinite}
@keyframes sfPulse{
  0%,100%{box-shadow:0 6px 20px rgba(255,201,60,.25)}
  50%{box-shadow:0 12px 35px rgba(255,201,60,.7), 0 0 0 8px rgba(255,201,60,.15)}
}

/* منطقة اللعبة */
.sf-game{
  background:rgba(0,0,0,.18);border-radius:22px;
  padding:1.4rem;text-align:center;margin-bottom:1rem;
}
.sf-hint{font-weight:800;color:#ffc93c;margin-bottom:1rem;font-size:1rem;line-height:1.6}
.sf-fb{
  min-height:2.8rem;margin-top:1rem;padding:.8rem 1rem;
  border-radius:18px;background:rgba(255,255,255,.06);
  font-weight:700;font-size:1rem;display:grid;place-items:center;
  text-align:center;
}
.sf-finish{text-align:center;margin-top:1rem}

/* ========== BODY ========== */
.body-wrap{position:relative;width:200px;height:300px;margin:0 auto;display:grid;place-items:center}
.body{position:relative;width:170px;height:280px}
.bp{position:absolute;transition:all .25s cubic-bezier(.34,1.56,.64,1);cursor:pointer;
  border:3px solid rgba(255,255,255,.15);box-shadow:inset 0 -5px 10px rgba(0,0,0,.15)}
.bp:hover:not(.off){transform:scale(1.08);border-color:#ffc93c;z-index:5}
.bp .lbl{position:absolute;bottom:-28px;left:50%;transform:translateX(-50%);
  background:rgba(0,0,0,.85);color:#fff;padding:3px 9px;border-radius:20px;
  font-size:.65rem;white-space:nowrap;opacity:0;transition:.25s;pointer-events:none}
.bp:hover .lbl{opacity:1}
.bp-head{width:65px;height:65px;top:0;left:50%;transform:translateX(-50%);
  border-radius:50%;background:linear-gradient(145deg,#ffe0b2,#f7d9aa)}
.bp-torso{width:95px;height:105px;top:60px;left:50%;transform:translateX(-50%);
  border-radius:30px 30px 40px 40px;background:linear-gradient(145deg,#f0cfa0,#e6b87a)}
.bp-arm{width:20px;height:75px;top:65px;border-radius:20px;background:linear-gradient(145deg,#ffe0b2,#f7d9aa)}
.bp-armL{left:5px;transform:rotate(15deg);transform-origin:top center}
.bp-armR{right:5px;transform:rotate(-15deg);transform-origin:top center}
.bp-leg{width:26px;height:85px;bottom:0;border-radius:20px 20px 10px 10px;background:linear-gradient(145deg,#ffe0b2,#f7d9aa)}
.bp-legL{left:40px}.bp-legR{right:40px}
.bp.ok{border-color:#2ec4b6;background:#2ec4b6!important;
  box-shadow:0 0 35px rgba(46,196,182,.7);animation:pop .5s}
.bp.no{border-color:#ff6b6b;animation:shake .4s}
.bp.off{pointer-events:none;opacity:.7}
@keyframes pop{0%{transform:scale(1)}50%{transform:scale(1.15)}100%{transform:scale(1.05)}}
@keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-8px)}75%{transform:translateX(8px)}}

/* ========== DISTANCE ========== */
.dist-zone{position:relative;height:220px;
  background:radial-gradient(circle at 30% 30%,rgba(91,141,239,.25),rgba(10,6,26,.9));
  border-radius:22px;overflow:hidden;touch-action:none}
.dist-safe{position:absolute;bottom:0;left:32%;width:36%;height:100%;
  border:3px dashed rgba(46,196,182,.6);border-radius:22px 22px 0 0;
  background:radial-gradient(circle at 50% 100%,rgba(46,196,182,.18),transparent 70%);
  pointer-events:none;display:grid;place-items:end center;padding-bottom:10px;
  color:rgba(46,196,182,.9);font-weight:800;font-size:.8rem}
.dist-kid{position:absolute;bottom:20px;left:8%;font-size:3.5rem;
  cursor:grab;user-select:none;transition:transform .1s;
  filter:drop-shadow(0 6px 12px rgba(0,0,0,.4))}
.dist-kid:active{cursor:grabbing;transform:scale(1.1)}
.dist-bad{position:absolute;bottom:20px;right:8%;font-size:3rem;
  filter:drop-shadow(0 6px 12px rgba(0,0,0,.4))}

/* ========== HOTSPOT ========== */
.hs-scene{position:relative;background:linear-gradient(160deg,rgba(42,27,74,.6),rgba(26,16,64,.7));
  border-radius:22px;min-height:280px;padding:1.2rem;
  display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem}
.hs-item{background:rgba(255,255,255,.08);border:2px solid rgba(255,255,255,.12);
  border-radius:18px;padding:1rem .6rem;cursor:pointer;transition:.25s;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:.4rem;min-height:90px}
.hs-item:hover{transform:translateY(-4px);border-color:#ffc93c}
.hs-item .ico{font-size:2.2rem}
.hs-item .txt{font-size:.75rem;color:#d9d0ff;font-weight:700;text-align:center;line-height:1.3}
.hs-item.danger-ok{background:rgba(255,59,59,.2);border-color:#ff3b3b;animation:pop .5s}
.hs-item.safe-ok{background:rgba(46,196,182,.15);border-color:#2ec4b6;opacity:.55;pointer-events:none}
.hs-item.wrong{background:rgba(255,107,107,.2);border-color:#ff6b6b;animation:shake .4s}

/* ========== SCENARIO ========== */
.sc-box{background:rgba(255,255,255,.05);border-radius:18px;padding:1.2rem}
.sc-q{font-weight:800;font-size:1.05rem;margin-bottom:1rem;line-height:1.6}
.sc-choices{display:flex;flex-direction:column;gap:.6rem}
.sc-choice{background:rgba(255,255,255,.08);border:2px solid transparent;
  border-radius:14px;padding:.8rem 1rem;color:#fff;font-weight:700;
  cursor:pointer;transition:.2s;font-family:inherit;text-align:right;
  font-size:.95rem;display:flex;align-items:center;gap:.6rem}
.sc-choice:hover{background:rgba(255,255,255,.14);border-color:rgba(255,201,60,.5)}
.sc-choice.ok{border-color:#2ec4b6;background:rgba(46,196,182,.2);animation:pop .4s}
.sc-choice.no{border-color:#ff6b6b;background:rgba(255,107,107,.2);animation:shake .4s}
.sc-num{background:rgba(255,201,60,.9);color:#241645;width:26px;height:26px;
  border-radius:50%;display:grid;place-items:center;font-weight:900;
  font-size:.85rem;flex-shrink:0}

/* ========== MATCH ========== */
.mt-board{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:.5rem}
.mt-col{display:flex;flex-direction:column;gap:.5rem}
.mt-item{background:rgba(255,255,255,.08);border:2px solid rgba(255,255,255,.12);
  border-radius:14px;padding:.8rem;color:#fff;font-weight:800;cursor:pointer;
  transition:.2s;font-family:inherit;font-size:1rem;
  display:flex;align-items:center;justify-content:center;gap:.5rem;min-height:52px}
.mt-item:hover{background:rgba(255,255,255,.15)}
.mt-item.sel{border-color:#ffc93c;background:rgba(255,201,60,.2);transform:scale(1.03)}
.mt-item.done{border-color:#2ec4b6;background:rgba(46,196,182,.2);opacity:.6;pointer-events:none}
.mt-item.err{border-color:#ff6b6b;animation:shake .4s}

/* ========== QUIZ ========== */
.qz-box{background:rgba(255,255,255,.05);border-radius:18px;padding:1.2rem}
.qz-progress{height:8px;background:rgba(255,255,255,.1);border-radius:10px;
  overflow:hidden;margin-bottom:1rem}
.qz-fill{height:100%;background:linear-gradient(90deg,#ffc93c,#2ec4b6);
  transition:.4s;border-radius:10px}
.qz-q{font-weight:800;font-size:1.05rem;margin-bottom:1rem;line-height:1.6}
.qz-btns{display:flex;gap:.8rem;justify-content:center}
.qz-btn{flex:1;background:rgba(255,255,255,.08);border:2px solid rgba(255,255,255,.15);
  border-radius:14px;padding:1rem;color:#fff;font-weight:900;font-size:1.1rem;
  cursor:pointer;transition:.2s;font-family:inherit}
.qz-btn:hover{background:rgba(255,255,255,.15);border-color:#ffc93c}
.qz-btn.ok{background:rgba(46,196,182,.25);border-color:#2ec4b6}
.qz-btn.no{background:rgba(255,107,107,.25);border-color:#ff6b6b}

/* ========== PASSWORD ========== */
.pw-box{max-width:380px;margin:0 auto;text-align:center}
.pw-input{width:100%;background:rgba(255,255,255,.1);border:2px solid rgba(255,255,255,.15);
  border-radius:14px;padding:.9rem 1rem;color:#fff;font-size:1.1rem;
  text-align:center;font-family:inherit;letter-spacing:2px}
.pw-input:focus{outline:none;border-color:#ffc93c}
.pw-bar{height:10px;border-radius:10px;background:rgba(255,255,255,.1);
  margin-top:.8rem;overflow:hidden}
.pw-fill{height:100%;width:0;background:linear-gradient(90deg,#ff6b6b,#ffc93c,#2ec4b6);
  transition:.4s;border-radius:10px}
.pw-rules{margin-top:1rem;text-align:right;color:#d9d0ff;font-size:.85rem;line-height:1.9}

/* ========== STREET ========== */
.st-scene{position:relative;
  background:linear-gradient(180deg,rgba(26,16,64,.9) 0%,rgba(26,16,64,.9) 40%,
    rgba(51,51,51,.9) 40%,rgba(51,51,51,.9) 55%,rgba(26,16,64,.9) 55%);
  border-radius:22px;height:280px;overflow:hidden;padding:1rem}
.st-road{position:absolute;top:40%;left:0;right:0;height:15%;background:#2a2a2a;
  border-top:3px dashed #ffc93c;border-bottom:3px dashed #ffc93c}
.st-kid{position:absolute;bottom:8%;left:50%;transform:translateX(-50%);
  font-size:3rem;transition:.3s}
.st-car{position:absolute;top:43%;font-size:2.5rem;transition:left 1.5s linear}
.st-light{position:absolute;top:8%;right:8%;width:60px;height:120px;
  background:#222;border-radius:14px;padding:6px;display:flex;
  flex-direction:column;gap:5px}
.st-lamp{flex:1;border-radius:50%;background:#333}
.st-lamp.on-r{background:#ff3b3b;box-shadow:0 0 20px #ff3b3b}
.st-lamp.on-g{background:#2ec4b6;box-shadow:0 0 20px #2ec4b6}
.st-btn{position:absolute;bottom:5%;right:5%;background:rgba(255,201,60,.9);
  color:#241645;border:none;border-radius:14px;padding:.7rem 1.2rem;
  font-weight:900;cursor:pointer;font-family:inherit;font-size:.9rem}

/* ========== MODAL الفيديو ========== */
.sf-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);
  backdrop-filter:blur(10px);z-index:999;justify-content:center;
  align-items:center;padding:1rem}
.sf-modal.open{display:flex}
.sf-modal-box{background:#1a1040;border-radius:26px;max-width:760px;width:100%;
  padding:1.5rem;position:relative;border:1px solid rgba(255,255,255,.1)}
.sf-modal-close{position:absolute;top:10px;left:10px;background:rgba(255,255,255,.1);
  color:#fff;border:none;width:36px;height:36px;border-radius:50%;
  cursor:pointer;font-size:1.2rem}
.sf-player{width:100%;aspect-ratio:16/9;background:#000;border-radius:14px;
  overflow:hidden;display:grid;place-items:center;color:#b9abd4}
.sf-player iframe{width:100%;height:100%;border:0}

/* ========== شاشة الاحتفال ========== */
.sf-celebrate{position:fixed;inset:0;z-index:9999;display:flex;
  align-items:center;justify-content:center;padding:1rem;
  background:rgba(10,6,26,.94);backdrop-filter:blur(14px);
  animation:sfFadeIn .4s ease}
@keyframes sfFadeIn{from{opacity:0}to{opacity:1}}
.sf-celebrate-box{background:linear-gradient(160deg,#2a1b4a,#1a1040);
  border:2px solid rgba(255,201,60,.4);border-radius:30px;
  padding:2.2rem 1.8rem;max-width:480px;width:100%;text-align:center;
  box-shadow:0 30px 80px rgba(255,201,60,.25),0 0 60px rgba(255,201,60,.15) inset;
  animation:sfPopIn .6s cubic-bezier(.34,1.56,.64,1)}
@keyframes sfPopIn{from{transform:scale(.7);opacity:0}to{transform:scale(1);opacity:1}}
.sf-celebrate-emoji{font-size:5rem;line-height:1;
  animation:sfBounce 1s ease infinite;
  filter:drop-shadow(0 8px 20px rgba(255,201,60,.6))}
@keyframes sfBounce{0%,100%{transform:translateY(0) scale(1)}
  50%{transform:translateY(-12px) scale(1.08)}}
.sf-celebrate-title{font-family:var(--font-display,inherit);font-size:2rem;
  margin:.6rem 0 .2rem;
  background:linear-gradient(135deg,#fff,#ffe99a,#ffc93c);
  -webkit-background-clip:text;background-clip:text;
  -webkit-text-fill-color:transparent}
.sf-celebrate-name{color:#d9d0ff;font-size:1.1rem;margin:.3rem 0 1rem;font-weight:700}
.sf-celebrate-stars{display:flex;justify-content:center;gap:.6rem;
  margin:.6rem 0 1rem;font-size:2.2rem}
.sf-celebrate-stars span{animation:sfStar 1.4s ease infinite}
.sf-celebrate-stars span:nth-child(2){animation-delay:.2s;font-size:2.8rem}
.sf-celebrate-stars span:nth-child(3){animation-delay:.4s}
@keyframes sfStar{0%,100%{transform:scale(1) rotate(0);
  filter:drop-shadow(0 0 8px rgba(255,201,60,.5))}
  50%{transform:scale(1.3) rotate(15deg);
    filter:drop-shadow(0 0 20px rgba(255,201,60,.9))}}
.sf-celebrate-msg{color:#e0d8f0;line-height:1.9;margin:0 0 1.5rem;font-size:1rem}
.sf-celebrate-msg strong{color:#ffc93c}
.sf-celebrate-actions{display:flex;flex-direction:column;gap:.7rem;margin-bottom:1rem}
.sf-celebrate-btn{padding:1rem 1.4rem;border-radius:50px;font-weight:900;
  font-size:1rem;text-decoration:none;text-align:center;transition:.25s;
  border:none;cursor:pointer;font-family:inherit;display:block}
.sf-btn-play{background:linear-gradient(135deg,#ffe99a,#ffc93c);color:#241645;
  box-shadow:0 10px 30px rgba(255,201,60,.4)}
.sf-btn-play:hover{transform:translateY(-3px);
  box-shadow:0 16px 40px rgba(255,201,60,.55)}
.sf-btn-finish{background:rgba(255,255,255,.08);color:#d9d0ff;
  border:1px solid rgba(255,255,255,.15)}
.sf-btn-finish:hover{background:rgba(255,255,255,.15);
  border-color:rgba(255,201,60,.5);color:#fff}
.sf-celebrate-note{color:#b9abd4;font-size:.85rem;margin:.6rem 0 0;line-height:1.6}

/* Confetti */
.sf-confetti{position:fixed;inset:0;z-index:9998;pointer-events:none;overflow:hidden}
.sf-confetti span{position:absolute;top:-60px;
  animation:sfFall 3s linear forwards}
@keyframes sfFall{0%{transform:translateY(-60px) rotate(0)}
  100%{transform:translateY(110vh) rotate(720deg);opacity:.3}}

/* شاشة الختام */
.sf-goodbye-emoji{font-size:5rem;line-height:1;
  animation:sfWave 1.2s ease infinite}
@keyframes sfWave{0%,100%{transform:rotate(0)}25%{transform:rotate(20deg)}
  75%{transform:rotate(-20deg)}}

@media(max-width:600px){
  .sf-guide{flex-direction:column;text-align:center}
  .body{transform:scale(.85);transform-origin:top center}
  .body-wrap{height:260px}
  .hs-scene{grid-template-columns:repeat(2,1fr)}
}
</style>

<div class="sf-wrap">

  <!-- المرشدة -->
  <div class="sf-guide">
    <div class="sf-guide-av">🦉</div>
    <div>
      <span class="sf-guide-name">رفيقتك الحكيمة</span>
      <span class="sf-guide-msg" id="sfMsg">مرحباً بطل! 🌟 درس اليوم جاهز.</span>
    </div>
  </div>

  <!-- الدرس -->
  <div class="sf-card" id="sfLesson">
    <div style="text-align:center;padding:2rem;color:#d9d0ff;">جاري التحميل...</div>
  </div>

</div>

<!-- مودال الفيديو -->
<div class="sf-modal" id="sfVideoModal">
  <div class="sf-modal-box">
    <button class="sf-modal-close" onclick="sfCloseVideo()">✕</button>
    <div class="sf-player" id="sfPlayer"></div>
  </div>
</div>

<script>
/* ============================================================
   البيانات من PHP
   ============================================================ */
const LESSONS = <?= safe_json($lessons) ?>;
const CHILD   = <?= safe_json(['name'=>$child['name']??'بطل','age'=>(int)($child['age']??6)]) ?>;

/* ============================================================
   تتبّع حالة اليوم
   ============================================================ */
function todayKey(){ return new Date().toISOString().slice(0,10); }

function getProgress(){
  let p = {day:0,last:null,done:false};
  try{ p = JSON.parse(localStorage.getItem('kidora_safety_v5')) || p; }catch(e){}
  const t = todayKey();
  if (p.last !== t){
    if (p.done) p.day += 1;
    p.last = t; p.done = false;
    localStorage.setItem('kidora_safety_v5', JSON.stringify(p));
  }
  return p;
}
let PROG = getProgress();
const todayLesson = LESSONS[PROG.day % LESSONS.length];

/* تتبّع المهمتين */
let videoWatched = false;
let gameCompleted = false;

function checkBothDone(){
  const btn = document.getElementById('sfFinish');
  if (btn && videoWatched && gameCompleted){
    btn.disabled = false;
    btn.style.opacity = 1;
    btn.classList.add('ready');
    updateFb('🎉 رائع! أنهيت القصة واللعبة. اضغط الزر الذهبي 👇', '#2ec4b6');
    speak('رائع! أنهيت القصة واللعبة. اضغط على زر أنهيت المهمة.');
  }
}

/* ============================================================
   رسم الدرس
   ============================================================ */
function renderLesson(){
  const wrap = document.getElementById('sfLesson');
  const L = todayLesson;
  if (!L){ wrap.innerHTML = '<p>لا يوجد محتوى</p>'; return; }

  const hasYT = L.youtube_id && String(L.youtube_id).trim() !== '';

  let html = `
    <span class="sf-kicker">📖 قصة اليوم</span>
    <h2 class="sf-title">${esc(L.title)}</h2>
    <p class="sf-desc">${esc(L.description)}</p>
  `;

  if (hasYT){
    html += `
      <div class="sf-video">
        <div class="sf-video-icon">🎬</div>
        <div class="sf-video-info">
          <h4>شاهد القصة</h4>
          <p>فيديو قصير يعلّمك درس اليوم.</p>
        </div>
        <button class="sf-btn" id="sfWatch">▶ شاهد</button>
      </div>
    `;
  } else {
    html += `
      <div class="sf-video">
        <div class="sf-video-icon">🔊</div>
        <div class="sf-video-info">
          <h4>استمع للقصة</h4>
          <p>اضغط لسماع القصة قبل اللعبة.</p>
        </div>
        <button class="sf-btn" id="sfListen">🔊 استمع</button>
      </div>
    `;
  }

  html += `
    <span class="sf-kicker">🎮 لعبة اليوم</span>
    <h3 class="sf-title" style="font-size:1.2rem">${gameTitle(L.game_type)}</h3>
    <p class="sf-desc" style="margin-bottom:.6rem">${gameDesc(L.game_type)}</p>
    <div class="sf-game" id="sfGameBox"></div>
    <div class="sf-fb" id="sfFb">جرّب اللعبة 👇</div>
    <div class="sf-finish">
      <button class="sf-btn" id="sfFinish" disabled>🎯 أنهيت المهمة</button>
    </div>
  `;
  wrap.innerHTML = html;

  /* ربط زر الفيديو */
  const wBtn = document.getElementById('sfWatch');
  if (wBtn) wBtn.addEventListener('click', () => {
    openVideo(`https://www.youtube.com/embed/${encodeURIComponent(L.youtube_id)}?autoplay=1&rel=0`);
    setTimeout(() => {
      videoWatched = true;
      checkBothDone();
    }, 1500);
  });
  const lBtn = document.getElementById('sfListen');
  if (lBtn) lBtn.addEventListener('click', () => {
    speak(L.title + '. ' + L.description);
    setTimeout(() => {
      videoWatched = true;
      checkBothDone();
    }, 1200);
  });

  /* تشغيل اللعبة */
  const box = document.getElementById('sfGameBox');
  const gt = (L.game_type || 'body').toLowerCase();
  const Engine = GAMES[gt] || GAMES.body;
  Engine(box, L);

  document.getElementById('sfMsg').textContent = `اليوم: "${L.title}". أنهِ اللعبة لفتح درس الغد.`;
}

/* ============================================================
   عناوين الألعاب
   ============================================================ */
function gameTitle(t){
  return ({
    body:'🛡️ لعبة المنطقة الخاصة',
    distance:'📏 لعبة المسافة الآمنة',
    hotspot:'🔥 لعبة اكتشف الخطر',
    scenario:'🤔 لعبة اتخاذ القرار',
    match:'📞 لعبة أرقام الطوارئ',
    quiz:'✅ لعبة صح أم خطأ',
    password:'🔐 لعبة كلمة السر القوية',
    street:'🚦 لعبة عبور الشارع'
  })[t] || '🎮 لعبة';
}
function gameDesc(t){
  return ({
    body:'اضغط على المنطقة التي لا يجوز لأحد لمسها.',
    distance:'اسحب نفسك إلى المنطقة الآمنة.',
    hotspot:'اضغط على الأشياء الخطيرة في المشهد.',
    scenario:'اختر التصرف الصحيح في كل موقف.',
    match:'وصّل الرقم بالجهة الصحيحة.',
    quiz:'أجب صح أو خطأ على الأسئلة.',
    password:'اكتب كلمة سر قوية لتربح.',
    street:'اعبر الشارع عندما تكون الإشارة خضراء.'
  })[t] || '';
}

/* ============================================================
   محرك 1: BODY
   ============================================================ */
function gameBody(box){
  box.innerHTML = `
    <div class="sf-hint">👇 اضغط على المنطقة التي لا يجوز لأحد لمسها</div>
    <div class="body-wrap">
      <div class="body" id="bodyEl">
        <div class="bp bp-head" data-priv="0"><span class="lbl">الرأس</span></div>
        <div class="bp bp-torso" data-priv="1"><span class="lbl">المنطقة الخاصة</span></div>
        <div class="bp bp-arm bp-armL" data-priv="0"><span class="lbl">الذراع</span></div>
        <div class="bp bp-arm bp-armR" data-priv="0"><span class="lbl">الذراع</span></div>
        <div class="bp bp-leg bp-legL" data-priv="0"><span class="lbl">الساق</span></div>
        <div class="bp bp-leg bp-legR" data-priv="0"><span class="lbl">الساق</span></div>
      </div>
    </div>
  `;
  setTimeout(()=>speak('اضغط على المنطقة التي لا يجوز لأحد لمسها. تذكر، جسمك ملكك.'), 400);

  let done = false;
  document.querySelectorAll('#bodyEl .bp').forEach(el=>{
    el.addEventListener('click', function(){
      if (done || this.classList.contains('off')) return;
      if (this.dataset.priv === '1'){
        this.classList.add('ok','off');
        updateFb('✅ أحسنت! هذه هي المنطقة الخاصة. 🌟', '#2ec4b6');
        speak('أحسنت! هذه المنطقة الخاصة، لا يجوز لأحد لمسها.');
        done = true; gameCompleted = true; checkBothDone();
      } else {
        this.classList.add('no');
        updateFb('❌ هذه ليست المنطقة الخاصة. جرّب غيرها.', '#ff6b6b');
        setTimeout(()=>this.classList.remove('no'), 600);
      }
    });
  });
}

/* ============================================================
   محرك 2: DISTANCE
   ============================================================ */
function gameDistance(box){
  box.innerHTML = `
    <div class="sf-hint">👇 اسحب نفسك إلى المنطقة الآمنة (الدائرة المنقطة)</div>
    <div class="dist-zone" id="dz">
      <div class="dist-safe">منطقة آمنة</div>
      <div class="dist-kid" id="dk">🧒</div>
      <div class="dist-bad">🧔</div>
    </div>
  `;
  setTimeout(()=>speak('اسحب نفسك بعيداً عن الغريب، إلى المنطقة الآمنة.'), 400);

  const dz = document.getElementById('dz');
  const dk = document.getElementById('dk');
  let drag=false, done=false;

  function move(clientX){
    if (!drag || done) return;
    const r = dz.getBoundingClientRect();
    let x = clientX - r.left - 28;
    x = Math.max(0, Math.min(x, r.width - 60));
    dk.style.left = x + 'px';
    const pct = (x / r.width) * 100;
    if (pct >= 32 && pct <= 68){
      done = true;
      updateFb('✅ ممتاز! هذا هو البعد الآمن عن الغريب.', '#2ec4b6');
      speak('أحسنت! هذه هي المسافة الآمنة.');
      gameCompleted = true; checkBothDone();
    } else {
      updateFb('⬅️ تابع السحب حتى المنطقة المنقطة', '#ffc93c');
    }
  }
  dk.addEventListener('mousedown', e=>{drag=true;e.preventDefault();});
  dk.addEventListener('touchstart', e=>{drag=true;e.preventDefault();}, {passive:false});
  document.addEventListener('mousemove', e=>move(e.clientX));
  document.addEventListener('touchmove', e=>move(e.touches[0].clientX), {passive:false});
  document.addEventListener('mouseup', ()=>drag=false);
  document.addEventListener('touchend', ()=>drag=false);
}

/* ============================================================
   محرك 3: HOTSPOT
   ============================================================ */
function gameHotspot(box, L){
  const title = (L.title || '').toLowerCase();
  let items;
  if (title.includes('مطبخ')) items = kitchenItems();
  else if (title.includes('سباح')) items = poolItems();
  else items = homeItems();

  box.innerHTML = `
    <div class="sf-hint">👇 اضغط على كل الأشياء الخطيرة في المشهد</div>
    <div class="hs-scene" id="hsScene"></div>
  `;
  setTimeout(()=>speak('اضغط على الأشياء الخطيرة في المشهد. احذر، ليست كل الأشياء خطيرة.'), 400);

  const scene = document.getElementById('hsScene');
  let found=0;
  const totalDanger = items.filter(i=>i.danger).length;

  items.forEach(it=>{
    const el = document.createElement('div');
    el.className='hs-item';
    el.dataset.danger = it.danger ? '1' : '0';
    el.innerHTML = `<div class="ico">${it.ico}</div><div class="txt">${it.txt}</div>`;
    el.addEventListener('click', function(){
      if (this.classList.contains('done')) return;
      this.classList.add('done');
      if (this.dataset.danger === '1'){
        this.classList.add('danger-ok');
        found++;
        updateFb(`✅ صحيح! "${it.txt}" خطير. (${found}/${totalDanger})`, '#2ec4b6');
        if (found === totalDanger){
          updateFb('🎉 أحسنت! اكتشفت كل الأشياء الخطيرة.', '#2ec4b6');
          speak('ممتاز! اكتشفت كل الأشياء الخطيرة.');
          gameCompleted = true; checkBothDone();
        }
      } else {
        this.classList.add('wrong');
        updateFb(`❌ "${it.txt}" آمن، ليس خطراً.`, '#ff6b6b');
        setTimeout(()=>{ this.classList.remove('done','wrong'); }, 800);
      }
    });
    scene.appendChild(el);
  });
}
function kitchenItems(){
  return [
    {ico:'🔪',txt:'سكين',danger:1},
    {ico:'🔥',txt:'فرن ساخن',danger:1},
    {ico:'🥤',txt:'كوب ماء',danger:0},
    {ico:'🍳',txt:'مقلاة ساخنة',danger:1},
    {ico:'🧴',txt:'منظف',danger:1},
    {ico:'🍎',txt:'تفاحة',danger:0}
  ];
}
function poolItems(){
  return [
    {ico:'🏊',txt:'السباحة مع شخص كبير',danger:0},
    {ico:'🛟',txt:'عوامة',danger:0},
    {ico:'🏃',txt:'الجري على الحافة',danger:1},
    {ico:'🌊',txt:'السباحة وحدك',danger:1},
    {ico:'💧',txt:'القفز في ماء غير معروف',danger:1},
    {ico:'🕶️',txt:'نظارة سباحة',danger:0}
  ];
}
function homeItems(){
  return [
    {ico:'🔌',txt:'قابس كهرباء',danger:1},
    {ico:'🔥',txt:'ولّاعة',danger:1},
    {ico:'🧸',txt:'دمية',danger:0},
    {ico:'💊',txt:'أدوية',danger:1},
    {ico:'📚',txt:'كتاب',danger:0},
    {ico:'🪜',txt:'درج مرتفع',danger:1}
  ];
}

/* ============================================================
   محرك 4: SCENARIO
   ============================================================ */
function gameScenario(box, L){
  const title = (L.title || '').toLowerCase();
  let qs;
  if (title.includes('حريق'))     qs = fireQs();
  else if (title.includes('تنمر')) qs = bullyQs();
  else if (title.includes('لا'))   qs = noQs();
  else if (title.includes('مشاعر') || title.includes('خائف')) qs = feelingsQs();
  else qs = generalQs();

  let i = 0;
  function render(){
    if (i >= qs.length){
      box.innerHTML = `<div style="padding:2rem;text-align:center"><div style="font-size:3rem">🎉</div><p style="font-weight:800;color:#2ec4b6">أحسنت! أجبت على كل المواقف.</p></div>`;
      speak('ممتاز! أنهيت كل المواقف.');
      gameCompleted = true; checkBothDone();
      return;
    }
    const q = qs[i];
    box.innerHTML = `
      <div class="sc-box">
        <div class="sc-q">${q.q}</div>
        <div class="sc-choices">
          ${q.a.map((opt,idx)=>`<button class="sc-choice" data-i="${idx}"><span class="sc-num">${idx+1}</span>${opt}</button>`).join('')}
        </div>
      </div>
    `;
    /* 🔊 قراءة السؤال والخيارات تلقائياً */
    const readText = `${cleanEmoji(q.q)}. الخيارات: ` + q.a.map((o,idx)=>`${idx+1}: ${cleanEmoji(o)}`).join('. ');
    setTimeout(()=>speak(readText), 400);

    box.querySelectorAll('.sc-choice').forEach(b=>{
      b.addEventListener('click', function(){
        const i2 = parseInt(this.dataset.i);
        box.querySelectorAll('.sc-choice').forEach(x=>x.disabled=true);
        if (i2 === q.correct){
          this.classList.add('ok');
          updateFb('✅ إجابة صحيحة!', '#2ec4b6');
          speak('إجابة صحيحة!');
          setTimeout(()=>{ i++; render(); }, 1500);
        } else {
          this.classList.add('no');
          box.querySelectorAll('.sc-choice')[q.correct].classList.add('ok');
          updateFb('❌ الإجابة الصحيحة معلمة بالأخضر.', '#ff6b6b');
          speak('ليست الإجابة الصحيحة. الإجابة الصحيحة معلمة بالأخضر.');
          setTimeout(()=>{ i++; render(); }, 2000);
        }
      });
    });
  }
  render();
}
function fireQs(){return[
  {q:'🔥 بدأ حريق في المطبخ. ماذا تفعل أولاً؟',a:['أختبئ تحت السرير','أخرج من البيت فوراً','أفتح كل الأبواب'],correct:1},
  {q:'🚪 الباب ساخن. ماذا تفعل؟',a:['أفتحه بسرعة','لا أفتحه وأبحث عن مخرج آخر','أقفز من الشباك'],correct:1},
  {q:'📞 بعد الخروج، ماذا تفعل؟',a:['أتصل بالدفاع المدني 102','أرجع للبيت','أبتعد وأسكت'],correct:0}
];}
function bullyQs(){return[
  {q:'💬 زميل يكتب كلاماً سيئاً عنك في مجموعة الصف. ماذا تفعل؟',a:['أرد بمثل كلامه','أحفظ الرسائل وأخبر والدي','أنسحب من المجموعة وأسكت'],correct:1},
  {q:'📸 أحد نشر صورتك بدون إذن. ماذا تفعل؟',a:['أنشر صورته','أخبر والدي وأطلب من المنصة حذفها','أتجاهل الموضوع'],correct:1}
];}
function noQs(){return[
  {q:'😟 شخص أكبر منك يطلب منك شيئاً لا تريده. ماذا تفعل؟',a:['أوافق خوفاً','أقول لا بصوت واضح وأخبر أهلي','أهرب وأسكت'],correct:1},
  {q:'🎁 غريب أعطاك حلوى في الشارع. ماذا تفعل؟',a:['آخذها وأكلها','أرفض وأبتعد وأخبر والدي','آخذها وأشكره'],correct:1}
];}
function feelingsQs(){return[
  {q:'😨 شعرت بالخوف. ماذا تفعل؟',a:['أكتم مشاعري','أخبر شخصاً كبيراً أثق به','أضحك وأتجاهل'],correct:1},
  {q:'😢 حزين لأن أحداً أذاك. ما الأفضل؟',a:['أخبر والدي أو معلمي','أبقى صامتاً','أنتقم بنفسي'],correct:0}
];}
function generalQs(){return[
  {q:'🤝 هل من حقك أن ترفض لمسة لا تريدها؟',a:['نعم، دائماً','لا، هذا وقاحة','فقط مع الغرباء'],correct:0},
  {q:'🗣️ إذا حدث شيء يزعجك، الأفضل أن...',a:['أخبر شخصاً كبيراً أثق به','أبقيه سرّاً','أضحك وأتجاهل'],correct:0}
];}

/* ============================================================
   محرك 5: MATCH
   ============================================================ */
function gameMatch(box){
  const pairs = [
    {a:'🚓',b:'100 - الشرطة'},
    {a:'🚑',b:'101 - الإسعاف'},
    {a:'🚒',b:'102 - الدفاع المدني'}
  ];
  const left  = pairs.map((p,i)=>({t:p.a,id:i}));
  const right = pairs.map((p,i)=>({t:p.b,id:i})).sort(()=>Math.random()-.5);

  box.innerHTML = `
    <div class="sf-hint">👇 وصّل الرمز بالرقم الصحيح</div>
    <div class="mt-board">
      <div class="mt-col" id="mtL">${left.map(x=>`<button class="mt-item" data-id="${x.id}" data-side="L">${x.t}</button>`).join('')}</div>
      <div class="mt-col" id="mtR">${right.map(x=>`<button class="mt-item" data-id="${x.id}" data-side="R">${x.t}</button>`).join('')}</div>
    </div>
  `;
  setTimeout(()=>speak('وصّل الرمز بالرقم الصحيح. اضغط على رمز، ثم اضغط على الرقم المناسب.'), 400);

  let sel = null, matches = 0;
  box.querySelectorAll('.mt-item').forEach(el=>{
    el.addEventListener('click', function(){
      if (this.classList.contains('done')) return;
      if (!sel){ sel = this; this.classList.add('sel'); return; }
      if (sel === this){ this.classList.remove('sel'); sel = null; return; }
      if (sel.dataset.side === this.dataset.side){
        sel.classList.remove('sel'); sel = this; this.classList.add('sel'); return;
      }
      if (sel.dataset.id === this.dataset.id){
        sel.classList.remove('sel'); sel.classList.add('done'); this.classList.add('done');
        matches++;
        updateFb(`✅ صحيح! (${matches}/3)`, '#2ec4b6');
        if (matches === 3){
          updateFb('🎉 رائع! تعلمت أرقام الطوارئ.', '#2ec4b6');
          speak('ممتاز! تعلمت أرقام الطوارئ.');
          gameCompleted = true; checkBothDone();
        }
      } else {
        const a = sel; sel = null;
        this.classList.add('err'); a.classList.add('err');
        setTimeout(()=>{ this.classList.remove('err','sel'); a.classList.remove('err','sel'); }, 600);
        updateFb('❌ غير متطابق، جرّب مرة أخرى', '#ff6b6b');
      }
      sel = null;
    });
  });
}

/* ============================================================
   محرك 6: QUIZ
   ============================================================ */
function gameQuiz(box){
  const qs = [
    {q:'🔒 كلمة السر يجب أن تكون سهلة مثل 1234', a:false},
    {q:'👤 لا تشارك اسمك وعنوانك مع غريب على الإنترنت', a:true},
    {q:'📸 من الآمن نشر صورك مع أي شخص', a:false},
    {q:'🚫 إذا أزعجك شخص على الإنترنت، أخبر والديك', a:true},
    {q:'👥 من الآمن مقابلة شخص تعرفت عليه على الإنترنت وحدك', a:false}
  ];
  let i = 0;
  function render(){
    if (i >= qs.length){
      box.innerHTML = `<div style="padding:2rem;text-align:center"><div style="font-size:3rem">🏆</div><p style="font-weight:800;color:#2ec4b6">أنهيت الاختبار!</p></div>`;
      speak('أحسنت! أنهيت الاختبار.');
      gameCompleted = true; checkBothDone();
      return;
    }
    const q = qs[i];
    const pct = (i/qs.length)*100;
    box.innerHTML = `
      <div class="qz-box">
        <div class="qz-progress"><div class="qz-fill" style="width:${pct}%"></div></div>
        <div class="qz-q">${q.q}</div>
        <div class="qz-btns">
          <button class="qz-btn" data-v="1">✅ صح</button>
          <button class="qz-btn" data-v="0">❌ خطأ</button>
        </div>
      </div>
    `;
    /* 🔊 قراءة السؤال تلقائياً */
    setTimeout(()=>speak(`السؤال ${i+1} من ${qs.length}. ${cleanEmoji(q.q)}. صح أم خطأ؟`), 400);

    box.querySelectorAll('.qz-btn').forEach(b=>{
      b.addEventListener('click', function(){
        const v = this.dataset.v === '1';
        box.querySelectorAll('.qz-btn').forEach(x=>x.disabled=true);
        if (v === q.a){
          this.classList.add('ok');
          updateFb('✅ صحيح!', '#2ec4b6'); speak('صحيح');
        } else {
          this.classList.add('no');
          updateFb('❌ خطأ', '#ff6b6b'); speak('خطأ');
        }
        setTimeout(()=>{ i++; render(); }, 1500);
      });
    });
  }
  render();
}

/* ============================================================
   محرك 7: PASSWORD
   ============================================================ */
function gamePassword(box){
  box.innerHTML = `
    <div class="sf-hint">🔐 اكتب كلمة سر قوية لتربح</div>
    <div class="pw-box">
      <input type="text" id="pwIn" class="pw-input" placeholder="••••••••" autocomplete="off">
      <div class="pw-bar"><div class="pw-fill" id="pwFill"></div></div>
      <div class="pw-rules" id="pwRules"></div>
    </div>
  `;
  setTimeout(()=>speak('اكتب كلمة سر قوية. لازم تكون 8 أحرف على الأقل، مع حرف كبير وحرف صغير ورقم ورمز.'), 400);

  const inp = document.getElementById('pwIn');
  const fill = document.getElementById('pwFill');
  const rules = document.getElementById('pwRules');
  let done = false;

  inp.addEventListener('input', function(){
    if (done) return;
    const v = this.value;
    const c = {
      len: v.length >= 8,
      up:  /[A-Z]/.test(v),
      lo:  /[a-z]/.test(v),
      num: /\d/.test(v),
      sym: /[!@#$%^&*]/.test(v)
    };
    const score = Object.values(c).filter(Boolean).length;
    fill.style.width = (score*20)+'%';
    rules.innerHTML = `
      <div>${c.len?'✅':'⬜'} 8 أحرف على الأقل</div>
      <div>${c.up?'✅':'⬜'} حرف كبير A-Z</div>
      <div>${c.lo?'✅':'⬜'} حرف صغير a-z</div>
      <div>${c.num?'✅':'⬜'} رقم 0-9</div>
      <div>${c.sym?'✅':'⬜'} رمز مثل !@#</div>
    `;
    if (score === 5){
      done = true;
      updateFb('🎉 كلمة سر قوية جداً!', '#2ec4b6');
      speak('ممتاز! كلمة سر قوية.');
      gameCompleted = true; checkBothDone();
    } else if (score >= 3){ updateFb('👍 جيدة، كمّل', '#ffc93c'); }
    else { updateFb('⚠️ ضعيفة', '#ff6b6b'); }
  });
}

/* ============================================================
   محرك 8: STREET
   ============================================================ */
function gameStreet(box){
  box.innerHTML = `
    <div class="sf-hint">🚦 اضغط اعبر عندما تكون الإشارة خضراء</div>
    <div class="st-scene" id="stScene">
      <div class="st-road"></div>
      <div class="st-light" id="stLight">
        <div class="st-lamp" id="stR"></div>
        <div class="st-lamp" id="stG"></div>
      </div>
      <div class="st-kid" id="stKid">🧒</div>
      <div class="st-car" id="stCar" style="left:-100px">🚗</div>
      <button class="st-btn" id="stBtn">🚶 اعبر</button>
    </div>
  `;
  setTimeout(()=>speak('انتظر حتى تصبح الإشارة خضراء والسيارة بعيدة، ثم اضغط اعبر.'), 400);

  const R = document.getElementById('stR'), G = document.getElementById('stG');
  const car = document.getElementById('stCar'), kid = document.getElementById('stKid');
  const btn = document.getElementById('stBtn');
  let green = false, done = false;

  function newRound(){
    if (done) return;
    green = Math.random() < 0.5;
    R.classList.toggle('on-r', !green);
    G.classList.toggle('on-g',  green);
    if (!green){
      car.style.transition = 'none';
      car.style.left = '-100px';
      setTimeout(()=>{
        car.style.transition = 'left 2s linear';
        car.style.left = '110%';
      }, 200);
    }
  }
  newRound();
  const lightTimer = setInterval(()=>{ if (!done) newRound(); }, 2500);

  btn.addEventListener('click', ()=>{
    if (done) return;
    const carX = car.getBoundingClientRect();
    const kidX = kid.getBoundingClientRect();
    const carNear = carX.left < kidX.right + 100 && carX.right > kidX.left - 100;

    if (green && !carNear){
      done = true; clearInterval(lightTimer);
      kid.textContent = '🧒✅';
      updateFb('🎉 أحسنت! عبرت الشارع بأمان.', '#2ec4b6');
      speak('أحسنت! عبرت بأمان.');
      gameCompleted = true; checkBothDone();
    } else {
      updateFb(green ? '⚠️ السيارة قادمة! انتظر.' : '⛔ الإشارة حمراء! انتظر.', '#ff6b6b');
      kid.style.transform = 'translateX(-50%) rotate(10deg)';
      setTimeout(()=> kid.style.transform = 'translateX(-50%)', 400);
    }
  });
}

/* ============================================================
   خريطة المحركات
   ============================================================ */
const GAMES = {
  body:     gameBody,
  distance: gameDistance,
  hotspot:  gameHotspot,
  scenario: gameScenario,
  match:    gameMatch,
  quiz:     gameQuiz,
  password: gamePassword,
  street:   gameStreet
};

/* ============================================================
   الفيديو
   ============================================================ */
function openVideo(url){
  document.getElementById('sfPlayer').innerHTML =
    `<iframe src="${url}" allow="autoplay;encrypted-media" allowfullscreen></iframe>`;
  document.getElementById('sfVideoModal').classList.add('open');
}
function sfCloseVideo(){
  document.getElementById('sfVideoModal').classList.remove('open');
  document.getElementById('sfPlayer').innerHTML = '';
}
document.getElementById('sfVideoModal').addEventListener('click', e=>{
  if (e.target.id === 'sfVideoModal') sfCloseVideo();
});

/* ============================================================
   مساعدات
   ============================================================ */
function updateFb(txt, color){
  const fb = document.getElementById('sfFb');
  if (fb){
    fb.innerHTML = txt;
    if (color) fb.style.color = color;
  }
}
function esc(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]);}
function cleanEmoji(s){
  return String(s||'').replace(/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{1F000}-\u{1F2FF}\u{FE0F}]/gu, '').trim();
}

/* ============================================================
   الصوت
   ============================================================ */
let currentUtterance = null;
function speak(t){
  if (!('speechSynthesis' in window)) return;
  window.speechSynthesis.cancel();
  const u = new SpeechSynthesisUtterance(String(t||''));
  u.lang = 'ar-SA'; u.rate = 1; u.pitch = 1.15;
  const v = speechSynthesis.getVoices().find(x=>x.lang.startsWith('ar'));
  if (v) u.voice = v;
  currentUtterance = u;
  speechSynthesis.speak(u);
}
if ('speechSynthesis' in window){
  speechSynthesis.getVoices();
  speechSynthesis.onvoiceschanged = ()=>speechSynthesis.getVoices();
}

/* ============================================================
   زر الإنهاء → شاشة الاحتفال
   ============================================================ */
document.addEventListener('click', e=>{
  if (e.target && e.target.id === 'sfFinish' && !e.target.disabled){
    showCelebration();
  }
});

/* ============================================================
   شاشة الاحتفال
   ============================================================ */
function showCelebration(){
  speak(`مبروك يا ${CHILD.name}! أنهيت مهمة اليوم بنجاح. أنت رائع!`);

  /* Confetti */
  const confetti = document.createElement('div');
  confetti.className = 'sf-confetti';
  for (let i=0; i<60; i++){
    const c = document.createElement('span');
    c.textContent = ['🎉','🎊','⭐','🌟','✨','🏆','💫'][Math.floor(Math.random()*7)];
    c.style.left = Math.random()*100 + '%';
    c.style.animationDelay = (Math.random()*2) + 's';
    c.style.fontSize = (Math.random()*20 + 20) + 'px';
    confetti.appendChild(c);
  }
  document.body.appendChild(confetti);
  setTimeout(()=>confetti.remove(), 4000);

  const modal = document.createElement('div');
  modal.className = 'sf-celebrate';
  modal.innerHTML = `
    <div class="sf-celebrate-box">
      <div class="sf-celebrate-emoji">🏆</div>
      <h2 class="sf-celebrate-title">مبروك يا بطل!</h2>
      <p class="sf-celebrate-name">${esc(CHILD.name)} 🌟</p>
      <div class="sf-celebrate-stars">
        <span>⭐</span><span>⭐</span><span>⭐</span>
      </div>
      <p class="sf-celebrate-msg">
        أنهيت قصة اليوم <strong>"${esc(todayLesson.title)}"</strong><br>
        وأكملت اللعبة بنجاح! 🎯
      </p>
      <div class="sf-celebrate-actions">
        <a href="<?= BASE_PATH ?>/games.php" class="sf-celebrate-btn sf-btn-play">
          🎮 العب لعبة إضافية
        </a>
        <button class="sf-celebrate-btn sf-btn-finish" id="sfFinishDay">
          🏁 أنهيت المهمة، أراك غداً
        </button>
      </div>
      <p class="sf-celebrate-note">
        💡 لا تنسَ أن تكمل أنشطتك اليومية الأخرى!
      </p>
    </div>
  `;
  document.body.appendChild(modal);

  /* زر الإنهاء النهائي → شاشة الختام */
  document.getElementById('sfFinishDay').addEventListener('click', ()=>{
    PROG.day += 1;
    PROG.last = todayKey();
    PROG.done = true;
    localStorage.setItem('kidora_safety_v5', JSON.stringify(PROG));

    showGoodbye(modal);
  });
}

/* ============================================================
   شاشة الختام — انتهى وقتك اليوم
   ============================================================ */
function showGoodbye(modal){
  speak(`خلص وقتك اليوم في المنصة يا ${CHILD.name}! بنشوفك بكرا بألعاب وقصص جديدة. إلى اللقاء!`);

  modal.querySelector('.sf-celebrate-box').innerHTML = `
    <div class="sf-goodbye-emoji">👋</div>
    <h2 class="sf-celebrate-title">خلص وقتك في المنصة!</h2>
    <p class="sf-celebrate-name">${esc(CHILD.name)} 🌟</p>
    <div class="sf-celebrate-stars">
      <span>🎮</span><span>📚</span><span>✨</span>
    </div>
    <p class="sf-celebrate-msg" style="font-size:1.05rem">
      <strong>بنشوفك بكرا</strong><br>
      بألعاب وقصص جديدة ومغامرات أحلى! 🚀
    </p>
    <div class="sf-celebrate-actions">
      <a href="<?= BASE_PATH ?>/dashboard.php" class="sf-celebrate-btn sf-btn-play">
        🏠 رجوع للرئيسية
      </a>
    </div>
    <p class="sf-celebrate-note">
      💚 اذهب الآن وأكمل أنشطتك اليومية.<br>
      نحن فخورون بك يا بطل!
    </p>
  `;
}

/* ============================================================
   التشغيل
   ============================================================ */
renderLesson();
setTimeout(()=>speak(`مرحباً ${CHILD.name}! درس اليوم جاهز.`), 800);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
