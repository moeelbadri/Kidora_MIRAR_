<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();

$dueNow = needs_assessment($child);

// جلسة أسئلة التحليل (10 أسئلة تبقى نفسها حتى تكتمل). الإجابات عبر api/assess-answer.php
// بلا إعادة تحميل، بلا تعليق، بلا مؤشر تقدّم — النتائج للأدمن فقط.
if ($dueNow) {
    if (empty($_SESSION['assess_qids']) || ($_SESSION['assess_child_id'] ?? 0) != $child['id']) {
        $ids = array_column($pdo->query("SELECT id FROM quiz_questions WHERE active = 1")->fetchAll(), 'id');
        shuffle($ids);
        $_SESSION['assess_qids'] = array_slice($ids, 0, min(10, count($ids)));
        $_SESSION['assess_answered'] = [];
        $_SESSION['assess_child_id'] = $child['id'];
    }
}
$qSet = $_SESSION['assess_qids'] ?? [];
$answered = $_SESSION['assess_answered'] ?? [];
$remaining = array_values(array_diff($qSet, $answered));

$currentQuestion = null;
if ($dueNow && $remaining) {
    $st = $pdo->prepare("SELECT id, question, option_1, option_2, option_3 FROM quiz_questions WHERE id = ?");
    $st->execute([$remaining[0]]);
    $currentQuestion = $st->fetch();
}
if ($dueNow && !$remaining && $qSet) {
    // كل الأسئلة أُجيبت في جلسة سابقة ولم يُسجَّل الإكمال — أكمِله وانطلق
    $pdo->prepare("UPDATE children SET last_assessment_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$child['id']]);
    unset($_SESSION['assess_qids'], $_SESSION['assess_answered'], $_SESSION['assess_child_id']);
    header('Location: tasks.php'); exit;
}

$hero = active_character($pdo, $child);
$__pageTitle = 'أسئلة صغيرة — Kidora';
$__pageLine = $dueNow ? "أسئلة قليلة يا {$child['name']}، اختر ما يشبهك. لا توجد إجابة خاطئة!" : "التحليل التالي بعد أيام… يلا إلى مهام اليوم!";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<style>
  .as-wrap{ max-width:720px; margin:0 auto; }
  .as-card{ position:relative; padding:34px 26px 28px; border-radius:30px; text-align:center; transition:opacity .3s, transform .3s; }
  .as-card.is-swapping{ opacity:0; transform:translateY(12px); }
  .as-q{ font-size:clamp(22px,3.4vw,30px); line-height:1.6; color:var(--ink); margin:8px 0 22px; font-weight:900; }
  .as-opts{ display:grid; gap:12px; }
  .as-opt{ display:flex; align-items:center; gap:14px; width:100%; min-height:64px; padding:14px 18px; border-radius:20px; border:3px solid transparent; background:var(--cream-2, #F6F2FF); color:var(--ink); font-size:clamp(17px,2.4vw,20px); font-weight:800; text-align:start; cursor:pointer; transition:transform .15s, border-color .15s, box-shadow .15s; }
  .as-opt:hover, .as-opt:focus-visible{ transform:translateY(-2px); border-color:var(--theme-accent); box-shadow:0 10px 24px rgba(0,0,0,.12); outline:none; }
  .as-opt:active{ transform:scale(.98); }
  .as-opt.is-picked{ border-color:var(--theme-accent); background:#fff; }
  .as-opt .as-num{ flex:0 0 40px; width:40px; height:40px; border-radius:50%; display:grid; place-items:center; background:linear-gradient(150deg,var(--theme-accent),var(--theme-glow)); color:#fff; font-size:18px; }
  .as-listen{ position:absolute; top:14px; inset-inline-start:14px; min-width:44px; min-height:44px; border-radius:999px; background:rgba(0,0,0,.05); font-size:20px; }
  .as-card[aria-busy="true"] .as-opt{ pointer-events:none; opacity:.6; }
</style>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="as-wrap">
  <?php if ($currentQuestion): ?>
    <div class="section-head" style="text-align:center;">
      <div class="eyebrow">لعبة «ما يشبهني»</div>
      <h2 class="section-title">اختر ما يشبهك</h2>
    </div>
    <div class="card as-card" id="asCard" data-qid="<?php echo (int)$currentQuestion['id']; ?>">
      <button type="button" class="as-listen" id="asListen" title="اسمع السؤال">🔊</button>
      <h3 class="as-q" id="asQuestion"><?php echo h($currentQuestion['question']); ?></h3>
      <div class="as-opts" id="asOpts">
        <?php for ($i = 1; $i <= 3; $i++): ?>
          <button type="button" class="as-opt" data-opt="<?php echo $i; ?>"><span class="as-num"><?php echo $i; ?></span><span><?php echo h($currentQuestion["option_{$i}"]); ?></span></button>
        <?php endfor; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="card as-card">
      <div style="font-size:56px;">🗓️</div>
      <h3 style="color:var(--ink);">الأسئلة الصغيرة تعود بعد أيام</h3>
      <p style="color:var(--ink-soft);">نسألك كل 10 أيام فقط. الآن… يلا إلى مهام اليوم!</p>
      <a href="tasks.php" class="btn btn-primary" style="min-height:56px;font-size:18px;">📋 مهام اليوم</a>
    </div>
  <?php endif; ?>
  </div>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>
window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;
<?php if ($currentQuestion): ?>
(function(){
  const card = document.getElementById('asCard');
  const qEl = document.getElementById('asQuestion');
  const opts = document.getElementById('asOpts');
  const childName = <?php echo json_encode($child['name'], JSON_UNESCAPED_UNICODE); ?>;
  let busy = false;

  function speechFor(){
    const parts = [qEl.textContent.trim()];
    opts.querySelectorAll('.as-opt').forEach((b, i) => parts.push('الخيار ' + (i + 1) + ': ' + b.lastElementChild.textContent.trim() + '.'));
    return parts.join(' ');
  }
  // الرفيق يقرأ السؤال والخيارات بنفسه (كل الأعمار)
  function readQuestion(delay){ setTimeout(() => { if (window.Companion) Companion.readAloud(speechFor()); }, delay || 0); }
  document.getElementById('asListen').addEventListener('click', () => readQuestion(0));

  function render(next){
    card.classList.add('is-swapping');
    setTimeout(() => {
      card.dataset.qid = next.id;
      qEl.textContent = next.question;
      opts.innerHTML = next.options.map((o, i) => `<button type="button" class="as-opt" data-opt="${i + 1}"><span class="as-num">${i + 1}</span><span></span></button>`).join('');
      opts.querySelectorAll('.as-opt span:last-child').forEach((sp, i) => sp.textContent = next.options[i]);
      card.classList.remove('is-swapping');
      card.removeAttribute('aria-busy');
      readQuestion(250);
    }, 300);
  }

  opts.addEventListener('click', async (e) => {
    const btn = e.target.closest('.as-opt');
    if (!btn || busy) return;
    busy = true; btn.classList.add('is-picked'); card.setAttribute('aria-busy', 'true');
    if (window.SoundEngine) { SoundEngine.stop(); SoundEngine.sfx('pop'); }
    const body = new URLSearchParams({ question_id: card.dataset.qid, option: btn.dataset.opt });
    let data = null;
    try { data = await (await fetch(<?php echo json_encode(BASE_PATH . '/api/assess-answer.php'); ?>, { method: 'POST', body })).json(); } catch (err) {}
    if (!data || !data.ok) { if (data && data.reload) location.reload(); busy = false; card.removeAttribute('aria-busy'); return; }
    if (data.done) {
      card.classList.add('is-swapping');
      if (window.Companion) {
        await Companion.celebrate('شكراً يا ' + childName + '! صرت أعرفك أكثر. يلا إلى مهام اليوم!');
        location.href = 'tasks.php';
      } else location.href = 'tasks.php';
      return;
    }
    busy = false;
    render(data.next);
  });

  // بعد تحية الصفحة يقرأ الرفيق السؤال الأول
  document.addEventListener('DOMContentLoaded', () => readQuestion(4200));
})();
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
