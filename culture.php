<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();

$__pageTitle = 'قصص ثقافية — Kidora';
$__pageLine = "تعال نكتشف سوا قصص تراثنا الجميل 🕌";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">قصص من تراثنا</div>
    <h2 class="section-title">قصص ثقافية عربية وإسلامية</h2>
    <p class="section-sub">حكايات وشخصيات تراثية حقيقية بأسلوب شيّق، كل قصة حوالي دقيقتين بالصوت والصورة والحركة.</p>
  </div>
  <div class="friend-grid" id="cultureGrid"></div>
  <div id="culturePlayerBox" style="margin-top:26px;"></div>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
const CULTURE_STORY_BANK = [
  { icon:"📜", title:"شهرزاد وألف ليلة وليلة", tag:"حكاية تراثية", scenes:[
    {caption:"منذ زمن بعيد، عاشت فتاة ذكية اسمها شهرزاد، اشتهرت بحكمتها وحبها الكبير للقراءة والمعرفة.", grad:"#6C63FF,#241645"},
    {caption:"قررت أن تروي كل ليلة حكاية جديدة مليئة بالعجائب: بحّارة يبحرون في محيطات مجهولة، وطيور سحرية، ومدن من الذهب.", grad:"#8B7CFF,#6C63FF"},
    {caption:"كانت تنهي كل حكاية عند أشوق لحظاتها، لتجعل من يستمع متحمساً لسماع بقية القصة في الليلة التالية.", grad:"#FF6FA5,#6C63FF"},
    {caption:"استمرت ليالي طويلة، وحكاياتها انتقلت من جيل إلى جيل حتى وصلت إلينا باسم ألف ليلة وليلة.", grad:"#FFC93C,#FF6FA5"},
    {caption:"تعلّمنا من شهرزاد أن الكلمة الطيبة والحكاية الجميلة يمكن أن تكون أقوى سلاح 💜", grad:"#6C63FF,#2EC4B6"}
  ]},
  { icon:"🧭", title:"ابن بطوطة .. أعظم رحّالة", tag:"شخصية تاريخية", scenes:[
    {caption:"في طنجة بالمغرب، وُلد صبي فضولي اسمه ابن بطوطة، كان يحلم دائماً بمعرفة ما وراء البحار والصحاري.", grad:"#2EC4B6,#0E3B37"},
    {caption:"في سن الحادية والعشرين، انطلق في رحلة لم يكن يتخيل أنها ستستمر ثمانية وعشرين عاماً!", grad:"#2EC4B6,#6C63FF"},
    {caption:"زار أكثر من أربعين دولة حول العالم، متحدياً الصعاب والمسافات الطويلة.", grad:"#1FA79A,#2EC4B6"},
    {caption:"في كل مكان زاره، دوّن ملاحظاته عن الناس وعاداتهم وعلومهم في كتاب عظيم اسمه 'تحفة النظار'.", grad:"#2EC4B6,#FFC93C"},
    {caption:"يعلّمنا أن الفضول والرغبة بالتعلّم يمكن أن يأخذانا لأبعد وأجمل الأماكن 🧭", grad:"#2EC4B6,#9FEFE7"}
  ]},
  { icon:"🔢", title:"الخوارزمي .. أبو علم الجبر", tag:"شخصية تاريخية", scenes:[
    {caption:"في بيت الحكمة ببغداد، منذ أكثر من ألف ومئتي عام، عمل عالم عظيم اسمه محمد بن موسى الخوارزمي.", grad:"#FF7A50,#5C2414"},
    {caption:"أحب الأرقام منذ صغره، وكان يفكّر: كيف يمكن حل المسائل الصعبة بطريقة منظّمة؟", grad:"#FF7A50,#FFC93C"},
    {caption:"ابتكر طريقة جديدة لحل المعادلات أسماها 'الجبر'، وأصبحت تُستخدم في كل لغات العالم!", grad:"#FFAE3C,#FF7A50"},
    {caption:"بفضل أفكاره، تطوّرت الرياضيات والحاسوب، فكل هاتف نستخدمه اليوم يعتمد على أفكاره.", grad:"#FF7A50,#6C63FF"},
    {caption:"يعلّمنا أن حب التفكير المنظّم يمكن أن يغيّر العالم كله 🔢", grad:"#FF7A50,#FFC1AC"}
  ]},
  { icon:"⚕️", title:"ابن سينا .. الطبيب الحكيم", tag:"شخصية تاريخية", scenes:[
    {caption:"وُلد ابن سينا قرب بخارى، وكان طفلاً موهوباً أتقن علوماً كثيرة باكراً جداً.", grad:"#4CAF6D,#173B23"},
    {caption:"شغفه بمساعدة المرضى دفعه لدراسة الطب بعمق، حتى أصبح طبيباً بارعاً وهو صغير السن!", grad:"#4CAF6D,#1F5A34"},
    {caption:"ألّف كتاباً ضخماً اسمه 'القانون في الطب'، جمع فيه كل ما عرفه العالم عن الطب حينها.", grad:"#4CAF6D,#2EC4B6"},
    {caption:"تُرجم كتابه ودُرّس في أعرق الجامعات لمئات السنين بعد وفاته.", grad:"#4CAF6D,#FFC93C"},
    {caption:"يعلّمنا أن السعي للمساعدة والعلم منذ الصغر يترك أثراً يبقى لقرون طويلة ⚕️", grad:"#4CAF6D,#B9EBC7"}
  ]},
  { icon:"🤝", title:"خديجة بنت خويلد .. سيدة أعمال ناجحة", tag:"شخصية تاريخية", scenes:[
    {caption:"عاشت في مكة تاجرة ذكية اشتهرت بالأمانة الشديدة في كل تعاملاتها التجارية.", grad:"#FF6FA5,#5C1A38"},
    {caption:"أدارت قوافل تجارية كبيرة بنجاح كبير، وكانت من أنجح تجار مكة في زمانها.", grad:"#FF6FA5,#8C2E56"},
    {caption:"عُرفت بمساعدة الفقراء والمحتاجين من مالها الخاص دون أن تنتظر مقابلاً.", grad:"#FF6FA5,#FFC2DD"},
    {caption:"تعلّمنا أن الصدق والأمانة في العمل هما أساس كل نجاح حقيقي ودائم 🤝", grad:"#FF6FA5,#FFAE3C"}
  ]},
  { icon:"🏛️", title:"رحلة إلى بيت الحكمة", tag:"قصة تعليمية", scenes:[
    {caption:"تخيّل معي طفلاً فضولياً يدخل لأول مرة إلى 'بيت الحكمة' في بغداد، أعظم مكتبة عرفها العالم قديماً.", grad:"#6C63FF,#241645"},
    {caption:"رأى رفوفاً ممتدة بلا نهاية، فيها كتب عن الفلك والطب والرياضيات والشعر من كل بقاع الأرض.", grad:"#6C63FF,#8B7CFF"},
    {caption:"شاهد علماء يترجمون كتباً من لغات مختلفة إلى العربية، ليستفيد منها الجميع.", grad:"#8B7CFF,#2EC4B6"},
    {caption:"سأل أحد العلماء: 'لماذا كل هذا الجهد؟' فأجابه: 'لأن العلم لا وطن له'.", grad:"#2EC4B6,#FFC93C"},
    {caption:"خرج الطفل وقد امتلأ قلبه بحب القراءة والاكتشاف 📚", grad:"#6C63FF,#FF6FA5"}
  ]}
];

const grid = document.getElementById('cultureGrid');
grid.innerHTML = CULTURE_STORY_BANK.map((s,i) => `
  <div class="friend-card card" style="border-top:5px solid var(--gold);">
    <div class="fchar"><div class="fe" style="background:linear-gradient(150deg, var(--gold), var(--coral));">${s.icon}</div><div><b>${s.title}</b><div style="font-size:12px;color:var(--ink-soft);">${s.tag} · ⏱️ حوالي دقيقتين</div></div></div>
    <button class="btn btn-sm btn-gold" onclick="playCultureStory(${i})">▶ شاهد واستمع</button>
  </div>`).join('');

function playCultureStory(i){
  const raw = CULTURE_STORY_BANK[i];
  const story = { title: raw.title, scenes: raw.scenes, spriteFace: raw.icon };
  StoryPlayer.render(story, 'culturePlayerBox', {});
  document.getElementById('culturePlayerBox').scrollIntoView({behavior:'smooth'});
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
