<?php
// تجربة Kidora العامة — لا تحتاج إلى تسجيل دخول
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/demo-content.php';

$characters = all_characters($pdo);
$requestedSlug = (string)($_GET['char'] ?? '');
$selectedChar = null;
if ($requestedSlug !== '' && preg_match('/^[a-z0-9_-]+$/i', $requestedSlug)) {
    $charStmt = $pdo->prepare("SELECT * FROM characters WHERE slug = ?");
    $charStmt->execute([$requestedSlug]);
    $selectedChar = $charStmt->fetch() ?: null;
}
$selectedChar = $selectedChar ?: ($characters[0] ?? null);

$demoChars = array_map(static function (array $c): array {
    return [
        'id' => (int)$c['id'],
        'slug' => $c['slug'],
        'name' => $c['name'],
        'title' => $c['title'],
        'quote' => $c['quote'],
        'trait' => $c['trait'],
        'color' => preg_match('/^#[0-9a-f]{3,8}$/i', (string)$c['color']) ? $c['color'] : '#6C63FF',
        'move' => $c['move_type'],
        'icons' => character_icons($c),
        'image' => !empty($c['image_path']) ? BASE_PATH . '/' . ltrim($c['image_path'], '/') : null,
        'is_premium' => (bool)$c['is_premium'],
    ];
}, $characters);

$demoStories = [];
foreach ($characters as $c) $demoStories[$c['slug']] = demo_story_for($c, 'الاسم');
$guideLines = demo_guide_lines();
$guideChar = $selectedChar ?: [];
$guideData = [
    'id' => (int)($guideChar['id'] ?? 0),
    'slug' => $guideChar['slug'] ?? '',
    'name' => $guideChar['name'] ?? 'رفيقك',
    'color' => preg_match('/^#[0-9a-f]{3,8}$/i', (string)($guideChar['color'] ?? '')) ? $guideChar['color'] : '#6C63FF',
    'move' => $guideChar['move_type'] ?? 'wiggle',
    'icons' => character_icons($guideChar ?: ['icons_json' => '["✨"]']),
    'image' => !empty($guideChar['image_path']) ? BASE_PATH . '/' . ltrim($guideChar['image_path'], '/') : null,
];

$__pageTitle = 'جرب Kidora — مغامرة قصيرة';
$__publicNavCompact = true;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/public-nav.php';
?>

<style>
/* =========================================================
   Kidora Demo — تصميم متجاوب نظيف مع ثيم ديناميكي
   ========================================================= */
:root{
  --demo-gold:#ffc93c;
  --demo-gold-2:#f5a623;
  --demo-pink:#ff6fa5;
  --demo-blue:#5b8def;
  --demo-teal:#2ec4b6;
  --demo-ink:#241645;
  --demo-bg-card:rgba(255,255,255,.075);
  --demo-bg-line:rgba(255,255,255,.14);
  --theme-accent:#6c63ff;
  --theme-glow:rgba(108,99,255,.35);
  --theme-soft:rgba(108,99,255,.12);
}

/* إعادة ضبط شاملة */
.demo-page, .demo-page *, .demo-page *::before, .demo-page *::after{ box-sizing:border-box; }
html, body{ overflow-x:hidden; max-width:100%; }

.demo-page{
  position:relative;
  z-index:2;
  min-height:calc(100vh - 72px);
  padding:26px 0 80px;
  color:#f1f5f9;
  transition:background .6s ease;
}
.demo-container{
  width:min(980px, calc(100% - 28px));
  margin:0 auto;
  max-width:100%;
}

/* ====== العنوان الرئيسي ====== */
.demo-head{ text-align:center; margin:8px auto 22px; }
.demo-kicker{
  display:inline-block;
  padding:.3rem .85rem;
  border-radius:999px;
  background:rgba(255,201,60,.14);
  color:#ffe99a;
  font-size:.78rem;
  font-weight:900;
  letter-spacing:.3px;
}
.demo-head h1{
  margin:.5rem 0 .4rem;
  font-family:var(--font-display, inherit);
  font-size:clamp(1.7rem, 5vw, 2.7rem);
  line-height:1.25;
  background:linear-gradient(135deg,#fff,#ffe99a 60%,#ffc93c);
  -webkit-background-clip:text;
  background-clip:text;
  color:transparent;
}
.demo-head p{
  max-width:620px;
  margin:0 auto;
  color:#b9abd4;
  font-size:.95rem;
  line-height:1.75;
}

/* ====== شريط التقدم ====== */
.demo-progress{
  display:flex;
  gap:6px;
  max-width:520px;
  margin:0 auto 22px;
  padding:0 4px;
}
.demo-progress span{
  height:6px;
  flex:1;
  border-radius:999px;
  background:rgba(255,255,255,.12);
  transition:background .35s ease, box-shadow .35s ease;
}
.demo-progress span.active{
  background:linear-gradient(90deg, var(--theme-accent), #ff6fa5);
  box-shadow:0 0 14px var(--theme-glow);
}
.demo-progress span.done{
  background:linear-gradient(90deg, #2ec4b6, var(--theme-accent));
}

/* ====== البطاقة الرئيسية ====== */
.demo-stage{
  padding:22px 18px;
  border:1px solid var(--demo-bg-line);
  border-radius:26px;
  background:var(--demo-bg-card);
  backdrop-filter:blur(14px);
  -webkit-backdrop-filter:blur(14px);
  box-shadow:0 24px 60px rgba(0,0,0,.22), 0 0 0 1px var(--theme-soft);
  animation:stageFadeIn .45s ease both;
  max-width:100%;
  overflow-x:clip;
  transition:box-shadow .5s ease;
}
.demo-stage[hidden]{ display:none; }
@keyframes stageFadeIn{
  from{ opacity:0; transform:translateY(14px); }
  to  { opacity:1; transform:translateY(0); }
}

.demo-stage h2{
  margin:0 0 8px;
  font-family:var(--font-display, inherit);
  font-size:clamp(1.25rem, 3.4vw, 1.75rem);
  text-align:center;
  color:#fff;
}
.demo-stage-intro{
  color:#d9d0ff;
  line-height:1.85;
  text-align:center;
  font-size:.92rem;
  margin:0 0 16px;
}

/* ====== بطاقة الرفيق ====== */
.demo-guide{
  display:flex;
  align-items:center;
  gap:14px;
  max-width:640px;
  margin:0 auto 20px;
  padding:12px 14px;
  border:1px solid rgba(255,255,255,.13);
  border-radius:20px;
  background:rgba(10,6,26,.32);
  transition:box-shadow .4s ease, border-color .4s ease;
}
.demo-guide.is-active{
  border-color:var(--theme-accent);
  box-shadow:0 0 0 3px var(--theme-soft), 0 12px 30px rgba(0,0,0,.2);
}
.demo-guide-avatar{
  width:64px;
  height:64px;
  flex:0 0 64px;
  display:grid;
  place-items:center;
  border:3px solid rgba(255,255,255,.45);
  border-radius:22px;
  background:linear-gradient(145deg, var(--guide-color, #6c63ff), rgba(10,6,26,.8));
  font-size:36px;
  overflow:hidden;
  transition:border-color .4s ease, box-shadow .4s ease;
  animation:avatarFloat 3.5s ease-in-out infinite;
}
@keyframes avatarFloat{
  0%,100%{ transform:translateY(0); }
  50%    { transform:translateY(-4px); }
}
.demo-guide-avatar img{ width:100%; height:100%; object-fit:cover; }
.demo-guide-bubble{
  flex:1;
  min-width:0;
  color:#fff;
  font-size:.95rem;
  font-weight:800;
  line-height:1.7;
  overflow-wrap:anywhere;
  word-break:break-word;
}
.demo-guide-name{
  display:block;
  margin-bottom:2px;
  color:#ffe99a;
  font-size:.78rem;
  letter-spacing:.2px;
}

/* ====== شارات المراحل ====== */
.demo-chips{
  display:flex;
  justify-content:center;
  flex-wrap:wrap;
  gap:8px;
  margin:16px auto 20px;
}
.demo-chip{
  padding:.5rem .9rem;
  border:1px solid rgba(255,201,60,.35);
  border-radius:999px;
  color:#ffe99a;
  background:rgba(255,201,60,.08);
  font-size:.78rem;
  font-weight:800;
}

/* ====== الأزرار ====== */
.demo-actions{
  display:flex;
  justify-content:center;
  flex-wrap:wrap;
  gap:10px;
  margin-top:16px;
}
.demo-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:.45rem;
  min-height:50px;
  padding:.7rem 1.5rem;
  border:1px solid transparent;
  border-radius:999px;
  font:inherit;
  font-size:.95rem;
  font-weight:900;
  cursor:pointer;
  text-decoration:none;
  transition:transform .2s ease, box-shadow .2s ease, background .2s ease;
}
.demo-btn:hover{ transform:translateY(-2px); }
.demo-btn:active{ transform:translateY(0); }
.demo-btn-gold{
  color:var(--demo-ink);
  background:linear-gradient(135deg,#ffe99a,#ffc93c);
  box-shadow:0 12px 28px rgba(255,201,60,.28);
}
.demo-btn-gold:hover{ box-shadow:0 16px 34px rgba(255,201,60,.42); }
.demo-btn-ghost{
  color:#fff;
  background:rgba(255,255,255,.08);
  border-color:rgba(255,255,255,.18);
}
.demo-btn-teal{
  color:#fff;
  background:linear-gradient(135deg,#2ec4b6,#1a9e93);
  box-shadow:0 12px 28px rgba(46,196,182,.28);
}
.demo-btn-pulse{ animation:btnPulse 1.8s ease infinite; }
@keyframes btnPulse{
  0%,100%{ box-shadow:0 12px 28px rgba(255,201,60,.28); }
  50%    { box-shadow:0 12px 40px rgba(255,201,60,.7), 0 0 0 8px rgba(255,201,60,.12); }
}

/* ====== شبكة الشخصيات ====== */
.demo-character-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(120px, 1fr));
  gap:10px;
  margin-top:12px;
}
.demo-character-grid > *{ min-width:0; }

.demo-character{
  position:relative;
  padding:8px;
  border:2px solid transparent;
  border-radius:18px;
  color:#fff;
  background:rgba(255,255,255,.06);
  text-align:center;
  cursor:pointer;
  transition:transform .25s cubic-bezier(.34,1.56,.64,1), border-color .2s ease, box-shadow .2s ease;
  font-family:inherit;
  min-width:0;
}
.demo-character:hover,
.demo-character:focus-visible{
  transform:translateY(-5px);
  border-color:var(--char-color);
  box-shadow:0 14px 24px rgba(0,0,0,.28);
  outline:none;
}
.demo-character.selected{
  border-color:#ffc93c;
  box-shadow:0 0 0 3px rgba(255,201,60,.25), 0 14px 24px rgba(255,201,60,.3);
  transform:translateY(-4px);
}
.demo-character.selected::before{
  content:"✓";
  position:absolute;
  top:6px;
  left:6px;
  width:24px;
  height:24px;
  display:grid;
  place-items:center;
  background:#ffc93c;
  color:#241645;
  border-radius:50%;
  font-weight:900;
  font-size:13px;
  z-index:2;
  animation:checkPop .35s cubic-bezier(.34,1.56,.64,1);
}
@keyframes checkPop{
  0%{ transform:scale(0); }
  100%{ transform:scale(1); }
}
.demo-character-media{
  position:relative;
  display:grid;
  place-items:center;
  aspect-ratio:1;
  border-radius:14px;
  overflow:hidden;
  background:linear-gradient(145deg, var(--char-color), rgba(10,6,26,.8));
  font-size:38px;
}
.demo-character-media img{ width:100%; height:100%; object-fit:cover; }
.demo-character-name{
  display:block;
  margin-top:6px;
  font-family:var(--font-display, inherit);
  font-size:.92rem;
  font-weight:900;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
  max-width:100%;
}
.demo-character-title{
  display:block;
  color:#b9abd4;
  font-size:.68rem;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
  margin-top:1px;
}
.demo-premium{
  position:absolute;
  top:5px;
  right:5px;
  padding:2px 6px;
  border-radius:999px;
  color:#ffe99a;
  background:rgba(10,6,26,.85);
  font-size:.6rem;
  font-weight:900;
  border:1px solid rgba(255,201,60,.4);
  z-index:2;
}

/* ====== بطاقة الشخصية المختارة ====== */
.demo-selected-card{
  text-align:center;
  margin:16px auto;
  display:flex;
  flex-direction:column;
  align-items:center;
  gap:6px;
}
.demo-selected-media{
  display:grid;
  place-items:center;
  width:110px;
  height:110px;
  border:4px solid rgba(255,255,255,.22);
  border-radius:36%;
  background:linear-gradient(145deg, var(--selected-color, #6c63ff), rgba(10,6,26,.8));
  font-size:58px;
  overflow:hidden;
  box-shadow:0 18px 30px rgba(0,0,0,.28), 0 0 40px var(--theme-glow);
  animation:selectedPulse 2.6s ease-in-out infinite;
}
@keyframes selectedPulse{
  0%,100%{ box-shadow:0 18px 30px rgba(0,0,0,.28), 0 0 30px var(--theme-glow); }
  50%    { box-shadow:0 18px 30px rgba(0,0,0,.28), 0 0 60px var(--theme-glow); }
}
.demo-selected-media img{ width:100%; height:100%; object-fit:cover; }
.demo-selected-card strong{
  font-family:var(--font-display, inherit);
  font-size:1.25rem;
}

/* ====== لعبة الذاكرة ====== */
.demo-memory-meta{
  text-align:center;
  color:#b9abd4;
  font-size:.85rem;
  margin:6px 0;
}
.demo-memory-meta b{ color:#ffe99a; }
.demo-memory-grid{
  display:grid;
  grid-template-columns:repeat(4, minmax(56px, 84px));
  justify-content:center;
  gap:10px;
  max-width:400px;
  margin:16px auto;
  padding:0 4px;
}
.demo-memory-card{
  width:100%;
  height:76px;
  perspective:700px;
  padding:0;
  background:transparent;
  border:0;
  cursor:pointer;
}
.demo-memory-inner{
  display:block;
  position:relative;
  width:100%;
  height:100%;
  transform-style:preserve-3d;
  transition:transform .45s cubic-bezier(.4,0,.2,1);
}
.demo-memory-card.is-open .demo-memory-inner,
.demo-memory-card.is-matched .demo-memory-inner{
  transform:rotateY(180deg);
}
.demo-memory-face{
  position:absolute;
  inset:0;
  display:grid;
  place-items:center;
  border:2px solid rgba(255,201,60,.4);
  border-radius:14px;
  backface-visibility:hidden;
  -webkit-backface-visibility:hidden;
  font-size:28px;
}
.demo-memory-front{
  color:#ffe99a;
  background:linear-gradient(145deg, rgba(255,201,60,.28), rgba(91,141,239,.24));
  font-size:24px;
}
.demo-memory-back{
  transform:rotateY(180deg);
  background:linear-gradient(145deg, var(--memory-color, #6c63ff), rgba(10,6,26,.8));
}
.demo-memory-card.is-matched .demo-memory-back{
  border-color:#8ff0d4;
  box-shadow:0 0 20px rgba(46,196,182,.45);
  animation:matchPop .5s ease;
}
@keyframes matchPop{
  0%{ transform:rotateY(180deg) scale(1); }
  50%{ transform:rotateY(180deg) scale(1.1); }
  100%{ transform:rotateY(180deg) scale(1); }
}
.demo-memory-card:focus-visible{ outline:3px solid #fff; outline-offset:3px; border-radius:14px; }

/* ====== نموذج الاسم ====== */
.demo-name-form{ max-width:420px; margin:16px auto 0; }
.demo-name-form label{
  display:block;
  margin-bottom:6px;
  color:#ffe99a;
  font-weight:900;
  font-size:.9rem;
}
.demo-name-form input{
  width:100%;
  min-height:52px;
  padding:.65rem 1rem;
  border:1px solid rgba(255,255,255,.18);
  border-radius:14px;
  color:#fff;
  background:rgba(0,0,0,.22);
  font:inherit;
  font-size:1.05rem;
  text-align:center;
  transition:border-color .2s ease, box-shadow .2s ease;
}
.demo-name-form input:focus{
  outline:none;
  border-color:var(--theme-accent);
  box-shadow:0 0 0 3px var(--theme-soft);
}
.demo-error{
  min-height:22px;
  color:#fecaca;
  font-size:.82rem;
  font-weight:800;
  text-align:center;
  margin-top:6px;
}

/* ====== القصة ====== */
.demo-story-player{ max-width:680px; margin:6px auto 0; }
.demo-story-scene{
  position:relative;
  min-height:340px;
  display:flex;
  align-items:flex-end;
  justify-content:center;
  overflow:hidden;
  border-radius:22px;
  background:linear-gradient(135deg, var(--story-color, #6c63ff), #241645);
  box-shadow:0 22px 50px rgba(0,0,0,.32), 0 0 60px var(--theme-glow);
  transition:background .6s ease, box-shadow .6s ease;
}
.demo-story-scene::before{
  content:"";
  position:absolute;
  inset:0;
  background:radial-gradient(circle at 50% 30%, rgba(255,255,255,.15), transparent 60%);
  pointer-events:none;
}
.demo-story-sprite{
  position:absolute;
  top:30%;
  left:50%;
  transform:translate(-50%, -50%);
  width:110px;
  height:110px;
  display:grid;
  place-items:center;
  border:4px solid rgba(255,255,255,.3);
  border-radius:38%;
  background:linear-gradient(145deg, var(--story-color, #6c63ff), rgba(10,6,26,.8));
  font-size:58px;
  overflow:hidden;
  animation:spriteFloat 3s ease-in-out infinite;
  box-shadow:0 20px 40px rgba(0,0,0,.35);
  z-index:2;
}
.demo-story-sprite img{ width:100%; height:100%; object-fit:cover; }
@keyframes spriteFloat{
  0%,100%{ margin-top:0; transform:translate(-50%,-50%) rotate(-3deg); }
  50%    { margin-top:-12px; transform:translate(-50%,-58%) rotate(3deg); }
}
.demo-story-chapter{
  position:absolute;
  top:7%;
  inset-inline:0;
  text-align:center;
  color:#ffe99a;
  font-family:var(--font-display, inherit);
  font-size:1.05rem;
  font-weight:900;
  text-shadow:0 2px 8px rgba(0,0,0,.4);
  z-index:2;
  animation:chapterIn .6s ease both;
}
@keyframes chapterIn{
  from{ opacity:0; transform:translateY(-12px); }
  to  { opacity:1; transform:translateY(0); }
}
.demo-story-chapter-icon{
  display:block;
  margin-bottom:2px;
  font-size:38px;
  animation:iconBounce 2s ease-in-out infinite;
}
@keyframes iconBounce{
  0%,100%{ transform:translateY(0) rotate(0); }
  50%    { transform:translateY(-6px) rotate(-6deg); }
}
.demo-story-caption{
  width:100%;
  padding:70px 20px 20px;
  background:linear-gradient(0deg, rgba(0,0,0,.82), rgba(0,0,0,.5) 55%, transparent);
  color:#fff;
  text-align:center;
  font-family:var(--font-display, inherit);
  font-size:1rem;
  line-height:1.75;
  min-height:130px;
  overflow-wrap:anywhere;
  word-break:break-word;
  position:relative;
  z-index:2;
}
.demo-story-caption .typing-cursor{
  display:inline-block;
  width:2px;
  height:1em;
  background:#ffc93c;
  margin-inline-start:3px;
  vertical-align:text-bottom;
  animation:blink .8s step-end infinite;
}
@keyframes blink{
  0%,100%{ opacity:1; }
  50%    { opacity:0; }
}
.demo-story-controls{
  display:flex;
  justify-content:center;
  gap:8px;
  flex-wrap:wrap;
  margin-top:14px;
}

/* ====== النهاية ====== */
.demo-finale{ text-align:center; position:relative; overflow:hidden; }
.demo-finale::before{
  content:"";
  position:absolute;
  inset:0;
  background:radial-gradient(circle at 50% 0%, var(--theme-soft), transparent 70%);
  pointer-events:none;
}
.demo-finale-icon{
  font-size:64px;
  animation:finalePop 1.8s ease-in-out infinite;
  display:inline-block;
}
@keyframes finalePop{
  0%,100%{ transform:scale(1) rotate(0); }
  50%    { transform:scale(1.12) rotate(4deg); }
}
.demo-finale p{
  max-width:520px;
  margin:10px auto 0;
  color:#d9d0ff;
  line-height:1.85;
  font-size:.95rem;
}
.demo-finale-rewards{
  display:flex;
  justify-content:center;
  flex-wrap:wrap;
  gap:8px;
  margin:16px 0 4px;
}
.demo-finale-rewards span{
  padding:.4rem .85rem;
  border-radius:999px;
  background:rgba(46,196,182,.15);
  border:1px solid rgba(46,196,182,.35);
  color:#c8fff6;
  font-size:.8rem;
  font-weight:800;
}

/* ====== الجزيئات ====== */
.demo-particles{
  position:fixed;
  inset:0;
  z-index:380;
  pointer-events:none;
  overflow:hidden;
}
.demo-particle{
  position:absolute;
  font-size:22px;
  animation:demoParticle 1.6s ease-out forwards;
}
@keyframes demoParticle{
  from{ opacity:1; transform:translate(0,0) scale(.5); }
  to  { opacity:0; transform:translate(var(--dx), var(--dy)) scale(1.25) rotate(200deg); }
}

/* ====== الاستجابة ====== */
@media (max-width: 640px){
  .demo-page{ padding:16px 0 60px; }
  .demo-container{ width:calc(100% - 20px); }
  .demo-stage{ padding:18px 12px; border-radius:22px; }
  .demo-head h1{ font-size:1.55rem; }
  .demo-head p{ font-size:.88rem; }
  .demo-guide{
    padding:10px 11px;
    gap:10px;
    border-radius:16px;
  }
  .demo-guide-avatar{
    width:52px; height:52px; flex-basis:52px;
    font-size:28px; border-radius:16px;
  }
  .demo-guide-bubble{ font-size:.86rem; }
  .demo-chip{ font-size:.72rem; padding:.4rem .7rem; }
  .demo-character-grid{
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:8px;
  }
  .demo-character-media{ font-size:32px; }
  .demo-character-name{ font-size:.85rem; }
  .demo-memory-grid{
    grid-template-columns:repeat(4, minmax(52px, 1fr));
    gap:7px;
    max-width:100%;
  }
  .demo-memory-card{ height:66px; }
  .demo-memory-face{ font-size:24px; border-radius:12px; }
  .demo-memory-front{ font-size:20px; }
  .demo-story-scene{ min-height:290px; }
  .demo-story-sprite{ width:88px; height:88px; font-size:46px; }
  .demo-story-caption{ padding:60px 14px 16px; font-size:.92rem; }
  .demo-story-chapter{ font-size:.95rem; }
  .demo-story-chapter-icon{ font-size:32px; }
  .demo-btn{ font-size:.88rem; padding:.62rem 1.2rem; min-height:46px; }
}

@media (max-width: 380px){
  .demo-stage{ padding:14px 10px; }
  .demo-character-grid{ gap:6px; }
  .demo-character-media{ font-size:28px; }
  .demo-memory-grid{ gap:5px; }
  .demo-memory-card{ height:58px; }
  .demo-memory-face{ font-size:20px; }
}

@media (prefers-reduced-motion: reduce){
  .demo-story-sprite,
  .demo-finale-icon,
  .demo-btn-pulse,
  .demo-guide-avatar,
  .demo-selected-media,
  .demo-story-chapter-icon{ animation:none; }
  .demo-stage{ animation:none; }
}
</style>

<main class="demo-page" id="demoPage">
  <div class="demo-container">
    <div class="demo-head">
      <span class="demo-kicker">🎈 تجربة Kidora القصيرة</span>
      <h1>ثلاث محطات... وقصة باسمك</h1>
      <p>جرّب عالم الشخصيات والألعاب والصوت في دقائق، ثم قرر إن كانت هذه بداية مغامرتك.</p>
    </div>

    <div class="demo-progress" id="demoProgress" aria-label="تقدم التجربة">
      <span class="active"></span><span></span><span></span>
    </div>

    <!-- ========== 1. الترحيب ========== -->
    <section class="demo-stage demo-welcome" id="demoWelcome">
      <div class="demo-guide" id="welcomeGuide">
        <div class="demo-guide-avatar" id="welcomeGuideAvatar"
             style="--guide-color:<?php echo h($guideData['color']); ?>">
          <?php if (!empty($guideData['image'])): ?>
            <img src="<?php echo h($guideData['image']); ?>" alt="<?php echo h($guideData['name']); ?>">
          <?php else: ?>
            <?php echo h($guideData['icons'][0] ?? '✨'); ?>
          <?php endif; ?>
        </div>
        <div class="demo-guide-bubble">
          <span class="demo-guide-name" id="welcomeGuideName"><?php echo h($guideData['name']); ?></span>
          <span class="js-guide-text" id="welcomeGuideText"><?php echo h($guideLines['welcome']); ?></span>
        </div>
      </div>

      <h2>أهلاً بك في عالم Kidora ✨</h2>
      <p class="demo-stage-intro">رفيقك سيقودك في اختيار شخصية، لعبة ذاكرة قصيرة، ثم قصة صغيرة تُروى بصوته.</p>

      <div class="demo-chips">
        <span class="demo-chip">1. اختر رفيقاً</span>
        <span class="demo-chip">2. طابق الأزواج</span>
        <span class="demo-chip">3. اسمع قصتك</span>
      </div>

      <div class="demo-actions">
        <button type="button" class="demo-btn demo-btn-gold demo-btn-pulse" id="demoStart">
          🚀 ابدأ التجربة
        </button>
        <a class="demo-btn demo-btn-ghost" href="<?php echo h(BASE_PATH . '/index.php'); ?>">
          العودة للرئيسية
        </a>
      </div>
    </section>

    <!-- ========== 2. اختيار الشخصية ========== -->
    <section class="demo-stage" id="demoPick" hidden>
      <div class="demo-guide">
        <div class="demo-guide-avatar" id="pickGuideAvatar"></div>
        <div class="demo-guide-bubble">
          <span class="demo-guide-name" id="pickGuideName"></span>
          <span class="js-guide-text" id="pickGuideText"><?php echo h($guideLines['pick']); ?></span>
        </div>
      </div>

      <h2>اختر الشخصية التي تحبها</h2>
      <p class="demo-stage-intro">الشخصيات المدفوعة متاحة للتجربة هنا، أما التسجيل فيبدأ بشخصيتين مجانيتين.</p>

      <div class="demo-character-grid" id="demoCharacterGrid"></div>
    </section>

    <!-- ========== 3. لعبة الذاكرة ========== -->
    <section class="demo-stage" id="demoMemory" hidden>
      <div class="demo-guide">
        <div class="demo-guide-avatar" id="memoryGuideAvatar"></div>
        <div class="demo-guide-bubble">
          <span class="demo-guide-name" id="memoryGuideName"></span>
          <span class="js-guide-text" id="memoryGuideText">أربع أزواج تنتظر ذاكرتك. خذ وقتك ولا تقلق من الخطأ.</span>
        </div>
      </div>

      <div class="demo-selected-card" id="memorySelectedCard"></div>
      <h2>لعبة الذاكرة</h2>
      <p class="demo-memory-meta">
        الأزواج المكتملة: <b id="memoryScore">0</b> / 4 &nbsp;·&nbsp; المحاولات: <b id="memoryMoves">0</b>
      </p>

      <div class="demo-memory-grid" id="memoryGrid" aria-label="بطاقات لعبة الذاكرة"></div>
      <p class="demo-memory-meta" id="memoryMessage" aria-live="polite">اقلب بطاقتين متشابهتين.</p>
    </section>

    <!-- ========== 4. الاسم ========== -->
    <section class="demo-stage" id="demoName" hidden>
      <div class="demo-guide">
        <div class="demo-guide-avatar" id="nameGuideAvatar"></div>
        <div class="demo-guide-bubble">
          <span class="demo-guide-name" id="nameGuideName"></span>
          <span class="js-guide-text" id="nameGuideText">بقيت لمسة واحدة: ما الاسم الذي أضعه في القصة؟</span>
        </div>
      </div>

      <h2>اسم بطل القصة</h2>
      <p class="demo-stage-intro">اكتب الاسم الذي تحب سماعه في المغامرة.</p>

      <form class="demo-name-form" id="demoNameForm" novalidate>
        <label for="demoChildName">ما اسمك؟</label>
        <input id="demoChildName" type="text" maxlength="30" autocomplete="off"
               inputmode="text" placeholder="مثال: سارة" required>
        <div class="demo-error" id="demoNameError" aria-live="polite"></div>
        <div class="demo-actions">
          <button type="submit" class="demo-btn demo-btn-gold demo-btn-pulse">
            📖 افتح قصتي
          </button>
        </div>
      </form>
    </section>

    <!-- ========== 5. القصة ========== -->
    <section class="demo-stage" id="demoStory" hidden>
      <div class="demo-guide">
        <div class="demo-guide-avatar" id="storyGuideAvatar"></div>
        <div class="demo-guide-bubble">
          <span class="demo-guide-name" id="storyGuideName"></span>
          <span class="js-guide-text" id="storyGuideText"><?php echo h($guideLines['story']); ?></span>
        </div>
      </div>

      <h2>قصتك بدأت ✨</h2>
      <div class="demo-story-player" id="demoStoryPlayer"></div>

      <div class="demo-actions" id="storyActions" style="display:none">
        <button type="button" class="demo-btn demo-btn-teal" id="storyNext">
          🏆 أنهيت القصة
        </button>
        <button type="button" class="demo-btn demo-btn-ghost" id="storyReplay">
          🔁 أعد القراءة
        </button>
      </div>
    </section>

    <!-- ========== 6. النهاية ========== -->
    <section class="demo-stage demo-finale" id="demoFinale" hidden>
      <div class="demo-finale-icon">🏆</div>
      <h2>وصلت إلى نهاية التجربة!</h2>
      <div class="demo-finale-rewards">
        <span>⭐ اخترت رفيقك</span>
        <span>⭐ أنهيت اللعبة</span>
        <span>⭐ سمعت قصتك</span>
      </div>
      <p id="finaleText">
        هذه ليست نهاية الرحلة، بل أول فصل فيها. سجّل الآن ليصبح لكل يوم قصة ومهمة ورفيق.
      </p>

      <div class="demo-actions">
        <a class="demo-btn demo-btn-gold demo-btn-pulse" id="demoRegister"
           href="<?php echo h(BASE_PATH . '/index.php?register=1'); ?>">
          🚀 سجل الآن مجاناً
        </a>
        <button type="button" class="demo-btn demo-btn-ghost" id="demoTryAgain">
          🔁 جرّب شخصية أخرى
        </button>
      </div>
    </section>

  </div>
</main>

<div class="demo-particles" id="demoParticles" aria-hidden="true"></div>

<script>
/* ============================================================
   Kidora Demo — نسخة محدّثة: ثيم ديناميكي + قصص صحيحة
   ============================================================ */
(function(){
  'use strict';

  const DATA = <?php echo json_encode([
      'base'       => BASE_PATH,
      'characters' => $demoChars,
      'stories'    => $demoStories,
      'guide'      => $guideData,
      'lines'      => $guideLines,
      'selectedSlug' => $selectedChar['slug'] ?? '',
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

  const $  = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /* ============================================================
     🛠️ أدوات نص
     ============================================================ */
  function escapeHtml(s){
    return String(s == null ? '' : s).replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
  }
  const escapeAttr = escapeHtml;

  /* ============================================================
     📖 تحويل أي شكل من القصة إلى نص
     ============================================================ */
  function storyToText(story){
    if (!story) return '';
    if (typeof story === 'string') return story.trim();
    if (Array.isArray(story)) {
      return story.map(s => {
        if (typeof s === 'string') return s;
        if (s && typeof s === 'object') return s.text || s.caption || s.line || s.narration || '';
        return '';
      }).filter(Boolean).join('\n').trim();
    }
    if (typeof story === 'object') {
      if (typeof story.text === 'string') return story.text.trim();
      if (typeof story.story === 'string') return story.story.trim();
      if (typeof story.caption === 'string') return story.caption.trim();
      if (Array.isArray(story.scenes)) {
        return story.scenes.map(s => {
          if (typeof s === 'string') return s;
          if (s && typeof s === 'object') return s.text || s.caption || s.line || s.narration || '';
          return '';
        }).filter(Boolean).join('\n').trim();
      }
      for (const k in story) {
        if (typeof story[k] === 'string' && story[k].trim()) return story[k].trim();
      }
    }
    return String(story).trim();
  }

  /* ============================================================
     🎨 تطبيق ثيم الرفيق على الصفحة
     ============================================================ */
  function hexToRgba(hex, alpha){
    hex = (hex || '').replace('#', '');
    if (hex.length === 3) hex = hex.split('').map(c => c+c).join('');
    const r = parseInt(hex.slice(0,2), 16) || 108;
    const g = parseInt(hex.slice(2,4), 16) || 99;
    const b = parseInt(hex.slice(4,6), 16) || 255;
    return `rgba(${r},${g},${b},${alpha})`;
  }

  function applyTheme(char){
    if (!char) return;
    const color = char.color || '#6c63ff';
    const root = document.documentElement;
    root.style.setProperty('--theme-accent', color);
    root.style.setProperty('--theme-glow', hexToRgba(color, 0.35));
    root.style.setProperty('--theme-soft', hexToRgba(color, 0.12));

    // إضافة تدرج خفيف على خلفية الصفحة
    const page = document.getElementById('demoPage');
    if (page) {
      page.style.background = `radial-gradient(circle at 50% 0%, ${hexToRgba(color, 0.10)}, transparent 55%)`;
    }
  }

  /* ============================================================
     🗣️ النطق
     ============================================================ */
  const Speech = (() => {
    function loadVoices(){ if (window.speechSynthesis) speechSynthesis.getVoices(); }
    if (window.speechSynthesis) {
      loadVoices();
      speechSynthesis.onvoiceschanged = loadVoices;
    }
    function speak(text, opts = {}){
      if (!window.speechSynthesis || !text) return;
      try { speechSynthesis.cancel(); } catch(e){}
      const u = new SpeechSynthesisUtterance(String(text).trim());
      u.lang  = opts.lang  || 'ar-SA';
      u.rate  = opts.rate  || 1.05;
      u.pitch = opts.pitch || 1.1;
      const voices = speechSynthesis.getVoices() || [];
      const ar = voices.find(v => v.lang && v.lang.toLowerCase().startsWith('ar'));
      if (ar) u.voice = ar;
      speechSynthesis.speak(u);
    }
    function stop(){ if (window.speechSynthesis) try { speechSynthesis.cancel(); } catch(e){} }
    return { speak, stop };
  })();

  /* ============================================================
     🎬 إدارة المراحل
     ============================================================ */
  const SECTIONS = ['demoWelcome','demoPick','demoMemory','demoName','demoStory','demoFinale'];

  function showSection(id){
    SECTIONS.forEach(s => {
      const el = document.getElementById(s);
      if (el) el.hidden = (s !== id);
    });
    try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch(e){ window.scrollTo(0,0); }
  }

  function updateProgress(step){
    const bars = $$('#demoProgress span');
    bars.forEach((b, i) => {
      b.classList.toggle('active', i === Math.min(step, 2));
      b.classList.toggle('done', i < step);
    });
  }

  function speakGuideOf(sectionId){
    const section = document.getElementById(sectionId);
    if (!section) return;
    const txt = $('.js-guide-text', section);
    if (txt && txt.textContent.trim()) Speech.speak(txt.textContent.trim(), { rate: 1.1 });
  }

  function setupGuideAvatar(sectionId, char){
    const section = document.getElementById(sectionId);
    if (!section) return;
    const avatar = $('.demo-guide-avatar', section);
    const nameEl = $('.demo-guide-name', section);
    if (avatar) {
      avatar.style.setProperty('--guide-color', char.color || '#6c63ff');
      if (char.image) {
        avatar.innerHTML = `<img src="${escapeAttr(char.image)}" alt="${escapeAttr(char.name)}">`;
      } else {
        avatar.textContent = (char.icons && char.icons[0]) || '✨';
      }
    }
    if (nameEl) nameEl.textContent = char.name || 'رفيقك';
  }

  /* ============================================================
     (1) الترحيب
     ============================================================ */
  function initWelcome(){
    setupGuideAvatar('demoWelcome', DATA.guide);
    setTimeout(() => speakGuideOf('demoWelcome'), 700);

    const btn = $('#demoStart');
    if (btn) btn.addEventListener('click', () => {
      Speech.stop();
      showSection('demoPick');
      updateProgress(1);
      buildCharacterGrid();
      setTimeout(() => speakGuideOf('demoPick'), 350);
    });
  }

  /* ============================================================
     (2) اختيار الشخصية
     ============================================================ */
  let selectedChar = null;

  function buildCharacterGrid(){
    const grid = $('#demoCharacterGrid');
    if (!grid) return;
    grid.innerHTML = '';

    DATA.characters.forEach(c => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'demo-character';
      btn.style.setProperty('--char-color', c.color || '#6c63ff');
      btn.dataset.slug = c.slug;
      if (c.slug === DATA.selectedSlug) btn.classList.add('selected');

      btn.innerHTML = `
        ${c.is_premium ? '<span class="demo-premium">🔒 مدفوعة</span>' : ''}
        <span class="demo-character-media">
          ${c.image
            ? `<img src="${escapeAttr(c.image)}" alt="${escapeAttr(c.name)}">`
            : escapeHtml((c.icons && c.icons[0]) || '✨')}
        </span>
        <span class="demo-character-name" title="${escapeAttr(c.name)}">${escapeHtml(c.name)}</span>
        <span class="demo-character-title" title="${escapeAttr(c.title)}">${escapeHtml(c.title)}</span>
      `;

      btn.addEventListener('click', () => onCharacterChosen(c, grid));
      grid.appendChild(btn);
    });
  }

  function onCharacterChosen(c, grid){
    $$('.demo-character', grid).forEach(b => b.classList.remove('selected'));
    const target = grid.querySelector(`.demo-character[data-slug="${c.slug}"]`);
    if (target) target.classList.add('selected');

    selectedChar = c;

    /* 🎨 تطبيق الثيم على كل الصفحة */
    applyTheme(c);

    /* ✨ تحديث كل صور المرشد في كل الأقسام */
    ['demoWelcome','demoPick','demoMemory','demoName','demoStory'].forEach(sec => {
      setupGuideAvatar(sec, c);
    });

    /* 📌 إضافة class التوهج على بطاقة المرشد الحالية */
    const pickGuide = $('#demoPick .demo-guide');
    if (pickGuide) pickGuide.classList.add('is-active');

    Speech.speak(`اخترت ${c.name}. ${c.trait || ''}`, { rate: 1.1 });

    setTimeout(() => startMemoryGame(), 1000);
  }

  /* ============================================================
     (3) لعبة الذاكرة
     ============================================================ */
  const MEMORY_PAIRS = [
    { id: 1, ico: '🦁', color: '#f5a623' },
    { id: 2, ico: '🚀', color: '#5b8def' },
    { id: 3, ico: '🐢', color: '#2ec4b6' },
    { id: 4, ico: '⭐', color: '#ff6fa5' },
  ];

  let memoryState = null;

  function startMemoryGame(){
    if (!selectedChar) return;
    showSection('demoMemory');
    updateProgress(2);

    const card = $('#memorySelectedCard');
    if (card) {
      card.innerHTML = `
        <div class="demo-selected-media" style="--selected-color:${escapeAttr(selectedChar.color)}">
          ${selectedChar.image
            ? `<img src="${escapeAttr(selectedChar.image)}" alt="${escapeAttr(selectedChar.name)}">`
            : escapeHtml((selectedChar.icons && selectedChar.icons[0]) || '✨')}
        </div>
        <strong>${escapeHtml(selectedChar.name)}</strong>
        <span style="color:#b9abd4;font-size:.82rem">${escapeHtml(selectedChar.title)}</span>
      `;
    }
    setupGuideAvatar('demoMemory', selectedChar);
    buildMemoryBoard();
    setTimeout(() => speakGuideOf('demoMemory'), 350);
  }

  function buildMemoryBoard(){
    const grid = $('#memoryGrid');
    const scoreEl = $('#memoryScore');
    const movesEl = $('#memoryMoves');
    const msgEl = $('#memoryMessage');
    if (!grid) return;

    memoryState = { deck: [], flipped: [], matched: 0, moves: 0, lock: false, win: false };

    if (scoreEl) scoreEl.textContent = '0';
    if (movesEl) movesEl.textContent = '0';
    if (msgEl) msgEl.textContent = 'اقلب بطاقتين متشابهتين.';

    const deck = [];
    MEMORY_PAIRS.forEach(p => {
      deck.push({ pairId: p.id, ico: p.ico, color: p.color });
      deck.push({ pairId: p.id, ico: p.ico, color: p.color });
    });
    shuffle(deck);
    memoryState.deck = deck;

    grid.innerHTML = '';
    deck.forEach((card, index) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'demo-memory-card';
      btn.dataset.index = index;
      btn.setAttribute('aria-label', 'بطاقة');
      btn.innerHTML = `
        <span class="demo-memory-inner">
          <span class="demo-memory-face demo-memory-front">❓</span>
          <span class="demo-memory-face demo-memory-back"
                style="--memory-color:${escapeAttr(card.color)}">${escapeHtml(card.ico)}</span>
        </span>
      `;
      btn.addEventListener('click', () => onMemoryCardClick(btn, index));
      grid.appendChild(btn);
    });
  }

  function onMemoryCardClick(btn, index){
    const s = memoryState;
    if (!s || s.lock || s.win) return;
    if (btn.classList.contains('is-open') || btn.classList.contains('is-matched')) return;
    if (s.flipped.length >= 2) return;

    btn.classList.add('is-open');
    s.flipped.push({ btn, index });

    if (s.flipped.length === 2) {
      s.moves++;
      const movesEl = $('#memoryMoves');
      if (movesEl) movesEl.textContent = String(s.moves);

      const [a, b] = s.flipped;
      const cardA = s.deck[a.index];
      const cardB = s.deck[b.index];

      if (cardA.pairId === cardB.pairId) {
        setTimeout(() => {
          a.btn.classList.add('is-matched');
          b.btn.classList.add('is-matched');
          a.btn.classList.remove('is-open');
          b.btn.classList.remove('is-open');
          s.matched++;
          const scoreEl = $('#memoryScore');
          if (scoreEl) scoreEl.textContent = String(s.matched);
          s.flipped = [];
          if (s.matched === MEMORY_PAIRS.length) onMemoryWin();
        }, 350);
      } else {
        s.lock = true;
        setTimeout(() => {
          a.btn.classList.remove('is-open');
          b.btn.classList.remove('is-open');
          s.flipped = [];
          s.lock = false;
        }, 850);
      }
    }
  }

  function onMemoryWin(){
    const s = memoryState;
    s.win = true;
    const msgEl = $('#memoryMessage');
    if (msgEl) msgEl.textContent = '🎉 أحسنت! أنهيت اللعبة.';
    Speech.speak('أحسنت! لقد أنهيت اللعبة. الآن اختر اسم بطل القصة.', { rate: 1.1 });
    spawnParticles(20);

    setTimeout(() => {
      showSection('demoName');
      updateProgress(3);
      setupGuideAvatar('demoName', selectedChar);
      setTimeout(() => speakGuideOf('demoName'), 350);
      focusNameInput();
    }, 1800);
  }

  function shuffle(arr){
    for (let i = arr.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [arr[i], arr[j]] = [arr[j], arr[i]];
    }
  }

  function focusNameInput(){
    const inp = $('#demoChildName');
    if (inp) setTimeout(() => inp.focus(), 400);
  }

  /* ============================================================
     (4) الاسم
     ============================================================ */
  function initNameForm(){
    const form = $('#demoNameForm');
    if (!form) return;
    form.addEventListener('submit', e => {
      e.preventDefault();
      const input = $('#demoChildName');
      const errEl = $('#demoNameError');
      const name = (input?.value || '').trim();
      if (!name || name.length < 2) {
        if (errEl) errEl.textContent = 'اكتب اسماً صحيحاً من حرفين على الأقل.';
        Speech.speak('اكتب اسمك أولاً.');
        return;
      }
      if (errEl) errEl.textContent = '';
      openStory(name);
    });
  }

  /* ============================================================
     (5) القصة
     ============================================================ */
  let storyTypingTimer = null;
  let lastStoryText = '';

  function openStory(childName){
    if (!selectedChar) return;
    showSection('demoStory');
    updateProgress(3);
    setupGuideAvatar('demoStory', selectedChar);

    const guideText = $('#storyGuideText');
    if (guideText) guideText.textContent = 'استمع إلى قصتك الآن!';

    const rawStory = DATA.stories[selectedChar.slug];
    let fullText = storyToText(rawStory);

    if (!fullText) {
      fullText = `في يوم من الأيام، كان ${childName} يسير في عالم ${selectedChar.name} السحري، وكانت المغامرة تنتظره.`;
    }

    fullText = fullText.replace(/الاسم/g, childName);
    lastStoryText = fullText;

    renderStoryScene({
      color: selectedChar.color || '#6c63ff',
      image: selectedChar.image,
      icon: (selectedChar.icons && selectedChar.icons[0]) || '✨',
      name: selectedChar.name,
      childName: childName,
      text: fullText,
    });
  }

  function renderStoryScene(opts){
    const player = $('#demoStoryPlayer');
    if (!player) return;

    player.innerHTML = `
      <div class="demo-story-scene" style="--story-color:${escapeAttr(opts.color)}">
        <div class="demo-story-sprite" style="--story-color:${escapeAttr(opts.color)}">
          ${opts.image
            ? `<img src="${escapeAttr(opts.image)}" alt="${escapeAttr(opts.name)}">`
            : escapeHtml(opts.icon)}
        </div>
        <div class="demo-story-chapter">
          <span class="demo-story-chapter-icon">📖</span>
          مغامرة ${escapeHtml(opts.childName)}
        </div>
        <div class="demo-story-caption" id="storyCaption" aria-live="polite"></div>
      </div>
    `;

    const actions = $('#storyActions');
    if (actions) actions.style.display = 'flex';

    typeStoryText(opts.text);
  }

  function typeStoryText(text){
    const caption = $('#storyCaption');
    if (!caption) return;
    if (storyTypingTimer) { clearInterval(storyTypingTimer); storyTypingTimer = null; }

    Speech.speak(text, { rate: 1.0 });

    let i = 0;
    const speed = 22;

    caption.textContent = '';
    storyTypingTimer = setInterval(() => {
      if (i >= text.length) {
        clearInterval(storyTypingTimer);
        storyTypingTimer = null;
        return;
      }
      caption.textContent += text.charAt(i);
      i++;
    }, speed);
  }

  function initStoryActions(){
    const next = $('#storyNext');
    const replay = $('#storyReplay');

    if (next) next.addEventListener('click', () => {
      Speech.stop();
      if (storyTypingTimer) { clearInterval(storyTypingTimer); storyTypingTimer = null; }
      showFinale();
    });

    if (replay) replay.addEventListener('click', () => {
      if (lastStoryText) typeStoryText(lastStoryText);
    });
  }

  /* ============================================================
     (6) النهاية
     ============================================================ */
  function showFinale(){
    showSection('demoFinale');
    updateProgress(4);
    setTimeout(() => {
      Speech.speak('مبروك! أنهيت التجربة. سجل الآن ليصبح لكل يوم قصة ومهمة ورفيق.', { rate: 1.05 });
    }, 450);
    spawnParticles(35);
  }

  function initFinaleActions(){
    const tryAgain = $('#demoTryAgain');
    if (tryAgain) tryAgain.addEventListener('click', () => {
      Speech.stop();
      selectedChar = null;
      lastStoryText = '';
      const nameInput = $('#demoChildName');
      if (nameInput) nameInput.value = '';

      // رجّع الثيم الافتراضي
      document.documentElement.style.setProperty('--theme-accent', '#6c63ff');
      document.documentElement.style.setProperty('--theme-glow', 'rgba(108,99,255,.35)');
      document.documentElement.style.setProperty('--theme-soft', 'rgba(108,99,255,.12)');
      const page = document.getElementById('demoPage');
      if (page) page.style.background = '';

      showSection('demoWelcome');
      updateProgress(0);
      setTimeout(() => speakGuideOf('demoWelcome'), 400);
    });
  }

  /* ============================================================
     ✨ الجزيئات
     ============================================================ */
  function spawnParticles(count){
    const host = $('#demoParticles');
    if (!host) return;
    const icons = ['⭐','✨','🎉','💫','🌟','🎊','🏆'];
    for (let i = 0; i < count; i++) {
      const p = document.createElement('span');
      p.className = 'demo-particle';
      p.textContent = icons[Math.floor(Math.random() * icons.length)];
      p.style.left = (10 + Math.random() * 80) + '%';
      p.style.top  = (20 + Math.random() * 50) + '%';
      p.style.setProperty('--dx', (Math.random() * 200 - 100) + 'px');
      p.style.setProperty('--dy', (-Math.random() * 220 - 60) + 'px');
      p.style.animationDelay = (Math.random() * 0.5) + 's';
      host.appendChild(p);
      setTimeout(() => p.remove(), 2200);
    }
  }

  /* ============================================================
     🚀 التهيئة
     ============================================================ */
  function init(){
    initWelcome();
    initNameForm();
    initStoryActions();
    initFinaleActions();
    updateProgress(0);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
