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
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">قصص متحركة جاهزة</div>
    <h2 class="section-title">قصص أصدقائي المتحركة</h2>
    <p class="section-sub">لكل شخصية عالمها الخاص! قصص كرتونية متحركة قصيرة بصوت وثيم كل صديق، شاهدها أو نزّلها كفيديو.</p>
  </div>
  <div class="friend-grid" id="friendGrid"></div>
  <div id="friendPlayerBox" style="margin-top:26px;"></div>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
const MY_CHARS = <?php echo json_encode(array_values(array_map(fn($c)=>[
    'slug'=>$c['slug'],'name'=>$c['name'],'trait'=>$c['trait'],'color'=>$c['color'],'move'=>$c['move_type'],
    'image'=>$c['image_path'],'icon'=>(character_icons($c)[0] ?? '✨')
], $myChars)), JSON_UNESCAPED_UNICODE); ?>;

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

const grid = document.getElementById('friendGrid');
grid.innerHTML = MY_CHARS.map(c => (FRIEND_STORY_BANK[c.slug]||[]).map((s,i) => `
  <div class="friend-card card" style="border-top:5px solid ${c.color};">
    <div class="fchar"><div class="fe" style="background:linear-gradient(150deg, ${c.color}, #fff2);">${c.image ? `<img src="${window.KIDAURA_BASE}/${c.image}">` : c.icon}</div><div><b>${c.name}</b><div style="font-size:12px;color:var(--ink-soft);">${c.trait}</div></div></div>
    <h4 style="margin:6px 0;color:var(--ink);">${c.name} ${s.title}</h4>
    <button class="btn btn-sm btn-primary" onclick="playFriendStory('${c.slug}',${i})">▶ شاهد القصة المتحركة</button>
  </div>`).join('')).join('');

function playFriendStory(slug, index){
  const c = MY_CHARS.find(x => x.slug === slug);
  const raw = FRIEND_STORY_BANK[slug][index];
  const story = { title: `${c.name} ${raw.title}`, scenes: raw.scenes, spriteFace: c.image ? null : c.icon };
  StoryPlayer.render(story, 'friendPlayerBox', {});
  document.getElementById('friendPlayerBox').scrollIntoView({behavior:'smooth'});
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
