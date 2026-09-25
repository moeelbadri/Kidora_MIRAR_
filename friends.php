<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
$myChars = array_filter([get_character($pdo, $child['character_1']), get_character($pdo, $child['character_2'])]);

$__pageTitle = 'قصص أصدقائي — Kidora';
$__pageLine = "تعال شوف قصصي المتحركة يا {$child['name']}! 🎥";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<style>
  /* ============================================================
     قصص أصدقائي — تحسينات
     ============================================================ */
  .friend-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));
    gap:16px;
    margin-top:18px;
  }
  .friend-card{
    padding:14px;
    border-radius:22px;
    display:flex;
    flex-direction:column;
    gap:10px;
    transition:transform .25s ease, box-shadow .25s ease;
  }
  .friend-card:hover{
    transform:translateY(-4px);
    box-shadow:0 18px 40px rgba(0,0,0,.28);
  }
  .friend-card .fchar{
    display:flex;
    align-items:center;
    gap:10px;
  }
  .friend-card .fe{
    width:52px;
    height:52px;
    border-radius:16px;
    display:grid;
    place-items:center;
    font-size:28px;
    overflow:hidden;
    flex-shrink:0;
  }
  .friend-card .fe img{ width:100%; height:100%; object-fit:cover; }
  .friend-card h4{
    margin:6px 0;
    color:var(--ink);
    font-size:1.05rem;
  }
  .friend-card .friend-actions{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-top:auto;
  }
  .friend-card .friend-actions .btn{
    flex:1;
    min-width:0;
    padding:8px 12px;
    font-size:13px;
  }
  .friend-video-badge{
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:2px 8px;
    border-radius:999px;
    background:rgba(255,0,0,.15);
    color:#ff4d4d;
    font-size:11px;
    font-weight:900;
    border:1px solid rgba(255,0,0,.3);
  }

  /* ============================================================
     مشغل الفيديو
     ============================================================ */
  .friend-video-wrap{
    margin-top:26px;
    padding:18px;
    border-radius:24px;
    background:linear-gradient(160deg, rgba(255,255,255,.08), rgba(255,255,255,.03));
    border:1px solid rgba(255,255,255,.15);
    animation:stageFadeIn .5s ease;
  }
  .friend-video-title{
    color:#fff;
    font-family:var(--font-display, inherit);
    font-size:1.15rem;
    margin:0 0 12px;
    display:flex;
    align-items:center;
    gap:8px;
  }
  .friend-video-frame{
    position:relative;
    width:100%;
    max-width:820px;
    margin:0 auto;
    border-radius:18px;
    overflow:hidden;
    background:#000;
    box-shadow:0 20px 50px rgba(0,0,0,.4);
  }
  .friend-video-frame .ratio{
    position:relative;
    padding-bottom:56.25%;
    height:0;
  }
  .friend-video-frame iframe{
    position:absolute;
    inset:0;
    width:100%;
    height:100%;
    border:0;
  }
  .friend-video-actions{
    display:flex;
    justify-content:center;
    flex-wrap:wrap;
    gap:8px;
    margin-top:14px;
  }

  /* ============================================================
     مكتبة الفيديوهات لكل شخصية
     ============================================================ */
  .friend-video-list{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    justify-content:center;
    margin-top:12px;
  }
  .friend-video-list button{
    padding:8px 14px;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.2);
    background:rgba(255,255,255,.08);
    color:#fff;
    font-weight:800;
    font-size:13px;
    cursor:pointer;
    transition:all .2s ease;
  }
  .friend-video-list button:hover,
  .friend-video-list button.is-active{
    background:var(--theme-accent, #6c63ff);
    color:#fff;
    border-color:transparent;
    transform:translateY(-2px);
  }

  @keyframes stageFadeIn{
    from{ opacity:0; transform:translateY(14px); }
    to  { opacity:1; transform:translateY(0); }
  }

  @media (max-width: 640px){
    .friend-grid{ grid-template-columns:1fr; }
    .friend-card h4{ font-size:1rem; }
  }
</style>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">قصص فيديو حقيقية</div>
    <h2 class="section-title">قصص أصدقائي المتحركة</h2>
    <p class="section-sub">لكل شخصية فيديو خاص بها! اختر شخصيتك، وشاهد فيديو ممتع من عالمها. أو اقرأ القصة النصية المتحركة.</p>
  </div>
  <div class="friend-grid" id="friendGrid"></div>
  <div id="friendPlayerBox"></div>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
/* ============================================================
   الشخصيات التي يملكها الطفل
   ============================================================ */
const MY_CHARS = <?php echo json_encode(array_values(array_map(fn($c)=>[
    'slug'=>$c['slug'],'name'=>$c['name'],'trait'=>$c['trait'],'color'=>$c['color'],'move'=>$c['move_type'],
    'image'=>$c['image_path'],'icon'=>(character_icons($c)[0] ?? '✨')
], $myChars)), JSON_UNESCAPED_UNICODE); ?>;

/* ============================================================
   🎬 مكتبة الفيديوهات — أضيفي/عدّلي الروابط هنا
   ------------------------------------------------------------
   كل شخصية عندها مصفوفة فيديوهات.
   كل فيديو فيه:
     - title:  عنوان الفيديو
     - id:     معرف يوتيوب (الجزء اللي بعد v= أو بعد youtu.be/)
     - desc:   وصف مختصر (اختياري)
   ============================================================ */
const FRIEND_VIDEOS = {
  spongebob: [
    { id: "SN-CxJVsbyg", title: "سبونج بوب في قاع الهامور", desc: "مغامرات الفقاعات" },
    { id: "ANG1Cq2fMl0", title: "يوم في مقرمشات سلطع", desc: "مغامرة طبخ" },
  ],
  dora: [
    { id: "dOBGOlrUCM4", title: "دورا والمغامرة الكبيرة", desc: "استكشاف الغابة" },
    { id: "unYS07FuDIU", title: "دورا والجبل المغنّي", desc: "الرحلة الشجاعة" },
  ],
  gumball: [
    { id: "NQ3ugCFXuEk", title: "عالم غامبول العجيب", desc: "المدرسة والمغامرات" },
    { id: "KP4Wmdf26ZE", title: "غامبول ودارون", desc: "الأخوّة والفوضى" },
  ],
  ladybug: [
    { id: "NqW4hwGHIjc", title: "ليدي باج في باريس", desc: "الحكمة والشجاعة" },
  ],
  spiderman: [
    { id: "B4OELaY0ink", title: "سبايدرمان والمسؤولية", desc: "البطولة اليومية" },
  ],
  batman: [
    { id: "iBZR8lCCPf0", title: "باتمان وحماية المدينة", desc: "الشجاعة الهادئة" },
  ],
  ben10: [
    { id: "34WNhf4wris", title: "بن تن والمغامرة الصيفية", desc: "الجدّ ماكس والطريق" },
  ],
  conan: [
    { id: "6KrkLfekHrU", title: "كونان والمحققون الصغار", desc: "ألغاز صغيرة" },
  ],
};

/* ============================================================
   القصص النصية المتحركة (Fallback)
   ============================================================ */
const FRIEND_STORY_BANK = {
  spongebob: [
    { title:"ويوم الفقاعات في قاع الهامور", scenes:[
      {caption:"صباح في قاع الهامور: سبونج بوب يقرر صنع أكبر فقاعة في المحيط! 🫧", grad:"#FFD93D,#FFE9A8"},
      {caption:"محاولة… ثم محاولة… وفي الثالثة فقاعة عملاقة مع بسيط! 🌟", grad:"#FFD93D,#FF7A50"},
      {caption:"دعوة لكل أصدقاء البحر للعب داخل الفقاعة معاً 🐠", grad:"#FFAE3C,#FFD93D"},
      {caption:"نهاية اليوم ضحكة جماعية كبيرة تحت الماء 😂", grad:"#FFD93D,#2EC4B6"}
    ]}
  ],
  dora: [
    { title:"وخريطة الجبل المغنّية", scenes:[
      {caption:"الخريطة تغنّي: الغابة، ثم الجسر، ثم الجبل! 🗺️", grad:"#B455D6,#E0B3F0"},
      {caption:"بوتس يقفز بجانبها، والحقيبة تحمل كل ما تحتاجه الرحلة 🎒", grad:"#B455D6,#FF7A50"},
      {caption:"فوق الجسر بخطوات هادئة؛ الشجاعة في الحذر 🌴", grad:"#8E3DB5,#B455D6"},
      {caption:"على القمة أخيراً: نجحنا معاً! 🏆", grad:"#B455D6,#FFC93C"}
    ]}
  ],
  gumball: [
    { title:"ودارون والبالون العملاق", scenes:[
      {caption:"فكرة جديدة في مدرسة إلمور: بالون أكبر من المدرسة! 🎈", grad:"#3FA9F5,#B3DDFF"},
      {caption:"دارون يضحك: هذه فكرة مجنونة… يلا نجرّبها! 🐟", grad:"#3FA9F5,#FF6FA5"},
      {caption:"البالون يطير والمدينة كلها تنظر للأعلى 🏫", grad:"#2E8FE0,#3FA9F5"},
      {caption:"حل بسيط أنهى الفوضى قبل العشاء، والأخوّة أجمل من أي مقلب 🍕", grad:"#3FA9F5,#FFC93C"}
    ]}
  ],
  ladybug: [
    { title:"وليلة باريس الهادئة", scenes:[
      {caption:"فوق أسطح باريس، وبرج إيفل يلمع في الليل 🗼", grad:"#FF3B6B,#FFB3C6"},
      {caption:"خطة ذكية بدل القوة؛ الحكمة أسرع من العجلة 🍀", grad:"#FF3B6B,#5B8DEF"},
      {caption:"القط الأسود يمزح، والفريق أقوى معاً 🐈‍⬛", grad:"#D9143C,#FF3B6B"},
      {caption:"المدينة بخير، والقلب مطمئن ⭐", grad:"#FF3B6B,#FFC93C"}
    ]}
  ],
  spiderman: [
    { title:"وخيط المسؤولية", scenes:[
      {caption:"بين ناطحات السحاب، قطة صغيرة فوق شجرة عالية 🏙️", grad:"#E02A2A,#FF8A80"},
      {caption:"خيط واحد… والقطة بأمان بين يدي طفل صغير 🕸️", grad:"#E02A2A,#5B8DEF"},
      {caption:"مساعدة جارٍ في حمل الأكياس؛ البطولة اليومية الحقيقية 🚕", grad:"#B71C1C,#E02A2A"},
      {caption:"القوة الكبيرة معها مسؤولية كبيرة 🦸", grad:"#E02A2A,#FFC93C"}
    ]}
  ],
  batman: [
    { title:"وإشارة الخفاش", scenes:[
      {caption:"ليل غوثام، وإشارة الخفاش تضيء السماء 🌃", grad:"#4B4FA0,#9A9DE0"},
      {caption:"الخوف يصغر حين نفهمه؛ نفس عميق قبل كل خطوة 🦇", grad:"#4B4FA0,#5B8DEF"},
      {caption:"روبن يضحك، والفريق يجعل الليل أخفّ 🐦", grad:"#2B2F5E,#4B4FA0"},
      {caption:"غوثام تنام بأمان، والحماية شجاعة هادئة 🛡️", grad:"#4B4FA0,#FFC93C"}
    ]}
  ],
  ben10: [
    { title:"والزائر الضائع", scenes:[
      {caption:"حافلة الجدّ ماكس على طريق مليء بالمفاجآت 🚐", grad:"#2ECC71,#A8F0C6"},
      {caption:"كائن غريب ضائع يحتاج مساعدة؛ يد ممدودة قبل أي تحوّل 👽", grad:"#2ECC71,#5B8DEF"},
      {caption:"غوين تفكّر بخطة ذكية بدل التسرّع ✨", grad:"#1E9E5A,#2ECC71"},
      {caption:"ليلة نجوم؛ الصيف مغامرة لا تنتهي 🌌", grad:"#2ECC71,#FFC93C"}
    ]}
  ],
  conan: [
    { title:"ولغز الكتاب المختفي", scenes:[
      {caption:"مكتبة هادئة، وكتاب قديم اختفى من رفّه 📖", grad:"#1F7A8C,#9FD8E3"},
      {caption:"أثر صغير على الأرض؛ العين الملاحِظة تراه أولاً 🔍", grad:"#1F7A8C,#5B8DEF"},
      {caption:"المحققون الصغار يرتّبون الأدلة كقطع لغز 🔎", grad:"#155E6B,#1F7A8C"},
      {caption:"الكتاب يعود إلى مكانه؛ الحقيقة واحدة دائماً 🏆", grad:"#1F7A8C,#FFC93C"}
    ]}
  ]
};

/* ============================================================
   بناء شبكة الشخصيات
   ============================================================ */
const grid = document.getElementById('friendGrid');
if (grid) {
  grid.innerHTML = MY_CHARS.map(c => {
    const hasVideo = FRIEND_VIDEOS[c.slug] && FRIEND_VIDEOS[c.slug].length > 0;
    const hasStory = FRIEND_STORY_BANK[c.slug] && FRIEND_STORY_BANK[c.slug].length > 0;

    return `
      <div class="friend-card card" style="border-top:5px solid ${c.color};">
        <div class="fchar">
          <div class="fe" style="background:linear-gradient(150deg, ${c.color}, #fff2);">
            ${c.image ? `<img src="${window.KIDAURA_BASE}/${c.image}" alt="${c.name}">` : c.icon}
          </div>
          <div>
            <b>${c.name}</b>
            <div style="font-size:12px;color:var(--ink-soft);">${c.trait || ''}</div>
          </div>
        </div>
        ${hasVideo ? `<span class="friend-video-badge">🎬 ${FRIEND_VIDEOS[c.slug].length} فيديو</span>` : ''}
        <div class="friend-actions">
          ${hasVideo ? `<button class="btn btn-sm btn-primary" onclick="playFriendVideo('${c.slug}',0)">🎬 شاهد الفيديو</button>` : ''}
          ${hasStory ? `<button class="btn btn-sm btn-ghost" onclick="playFriendStory('${c.slug}',0)">📖 القصة النصية</button>` : ''}
        </div>
      </div>
    `;
  }).join('');
}

/* ============================================================
   🎬 تشغيل فيديو يوتيوب
   ============================================================ */
function playFriendVideo(slug, videoIndex){
  const c = MY_CHARS.find(x => x.slug === slug);
  const videos = FRIEND_VIDEOS[slug] || [];
  const video = videos[videoIndex];
  if (!c || !video) return;

  // بنينا كل الفيديوهات كأزرار للتبديل بينها
  const videoListHTML = videos.length > 1 ? `
    <div class="friend-video-list">
      ${videos.map((v, i) => `
        <button type="button"
                class="${i === videoIndex ? 'is-active' : ''}"
                onclick="playFriendVideo('${slug}',${i})">
          ${escapeHtml(v.title)}
        </button>
      `).join('')}
    </div>
  ` : '';

  const box = document.getElementById('friendPlayerBox');
  box.innerHTML = `
    <div class="friend-video-wrap">
      <h3 class="friend-video-title">
        <span>🎬</span>
        ${escapeHtml(c.name)} — ${escapeHtml(video.title)}
      </h3>
      ${video.desc ? `<p style="text-align:center;color:#b9abd4;margin:0 0 12px;font-size:.9rem;">${escapeHtml(video.desc)}</p>` : ''}
      <div class="friend-video-frame">
        <div class="ratio">
          <iframe
            src="https://www.youtube-nocookie.com/embed/${encodeURIComponent(video.id)}?rel=0&modestbranding=1&playsinline=1"
            title="${escapeHtml(video.title)}"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
            loading="lazy"></iframe>
        </div>
      </div>
      ${videoListHTML}
      <div class="friend-video-actions">
        <button class="btn btn-sm btn-ghost" onclick="closeFriendPlayer()">✖ إغلاق</button>
        ${FRIEND_STORY_BANK[slug] ? `<button class="btn btn-sm btn-ghost" onclick="playFriendStory('${slug}',0)">📖 اقرأ القصة النصية</button>` : ''}
      </div>
    </div>
  `;

  box.scrollIntoView({ behavior:'smooth', block:'start' });
}

/* ============================================================
   📖 تشغيل القصة النصية
   ============================================================ */
function playFriendStory(slug, index){
  const c = MY_CHARS.find(x => x.slug === slug);
  if (!c) return;
  const bank = FRIEND_STORY_BANK[slug] || [];
  const raw = bank[index];
  if (!raw) return;

  const story = {
    title: `${c.name} — ${raw.title}`,
    scenes: raw.scenes,
    spriteFace: c.image ? null : c.icon
  };

  const box = document.getElementById('friendPlayerBox');
  box.innerHTML = `
    <div class="friend-video-wrap">
      <h3 class="friend-video-title">
        <span>📖</span>
        ${escapeHtml(c.name)} — ${escapeHtml(raw.title)}
      </h3>
      <div id="friendStoryInner"></div>
      <div class="friend-video-actions">
        <button class="btn btn-sm btn-ghost" onclick="closeFriendPlayer()">✖ إغلاق</button>
        ${FRIEND_VIDEOS[slug] ? `<button class="btn btn-sm btn-primary" onclick="playFriendVideo('${slug}',0)">🎬 شاهد الفيديو</button>` : ''}
      </div>
    </div>
  `;

  if (window.StoryPlayer && typeof StoryPlayer.render === 'function') {
    StoryPlayer.render(story, 'friendStoryInner', {});
  }

  box.scrollIntoView({ behavior:'smooth', block:'start' });
}

function closeFriendPlayer(){
  const box = document.getElementById('friendPlayerBox');
  if (box) box.innerHTML = '';
}

/* ============================================================
   أدوات
   ============================================================ */
function escapeHtml(s){
  return String(s == null ? '' : s).replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[m]));
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
