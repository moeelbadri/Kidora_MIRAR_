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
  :root{--demo-gold:#ffc93c;--demo-pink:#ff6fa5;--demo-blue:#5b8def;--demo-ink:#241645}
  
  /* ✅ Fix horizontal scroll */
  html, body { overflow-x: hidden; max-width: 100vw; }
  *, *::before, *::after { box-sizing: border-box; }
  
  .demo-page{position:relative;min-height:calc(100vh - 72px);padding:32px 0 90px;color:#f1f5f9;overflow:hidden}
  .demo-container{width:min(980px,calc(100% - 28px));margin:0 auto}
  
  /* Background particles */
  .demo-bg{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden}
  .demo-bg-star{position:absolute;width:3px;height:3px;border-radius:50%;background:rgba(255,201,60,.5);animation:starFloat linear infinite}
  @keyframes starFloat{
    0%{transform:translateY(0) scale(1);opacity:0}
    10%{opacity:.8}
    90%{opacity:.8}
    100%{transform:translateY(-100vh) scale(.3);opacity:0}
  }
  
  .demo-head{text-align:center;margin:15px auto 24px;position:relative;z-index:1}
  .demo-kicker{color:#ffe99a;font-size:13px;font-weight:900}
  .demo-head h1{margin:7px 0;font-family:var(--font-display);font-size:clamp(28px,6vw,58px);line-height:1.15}
  .demo-head p{max-width:650px;margin:0 auto;color:#b9abd4;line-height:1.8;font-size:clamp(14px,3.5vw,16px)}
  
  /* Progress bar with icons */
  .demo-progress{display:flex;gap:8px;max-width:520px;margin:0 auto 24px;position:relative;z-index:1}
  .demo-progress-step{flex:1;height:8px;border-radius:999px;background:rgba(255,255,255,.12);position:relative;overflow:hidden;transition:background .4s ease}
  .demo-progress-step::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,#ffc93c,#ff6fa5);transform:scaleX(0);transform-origin:right;transition:transform .5s cubic-bezier(.4,0,.2,1)}
  .demo-progress-step.active::after{transform:scaleX(1)}
  .demo-progress-step.done::after{transform:scaleX(1);background:linear-gradient(90deg,#2ec4b6,#5b8def)}
  
  /* Stages with smooth transitions */
  .demo-stage{padding:28px;border:1px solid rgba(255,255,255,.15);border-radius:30px;background:rgba(255,255,255,.075);backdrop-filter:blur(14px);box-shadow:0 25px 65px rgba(0,0,0,.2);position:relative;z-index:1}
  .demo-stage[hidden]{display:none}
  .demo-stage.stage-in{animation:stageIn .55s cubic-bezier(.34,1.56,.64,1) both}
  .demo-stage.stage-out{animation:stageOut .3s ease forwards}
  @keyframes stageIn{
    0%{opacity:0;transform:translateY(30px) scale(.96)}
    100%{opacity:1;transform:translateY(0) scale(1)}
  }
  @keyframes stageOut{
    to{opacity:0;transform:translateY(-20px) scale(.97)}
  }
  
  /* Guide character */
  .demo-guide{display:flex;align-items:center;gap:18px;max-width:720px;margin:0 auto 25px;padding:16px;border:1px solid rgba(255,255,255,.13);border-radius:22px;background:rgba(10,6,26,.35);transition:box-shadow .3s ease}
  .demo-guide.is-speaking{box-shadow:0 0 0 2px rgba(255,201,60,.4),0 0 30px rgba(255,201,60,.2)}
  .demo-guide-avatar{width:78px;height:78px;flex:0 0 78px;display:grid;place-items:center;border:3px solid rgba(255,255,255,.5);border-radius:28px;background:linear-gradient(145deg,var(--guide-color,#6c63ff),rgba(10,6,26,.8));font-size:44px;overflow:hidden;animation:guideFloat 3s ease-in-out infinite;position:relative}
  .demo-guide-avatar::after{content:"";position:absolute;inset:-6px;border-radius:32px;border:2px dashed rgba(255,201,60,.35);animation:guideOrbit 8s linear infinite}
  @keyframes guideFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
  @keyframes guideOrbit{to{transform:rotate(360deg)}}
  .demo-guide-avatar img{width:100%;height:100%;object-fit:cover;border-radius:inherit}
  .demo-guide-bubble{flex:1;min-width:0;color:#fff;font-size:15px;font-weight:800;line-height:1.8;overflow-wrap:break-word;word-break:break-word}
  .demo-guide-name{display:block;margin-bottom:2px;color:#ffe99a;font-size:13px}
  
  /* Headings and text */
  .demo-welcome{text-align:center}
  .demo-welcome h2,.demo-stage h2{margin:0 0 9px;font-family:var(--font-display);font-size:clamp(22px,5vw,37px)}
  .demo-welcome p,.demo-stage-intro{color:#d9d0ff;line-height:1.9;font-size:clamp(14px,3.5vw,16px)}
  
  /* Chips */
  .demo-chips{display:flex;justify-content:center;flex-wrap:wrap;gap:9px;margin:20px auto 25px}
  .demo-chip{padding:9px 14px;border:1px solid rgba(255,201,60,.35);border-radius:999px;color:#ffe99a;background:rgba(255,201,60,.1);font-size:13px;font-weight:800;transition:transform .3s ease,box-shadow .3s ease}
  .demo-chip:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(255,201,60,.2)}
  
  /* Buttons with animation */
  .demo-actions{display:flex;justify-content:center;flex-wrap:wrap;gap:10px;margin-top:20px}
  .demo-btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:10px 23px;border:1px solid transparent;border-radius:999px;font:inherit;font-weight:900;font-size:15px;transition:transform .25s ease,box-shadow .25s ease;cursor:pointer;position:relative;overflow:hidden}
  .demo-btn:hover{transform:translateY(-3px) scale(1.02)}
  .demo-btn:active{transform:translateY(-1px) scale(.99)}
  .demo-btn-gold{color:var(--demo-ink);background:linear-gradient(135deg,#ffe99a,#ffc93c);box-shadow:0 12px 28px rgba(255,201,60,.25);animation:btnGlow 2.5s ease-in-out infinite}
  .demo-btn-gold::before{content:"";position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.5),transparent);transition:left .5s ease}
  .demo-btn-gold:hover::before{left:100%}
  @keyframes btnGlow{0%,100%{box-shadow:0 12px 28px rgba(255,201,60,.25)}50%{box-shadow:0 16px 38px rgba(255,201,60,.5)}}
  .demo-btn-ghost{color:#fff;background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.18)}
  
  /* Character grid */
  .demo-character-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:12px;margin-top:20px}
  .demo-character{position:relative;padding:8px;border:2px solid transparent;border-radius:19px;color:#fff;background:rgba(255,255,255,.065);text-align:center;cursor:pointer;transition:transform .3s cubic-bezier(.34,1.56,.64,1),border-color .2s ease,box-shadow .2s ease;overflow:hidden}
  .demo-character::after{content:"";position:absolute;inset:0;border-radius:inherit;background:radial-gradient(circle at 50% 0%,var(--char-color),transparent 70%);opacity:0;transition:opacity .3s ease;pointer-events:none}
  .demo-character:hover,.demo-character:focus-visible{transform:translateY(-7px);border-color:var(--char-color);box-shadow:0 16px 30px rgba(0,0,0,.3);outline:none}
  .demo-character:hover::after{opacity:.18}
  .demo-character.selected{border-color:#ffc93c;box-shadow:0 0 0 3px rgba(255,201,60,.3),0 12px 30px rgba(255,201,60,.25);transform:translateY(-5px)}
  .demo-character.selected::before{content:"✓";position:absolute;top:8px;right:8px;width:26px;height:26px;display:grid;place-items:center;background:#ffc93c;color:#241645;border-radius:50%;font-weight:900;font-size:14px;z-index:2;animation:checkPop .4s cubic-bezier(.34,1.56,.64,1)}
  @keyframes checkPop{0%{transform:scale(0)}100%{transform:scale(1)}}
  .demo-character-media{position:relative;display:grid;place-items:center;aspect-ratio:1;border-radius:13px;overflow:hidden;background:linear-gradient(145deg,var(--char-color),rgba(10,6,26,.8));font-size:42px}
  .demo-character-media img{width:100%;height:100%;object-fit:cover}
  .demo-character-name{display:block;margin-top:7px;font-family:var(--font-display);font-size:15px}
  .demo-character-title{display:block;color:#b9abd4;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .demo-premium{position:absolute;top:5px;right:5px;padding:3px 5px;border-radius:999px;color:#fff;background:rgba(10,6,26,.82);font-size:9px;font-weight:900;z-index:2}
  
  /* Selected card */
  .demo-selected-card{max-width:190px;margin:20px auto;text-align:center}
  .demo-selected-media{display:grid;place-items:center;width:145px;height:145px;margin:0 auto 9px;border:5px solid rgba(255,255,255,.25);border-radius:38%;background:linear-gradient(145deg,var(--selected-color),rgba(10,6,26,.8));font-size:78px;overflow:hidden;box-shadow:0 20px 35px rgba(0,0,0,.25);animation:selectedPulse 2.5s ease-in-out infinite}
  @keyframes selectedPulse{0%,100%{box-shadow:0 20px 35px rgba(0,0,0,.25),0 0 0 0 rgba(255,201,60,.4)}50%{box-shadow:0 20px 35px rgba(0,0,0,.25),0 0 0 20px rgba(255,201,60,0)}}
  .demo-selected-media img{width:100%;height:100%;object-fit:cover}
  .demo-selected-card strong{font-family:var(--font-display);font-size:24px}
  
  /* Memory game */
  .demo-memory-meta{text-align:center;color:#b9abd4;font-size:13px}
  .demo-memory-meta b{color:#ffe99a}
  .demo-memory-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));justify-content:center;gap:12px;max-width:430px;margin:23px auto}
  .demo-memory-card{width:100%;aspect-ratio:1;perspective:700px;padding:0;background:transparent;border:none;cursor:pointer}
  .demo-memory-inner{display:block;position:relative;width:100%;height:100%;transform-style:preserve-3d;transition:transform .5s cubic-bezier(.4,0,.2,1)}
  .demo-memory-card.is-open .demo-memory-inner,.demo-memory-card.is-matched .demo-memory-inner{transform:rotateY(180deg)}
  .demo-memory-face{position:absolute;inset:0;display:grid;place-items:center;border:2px solid rgba(255,201,60,.38);border-radius:16px;backface-visibility:hidden;font-size:clamp(22px,6vw,31px)}
  .demo-memory-front{color:#ffe99a;background:linear-gradient(145deg,rgba(255,201,60,.25),rgba(91,141,239,.22))}
  .demo-memory-back{transform:rotateY(180deg);background:linear-gradient(145deg,var(--memory-color,#6c63ff),rgba(10,6,26,.8))}
  .demo-memory-card.is-matched .demo-memory-back{border-color:#8ff0d4;box-shadow:0 0 20px rgba(46,196,182,.35);animation:matchPop .5s ease}
  @keyframes matchPop{0%{transform:rotateY(180deg) scale(1)}50%{transform:rotateY(180deg) scale(1.08)}100%{transform:rotateY(180deg) scale(1)}}
  .demo-memory-card:focus-visible{outline:3px solid #fff;outline-offset:3px;border-radius:16px}
  
  /* Name form */
  .demo-name-form{max-width:430px;margin:25px auto}
  .demo-name-form label{display:block;margin-bottom:8px;color:#ffe99a;font-weight:900}
  .demo-name-form input{width:100%;min-height:52px;padding:10px 15px;border:1px solid rgba(255,255,255,.18);border-radius:15px;color:#fff;background:rgba(0,0,0,.22);font:inherit;font-size:18px;transition:border-color .2s ease,box-shadow .2s ease}
  .demo-name-form input:focus{outline:none;border-color:#ffc93c;box-shadow:0 0 0 3px rgba(255,201,60,.2)}
  .demo-error{min-height:25px;color:#fecaca;font-size:13px;font-weight:800}
  
  /* Story player */
  .demo-story-player{max-width:700px;margin:10px auto}
  .demo-story-scene{position:relative;min-height:365px;display:flex;align-items:flex-end;justify-content:center;overflow:hidden;border-radius:25px;background:linear-gradient(135deg,var(--story-color,#6c63ff),#241645);box-shadow:0 20px 50px rgba(0,0,0,.3);transition:background .8s ease}
  .demo-story-scene::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 50% 30%,rgba(255,255,255,.15),transparent 60%);pointer-events:none}
  .demo-story-sprite{position:absolute;top:30%;left:50%;transform:translate(-50%,-50%);width:128px;height:128px;display:grid;place-items:center;border:5px solid rgba(255,255,255,.3);border-radius:38%;background:linear-gradient(145deg,var(--story-color),rgba(10,6,26,.8));font-size:70px;overflow:hidden;animation:storySpriteFloat 3s ease-in-out infinite;z-index:2;box-shadow:0 20px 40px rgba(0,0,0,.4)}
  .demo-story-sprite img{width:100%;height:100%;object-fit:cover}
  @keyframes storySpriteFloat{
    0%,100%{transform:translate(-50%,-50%) rotate(-4deg)}
    50%{transform:translate(-50%,-58%) rotate(4deg)}
  }
  .demo-story-chapter{position:absolute;top:8%;inset-inline:0;text-align:center;color:#ffe99a;font-family:var(--font-display);font-size:22px;font-weight:900;z-index:2;animation:chapterIn .8s ease both}
  .demo-story-chapter-icon{display:block;margin-bottom:3px;font-size:48px;animation:iconBounce 2s ease-in-out infinite}
  @keyframes chapterIn{0%{opacity:0;transform:translateY(-20px)}100%{opacity:1;transform:translateY(0)}}
  @keyframes iconBounce{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-8px) rotate(-8deg)}}
  .demo-story-caption{width:100%;padding:85px 24px 25px;background:linear-gradient(0deg,rgba(0,0,0,.85),rgba(0,0,0,.4) 60%,transparent);color:#fff;text-align:center;font-family:var(--font-display);font-size:clamp(16px,4vw,21px);line-height:1.75;position:relative;z-index:2}
  .demo-story-caption .typing-cursor{display:inline-block;width:3px;height:1em;background:#ffc93c;margin-right:3px;vertical-align:text-bottom;animation:blink .8s step-end infinite;border-radius:2px}
  @keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
  
  /* Finale */
  .demo-finale{text-align:center;position:relative;overflow:hidden}
  .demo-finale-icon{font-size:85px;animation:finalePop 1.8s ease-in-out infinite;display:inline-block}
  @keyframes finalePop{0%,100%{transform:scale(1) rotate(0)}50%{transform:scale(1.15) rotate(5deg)}}
  .demo-finale p{max-width:560px;margin:10px auto;color:#d9d0ff;line-height:1.9}
  .demo-finale::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 50% 0%,rgba(255,201,60,.2),transparent 70%);pointer-events:none}
  
  /* Confetti */
  .demo-confetti{position:fixed;inset:0;z-index:390;pointer-events:none;overflow:hidden}
  .demo-confetti-piece{position:absolute;width:10px;height:14px;opacity:.9;animation:confettiFall linear forwards}
  @keyframes confettiFall{
    0%{transform:translateY(-20vh) rotate(0);opacity:1}
    100%{transform:translateY(110vh) rotate(720deg);opacity:0}
  }
  
  /* Particle burst on selection */
  .demo-particle{position:fixed;font-size:22px;pointer-events:none;z-index:395;animation:particleBurst 1s ease-out forwards}
  @keyframes particleBurst{
    0%{opacity:1;transform:translate(0,0) scale(.4)}
    100%{opacity:0;transform:translate(var(--dx),var(--dy)) scale(1.3) rotate(180deg)}
  }
  
  /* Responsive */
  @media(max-width:800px){
    .demo-guide{align-items:center;gap:12px;padding:12px}
    .demo-guide-avatar{width:64px;height:64px;flex-basis:64px;font-size:36px;border-radius:22px}
    .demo-guide-bubble{font-size:13.5px;line-height:1.7}
    .demo-stage{padding:22px 16px;border-radius:24px}
    .demo-head{margin-bottom:16px}
    .demo-story-scene{min-height:340px}
  }
  @media(max-width:520px){
    .demo-page{padding-top:16px;padding-bottom:60px}
    .demo-container{width:calc(100% - 20px)}
    .demo-stage{padding:18px 12px;border-radius:20px}
    .demo-guide{gap:10px;padding:10px;flex-direction:column;text-align:center}
    .demo-guide-avatar{width:72px;height:72px;flex-basis:72px;font-size:40px;border-radius:24px}
    .demo-guide-bubble{text-align:center;font-size:14px}
    .demo-character-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .demo-memory-grid{gap:8px}
    .demo-memory-face{border-radius:12px}
    .demo-story-scene{min-height:290px}
    .demo-story-sprite{width:94px;height:94px;font-size:53px}
    .demo-story-caption{padding:70px 14px 18px;font-size:16px}
    .demo-story-chapter{font-size:17px}
    .demo-story-chapter-icon{font-size:36px}
    .demo-btn{min-height:44px;padding:9px 18px;font-size:14px;flex:1;min-width:calc(50% - 5px)}
    .demo-chip{font-size:11px;padding:7px 11px}
  }
  @media(prefers-reduced-motion:reduce){
    .demo-story-sprite,.demo-finale-icon,.demo-guide-avatar,.demo-btn-gold,.demo-guide-avatar::after{animation:none}
    .demo-bg-star{animation:none;display:none}
  }
</style>

<div class="demo-bg" id="demoBg" aria-hidden="true"></div>

<main class="demo-page">
  <div class="demo-container">
    <div class="demo-head">
      <span class="demo-kicker">تجربة Kidora القصيرة</span>
      <h1>ثلاث محطات... وقصة باسمك</h1>
      <p>جرّب عالم الشخصيات والألعاب والصوت في دقائق، ثم قرر إن كانت هذه بداية مغامرتك.</p>
    </div>
    <div class="demo-progress" id="demoProgress" aria-label="تقدم التجربة">
      <span class="demo-progress-step active"></span>
      <span class="demo-progress-step"></span>
      <span class="demo-progress-step"></span>
      <span class="demo-progress-step"></span>
    </div>

    <section class="demo-stage demo-welcome stage-in" id="demoWelcome">
      <div class="demo-guide" id="demoGuide">
        <div class="demo-guide-avatar" id="guideAvatar" style="--guide-color:<?php echo h($guideData['color']); ?>">
          <?php if (!empty($guideData['image'])): ?><img src="<?php echo h($guideData['image']); ?>" alt="<?php echo h($guideData['name']); ?>"><?php else: ?><?php echo h($guideData['icons'][0] ?? '✨'); ?><?php endif; ?>
        </div>
        <div class="demo-guide-bubble"><span class="demo-guide-name" id="guideName"><?php echo h($guideData['name']); ?></span><span id="guideBubble"><?php echo h($guideLines['welcome']); ?></span></div>
      </div>
      <h2>أهلاً بك في عالم Kidora ✨</h2>
      <p>رفيقك سيقودك في اختيار شخصية، لعبة ذاكرة قصيرة، ثم قصة صغيرة تُروى بصوته.</p>
      <div class="demo-chips"><span class="demo-chip">1. اختر رفيقاً</span><span class="demo-chip">2. طابق الأزواج</span><span class="demo-chip">3. اسمع قصتك</span></div>
      <div class="demo-actions"><button type="button" class="demo-btn demo-btn-gold" id="demoStart">🚀 ابدأ التجربة</button><a class="demo-btn demo-btn-ghost" href="<?php echo h(BASE_PATH . '/index.php'); ?>">العودة للرئيسية</a></div>
    </section>

    <section class="demo-stage" id="demoPick" hidden>
      <div class="demo-guide"><div class="demo-guide-avatar" id="pickGuideAvatar"></div><div class="demo-guide-bubble"><span class="demo-guide-name" id="pickGuideName"></span><span id="pickGuideText"><?php echo h($guideLines['pick']); ?></span></div></div>
      <h2>اختر الشخصية التي تحبها</h2>
      <p class="demo-stage-intro">الشخصيات المدفوعة متاحة للتجربة هنا، أما التسجيل فيبدأ بشخصيتين مجانيتين.</p>
      <div class="demo-character-grid" id="demoCharacterGrid"></div>
    </section>

    <section class="demo-stage" id="demoMemory" hidden>
      <div class="demo-guide"><div class="demo-guide-avatar" id="memoryGuideAvatar"></div><div class="demo-guide-bubble"><span class="demo-guide-name" id="memoryGuideName"></span><span id="memoryGuideText">أربع أزواج تنتظر ذاكرتك. خذ وقتك ولا تقلق من الخطأ.</span></div></div>
      <div class="demo-selected-card" id="memorySelectedCard"></div>
      <h2 style="text-align:center;">لعبة الذاكرة</h2>
      <p class="demo-memory-meta">الأزواج المكتملة: <b id="memoryScore">0</b> / 4 &nbsp; · &nbsp; المحاولات: <b id="memoryMoves">0</b></p>
      <div class="demo-memory-grid" id="memoryGrid" aria-label="بطاقات لعبة الذاكرة"></div>
      <p class="demo-memory-meta" id="memoryMessage" aria-live="polite">اقلب بطاقتين متشابهتين.</p>
    </section>

    <section class="demo-stage" id="demoName" hidden>
      <div class="demo-guide"><div class="demo-guide-avatar" id="nameGuideAvatar"></div><div class="demo-guide-bubble"><span class="demo-guide-name" id="nameGuideName"></span><span id="nameGuideText">بقيت لمسة واحدة: ما الاسم الذي أضعه في القصة؟</span></div></div>
      <h2 style="text-align:center;">اسم بطل القصة</h2>
      <p class="demo-stage-intro" style="text-align:center;">اكتب الاسم الذي تحب سماعه في المغامرة.</p>
      <form class="demo-name-form" id="demoNameForm">
        <label for="demoChildName">ما اسمك؟</label>
        <input id="demoChildName" type="text" maxlength="30" autocomplete="off" required>
        <div class="demo-error" id="demoNameError" aria-live="polite"></div>
        <div class="demo-actions"><button type="submit" class="demo-btn demo-btn-gold">📖 افتح قصتي</button></div>
      </form>
    </section>

    <section class="demo-stage" id="demoStory" hidden>
      <div class="demo-guide"><div class="demo-guide-avatar" id="storyGuideAvatar"></div><div class="demo-guide-bubble"><span class="demo-guide-name" id="storyGuideName"></span><span id="storyGuideText"><?php echo h($guideLines['story']); ?></span></div></div>
      <h2 style="text-align:center;">قصتك بدأت ✨</h2>
      <div class="demo-story-player" id="demoStoryPlayer"></div>
    </section>

    <section class="demo-stage demo-finale" id="demoFinale" hidden>
      <div class="demo-finale-icon">🏆</div>
      <h2>وصلت إلى نهاية التجربة!</h2>
      <p id="finaleText">هذه ليست نهاية الرحلة، بل أول فصل فيها. سجّل الآن ليصبح لكل يوم قصة ومهمة ورفيق.</p>
      <div class="demo-actions">
        <a class="demo-btn demo-btn-gold" id="demoRegister">🚀 سجل الآن</a>
        <button type="button" class="demo-btn demo-btn-ghost" id="demoTryAgain">🔁 جرّب شخصية أخرى</button>
      </div>
    </section>
  </div>
</main>

<script>
window.KIDORA_DEMO = <?php echo json_encode([
    'base' => BASE_PATH,
    'characters' => $demoChars,
    'stories' => $demoStories,
    'guide' => $guideData,
    'lines' => $guideLines,
    'selectedSlug' => $selectedChar['slug'] ?? '',
    'loggedIn' => !empty($_SESSION['child_id']),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<script>
(function () {
  'use strict';
  const D = window.KIDORA_DEMO;
  let selectedSlug = null;
  let selectedChar = null;
  let memoryState = { first: null, lock: false, matches: 0, moves: 0 };

  // ==== نطق آمن ====
  function speak(text, rate) {
    if (!window.speechSynthesis || !text) return;
    try {
      window.speechSynthesis.cancel();
      const u = new SpeechSynthesisUtterance(String(text));
      u.lang = 'ar-SA';
      u.rate = rate || 1.15;
      u.pitch = 1.1;
      const voices = window.speechSynthesis.getVoices() || [];
      const ar = voices.find(v => /^ar([-_]|$)/i.test(v.lang));
      if (ar) u.voice = ar;
      window.speechSynthesis.speak(u);
    } catch (e) {}
  }
  if (window.speechSynthesis) {
    window.speechSynthesis.getVoices();
    window.speechSynthesis.onvoiceschanged = () => window.speechSynthesis.getVoices();
  }

  // ==== نجوم الخلفية ====
  function buildStars() {
    const bg = document.getElementById('demoBg');
    if (!bg) return;
    const count = window.innerWidth < 600 ? 15 : 30;
    for (let i = 0; i < count; i++) {
      const s = document.createElement('span');
      s.className = 'demo-bg-star';
      s.style.left = Math.random() * 100 + '%';
      s.style.top = (100 + Math.random() * 30) + '%';
      s.style.animationDuration = (8 + Math.random() * 12) + 's';
      s.style.animationDelay = (-Math.random() * 15) + 's';
      s.style.opacity = 0.3 + Math.random() * 0.5;
      s.style.width = s.style.height = (2 + Math.random() * 3) + 'px';
      bg.appendChild(s);
    }
  }

  // ==== انتقال مراحل ====
  function showStage(id) {
    const current = document.querySelector('.demo-stage:not([hidden])');
    const next = document.getElementById(id);
    if (!next || current === next) return;
    if (current) {
      current.classList.add('stage-out');
      setTimeout(() => {
        current.hidden = true;
        current.classList.remove('stage-out', 'stage-in');
        next.hidden = false;
        next.classList.add('stage-in');
        setTimeout(() => next.classList.remove('stage-in'), 600);
      }, 280);
    } else {
      next.hidden = false;
      next.classList.add('stage-in');
    }
  }

  // ==== تحديث شريط التقدم ====
  function setProgress(step) {
    document.querySelectorAll('#demoProgress .demo-progress-step').forEach((el, i) => {
      el.classList.toggle('active', i <= step);
      el.classList.toggle('done', i < step);
    });
  }

  // ==== ربط المرشد ====
  function setGuide(prefix, char) {
    if (!char) return;
    const av = document.getElementById(prefix + 'GuideAvatar');
    const nm = document.getElementById(prefix + 'GuideName');
    if (av) {
      av.style.setProperty('--guide-color', char.color);
      av.innerHTML = char.image ? `<img src="${char.image}" alt="${char.name}">` : (char.icons[0] || '✨');
    }
    if (nm) nm.textContent = char.name;
  }

  // ==== قراءة نص المرشد ====
  function readBubble(prefix, text) {
    const guide = document.querySelector(`#demo${prefix} .demo-guide`) ||
                  document.querySelector('.demo-guide');
    if (guide) {
      guide.classList.add('is-speaking');
      setTimeout(() => guide.classList.remove('is-speaking'), 3000);
    }
    speak(text, 1.15);
  }

  // ==== Particles burst ====
  function burst(x, y, emoji) {
    const chars = emoji ? [emoji] : ['✨', '⭐', '🌟', '💫', '🎉'];
    for (let i = 0; i < 8; i++) {
      const p = document.createElement('span');
      p.className = 'demo-particle';
      p.textContent = chars[Math.floor(Math.random() * chars.length)];
      p.style.left = x + 'px';
      p.style.top = y + 'px';
      p.style.setProperty('--dx', (Math.random() - 0.5) * 300 + 'px');
      p.style.setProperty('--dy', (Math.random() - 0.7) * 300 + 'px');
      document.body.appendChild(p);
      setTimeout(() => p.remove(), 1100);
    }
  }

  // ==== Confetti ====
  function confetti() {
    const colors = ['#ffc93c', '#ff6fa5', '#5b8def', '#2ec4b6', '#ffe99a'];
    const wrap = document.createElement('div');
    wrap.className = 'demo-confetti';
    document.body.appendChild(wrap);
    for (let i = 0; i < 60; i++) {
      const p = document.createElement('span');
      p.className = 'demo-confetti-piece';
      p.style.left = Math.random() * 100 + '%';
      p.style.background = colors[Math.floor(Math.random() * colors.length)];
      p.style.animationDuration = (2 + Math.random() * 2) + 's';
      p.style.animationDelay = (Math.random() * 0.5) + 's';
      p.style.transform = `rotate(${Math.random() * 360}deg)`;
      wrap.appendChild(p);
    }
    setTimeout(() => wrap.remove(), 5000);
  }

  // ==== تجهيز شبكة الشخصيات ====
  function renderCharacters() {
    const grid = document.getElementById('demoCharacterGrid');
    if (!grid) return;
    grid.innerHTML = '';
    D.characters.forEach(c => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'demo-character';
      btn.style.setProperty('--char-color', c.color);
      btn.dataset.slug = c.slug;
      if (c.slug === D.selectedSlug) btn.classList.add('selected');
      btn.innerHTML = `
        ${c.is_premium ? '<span class="demo-premium">🔒</span>' : ''}
        <span class="demo-character-media">
          ${c.image ? `<img src="${c.image}" alt="${c.name}">` : (c.icons[0] || '✨')}
        </span>
        <span class="demo-character-name">${c.name}</span>
        <span class="demo-character-title">${c.title}</span>
      `;
      btn.addEventListener('click', function (ev) {
        grid.querySelectorAll('.demo-character').forEach(b => b.classList.remove('selected'));
        this.classList.add('selected');
        selectedSlug = this.dataset.slug;
        selectedChar = D.characters.find(x => x.slug === selectedSlug);
        // burst عند الاختيار
        const r = this.getBoundingClientRect();
        burst(r.left + r.width / 2, r.top + r.height / 2, '✨');
        speak(`اخترت ${selectedChar.name}`, 1.2);
        // الانتقال للعبة الذاكرة
        setTimeout(() => {
          setGuide('memory', selectedChar);
          document.getElementById('memorySelectedCard').innerHTML = `
            <div class="demo-selected-media" style="--selected-color:${selectedChar.color}">
              ${selectedChar.image ? `<img src="${selectedChar.image}" alt="${selectedChar.name}">` : (selectedChar.icons[0] || '✨')}
            </div>
            <strong>${selectedChar.name}</strong>
          `;
          showStage('demoMemory');
          setProgress(2);
          startMemoryGame();
        }, 700);
      });
      grid.appendChild(btn);
    });
  }

  // ==== لعبة الذاكرة (كاملة، بدون demo.js) ====
  const MEMORY_EMOJIS = ['🍎', '⭐', '🎈', '🌸'];
  function startMemoryGame() {
    const grid = document.getElementById('memoryGrid');
    const scoreEl = document.getElementById('memoryScore');
    const movesEl = document.getElementById('memoryMoves');
    const msgEl = document.getElementById('memoryMessage');
    memoryState = { first: null, lock: false, matches: 0, moves: 0 };
    scoreEl.textContent = '0';
    movesEl.textContent = '0';
    msgEl.textContent = 'اقلب بطاقتين متشابهتين.';

    // بطاقات مكررة
    const cards = MEMORY_EMOJIS.concat(MEMORY_EMOJIS)
      .map((emoji, i) => ({ emoji, key: i }))
      .sort(() => Math.random() - 0.5);

    grid.innerHTML = '';
    cards.forEach(c => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'demo-memory-card';
      btn.dataset.emoji = c.emoji;
      btn.style.setProperty('--memory-color', selectedChar ? selectedChar.color : '#6c63ff');
      btn.innerHTML = `
        <span class="demo-memory-inner">
          <span class="demo-memory-face demo-memory-front">❓</span>
          <span class="demo-memory-face demo-memory-back">${c.emoji}</span>
        </span>
      `;
      btn.addEventListener('click', () => flipCard(btn, msgEl));
      grid.appendChild(btn);
    });
  }

  function flipCard(card, msgEl) {
    if (memoryState.lock) return;
    if (card.classList.contains('is-open') || card.classList.contains('is-matched')) return;
    card.classList.add('is-open');

    if (!memoryState.first) {
      memoryState.first = card;
      return;
    }

    // بطاقة ثانية
    memoryState.moves++;
    document.getElementById('memoryMoves').textContent = memoryState.moves;

    if (memoryState.first.dataset.emoji === card.dataset.emoji) {
      // نجاح
      memoryState.first.classList.add('is-matched');
      card.classList.add('is-matched');
      memoryState.matches++;
      document.getElementById('memoryScore').textContent = memoryState.matches;
      msgEl.textContent = 'أحسنت! زوج صحيح ✨';
      memoryState.first = null;
      if (window.SoundEngine && window.SoundEngine.sfx) window.SoundEngine.sfx('match');

      if (memoryState.matches === 4) {
        msgEl.textContent = '🎉 أكملت جميع الأزواج!';
        confetti();
        if (window.SoundEngine && window.SoundEngine.sfx) window.SoundEngine.sfx('win');
        speak('رائع! لقد أكملت اللعبة. الآن اختر اسماً لقصتك', 1.2);
        setTimeout(() => {
          setGuide('name', selectedChar);
          showStage('demoName');
          setProgress(3);
        }, 1600);
      }
    } else {
      // فشل
      memoryState.lock = true;
      msgEl.textContent = 'حاول مرة أخرى 💭';
      if (window.SoundEngine && window.SoundEngine.sfx) window.SoundEngine.sfx('miss');
      setTimeout(() => {
        memoryState.first.classList.remove('is-open');
        card.classList.remove('is-open');
        memoryState.first = null;
        memoryState.lock = false;
      }, 900);
    }
  }

  // ==== عرض القصة ====
  function renderStory(storyHtml, childName) {
    const player = document.getElementById('demoStoryPlayer');
    if (!player) return;
    const scene = document.createElement('div');
    scene.className = 'demo-story-scene';
    scene.style.setProperty('--story-color', selectedChar.color);
    scene.innerHTML = `
      <div class="demo-story-sprite" style="--story-color:${selectedChar.color}">
        ${selectedChar.image ? `<img src="${selectedChar.image}" alt="${selectedChar.name}">` : (selectedChar.icons[0] || '✨')}
      </div>
      <div class="demo-story-chapter">
        <span class="demo-story-chapter-icon">📖</span>
        مغامرة ${childName}
      </div>
      <div class="demo-story-caption" id="storyCaption"></div>
    `;
    player.innerHTML = '';
    player.appendChild(scene);

    // استخراج النص
    const tmp = document.createElement('div');
    tmp.innerHTML = storyHtml;
    const fullText = tmp.textContent.trim();

    const caption = scene.querySelector('#storyCaption');
    let i = 0;
    const speed = 45;

    function type() {
      if (i < fullText.length) {
        caption.innerHTML = fullText.substring(0, i + 1) + '<span class="typing-cursor"></span>';
        i++;
        setTimeout(type, speed);
      } else {
        caption.textContent = fullText;
        speak(fullText, 1.05);
        // بعد 8 ثوانٍ من انتهاء القراءة، ننتقل للنهاية
        setTimeout(() => {
          showStage('demoFinale');
          setProgress(4);
          confetti();
          speak('وصلت إلى نهاية التجربة! سجّل الآن لتبدأ رحلتك الحقيقية.', 1.15);
        }, 8000);
      }
    }
    type();
  }

  // ==== ربط الأحداث ====
  document.addEventListener('DOMContentLoaded', () => {
    buildStars();
    renderCharacters();

    // زر البدء
    const startBtn = document.getElementById('demoStart');
    if (startBtn) {
      startBtn.addEventListener('click', () => {
        showStage('demoPick');
        setProgress(1);
        setGuide('pick', D.characters[0]);
        setTimeout(() => readBubble('Pick', D.lines.pick), 300);
      });
    }

    // نموذج الاسم
    const form = document.getElementById('demoNameForm');
    if (form) {
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        const input = document.getElementById('demoChildName');
        const err = document.getElementById('demoNameError');
        const name = input.value.trim();
        if (!name) { err.textContent = 'الرجاء كتابة اسمك'; return; }
        if (!selectedChar) { err.textContent = 'لم تختر شخصية بعد!'; return; }
        err.textContent = '';
        const rawStory = D.stories[selectedChar.slug] || 'مرحباً بك في مغامرتك!';
        const story = rawStory.replace(/الاسم/g, name);
        setGuide('story', selectedChar);
        showStage('demoStory');
        setTimeout(() => renderStory(story, name), 350);
      });
    }

    // زر التسجيل
    const regBtn = document.getElementById('demoRegister');
    if (regBtn) {
      regBtn.href = D.base + '/index.php?register=1';
    }

    // زر إعادة التجربة
    const again = document.getElementById('demoTryAgain');
    if (again) {
      again.addEventListener('click', () => {
        if (window.speechSynthesis) window.speechSynthesis.cancel();
        selectedSlug = null;
        selectedChar = null;
        setProgress(0);
        showStage('demoWelcome');
      });
    }
  });

  // إخفاء المرشد المكرر (نستخدم مرشد واحد فقط في كل قسم)
  window.addEventListener('load', () => {
    // مزامنة المرشد في كل الأقسام مع الشخصية المختارة
    if (D.characters.length) setGuide('pick', D.characters[0]);
  });
})();
</script>
<script src="<?php echo h(BASE_PATH . '/assets/vendor/gsap/gsap.min.js'); ?>"></script>
<script src="<?php echo h(BASE_PATH . '/assets/vendor/gsap/ScrollTrigger.min.js'); ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
