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
  mimo: [
    { title:"ولغز الصندوق الغامض", scenes:[
      {caption:"في مكتبة قديمة، وجد صديقنا صندوقاً غريباً مقفلاً بثلاثة أقفال! 📦", grad:"#6C63FF,#B7B2FF"},
      {caption:"استخدم ذكاءه وحلّ لغز القفل الأول بالأرقام 🔢", grad:"#6C63FF,#2E1A5C"},
      {caption:"القفل الثاني احتاج ملاحظة دقيقة... ونجح مجدداً! 🔍", grad:"#8B7CFF,#6C63FF"},
      {caption:"وأخيراً، انفتح الصندوق ليجد بداخله... خريطة كنز! 🗺️", grad:"#6C63FF,#FF6FA5"}
    ]}
  ],
  zizo: [
    { title:"ويوم البحر المرح", scenes:[
      {caption:"استيقظ صباحاً وقرر أن يصنع أكبر فقاعة في المحيط! 🫧", grad:"#FFC93C,#FFE9A8"},
      {caption:"حاول مرة... مرتين... وفي الثالثة نجح بفقاعة عملاقة! 🎈", grad:"#FFC93C,#FF7A50"},
      {caption:"دعا كل أصدقاء البحر للعب داخل الفقاعة معاً 🐠", grad:"#FFAE3C,#FFC93C"},
      {caption:"انتهى اليوم بضحكة جماعية كبيرة تحت الماء 😂", grad:"#FFC93C,#2EC4B6"}
    ]}
  ],
  finn: [
    { title:"وجبل التحدي", scenes:[
      {caption:"وقف أمام أعلى جبل في الغابة، وقرر تسلّقه! 🏔️", grad:"#FF7A50,#FFC1AC"},
      {caption:"في منتصف الطريق شعر بالتعب، لكنه لم يستسلم 💪", grad:"#FF7A50,#FF6F5E"},
      {caption:"بنفس عميق، تابع خطوة بعد خطوة بشجاعة 🦶", grad:"#FF7A50,#FFAE3C"},
      {caption:"وأخيراً وصل للقمة! المنظر من فوق يستحق كل خطوة 🏆", grad:"#FF7A50,#FFC93C"}
    ]}
  ],
  nova: [
    { title:"وسباق الروبوتات", scenes:[
      {caption:"شاركت في سباق بناء الروبوتات الصغيرة 🤖", grad:"#2EC4B6,#9FEFE7"},
      {caption:"واجهت مشكلة في الأسلاك، لكنها حلّتها بمنطق هادئ 🔧", grad:"#2EC4B6,#1FA79A"},
      {caption:"انطلق روبوتها بسرعة مذهلة نحو خط النهاية 🚀", grad:"#2EC4B6,#6C63FF"},
      {caption:"فازت، وتعلّمت أن العلم يحتاج صبراً وتجربة 🏅", grad:"#2EC4B6,#FFC93C"}
    ]}
  ],
  lulu: [
    { title:"وصديقتها الجديدة الخجولة", scenes:[
      {caption:"لاحظت صديقة جديدة تجلس وحيدة وخجولة 🐰", grad:"#FF6FA5,#FFC2DD"},
      {caption:"اقتربت بابتسامة دافئة وقدّمت نفسها بلطف 😊", grad:"#FF6FA5,#FF7A50"},
      {caption:"لعبتا معاً، وبدأ الخجل يختفي شيئاً فشيئاً 🌸", grad:"#FFC2DD,#FF6FA5"},
      {caption:"أصبحتا صديقتين مقربتين بفضل اللطف 💞", grad:"#FF6FA5,#FFE9A8"}
    ]}
  ],
  rex: [
    { title:"يحمي الغابة", scenes:[
      {caption:"لاحظ أن بعض الحيوانات الصغيرة خائفة من عاصفة قادمة ⛈️", grad:"#4CAF6D,#B9EBC7"},
      {caption:"استخدم قوته لبناء ملجأ آمن للجميع بسرعة 🏗️", grad:"#4CAF6D,#2E7D4F"},
      {caption:"احتمى الجميع بداخله، وشعروا بالأمان 🛖", grad:"#4CAF6D,#9FEFE7"},
      {caption:"بعد العاصفة، شكره الجميع لأنه بطل حقيقي 🦖", grad:"#4CAF6D,#FFC93C"}
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
