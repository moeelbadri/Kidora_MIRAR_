<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
if (!has_full_access($pdo, $child)) {
    $__pageTitle = 'بطل الأمان — Kidora';
    $__pageLine = 'مهمات الأمان تعود مع الاشتراك، والقصص والألعاب ما زالت مفتوحة لك.';
    require_once __DIR__ . '/includes/header.php';
    require_once __DIR__ . '/includes/navbar.php';
    render_upgrade_gate('مهمات بطل الأمان');
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

/* json_encode آمن */
function safe_json($d){
    $j = json_encode($d,
        JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|
        JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE);
    return $j === false ? '[]' : $j;
}

/* كل الدروس المجانية. عمر الطفل لا يخفي درساً. */
$stmt = $pdo->prepare("
  SELECT id,type,title,description,youtube_id,game_type,age_min,age_max
  FROM safety_content
  WHERE (is_premium=0 OR is_premium IS NULL)
  ORDER BY id ASC
");
$stmt->execute();
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if (!$lessons) {
  $lessons = [[
    'id'=>0,'type'=>'video','title'=>'جسدي ملكي',
    'description'=>'جسمك لك وحدك، وهناك مناطق خاصة لا يجوز لأحد لمسها.',
    'youtube_id'=>'FUC2yrD2Gz8','game_type'=>'body'
  ]];
}

$progress = ensure_daily_progress($pdo, $child['id']);
$__pageTitle = 'بطل الأمان — Kidora';
$__pageLine  = 'مهمة الأمان اليوم من ثلاث خطوات: قاعدة، ولعبة، ووسام. أنا معك في كل خطوة.';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
/* =========================================================
   قسم الحماية — تصميم متجاوب بالكامل
   ========================================================= */
.sf-wrap{
  max-width: 860px;
  margin: 0 auto;
  padding: 1rem .85rem 2.5rem;
  direction: rtl;
  box-sizing: border-box;
}
.sf-wrap *,
.sf-wrap *::before,
.sf-wrap *::after{ box-sizing: border-box; }

/* ---------- شريط الخطوات ---------- */
.sf-steps{
  display: flex;
  justify-content: center;
  align-items: center;
  gap: .4rem;
  margin: 0 0 1rem;
  flex-wrap: wrap;
  position: relative;
  z-index: 2;
}
.sf-step{
  display: flex;
  align-items: center;
  gap: .4rem;
  padding: .5rem .9rem;
  border-radius: 50px;
  background: rgba(255,255,255,.07);
  border: 2px solid rgba(255,255,255,.12);
  color: #d9d0ff;
  font-weight: 900;
  font-size: .85rem;
  min-height: 42px;
  transition: .3s;
  white-space: nowrap;
}
.sf-step .n{
  width: 24px; height: 24px;
  border-radius: 50%;
  display: grid; place-items: center;
  background: rgba(255,255,255,.12);
  font-size: .8rem;
  flex-shrink: 0;
}
.sf-step.is-on{
  background: linear-gradient(135deg,#ffe99a,#ffc93c);
  color: #241645;
  border-color: #ffc93c;
  box-shadow: 0 6px 18px rgba(255,201,60,.35);
  transform: scale(1.04);
}
.sf-step.is-on .n{ background: #241645; color: #ffc93c; }
.sf-step.is-done{
  background: rgba(46,196,182,.18);
  border-color: #2ec4b6;
  color: #c8fff6;
}
.sf-step.is-done .n{ background: #2ec4b6; color: #0b3b36; }

/* ---------- البطاقة الرئيسية ---------- */
.sf-view{
  transition: opacity .3s, transform .3s;
}
.sf-view.is-out{
  opacity: 0;
  transform: translateY(14px);
}
.sf-card{
  text-align: center;
  background: var(--k-card, rgba(255,255,255,.06));
  border: 1px solid var(--k-line, rgba(255,255,255,.12));
  border-radius: 22px;
  padding: 1.4rem 1.1rem;
  margin: 0 0 1rem;
  backdrop-filter: blur(6px);
  box-shadow: 0 14px 40px rgba(0,0,0,.18);
}

/* شارة الخطوة */
.sf-badge{
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .85rem;
  font-weight: 900;
  color: #241645;
  background: linear-gradient(135deg,#ffe99a,#ffc93c);
  padding: .35rem .9rem;
  border-radius: 50px;
  margin-bottom: .7rem;
  line-height: 1.4;
}
.sf-hero-emoji{
  font-size: 2.6rem;
  line-height: 1;
  margin: .1rem 0 .35rem;
  display: inline-block;
  animation: sfFloat 3s ease-in-out infinite;
}
@keyframes sfFloat{
  0%,100%{ transform: translateY(0); }
  50%    { transform: translateY(-6px); }
}
.sf-title{
  font-family: var(--font-display, inherit);
  font-size: 1.3rem;
  margin: .2rem 0 .6rem;
  color: #fff;
  line-height: 1.4;
}
.sf-rule{
  background: rgba(0,0,0,.22);
  border-right: 5px solid #ffc93c;
  border-radius: 14px;
  padding: .85rem 1rem;
  font-size: 1.05rem;
  line-height: 1.75;
  color: #fff;
  text-align: right;
  margin: 0 0 1rem;
}
.sf-desc{
  color: #d9d0ff;
  line-height: 1.7;
  font-size: .95rem;
  margin: 0 0 .9rem;
}

/* الفيديو */
.sf-ratio{
  position: relative;
  padding-top: 56.25%;
  border-radius: 16px;
  overflow: hidden;
  background: #000;
  margin-bottom: 1rem;
}
.sf-ratio .yt-host,
.sf-ratio iframe{
  position: absolute; inset: 0;
  width: 100%; height: 100%; border: 0;
}

/* ---------- الأزرار ---------- */
.sf-actions{
  display: flex;
  gap: .7rem;
  justify-content: center;
  flex-wrap: wrap;
  margin-top: .8rem;
}
.sf-btn{
  background: linear-gradient(135deg,#ffc93c,#f5a623);
  border: none;
  color: #241645;
  font-weight: 900;
  padding: .65rem 1.3rem;
  border-radius: 50px;
  cursor: pointer;
  transition: .2s;
  font-family: inherit;
  font-size: .9rem;
  box-shadow: 0 6px 20px rgba(255,201,60,.25);
}
.sf-btn:hover{ transform: translateY(-2px); box-shadow: 0 10px 25px rgba(255,201,60,.4); }
.sf-btn:disabled{ opacity: .5; cursor: not-allowed; transform: none; box-shadow: none; }
.sf-btn-lg{
  min-height: 54px;
  font-size: 1.05rem !important;
  padding: .8rem 1.6rem !important;
  width: 100%;
  max-width: 340px;
}
.sf-btn.ready{ animation: sfPulse 1.6s ease infinite; }
@keyframes sfPulse{
  0%,100%{ box-shadow: 0 6px 20px rgba(255,201,60,.25); }
  50%    { box-shadow: 0 12px 35px rgba(255,201,60,.7), 0 0 0 8px rgba(255,201,60,.15); }
}

/* ---------- منطقة اللعبة ---------- */
.sf-game{
  background: rgba(0,0,0,.18);
  border-radius: 18px;
  padding: 1rem .8rem;
  text-align: center;
  margin-bottom: .8rem;
  overflow: hidden;
}
.sf-hint{
  font-weight: 800;
  color: #ffc93c;
  margin-bottom: .8rem;
  font-size: .9rem;
  line-height: 1.6;
}
.sf-fb{
  min-height: 2.6rem;
  margin-top: .8rem;
  padding: .7rem .9rem;
  border-radius: 14px;
  background: rgba(255,255,255,.06);
  font-weight: 700;
  font-size: .9rem;
  display: grid;
  place-items: center;
  text-align: center;
  line-height: 1.5;
}
.sf-finish{ text-align: center; margin-top: .8rem; }

/* ========== BODY ========== */
.body-wrap{
  position: relative;
  width: 100%;
  max-width: 200px;
  height: 260px;
  margin: 0 auto;
  display: grid;
  place-items: center;
}
.body{
  position: relative;
  width: 160px;
  height: 250px;
  transform-origin: top center;
}
.bp{
  position: absolute;
  transition: all .25s cubic-bezier(.34,1.56,.64,1);
  cursor: pointer;
  border: 3px solid rgba(255,255,255,.15);
  box-shadow: inset 0 -5px 10px rgba(0,0,0,.15);
}
.bp:hover:not(.off){ transform: scale(1.08); border-color: #ffc93c; z-index: 5; }
.bp .lbl{
  position: absolute; bottom: -26px; left: 50%;
  transform: translateX(-50%);
  background: rgba(0,0,0,.85);
  color: #fff;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: .62rem;
  white-space: nowrap;
  opacity: 0;
  transition: .25s;
  pointer-events: none;
}
.bp:hover .lbl{ opacity: 1; }
.bp-head{
  width: 60px; height: 60px; top: 0; left: 50%;
  transform: translateX(-50%); border-radius: 50%;
  background: linear-gradient(145deg,#ffe0b2,#f7d9aa);
}
.bp-torso{
  width: 88px; height: 96px; top: 56px; left: 50%;
  transform: translateX(-50%);
  border-radius: 28px 28px 36px 36px;
  background: linear-gradient(145deg,#f0cfa0,#e6b87a);
}
.bp-arm{
  width: 18px; height: 68px; top: 60px;
  border-radius: 20px;
  background: linear-gradient(145deg,#ffe0b2,#f7d9aa);
}
.bp-armL{ left: 6px; transform: rotate(15deg); transform-origin: top center; }
.bp-armR{ right: 6px; transform: rotate(-15deg); transform-origin: top center; }
.bp-leg{
  width: 24px; height: 78px; bottom: 0;
  border-radius: 20px 20px 10px 10px;
  background: linear-gradient(145deg,#ffe0b2,#f7d9aa);
}
.bp-legL{ left: 38px; }
.bp-legR{ right: 38px; }
.bp.ok{
  border-color: #2ec4b6;
  background: #2ec4b6 !important;
  box-shadow: 0 0 35px rgba(46,196,182,.7);
  animation: pop .5s;
}
.bp.no{ border-color: #ffc93c; animation: shake .4s; }
.bp.off{ pointer-events: none; opacity: .7; }
@keyframes pop{ 0%{transform:scale(1)} 50%{transform:scale(1.15)} 100%{transform:scale(1.05)} }
@keyframes shake{ 0%,100%{transform:translateX(0)} 25%{transform:translateX(-8px)} 75%{transform:translateX(8px)} }

/* ========== DISTANCE ========== */
.dist-zone{
  position: relative;
  height: 190px;
  background: radial-gradient(circle at 30% 30%, rgba(91,141,239,.25), rgba(10,6,26,.9));
  border-radius: 18px;
  overflow: hidden;
  touch-action: none;
}
.dist-safe{
  position: absolute; bottom: 0; left: 32%;
  width: 36%; height: 100%;
  border: 3px dashed rgba(46,196,182,.6);
  border-radius: 18px 18px 0 0;
  background: radial-gradient(circle at 50% 100%, rgba(46,196,182,.18), transparent 70%);
  pointer-events: none;
  display: grid; place-items: end center;
  padding-bottom: 8px;
  color: rgba(46,196,182,.9);
  font-weight: 800;
  font-size: .72rem;
}
.dist-kid{
  position: absolute; bottom: 16px; left: 8%;
  font-size: 3rem;
  cursor: grab;
  user-select: none;
  filter: drop-shadow(0 6px 12px rgba(0,0,0,.4));
}
.dist-kid:active{ cursor: grabbing; transform: scale(1.1); }
.dist-bad{
  position: absolute; bottom: 16px; right: 8%;
  font-size: 2.6rem;
  filter: drop-shadow(0 6px 12px rgba(0,0,0,.4));
}

/* ========== HOTSPOT ========== */
.hs-scene{
  position: relative;
  background: linear-gradient(160deg, rgba(42,27,74,.6), rgba(26,16,64,.7));
  border-radius: 18px;
  padding: .8rem;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(90px, 1fr));
  gap: .6rem;
}
.hs-item{
  background: rgba(255,255,255,.08);
  border: 2px solid rgba(255,255,255,.12);
  border-radius: 14px;
  padding: .7rem .4rem;
  cursor: pointer;
  transition: .25s;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: .3rem;
  min-height: 80px;
}
.hs-item:hover{ transform: translateY(-3px); border-color: #ffc93c; }
.hs-item .ico{ font-size: 1.8rem; }
.hs-item .txt{
  font-size: .72rem; color: #d9d0ff;
  font-weight: 700; text-align: center; line-height: 1.3;
}
.hs-item.danger-ok{
  background: rgba(255,59,59,.2);
  border-color: #ff3b3b;
  animation: pop .5s;
}
.hs-item.safe-ok{
  background: rgba(46,196,182,.15);
  border-color: #2ec4b6;
  opacity: .55; pointer-events: none;
}
.hs-item.wrong{
  background: rgba(255,201,60,.2);
  border-color: #ffc93c;
  animation: shake .4s;
}

/* ========== SCENARIO ========== */
.sc-box{ background: rgba(255,255,255,.05); border-radius: 16px; padding: 1rem; }
.sc-q{ font-weight: 800; font-size: .98rem; margin-bottom: .8rem; line-height: 1.6; }
.sc-choices{ display: flex; flex-direction: column; gap: .5rem; }
.sc-choice{
  min-height: 56px;
  background: rgba(255,255,255,.08);
  border: 2px solid transparent;
  border-radius: 12px;
  padding: .7rem .9rem;
  color: #fff; font-weight: 700;
  cursor: pointer;
  transition: .2s;
  font-family: inherit;
  text-align: right;
  font-size: .9rem;
  display: flex; align-items: center; gap: .55rem;
}
.sc-choice:hover{ background: rgba(255,255,255,.14); border-color: rgba(255,201,60,.5); }
.sc-choice.ok{ border-color: #2ec4b6; background: rgba(46,196,182,.2); animation: pop .4s; }
.sc-choice.no{ border-color: #ffc93c; background: rgba(255,201,60,.2); animation: shake .4s; }
.sc-num{
  background: rgba(255,201,60,.9); color: #241645;
  width: 24px; height: 24px; border-radius: 50%;
  display: grid; place-items: center;
  font-weight: 900; font-size: .78rem;
  flex-shrink: 0;
}

/* ========== MATCH ========== */
.mt-board{
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .6rem;
  margin-top: .4rem;
}
.mt-col{ display: flex; flex-direction: column; gap: .5rem; }
.mt-item{
  min-height: 52px;
  background: rgba(255,255,255,.08);
  border: 2px solid rgba(255,255,255,.12);
  border-radius: 12px;
  padding: .6rem .5rem;
  color: #fff; font-weight: 800;
  cursor: pointer;
  transition: .2s;
  font-family: inherit;
  font-size: .85rem;
  display: flex; align-items: center; justify-content: center;
  text-align: center;
}
.mt-item:hover{ background: rgba(255,255,255,.15); }
.mt-item.sel{ border-color: #ffc93c; background: rgba(255,201,60,.2); transform: scale(1.03); }
.mt-item.done{ border-color: #2ec4b6; background: rgba(46,196,182,.2); opacity: .6; pointer-events: none; }
.mt-item.err{ border-color: #ffc93c; animation: shake .4s; }

/* ========== QUIZ ========== */
.qz-box{ background: rgba(255,255,255,.05); border-radius: 16px; padding: 1rem; }
.qz-progress{
  height: 8px; background: rgba(255,255,255,.1);
  border-radius: 10px; overflow: hidden; margin-bottom: .8rem;
}
.qz-fill{
  height: 100%;
  background: linear-gradient(90deg,#ffc93c,#2ec4b6);
  transition: .4s; border-radius: 10px;
}
.qz-q{ font-weight: 800; font-size: .98rem; margin-bottom: .9rem; line-height: 1.6; }
.qz-btns{ display: flex; gap: .6rem; justify-content: center; }
.qz-btn{
  flex: 1; min-height: 60px;
  background: rgba(255,255,255,.08);
  border: 2px solid rgba(255,255,255,.15);
  border-radius: 12px;
  padding: .8rem;
  color: #fff; font-weight: 900; font-size: 1rem;
  cursor: pointer; transition: .2s;
  font-family: inherit;
}
.qz-btn:hover{ background: rgba(255,255,255,.15); border-color: #ffc93c; }
.qz-btn.ok{ background: rgba(46,196,182,.25); border-color: #2ec4b6; }
.qz-btn.soft{ background: rgba(255,201,60,.22); border-color: #ffc93c; }

/* ========== PASSWORD ========== */
.pw-box{ max-width: 360px; margin: 0 auto; text-align: center; }
.pw-input{
  width: 100%;
  background: rgba(255,255,255,.1);
  border: 2px solid rgba(255,255,255,.15);
  border-radius: 12px;
  padding: .85rem 1rem;
  color: #fff; font-size: 1rem;
  text-align: center;
  font-family: inherit;
  letter-spacing: 2px;
}
.pw-input:focus{ outline: none; border-color: #ffc93c; }
.pw-bar{
  height: 10px; border-radius: 10px;
  background: rgba(255,255,255,.1);
  margin-top: .7rem; overflow: hidden;
}
.pw-fill{
  height: 100%; width: 0;
  background: linear-gradient(90deg,#ff6b6b,#ffc93c,#2ec4b6);
  transition: .4s; border-radius: 10px;
}
.pw-rules{
  margin-top: .8rem;
  text-align: right;
  color: #d9d0ff;
  font-size: .82rem;
  line-height: 1.8;
}

/* ========== STREET ========== */
.st-scene{
  position: relative;
  background: linear-gradient(180deg,rgba(26,16,64,.9) 0%,rgba(26,16,64,.9) 40%,
    rgba(51,51,51,.9) 40%,rgba(51,51,51,.9) 55%,rgba(26,16,64,.9) 55%);
  border-radius: 18px;
  height: 240px;
  overflow: hidden;
  padding: .6rem;
}
.st-road{
  position: absolute; top: 40%; left: 0; right: 0; height: 15%;
  background: #2a2a2a;
  border-top: 3px dashed #ffc93c;
  border-bottom: 3px dashed #ffc93c;
}
.st-kid{
  position: absolute; bottom: 8%; left: 50%;
  transform: translateX(-50%);
  font-size: 2.6rem; transition: .3s;
}
.st-car{
  position: absolute; top: 43%;
  font-size: 2.2rem;
  transition: left 1.5s linear;
}
.st-light{
  position: absolute; top: 6%; right: 6%;
  width: 50px; height: 100px;
  background: #222; border-radius: 12px;
  padding: 5px; display: flex; flex-direction: column; gap: 4px;
}
.st-lamp{ flex: 1; border-radius: 50%; background: #333; }
.st-lamp.on-r{ background: #ff3b3b; box-shadow: 0 0 20px #ff3b3b; }
.st-lamp.on-g{ background: #2ec4b6; box-shadow: 0 0 20px #2ec4b6; }
.st-btn{
  position: absolute; bottom: 4%; right: 4%;
  background: rgba(255,201,60,.9); color: #241645;
  border: none; border-radius: 12px;
  padding: .7rem 1.1rem;
  min-height: 54px; min-width: 100px;
  font-weight: 900; cursor: pointer;
  font-family: inherit; font-size: .95rem;
}

/* ========== الاحتفال ========== */
.sf-confetti{
  position: fixed; inset: 0; z-index: 9998;
  pointer-events: none; overflow: hidden;
}
.sf-confetti span{
  position: absolute; top: -60px;
  animation: sfFall 3s linear forwards;
}
@keyframes sfFall{
  0%  { transform: translateY(-60px) rotate(0); }
  100%{ transform: translateY(110vh) rotate(720deg); opacity: .3; }
}

/* الوسام */
.sf-medal{
  width: 120px; height: 120px;
  border-radius: 50%;
  margin: 0 auto .8rem;
  display: grid; place-items: center;
  font-size: 3.4rem;
  background: radial-gradient(circle at 35% 30%,#fff3b0,#ffc93c 55%,#f5a623);
  box-shadow: 0 0 0 8px rgba(255,201,60,.18), 0 20px 50px rgba(255,201,60,.4);
  animation: sfMedal 1s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes sfMedal{
  from{ transform: scale(.2) rotate(-30deg); opacity: 0; }
  to  { transform: scale(1) rotate(0); opacity: 1; }
}
.sf-goodbye-emoji{ font-size: 4rem; line-height: 1; animation: sfWave 1.2s ease infinite; }
@keyframes sfWave{
  0%,100%{ transform: rotate(0); }
  25%    { transform: rotate(20deg); }
  75%    { transform: rotate(-20deg); }
}

/* ---------- اختيارات الختام ---------- */
.sf-choice{
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: .6rem;
  margin-top: 1rem;
}
.sf-choice a{
  display: block;
  padding: 1rem .7rem;
  border-radius: 16px;
  text-decoration: none;
  color: #fff;
  font-weight: 900;
  font-size: .95rem;
  background: rgba(255,255,255,.08);
  border: 2px solid rgba(255,255,255,.14);
  transition: .2s;
  min-height: 100px;
}
.sf-choice a span{ display: block; font-size: 2rem; margin-bottom: .3rem; }
.sf-choice a:hover{
  transform: translateY(-3px);
  border-color: #ffc93c;
  background: rgba(255,201,60,.14);
}
.sf-choice a.is-lock{ opacity: .6; }

/* =========================================================
   استجابة الشاشات
   ========================================================= */
@media (max-width: 640px){
  .sf-wrap{ padding: .75rem .6rem 2rem; }
  .sf-card{ padding: 1.1rem .85rem; border-radius: 18px; }
  .sf-title{ font-size: 1.12rem; }
  .sf-rule{ font-size: .98rem; padding: .75rem .85rem; border-right-width: 4px; }
  .sf-hero-emoji{ font-size: 2.1rem; }
  .sf-badge{ font-size: .78rem; padding: .3rem .75rem; }
  .sf-step{ font-size: .78rem; padding: .45rem .7rem; min-height: 38px; gap: .3rem; }
  .sf-step .n{ width: 22px; height: 22px; font-size: .72rem; }
  .sf-btn-lg{ font-size: .98rem !important; min-height: 50px; }
  .sf-medal{ width: 100px; height: 100px; font-size: 2.8rem; }
  .body-wrap{ height: 230px; }
  .body{ transform: scale(.85); }
  .dist-zone{ height: 170px; }
  .dist-kid{ font-size: 2.4rem; }
  .dist-bad{ font-size: 2.1rem; }
  .st-scene{ height: 210px; }
  .st-kid{ font-size: 2.2rem; }
  .st-car{ font-size: 1.9rem; }
  .st-light{ width: 42px; height: 84px; }
  .st-btn{ min-height: 48px; min-width: 88px; font-size: .88rem; }
}

/* للشاشات الصغيرة جداً */
@media (max-width: 360px){
  .sf-wrap{ padding: .5rem .45rem 1.5rem; }
  .sf-step{ font-size: .72rem; padding: .35rem .55rem; min-height: 34px; }
  .sf-step .n{ width: 20px; height: 20px; font-size: .68rem; }
  .sf-card{ padding: .9rem .7rem; }
  .sf-title{ font-size: 1rem; }
  .sf-rule{ font-size: .9rem; }
  .sf-choice{ grid-template-columns: 1fr; }
}

/* الوضع الأفقي على الجوال */
@media (max-height: 480px) and (orientation: landscape){
  .sf-wrap{ padding-top: .4rem; }
  .sf-steps{ margin-bottom: .6rem; }
  .sf-card{ padding: .9rem .8rem; }
  .sf-hero-emoji{ display: none; }
  .sf-rule{ margin-bottom: .6rem; }
}
</style>

<div class="sf-wrap">
  <div class="sf-steps" aria-hidden="true">
    <div class="sf-step" data-step="1"><span class="n">1</span> القاعدة</div>
    <div class="sf-step" data-step="2"><span class="n">2</span> اللعبة</div>
    <div class="sf-step" data-step="3"><span class="n">3</span> الوسام</div>
  </div>
  <div class="sf-card sf-view" id="sfLesson">
    <div style="padding:2rem;color:#d9d0ff;">جاري التحميل...</div>
  </div>
</div>

<script>
/* ============================================================
   البيانات من PHP
   ============================================================ */
const LESSONS = <?= safe_json($lessons) ?>;
const CHILD   = <?= safe_json(['name'=>$child['name']??'بطل','age'=>(int)($child['age']??6)]) ?>;

/* ============================================================
   تتبّع حالة اليوم — درس مختلف كل يوم يُنجز فيه القسم
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
const STORY_OK = true;

/* المهمة من ثلاث خطوات: 1 القاعدة (+فيديو) → 2 اللعبة → 3 الوسام */
const SF_MAX_QUIZ = 3, SF_MAX_SCENES = 2;
let step = 0;
let gameCompleted = false;
function checkBothDone(){ if (gameCompleted && step === 2) setTimeout(goStep3, 1400); }

const view = document.getElementById('sfLesson');
function setStep(n){
  step = n;
  document.querySelectorAll('.sf-step').forEach(el => {
    const k = +el.dataset.step;
    el.classList.toggle('is-on', k === n);
    el.classList.toggle('is-done', k < n);
  });
}
function swapView(html){
  return new Promise(res => {
    view.classList.add('is-out');
    setTimeout(() => { if (window.KidoraYT) KidoraYT.destroy(); view.innerHTML = html; view.classList.remove('is-out'); res(); }, 300);
  });
}
function say(t, mood){
  if (window.Companion) return Companion.say(t, { mood: mood || 'talk' });
  speak(t); return Promise.resolve();
}

/* ---------- الخطوة 1: القاعدة ---------- */
async function goStep1(){
  const L = todayLesson;
  console.log('🎬 lesson:', L, '| KidoraYT:', !!window.KidoraYT);

  setStep(1);
  const hasYT = L.youtube_id && String(L.youtube_id).trim() !== '';
  await swapView(`
    <span class="sf-badge">📖 الخطوة 1 — قاعدة اليوم</span>
    <div class="sf-hero-emoji">🛡️</div>
    <h2 class="sf-title">${esc(L.title)}</h2>
    <p class="sf-rule">${esc(L.description)}</p>
    ${hasYT ? `<div class="sf-ratio"><div class="yt-host" id="sfVideo"></div></div>` : ''}
    <div class="sf-actions">
      <button type="button" class="sf-btn sf-btn-lg" id="sfNext1">🎮 فهمت، إلى اللعبة</button>
    </div>
  `);
  document.getElementById('sfNext1').onclick = goStep2;
  await say(`${CHILD.name}، قاعدة اليوم: ${cleanEmoji(L.title)}. ${cleanEmoji(L.description)}`, 'talk');

  if (hasYT){
    await say('شاهد معي هذه القصة القصيرة.', 'cheer');
    const host = document.getElementById('sfVideo');
    if (window.KidoraYT){
      try {
        await KidoraYT.play('sfVideo', L.youtube_id, { autoplay: true, skipId: 'sfNext1' });
      } catch(err){
        console.warn('KidoraYT.play فشل، استخدم iframe مباشر:', err);
        host.innerHTML = `<iframe src="https://www.youtube.com/embed/${encodeURIComponent(L.youtube_id)}?autoplay=1&rel=0" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>`;
      }
    } else {
      /* fallback: iframe مباشر — يشتغل حتى بدون KidoraYT */
      console.warn('⚠️ KidoraYT غير محمّل — استخدام iframe مباشر');
      host.innerHTML = `<iframe src="https://www.youtube.com/embed/${encodeURIComponent(L.youtube_id)}?autoplay=1&rel=0" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>`;
    }
    if (step === 1) goStep2();
  } else {
    await say('هل أنت مستعد للعبة؟ اضغط الزر الكبير.', 'cheer');
  }
}

/* ---------- الخطوة 2: اللعبة ---------- */
async function goStep2(){
  if (step >= 2) return;
  const L = todayLesson;
  gameCompleted = false;
  setStep(2);
  await swapView(`
    <span class="sf-badge">🎮 الخطوة 2 — اللعبة</span>
    <h2 class="sf-title" style="font-size:1.35rem">${gameTitle(L.game_type)}</h2>
    <p class="sf-desc">${gameDesc(L.game_type)}</p>
    <div class="sf-game" id="sfGameBox"></div>
    <div class="sf-fb" id="sfFb">لنجرّب اللعبة 👇</div>
  `);
  const gt = (L.game_type || 'body').toLowerCase();
  (GAMES[gt] || GAMES.body)(document.getElementById('sfGameBox'), L);
}

/* ---------- الخطوة 3: الوسام ---------- */
async function goStep3(){
  if (step >= 3) return;
  setStep(3);
  PROG.day += 1; PROG.last = todayKey(); PROG.done = true;
  localStorage.setItem('kidora_safety_v5', JSON.stringify(PROG));
  if (window.kidoraMarkDone) kidoraMarkDone('safety'); else localStorage.setItem('kidora_done_safety_' + todayKey(), '1');
  confettiBurst();
  await swapView(`
    <span class="sf-badge">🏅 الخطوة 3 — الوسام</span>
    <div class="sf-medal">🏅</div>
    <h2 class="sf-title">أنت بطل الأمان يا ${esc(CHILD.name)}!</h2>
    <p class="sf-rule" style="text-align:center">تعلّمت اليوم: <strong>${esc(todayLesson.title)}</strong></p>
    <div class="sf-choice">
      <a href="<?= BASE_PATH ?>/story.php" class="${STORY_OK ? '' : 'is-lock'}"><span>📖</span>${STORY_OK ? 'قصتي اليومية' : 'قصتي اليومية 🔒'}</a>
      <a href="<?= BASE_PATH ?>/games.php"><span>🎮</span>لعبة إضافية</a>
      <a href="<?= BASE_PATH ?>/dashboard.php"><span>🏠</span>الرئيسية</a>
    </div>
  `);
  if (window.Companion) await Companion.celebrate(`مبروك يا ${CHILD.name}! حصلت على وسام بطل الأمان. حفظت قاعدة اليوم وأنهيت اللعبة.`);
  else speak(`مبروك يا ${CHILD.name}! حصلت على وسام بطل الأمان.`);
  await say(STORY_OK ? 'الآن اختر: قصتك اليومية، أو لعبة إضافية، أو الرجوع للرئيسية.' : 'الآن اختر: لعبة إضافية أو الرجوع للرئيسية.', 'cheer');
}

function confettiBurst(){
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
}

/* ============================================================
   عناوين الألعاب
   قرار تربوي ثابت: لا يُقال «خطأ»/«غلط» ولا تظهر علامة X أو نتيجة.
   الإجابة الآمنة تُكافأ بـ«أحسنت». غيرها لا تُمدَح: «حسناً» ثم الرفيق
   قدوةً للصواب ثم القاعدة، بصيغة «نحن» حتى لا نفترض جنس الطفل.
   أي محرك جديد يلتزم بـ teach() أدناه.
   ============================================================ */
function mateName(){
  const raw = window.KIDAURA_ACTIVE_CHARACTER && window.KIDAURA_ACTIVE_CHARACTER.name;
  const name = String(raw || '').replace(/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE0F}]/gu, '').trim();
  return name || 'رفيقنا';
}
function teach(rule){
  return 'حسناً، لكنّ الأفضلَ أن نتصرّفَ مثلَ ' + mateName() + ': ' + rule;
}
function teachChoice(choice){
  return 'حسناً، لكنّ الأفضلَ أن نختارَ مثلَ ' + mateName() + ': «' + choice + '»';
}
function teachSafe(label){
  return 'حسناً، «' + label + '» شيءٌ آمنٌ. لنبحثْ مثلَ ' + mateName() + ' عمّا قد يؤذينا.';
}
function teachNumber(){
  return 'حسناً، لكلِّ جهةٍ رقمُها. لنجرّبْ رقماً آخرَ مثلَ ' + mateName() + '.';
}
function gameTitle(t){
  return ({
    body:'🛡️ لعبة المنطقة الخاصة',
    distance:'📏 لعبة المسافة الآمنة',
    hotspot:'🔥 لعبة اكتشف الخطر',
    scenario:'🤔 لعبة اتخاذ القرار',
    match:'📞 لعبة أرقام الطوارئ',
    quiz:'💬 لعبة نعم أم لا',
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
    quiz:'أجب بنعم أو لا، وتعلّم قاعدة ذهبية مع كل سؤال.',
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
        const line = teach('المنطقةُ الخاصّةُ نُغطّيها دائماً، ولا يحقُّ لأحدٍ أن يلمسَها') + ' لنجرّبْ مرةً أخرى.';
        updateFb(line, '#ffc93c');
        speak(line);
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
  let drag=false, done=false, moved=false;

  function move(clientX){
    if (!drag || done) return;
    const r = dz.getBoundingClientRect();
    let x = clientX - r.left - 28;
    x = Math.max(0, Math.min(x, r.width - 60));
    dk.style.left = x + 'px';
    moved = true;
    const pct = (x / r.width) * 100;
    if (pct >= 32 && pct <= 68){
      done = true;
      updateFb('✅ ممتاز! هذا هو البعد الآمن عن الغريب.', '#2ec4b6');
      speak('أحسنت! هذه هي المسافة الآمنة.');
      gameCompleted = true; checkBothDone();
    } else {
      updateFb('نُكملُ السحبَ حتى المنطقةِ المنقّطة', '#ffc93c');
    }
  }
  function release(){
    const wasDragging = drag;
    drag = false;
    if (!wasDragging || done || !moved) return;
    const line = teach('نبتعدُ عن الغريبِ ونبقى في المكانِ الآمن');
    updateFb(line, '#ffc93c');
    speak(line);
  }
  dk.addEventListener('mousedown', e=>{drag=true;e.preventDefault();});
  dk.addEventListener('touchstart', e=>{drag=true;e.preventDefault();}, {passive:false});
  document.addEventListener('mousemove', e=>move(e.clientX));
  document.addEventListener('touchmove', e=>move(e.touches[0].clientX), {passive:false});
  document.addEventListener('mouseup', release);
  document.addEventListener('touchend', release);
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
        updateFb(`✅ أحسنت! "${it.txt}" قد يؤذينا، نبتعد عنه 🌟`, '#2ec4b6');
        if (found === totalDanger){
          updateFb('🎉 أحسنت! اكتشفت كل الأشياء الخطيرة.', '#2ec4b6');
          speak('ممتاز! اكتشفت كل الأشياء الخطيرة.');
          gameCompleted = true; checkBothDone();
        }
      } else {
        this.classList.add('wrong');
        const line = teachSafe(it.txt);
        updateFb(line, '#ffc93c');
        speak(line);
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
  qs = qs.slice(0, SF_MAX_SCENES);

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
          const line = teachChoice(cleanEmoji(q.a[q.correct]));
          updateFb(line, '#ffc93c');
          speak(line);
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
        updateFb('✅ أحسنت! رقم مهم حفظته 🌟', '#2ec4b6');
        if (matches === 3){
          updateFb('🎉 رائع! تعلمت أرقام الطوارئ.', '#2ec4b6');
          speak('ممتاز! تعلمت أرقام الطوارئ.');
          gameCompleted = true; checkBothDone();
        }
      } else {
        const a = sel; sel = null;
        this.classList.add('err'); a.classList.add('err');
        setTimeout(()=>{ this.classList.remove('err','sel'); a.classList.remove('err','sel'); }, 600);
        const line = teachNumber();
        updateFb(line, '#ffc93c');
        speak(line);
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
    {q:'هل كلمة السر يجب أن تكون سهلة مثل 1234؟', a:false, tip:'كلمةُ السرِّ طويلةٌ وسرّيّة، ولا يعرفُها إلا نحن وأهلُنا'},
    {q:'هل أُبقي اسمي وعنواني سرّاً عن الغرباء على الإنترنت؟', a:true, tip:'معلوماتُنا الشخصيّةُ سرٌّ، ولا نُعطيها لأيِّ غريب'},
    {q:'هل من الآمن نشر صوري مع أي شخص؟', a:false, tip:'لا نُشاركُ صورَنا إلا بإذنِ أهلِنا، ومع مَن نعرفُهم'},
    {q:'إذا أزعجني شخص على الإنترنت، هل أُخبرُ أهلي؟', a:true, tip:'إذا أزعجَنا أحدٌ على الإنترنت، نُخبرُ أهلَنا فوراً'},
    {q:'هل من الآمن مقابلة شخص تعرّفت عليه على الإنترنت وحدي؟', a:false, tip:'لا نذهبُ إلى لقاءٍ لا يعرفُه أهلُنا'}
  ].filter((_, k, all) => ((k - PROG.day) % all.length + all.length) % all.length < SF_MAX_QUIZ);
  let i = 0;
  function render(){
    if (i >= qs.length){
      box.innerHTML = `<div style="padding:1.6rem 1rem;text-align:center"><div style="font-size:3rem">🛡️</div>
        <p style="font-weight:800;color:#2ec4b6;margin:.4rem 0 .8rem">أنت الآن بطل الحماية! هذه قواعدك الذهبية:</p>
        <ul style="text-align:right;display:inline-block;margin:0;padding:0 1.2rem;line-height:1.9;font-weight:700">${qs.map(x=>`<li>${x.tip}</li>`).join('')}</ul></div>`;
      speak('أنت الآن بطل الحماية! ' + qs.map(x=>cleanEmoji(x.tip)).join('. '));
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
          <button class="qz-btn" data-v="1">✅ نعم</button>
          <button class="qz-btn" data-v="0">🙅 لا</button>
        </div>
      </div>
    `;
    setTimeout(()=>speak(`${cleanEmoji(q.q)} نعم أم لا؟`), 400);

    box.querySelectorAll('.qz-btn').forEach(b=>{
      b.addEventListener('click', function(){
        const v = this.dataset.v === '1';
        box.querySelectorAll('.qz-btn').forEach(x=>x.disabled=true);
        if (v === q.a){
          this.classList.add('ok');
          const praise = 'أحسنت يا ' + CHILD.name + '! ' + q.tip;
          updateFb('✅ ' + praise, '#2ec4b6');
          speak(praise);
          setTimeout(()=>{ i++; render(); }, 2200);
        } else {
          this.classList.add('soft');
          const line = teach(q.tip);
          updateFb(line, '#ffc93c');
          speak(line);
          setTimeout(()=>{ i++; render(); }, 3200);
        }
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
    } else if (score >= 3){ updateFb('ما زالت تحتاج إلى ما بقي. لنُضِفْه', '#ffc93c'); }
    else { updateFb('لنُضِفْ حروفاً وأرقاماً لتصبح أقوى', '#ffc93c'); }
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
      const line = teach(green ? 'ننتظرُ حتى تبتعدَ السيّارة' : 'ننتظرُ الإشارةَ الخضراء');
      updateFb(line, '#ffc93c');
      speak(line);
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
   الصوت — محرّكات الألعاب تنادي speak()؛ نمرّرها للرفيق حتى يقرأ هو
   (يوقف الموسيقى ويزيل الرموز). عند غياب الرفيق نعود إلى SpeechSynthesis.
   ============================================================ */
function speak(t){
  const txt = cleanEmoji(t);
  if (!txt) return;
  if (window.Companion){ Companion.say(txt, { mood: 'talk' }); return; }
  if (!('speechSynthesis' in window)) return;
  window.speechSynthesis.cancel();
  const u = new SpeechSynthesisUtterance(txt);
  u.lang = 'ar-SA'; u.rate = 1; u.pitch = 1.1;
  const v = speechSynthesis.getVoices().find(x=>x.lang.startsWith('ar'));
  if (v) u.voice = v;
  speechSynthesis.speak(u);
}

/* ============================================================
   التشغيل
   ============================================================ */
goStep1();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
