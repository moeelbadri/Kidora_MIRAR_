<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();

$stmt = $pdo->prepare("SELECT * FROM safety_content WHERE age_min <= ? AND age_max >= ?");
$stmt->execute([$child['age'], $child['age']]);
$items = $stmt->fetchAll();

$__pageTitle = 'قسم الحماية — Kidora';
$__pageLine = "خليني احكيلك كيف تحمي حالك يا بطل 🛡️";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="safety-hero">
    <h2 class="section-title" style="color:#fff;">قسم الحماية 🛡️</h2>
    <p style="opacity:.95;line-height:1.8;">فيديوهات وألعاب توعوية قصيرة تعلّم طفلك كيف يحمي نفسه بثقة، بأسلوب لطيف ومناسب لعمره.</p>
  </div>

  <div class="safety-grid">
    <?php foreach ($items as $s): ?>
      <div class="safety-item card">
        <span class="pill <?php echo $s['type']==='video' ? 'tag-video' : 'tag-game'; ?>"><?php echo $s['type']==='video' ? '🎬 فيديو توعوي' : '🎮 لعبة'; ?></span>
        <h4 style="margin:12px 0 6px;color:var(--ink);"><?php echo h($s['title']); ?></h4>
        <p style="color:var(--ink-soft);font-size:14px;line-height:1.8;"><?php echo h($s['description']); ?></p>
        <button class="btn btn-mint btn-sm" onclick="openSafetyItem(<?php echo (int)$s['id']; ?>, '<?php echo $s['type']; ?>')"><?php echo $s['type']==='video' ? 'استمع وشاهد' : 'العب الآن'; ?></button>
      </div>
    <?php endforeach; ?>
  </div>
</main>
</div>

<div class="modal-overlay" id="safetyModal">
  <div class="modal-content" id="safetyModalContent"></div>
</div>

<footer class="site-footer">Kidora © 2026</footer>
<script>window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
const SAFETY_ITEMS = <?php echo json_encode($items, JSON_UNESCAPED_UNICODE); ?>;
const SAFETY_GAME_QUESTIONS = [
  { q:"غريب طلب منك بياناتك الشخصية عبر الإنترنت، هل توافق؟", a:false },
  { q:"أخبرت أهلي أن شخصاً أزعجني في رسالة، هل هذا تصرف صحيح؟", a:true },
  { q:"هل يمكنني مشاركة كلمة مرور حسابي مع أي شخص أعرفه على الإنترنت؟", a:false },
  { q:"إذا طلب مني أحد لقاءً سرياً لا يعرفه أهلي، هل أرفض وأخبرهم؟", a:true },
  { q:"هل من الآمن الضغط على أي رابط يرسله شخص لا أعرفه؟", a:false }
];

function openModal(html){
  document.getElementById('safetyModalContent').innerHTML = '<button class="modal-close" onclick="closeSafetyModal()">✖</button>' + html;
  document.getElementById('safetyModal').classList.add('open');
}
function closeSafetyModal(){
  document.getElementById('safetyModal').classList.remove('open');
  if ('speechSynthesis' in window) window.speechSynthesis.cancel();
}

function openSafetyItem(id, type){
  const item = SAFETY_ITEMS.find(x => x.id == id);
  openSafetyVideo(item, () => openSafetyGame(item));
}

function openSafetyVideo(item, onDone){
  const scenes = [
    { caption: `${item.title}: ${item.description}`, grad: '#2EC4B6,#1B9E92' },
    { caption: 'تذكّر يا بطل: إذا شعرت بعدم الارتياح تجاه أي موقف أو شخص، فمن حقك أن تقول "لا" بكل ثقة.', grad: '#6C63FF,#2EC4B6' },
    { caption: 'أخبر أحد والديك أو شخصاً بالغاً تثق به فوراً بأي شيء يزعجك، فهذا هو التصرف الأشجع دائماً.', grad: '#FF7A50,#FFC93C' }
  ];
  let idx = 0;
  openModal(`
    <div class="story-scene" id="safetyScene" style="border-radius:16px;background:linear-gradient(135deg, ${scenes[0].grad});">
      <div class="story-sprite" style="font-size:54px;">🛡️</div>
      <div class="story-scene-caption" id="safetyCaption" style="font-size:15px;">${scenes[0].caption}</div>
    </div>
    <div style="display:flex;justify-content:center;gap:8px;margin:12px 0;" id="safetyDots"></div>
    <button class="btn btn-mint" id="safetyNextBtn">▶ استمع وتابع</button>
  `);
  const dotsEl = document.getElementById('safetyDots');
  dotsEl.innerHTML = scenes.map((_,i)=>`<span style="width:8px;height:8px;border-radius:50%;background:${i===0?'var(--mint)':'#E3FBF8'};display:inline-block;"></span>`).join('');
  function renderScene(){
    const sc = scenes[idx];
    document.getElementById('safetyScene').style.background = `linear-gradient(135deg, ${sc.grad})`;
    document.getElementById('safetyCaption').textContent = sc.caption;
    [...dotsEl.children].forEach((d,i)=> d.style.background = i===idx ? 'var(--mint)' : '#E3FBF8');
    SoundEngine.speak(sc.caption, window.KIDAURA_ACTIVE_CHARACTER);
    document.getElementById('safetyNextBtn').textContent = idx < scenes.length-1 ? '▶ التالي' : '🧠 وصلنا لاختبار الفهم!';
  }
  document.getElementById('safetyNextBtn').onclick = () => {
    idx++;
    if (idx >= scenes.length) { if (typeof onDone === 'function') onDone(); else closeSafetyModal(); return; }
    renderScene();
  };
  renderScene();
}

function openSafetyGame(item){
  let idx = 0, score = 0;
  openModal(`<div id="safetyGameBody"></div>`);
  function render(){
    const body = document.getElementById('safetyGameBody');
    if (idx >= SAFETY_GAME_QUESTIONS.length){
      body.innerHTML = `<div style="font-size:50px;">🏅</div><h3>أحسنت!</h3><p>أجبت بشكل صحيح على ${score} من ${SAFETY_GAME_QUESTIONS.length} أسئلة.</p><button class="btn btn-primary" id="closeGameBtn">إغلاق</button>`;
      document.getElementById('closeGameBtn').onclick = closeSafetyModal;
      return;
    }
    const q = SAFETY_GAME_QUESTIONS[idx];
    body.innerHTML = `<span class="pill" style="color:var(--mint);background:#E3FBF8;">${item.title}</span><h3 style="margin:14px 0;">${q.q}</h3>
      <div style="display:flex;gap:12px;justify-content:center;"><button class="btn btn-primary" data-v="true">✅ صح</button><button class="btn btn-ghost" data-v="false">❌ خطأ</button></div>`;
    body.querySelectorAll('[data-v]').forEach(btn => {
      btn.onclick = () => { if ((btn.dataset.v === 'true') === q.a) score++; idx++; render(); };
    });
  }
  render();
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
