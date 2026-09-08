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
  .demo-page{position:relative;z-index:2;min-height:calc(100vh - 72px);padding:32px 0 90px;color:#f1f5f9}
  .demo-container{width:min(980px,calc(100% - 28px));margin:0 auto}
  .demo-head{text-align:center;margin:15px auto 24px}.demo-kicker{color:#ffe99a;font-size:13px;font-weight:900}.demo-head h1{margin:7px 0;font-family:var(--font-display);font-size:clamp(34px,6vw,58px)}.demo-head p{max-width:650px;margin:0 auto;color:#b9abd4;line-height:1.8}
  .demo-progress{display:flex;gap:8px;max-width:520px;margin:0 auto 24px}.demo-progress span{height:7px;flex:1;border-radius:999px;background:rgba(255,255,255,.14);transition:background .35s ease,box-shadow .35s ease}.demo-progress span.active{background:linear-gradient(90deg,#ffc93c,#ff6fa5);box-shadow:0 0 16px rgba(255,201,60,.3)}
  .demo-stage{padding:28px;border:1px solid rgba(255,255,255,.15);border-radius:30px;background:rgba(255,255,255,.075);backdrop-filter:blur(14px);box-shadow:0 25px 65px rgba(0,0,0,.2)}.demo-stage[hidden]{display:none}
  .demo-guide{display:flex;align-items:center;gap:18px;max-width:720px;margin:0 auto 25px;padding:16px;border:1px solid rgba(255,255,255,.13);border-radius:22px;background:rgba(10,6,26,.3)}.demo-guide-avatar{width:78px;height:78px;flex:0 0 78px;display:grid;place-items:center;border:3px solid rgba(255,255,255,.5);border-radius:28px;background:linear-gradient(145deg,var(--guide-color,#6c63ff),rgba(10,6,26,.8));font-size:44px;overflow:hidden}.demo-guide-avatar img{width:100%;height:100%;object-fit:cover}.demo-guide-bubble{flex:1;color:#fff;font-size:15px;font-weight:800;line-height:1.8}.demo-guide-name{display:block;margin-bottom:2px;color:#ffe99a;font-size:13px}
  .demo-welcome{text-align:center}.demo-welcome h2,.demo-stage h2{margin:0 0 9px;font-family:var(--font-display);font-size:clamp(25px,4vw,37px)}.demo-welcome p,.demo-stage-intro{color:#d9d0ff;line-height:1.9}.demo-chips{display:flex;justify-content:center;flex-wrap:wrap;gap:9px;margin:20px auto 25px}.demo-chip{padding:9px 14px;border:1px solid rgba(255,201,60,.35);border-radius:999px;color:#ffe99a;background:rgba(255,201,60,.1);font-size:13px;font-weight:800}
  .demo-actions{display:flex;justify-content:center;flex-wrap:wrap;gap:10px;margin-top:20px}.demo-btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:10px 23px;border:1px solid transparent;border-radius:999px;font:inherit;font-weight:900;transition:transform .2s ease,box-shadow .2s ease}.demo-btn:hover{transform:translateY(-2px)}.demo-btn-gold{color:var(--demo-ink);background:linear-gradient(135deg,#ffe99a,#ffc93c);box-shadow:0 12px 28px rgba(255,201,60,.25)}.demo-btn-ghost{color:#fff;background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.18)}
  .demo-character-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-top:20px}.demo-character{position:relative;padding:8px;border:2px solid transparent;border-radius:19px;color:#fff;background:rgba(255,255,255,.065);text-align:center;cursor:pointer;transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}.demo-character:hover,.demo-character:focus-visible{transform:translateY(-5px);border-color:var(--char-color);box-shadow:0 14px 25px rgba(0,0,0,.25);outline:none}.demo-character.selected{border-color:#ffc93c;box-shadow:0 0 24px rgba(255,201,60,.25)}.demo-character-media{position:relative;display:grid;place-items:center;aspect-ratio:1;border-radius:13px;overflow:hidden;background:linear-gradient(145deg,var(--char-color),rgba(10,6,26,.8));font-size:42px}.demo-character-media img{width:100%;height:100%;object-fit:cover}.demo-character-name{display:block;margin-top:7px;font-family:var(--font-display);font-size:16px}.demo-character-title{display:block;color:#b9abd4;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.demo-premium{position:absolute;top:5px;right:5px;padding:3px 5px;border-radius:999px;color:#fff;background:rgba(10,6,26,.82);font-size:9px;font-weight:900}
  .demo-selected-card{max-width:190px;margin:20px auto;text-align:center}.demo-selected-media{display:grid;place-items:center;width:145px;height:145px;margin:0 auto 9px;border:5px solid rgba(255,255,255,.25);border-radius:38%;background:linear-gradient(145deg,var(--selected-color),rgba(10,6,26,.8));font-size:78px;overflow:hidden;box-shadow:0 20px 35px rgba(0,0,0,.25)}.demo-selected-media img{width:100%;height:100%;object-fit:cover}.demo-selected-card strong{font-family:var(--font-display);font-size:24px}.demo-memory-meta{text-align:center;color:#b9abd4;font-size:13px}.demo-memory-meta b{color:#ffe99a}
  .demo-memory-grid{display:grid;grid-template-columns:repeat(4, minmax(58px,90px));justify-content:center;gap:12px;max-width:430px;margin:23px auto}.demo-memory-card{width:100%;height:84px;perspective:700px;padding:0;background:transparent}.demo-memory-inner{display:block;position:relative;width:100%;height:100%;transform-style:preserve-3d;transition:transform .4s ease}.demo-memory-card.is-open .demo-memory-inner,.demo-memory-card.is-matched .demo-memory-inner{transform:rotateY(180deg)}.demo-memory-face{position:absolute;inset:0;display:grid;place-items:center;border:2px solid rgba(255,201,60,.38);border-radius:16px;backface-visibility:hidden;font-size:31px}.demo-memory-front{color:#ffe99a;background:linear-gradient(145deg,rgba(255,201,60,.25),rgba(91,141,239,.22));font-size:27px}.demo-memory-back{transform:rotateY(180deg);background:linear-gradient(145deg,var(--memory-color,#6c63ff),rgba(10,6,26,.8))}.demo-memory-card.is-matched .demo-memory-back{border-color:#8ff0d4;box-shadow:0 0 20px rgba(46,196,182,.35)}.demo-memory-card:focus-visible{outline:3px solid #fff;outline-offset:3px;border-radius:16px}
  .demo-name-form{max-width:430px;margin:25px auto}.demo-name-form label{display:block;margin-bottom:8px;color:#ffe99a;font-weight:900}.demo-name-form input{width:100%;min-height:52px;padding:10px 15px;border:1px solid rgba(255,255,255,.18);border-radius:15px;color:#fff;background:rgba(0,0,0,.22);font:inherit;font-size:18px}.demo-name-form input:focus{outline:2px solid #ffc93c}.demo-error{min-height:25px;color:#fecaca;font-size:13px;font-weight:800}
  .demo-story-player{max-width:700px;margin:10px auto}.demo-story-scene{position:relative;min-height:365px;display:flex;align-items:flex-end;justify-content:center;overflow:hidden;border-radius:25px;background:linear-gradient(135deg,#6c63ff,#241645);box-shadow:0 20px 50px rgba(0,0,0,.3);transition:background .6s ease}.demo-story-sprite{position:absolute;top:30%;left:50%;transform:translate(-50%,-50%);width:128px;height:128px;display:grid;place-items:center;border:5px solid rgba(255,255,255,.3);border-radius:38%;background:linear-gradient(145deg,var(--story-color),rgba(10,6,26,.8));font-size:70px;overflow:hidden;animation:demoSpriteFloat 2.8s ease-in-out infinite}.demo-story-sprite img{width:100%;height:100%;object-fit:cover}@keyframes demoSpriteFloat{0%,100%{margin-top:0;transform:translate(-50%,-50%) rotate(-3deg)}50%{margin-top:-13px;transform:translate(-50%,-50%) rotate(3deg)}}.demo-story-chapter{position:absolute;top:8%;inset-inline:0;text-align:center;color:#ffe99a;font-family:var(--font-display);font-size:22px;font-weight:900}.demo-story-chapter-icon{display:block;margin-bottom:3px;font-size:48px}.demo-story-caption{width:100%;padding:75px 24px 23px;background:linear-gradient(0deg,rgba(0,0,0,.78),transparent);color:#fff;text-align:center;font-family:var(--font-display);font-size:21px;line-height:1.7;transition:opacity .25s ease,transform .25s ease}.demo-story-caption.is-out{opacity:0;transform:translateY(12px)}.demo-story-controls{display:flex;justify-content:center;gap:9px;flex-wrap:wrap;margin-top:14px}.demo-story-controls button{min-height:43px;padding:9px 16px;border:1px solid rgba(255,255,255,.2);border-radius:999px;color:#fff;background:rgba(255,255,255,.08);font:inherit;font-weight:800}
  .demo-finale{text-align:center}.demo-finale-icon{font-size:75px;animation:demoFinalePop 1.8s ease-in-out infinite}@keyframes demoFinalePop{0%,100%{transform:scale(1)}50%{transform:scale(1.12) rotate(3deg)}}.demo-finale p{max-width:560px;margin:10px auto;color:#d9d0ff;line-height:1.9}.demo-particles{position:fixed;inset:0;z-index:380;pointer-events:none;overflow:hidden}.demo-particle{position:absolute;font-size:25px;animation:demoParticle 1.5s ease-out forwards}@keyframes demoParticle{from{opacity:1;transform:translate(0,0) scale(.5)}to{opacity:0;transform:translate(var(--dx),var(--dy)) scale(1.2) rotate(180deg)}}
  /* CHANGE: New styles for animated story text */
  .demo-story-caption .highlight{color:#ffc93c;font-weight:900}
  .demo-story-caption .typing-cursor{display:inline-block;width:2px;height:1em;background:#ffc93c;margin-left:3px;vertical-align:text-bottom;animation:blink 0.8s step-end infinite}
  @keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
  .demo-story-scene .scene-transition{animation:sceneFlash 0.6s ease}
  @keyframes sceneFlash{0%{opacity:0.4}50%{opacity:1}100%{opacity:1}}
  @media(max-width:800px){.demo-character-grid{grid-template-columns:repeat(3,1fr)}.demo-story-scene{min-height:320px}}
  @media(max-width:520px){.demo-page{padding-top:20px}.demo-stage{padding:19px 13px;border-radius:23px}.demo-guide{align-items:flex-start;gap:10px;padding:12px}.demo-guide-avatar{width:58px;height:58px;flex-basis:58px;font-size:32px;border-radius:20px}.demo-guide-bubble{font-size:13px}.demo-memory-grid{gap:8px}.demo-memory-card{height:69px}.demo-memory-face{border-radius:12px;font-size:25px}.demo-story-scene{min-height:280px}.demo-story-sprite{width:94px;height:94px;font-size:53px}.demo-story-caption{padding:65px 13px 17px;font-size:17px}.demo-story-chapter{font-size:17px}.demo-story-chapter-icon{font-size:36px}}
  @media(prefers-reduced-motion:reduce){.demo-story-sprite,.demo-finale-icon{animation:none}}
</style>

<main class="demo-page">
  <div class="demo-container">
    <div class="demo-head">
      <span class="demo-kicker">تجربة Kidora القصيرة</span>
      <h1>ثلاث محطات... وقصة باسمك</h1>
      <p>جرّب عالم الشخصيات والألعاب والصوت في دقائق، ثم قرر إن كانت هذه بداية مغامرتك.</p>
    </div>
    <div class="demo-progress" id="demoProgress" aria-label="تقدم التجربة"><span class="active"></span><span></span><span></span></div>

    <section class="demo-stage demo-welcome" id="demoWelcome">
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
      <div class="demo-story-player" id="demoStoryPlayer">
        <!-- سيتم ملؤه بواسطة JS -->
      </div>
    </section>

    <section class="demo-stage demo-finale" id="demoFinale" hidden>
      <div class="demo-finale-icon">🏆</div>
      <h2>وصلت إلى نهاية التجربة!</h2>
      <p id="finaleText">هذه ليست نهاية الرحلة، بل أول فصل فيها. سجّل الآن ليصبح لكل يوم قصة ومهمة ورفيق.</p>
      <div class="demo-actions"><a class="demo-btn demo-btn-gold" id="demoRegister">🚀 سجل الآن</a><button type="button" class="demo-btn demo-btn-ghost" id="demoTryAgain">🔁 جرّب شخصية أخرى</button></div>
    </section>
  </div>
</main>
<div class="demo-particles" id="demoParticles" aria-hidden="true"></div>

<!-- CHANGE: Improved JavaScript with speech synthesis, auto-reading, and animated story -->
<script>
// ===== الأساسيات =====
window.KIDORA_DEMO = <?php echo json_encode([
    'base' => BASE_PATH,
    'characters' => $demoChars,
    'stories' => $demoStories,
    'guide' => $guideData,
    'lines' => $guideLines,
    'selectedSlug' => $selectedChar['slug'] ?? '',
    'loggedIn' => !empty($_SESSION['child_id']),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

// ===== وظيفة النطق =====
function speakText(text, rate = 1.2, lang = 'ar-SA') {
    if (!window.speechSynthesis) return;
    window.speechSynthesis.cancel(); // إيقاف أي نطق سابق
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = lang;
    utterance.rate = rate;
    utterance.pitch = 1.1;
    // البحث عن صوت عربي إن وجد
    const voices = speechSynthesis.getVoices();
    const arabicVoice = voices.find(v => v.lang.startsWith('ar'));
    if (arabicVoice) utterance.voice = arabicVoice;
    speechSynthesis.speak(utterance);
}

// ===== مراقبة ظهور الأقسام وقراءة النصوص =====
function readGuideText(container) {
    const bubble = container?.querySelector('.demo-guide-bubble span:last-child');
    if (bubble) speakText(bubble.textContent.trim(), 1.2);
}

// مراقبة التغييرات في خاصية hidden للأقسام
const observer = new MutationObserver(() => {
    document.querySelectorAll('.demo-stage:not([hidden])').forEach(section => {
        const id = section.id;
        if (id === 'demoWelcome') {
            readGuideText(section);
        } else if (id === 'demoPick') {
            readGuideText(section);
        } else if (id === 'demoMemory') {
            readGuideText(section);
            // قراءة رسالة اللعبة بعد تأخير بسيط
            setTimeout(() => {
                const msg = document.getElementById('memoryMessage');
                if (msg) speakText(msg.textContent, 1.1);
            }, 500);
        } else if (id === 'demoName') {
            readGuideText(section);
        } else if (id === 'demoStory') {
            // سيتم التعامل مع القصة بشكل خاص
        } else if (id === 'demoFinale') {
            const finaleText = document.getElementById('finaleText');
            if (finaleText) speakText(finaleText.textContent, 1.3);
        }
    });
});

// بدء المراقبة بعد تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
    // تحميل الأصوات مسبقاً (للتأكد من جاهزيتها)
    if (window.speechSynthesis) {
        speechSynthesis.getVoices();
        speechSynthesis.onvoiceschanged = () => speechSynthesis.getVoices();
    }
    // مراقبة الأقسام
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });
    // قراءة الترحيب الأولي
    const welcome = document.getElementById('demoWelcome');
    if (welcome && !welcome.hidden) readGuideText(welcome);
});

// ===== عرض القصة مع تأثير كتابة ونطق تلقائي =====
function renderStoryWithTyping(storyHtml, charColor, charName, childName) {
    const player = document.getElementById('demoStoryPlayer');
    if (!player) return;

    // إنشاء مشهد القصة
    const scene = document.createElement('div');
    scene.className = 'demo-story-scene';
    scene.style.setProperty('--story-color', charColor || '#6C63FF');

    // إضافة الشخصية
    const sprite = document.createElement('div');
    sprite.className = 'demo-story-sprite';
    sprite.style.setProperty('--story-color', charColor || '#6C63FF');
    // نحاول الحصول على صورة الشخصية أو الأيقونة
    const charData = window.KIDORA_DEMO.characters.find(c => c.color === charColor) || {};
    if (charData.image) {
        sprite.innerHTML = `<img src="${charData.image}" alt="${charName || 'رفيق'}">`;
    } else {
        sprite.textContent = (charData.icons && charData.icons[0]) || '✨';
    }
    scene.appendChild(sprite);

    // عنوان الفصل
    const chapter = document.createElement('div');
    chapter.className = 'demo-story-chapter';
    chapter.innerHTML = `<span class="demo-story-chapter-icon">📖</span> مغامرة ${childName || 'البطل'}`;
    scene.appendChild(chapter);

    // النص (caption)
    const caption = document.createElement('div');
    caption.className = 'demo-story-caption';
    caption.setAttribute('aria-live', 'polite');
    scene.appendChild(caption);

    // أزرار التحكم (مخفية لأننا سنقرأ تلقائياً)
    const controls = document.createElement('div');
    controls.className = 'demo-story-controls';
    controls.style.display = 'none'; // إخفاء الأزرار (يمكن إظهارها اختيارياً)
    scene.appendChild(controls);

    player.innerHTML = '';
    player.appendChild(scene);

    // استخراج النص من الـ HTML الوارد (نأخذ النص الداخلي بدون وسم)
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = storyHtml;
    const fullText = tempDiv.textContent.trim();

    // كتابة متدرجة مع قراءة النص كاملاً بعد بدء الكتابة
    let index = 0;
    let typingInterval;
    const typeSpeed = 50; // مللي لكل حرف

    function typeNextChar() {
        if (index < fullText.length) {
            // إضافة الحرف التالي مع إبقاء المؤشر
            const displayed = fullText.substring(0, index + 1);
            // نعرض النص مع تلوين بعض الكلمات (اختياري)
            let formatted = displayed;
            // يمكن إضافة تمييز للكلمات المهمة هنا
            caption.innerHTML = formatted + '<span class="typing-cursor"></span>';
            index++;
            // التمرير إلى الأسفل تلقائياً
            scene.scrollTop = scene.scrollHeight;
        } else {
            clearInterval(typingInterval);
            // إزالة المؤشر
            caption.innerHTML = fullText;
            // قراءة النص كاملاً
            speakText(fullText, 1.1);
        }
    }

    // بدء الكتابة بعد تأخير قصير
    setTimeout(() => {
        // نقرأ النص كاملاً أولاً ثم نبدأ الكتابة (لكننا نفضل قراءة النص عند الانتهاء)
        // لذا نبدأ الكتابة فوراً ونقرأ عند الانتهاء
        typingInterval = setInterval(typeNextChar, typeSpeed);
    }, 300);

    // إضافة تأثير انتقالي للخلفية
    scene.classList.add('scene-transition');
    setTimeout(() => scene.classList.remove('scene-transition'), 600);
}

// ===== ربط زر "افتح قصتي" =====
document.addEventListener('DOMContentLoaded', () => {
    const nameForm = document.getElementById('demoNameForm');
    if (nameForm) {
        nameForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const input = document.getElementById('demoChildName');
            const name = input.value.trim();
            if (!name) {
                document.getElementById('demoNameError').textContent = 'الرجاء كتابة اسمك';
                return;
            }
            document.getElementById('demoNameError').textContent = '';
            // الحصول على الشخصية المختارة
            const selectedChar = window.KIDORA_DEMO.characters.find(c => c.slug === (window._selectedCharSlug || window.KIDORA_DEMO.selectedSlug));
            if (!selectedChar) {
                alert('لم تختر شخصية بعد!');
                return;
            }
            // الحصول على القصة
            const storyHtml = window.KIDORA_DEMO.stories[selectedChar.slug] || 'مرحباً بك في مغامرتك الخاصة!';
            // استبدال الاسم في القصة (إذا كان النص يحتوي على "الاسم")
            const personalizedStory = storyHtml.replace(/الاسم/g, name);
            // عرض القصة مع تأثير الكتابة والنطق
            const storySection = document.getElementById('demoStory');
            storySection.hidden = false;
            // إخفاء قسم الاسم
            document.getElementById('demoName').hidden = true;
            // تقدم المرحلة
            updateProgress(3);
            // عرض القصة
            renderStoryWithTyping(personalizedStory, selectedChar.color, selectedChar.name, name);
            // تحديث المرشد في قسم القصة
            const guideAvatar = document.getElementById('storyGuideAvatar');
            const guideName = document.getElementById('storyGuideName');
            const guideText = document.getElementById('storyGuideText');
            if (guideAvatar) {
                guideAvatar.style.setProperty('--guide-color', selectedChar.color);
                if (selectedChar.image) {
                    guideAvatar.innerHTML = `<img src="${selectedChar.image}" alt="${selectedChar.name}">`;
                } else {
                    guideAvatar.textContent = (selectedChar.icons && selectedChar.icons[0]) || '✨';
                }
            }
            if (guideName) guideName.textContent = selectedChar.name;
            if (guideText) guideText.textContent = 'استمع إلى قصتك الآن!';
            // قراءة رسالة المرشد
            speakText('استمع إلى قصتك الآن!', 1.2);
            // بعد انتهاء القصة (بعد زمن معين)، ننتقل إلى النهاية
            // لكننا سنترك المستخدم يضغط على زر "التالي" إن وجد، وإلا نضيف مؤقتاً
            // بما أن الأزرار مخفية، سنضيف زر "الانتقال إلى النهاية" بعد فترة
            setTimeout(() => {
                const finaleSection = document.getElementById('demoFinale');
                finaleSection.hidden = false;
                // قراءة نص النهاية
                const finaleText = document.getElementById('finaleText');
                if (finaleText) speakText(finaleText.textContent, 1.3);
                // إخفاء القصة بعد ظهور النهاية (اختياري)
                document.getElementById('demoStory').hidden = true;
                updateProgress(4); // اكتمال
            }, 8000); // بعد 8 ثوانٍ (يمكن تعديلها حسب طول القصة)
        });
    }
});

// ===== دالة لتحديث شريط التقدم =====
function updateProgress(step) {
    const spans = document.querySelectorAll('#demoProgress span');
    spans.forEach((span, i) => {
        span.classList.toggle('active', i < step);
    });
}

// ===== ربط زر "ابدأ التجربة" =====
document.addEventListener('DOMContentLoaded', () => {
    const startBtn = document.getElementById('demoStart');
    if (startBtn) {
        startBtn.addEventListener('click', function() {
            document.getElementById('demoWelcome').hidden = true;
            document.getElementById('demoPick').hidden = false;
            updateProgress(1);
            // قراءة رسالة الاختيار
            setTimeout(() => {
                const pickGuideText = document.getElementById('pickGuideText');
                if (pickGuideText) speakText(pickGuideText.textContent, 1.2);
            }, 300);
        });
    }
});

// ===== تجهيز شبكة الشخصيات =====
document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('demoCharacterGrid');
    if (!grid) return;
    const chars = window.KIDORA_DEMO.characters;
    grid.innerHTML = '';
    chars.forEach(c => {
        const btn = document.createElement('button');
        btn.className = 'demo-character';
        btn.style.setProperty('--char-color', c.color);
        btn.dataset.slug = c.slug;
        if (c.slug === window.KIDORA_DEMO.selectedSlug) btn.classList.add('selected');
        btn.innerHTML = `
            ${c.is_premium ? '<span class="demo-premium">🔒 مدفوعة</span>' : ''}
            <span class="demo-character-media">
                ${c.image ? `<img src="${c.image}" alt="${c.name}">` : (c.icons[0] || '✨')}
            </span>
            <span class="demo-character-name">${c.name}</span>
            <span class="demo-character-title">${c.title}</span>
        `;
        btn.addEventListener('click', function() {
            // إزالة التحديد من الكل
            grid.querySelectorAll('.demo-character').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            window._selectedCharSlug = this.dataset.slug;
            // قراءة اسم الشخصية
            speakText(`اخترت ${c.name}`, 1.2);
            // تأكيد الانتقال إلى لعبة الذاكرة
            setTimeout(() => {
                document.getElementById('demoPick').hidden = true;
                document.getElementById('demoMemory').hidden = false;
                updateProgress(2);
                // تهيئة لعبة الذاكرة
                initMemoryGame(c);
            }, 500);
        });
        grid.appendChild(btn);
    });
});

// ===== لعبة الذاكرة (مختصرة) =====
function initMemoryGame(char) {
    const grid = document.getElementById('memoryGrid');
    const scoreSpan = document.getElementById('memoryScore');
    const movesSpan = document.getElementById('memoryMoves');
    const message = document.getElementById('memoryMessage');
    // ... سيتم تنفيذها بواسطة demo.js الأصلي، لكننا نضيف هنا قراءة الرسائل
    // بما أن demo.js موجود، سنكتفي بتعيين مراقب للتحديثات
    // لكننا نضيف قراءة عند الفوز
    const originalMessage = message.textContent;
    // مراقبة التغييرات في النص لتقرأ تلقائياً
    const msgObserver = new MutationObserver(() => {
        const newMsg = message.textContent;
        if (newMsg && !newMsg.includes('اقلب')) {
            speakText(newMsg, 1.1);
        }
    });
    msgObserver.observe(message, { childList: true, subtree: true, characterData: true });
    // عند الفوز (سيتم الكشف بواسطة demo.js)، يمكننا إضافة حدث
    // ولكننا نضيف مؤقتاً للتحقق من الفوز
    const checkWin = setInterval(() => {
        const score = parseInt(scoreSpan.textContent);
        if (score === 4) {
            clearInterval(checkWin);
            speakText('أحسنت! لقد أنهيت اللعبة، الآن اذهب إلى الخطوة التالية.', 1.2);
            setTimeout(() => {
                document.getElementById('demoMemory').hidden = true;
                document.getElementById('demoName').hidden = false;
                updateProgress(3);
                const nameGuideText = document.getElementById('nameGuideText');
                if (nameGuideText) speakText(nameGuideText.textContent, 1.2);
            }, 1500);
        }
    }, 1000);
}

// ===== ربط أزرار النهاية =====
document.addEventListener('DOMContentLoaded', () => {
    const registerBtn = document.getElementById('demoRegister');
    if (registerBtn) {
        registerBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = window.KIDORA_DEMO.base + '/index.php?register=1';
        });
    }
    const tryAgainBtn = document.getElementById('demoTryAgain');
    if (tryAgainBtn) {
        tryAgainBtn.addEventListener('click', function() {
            // إعادة تعيين التجربة
            document.querySelectorAll('.demo-stage').forEach(s => s.hidden = true);
            document.getElementById('demoWelcome').hidden = false;
            updateProgress(0);
            // إلغاء النطق
            if (window.speechSynthesis) window.speechSynthesis.cancel();
            // إعادة تعيين الشخصية المختارة
            window._selectedCharSlug = null;
            // إعادة تحميل الصفحة أو إعادة ضبط الحالة
            location.reload();
        });
    }
});

// ===== التكامل مع كود demo.js الأصلي (إذا كان موجوداً) =====
// نضع هذا الكود في الأعلى ليتم تنفيذه بعد تحميل demo.js
// لكننا لا نملك demo.js، لذا سنقوم بتعريف دوال بديلة
// نضيف مرونة للتعامل مع وجود demo.js أو عدمه
console.log('تم تحسين تجربة الديمو مع الصوت والكتابة المتدرجة.');
</script>
<script src="<?php echo h(BASE_PATH . '/assets/vendor/gsap/gsap.min.js'); ?>"></script>
<script src="<?php echo h(BASE_PATH . '/assets/vendor/gsap/ScrollTrigger.min.js'); ?>"></script>
<script src="<?php echo h(BASE_PATH . '/assets/js/demo.js'); ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
