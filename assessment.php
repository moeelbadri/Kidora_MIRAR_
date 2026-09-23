<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
if (!has_full_access($pdo, $child)) {
    $__pageTitle = 'أسئلة التحليل — Kidora';
    $__pageLine = 'أسئلة التحليل تعود مع الاشتراك، والقصص والألعاب ما زالت مفتوحة لك.';
    require_once __DIR__ . '/includes/header.php';
    require_once __DIR__ . '/includes/navbar.php';
    render_upgrade_gate('أسئلة التحليل');
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

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

$hero = effective_character($pdo, $child);
$__pageTitle = 'أسئلة صغيرة — Kidora';
$__pageLine = $dueNow ? "أسئلة قليلة يا {$child['name']}، اختر ما يشبهك. لا توجد إجابة خاطئة!" : "التحليل التالي بعد أيام… يلا إلى مهام اليوم!";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<style>
  .as-wrap{ max-width:720px; margin:0 auto; }
  .section-head{ margin-bottom:18px; text-align:center; }
  .section-head .eyebrow{ font-size:clamp(13px,2vw,15px); color:var(--gold); font-weight:800; margin-bottom:4px; }
  .section-head .section-title{ font-size:clamp(22px,3.5vw,32px); color:#fff; margin:0; text-shadow:0 2px 10px rgba(0,0,0,.3); }
  .as-card{ position:relative; padding:32px 24px 26px; border-radius:30px; text-align:center; transition:opacity .3s, transform .3s; }
  .as-card.is-swapping{ opacity:0; transform:translateY(12px); }
  .as-q{ font-size:clamp(20px,3.2vw,28px); line-height:1.6; color:var(--ink); margin:6px 0 20px; font-weight:900; }
  .as-opts{ display:grid; gap:12px; }
  .as-opt{ display:flex; align-items:center; gap:14px; width:100%; min-height:62px; padding:12px 18px; border-radius:20px; border:3px solid transparent; background:var(--cream-2, #F6F2FF); color:var(--ink); font-size:clamp(16px,2.2vw,19px); font-weight:800; text-align:start; cursor:pointer; transition:transform .15s, border-color .15s, box-shadow .15s; }
  .as-opt:hover, .as-opt:focus-visible{ transform:translateY(-2px); border-color:var(--theme-accent); box-shadow:0 10px 24px rgba(0,0,0,.12); outline:none; }
  .as-opt:active{ transform:scale(.98); }
  .as-opt.is-picked{ border-color:var(--theme-accent); background:#fff; }
  .as-opt .as-num{ flex:0 0 38px; width:38px; height:38px; border-radius:50%; display:grid; place-items:center; background:linear-gradient(150deg,var(--theme-accent),var(--theme-glow)); color:#fff; font-size:17px; }
  .as-listen{ position:absolute; top:12px; inset-inline-start:12px; min-width:44px; min-height:44px; border-radius:999px; background:rgba(108,99,255,.1); border:2px solid rgba(108,99,255,.2); font-size:20px; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; color:var(--theme-accent); transition:.2s; }
  .as-listen:hover{ transform:scale(1.08); background:rgba(108,99,255,.2); }
  .as-listen.is-speaking{ animation:listenPulse .6s ease-in-out infinite alternate; background:linear-gradient(135deg,#ffc93c,#ffa726); border-color:#ffc93c; color:#241645; box-shadow:0 0 16px rgba(255,201,60,.5); }
  @keyframes listenPulse{ from{ transform:scale(1); } to{ transform:scale(1.12); } }
  .as-card[aria-busy="true"] .as-opt{ pointer-events:none; opacity:.6; }

  @media (max-height: 800px) {
    .section-head{ margin-bottom:12px; }
    .as-card{ padding:22px 20px 20px; border-radius:24px; }
    .as-q{ margin:4px 0 14px; font-size:clamp(18px,2.8vw,24px); }
    .as-opts{ gap:10px; }
    .as-opt{ min-height:54px; padding:10px 14px; font-size:16px; }
    .as-opt .as-num{ flex:0 0 34px; width:34px; height:34px; font-size:15px; }
  }
</style>
<div class="page-body">
<main class="container" style="padding-top:14px; padding-bottom:40px;">
  <div class="as-wrap">
  <?php if ($currentQuestion): ?>
    <div class="section-head">
      <div class="eyebrow">لعبة «ما يشبهني»</div>
      <h2 class="section-title">اختر ما يشبهك</h2>
    </div>
    <div class="card as-card" id="asCard" data-qid="<?php echo (int)$currentQuestion['id']; ?>">
      <button type="button" class="as-listen" id="asListen" title="اسمع السؤال بصوت رفيقك" aria-label="اسمع السؤال">🔊</button>
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
window.KIDAURA_SILENT_PAGE = true; // الرفيق يقرأ السؤال مباشرة بصوته بدلاً من رسالة الصفحة العامة
window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;
<?php if ($currentQuestion): ?>
(function(){
  const card = document.getElementById('asCard');
  const qEl = document.getElementById('asQuestion');
  const opts = document.getElementById('asOpts');
  const listenBtn = document.getElementById('asListen');
  const childName = <?php echo json_encode($child['name'], JSON_UNESCAPED_UNICODE); ?>;
  const ordinals = ['الأول', 'الثاني', 'الثالث', 'الرابع'];
  let busy = false;
  let readTimer = null;
  let isSpeaking = false;

  function speechFor(){
    const qText = qEl.textContent.trim();
    const parts = [qText];
    opts.querySelectorAll('.as-opt').forEach((b, i) => {
      const sp = b.querySelector('span:last-child') || b.lastElementChild;
      const optText = sp ? sp.textContent.trim() : '';
      parts.push(`الخيار ${ordinals[i] || (i + 1)}: ${optText}.`);
    });
    return parts.join(' ');
  }

  function stopReading(){
    clearTimeout(readTimer);
    isSpeaking = false;
    if (listenBtn) listenBtn.classList.remove('is-speaking');
    if (window.Companion) Companion.stop();
    else if (window.SoundEngine) SoundEngine.stop();
  }

  // الرفيق يقرأ السؤال والخيارات مباشرة بصوته
  function readQuestion(delay){
    clearTimeout(readTimer);
    readTimer = setTimeout(() => {
      if (busy) return;
      stopReading();
      isSpeaking = true;
      if (listenBtn) listenBtn.classList.add('is-speaking');

      const onDone = () => {
        isSpeaking = false;
        if (listenBtn) listenBtn.classList.remove('is-speaking');
      };

      if (window.Companion) {
        Companion.readAloud(speechFor()).then(onDone).catch(onDone);
      } else if (window.SoundEngine) {
        SoundEngine.speak(speechFor(), window.KIDAURA_ACTIVE_CHARACTER, { onEnd: onDone });
      } else {
        onDone();
      }
    }, delay || 0);
  }

  if (listenBtn) {
    listenBtn.addEventListener('click', () => {
      if (isSpeaking) {
        stopReading();
      } else {
        readQuestion(0);
      }
    });
  }

  function render(next){
    stopReading();
    card.classList.add('is-swapping');
    setTimeout(() => {
      card.dataset.qid = next.id;
      qEl.textContent = next.question;
      opts.innerHTML = next.options.map((o, i) => `<button type="button" class="as-opt" data-opt="${i + 1}"><span class="as-num">${i + 1}</span><span></span></button>`).join('');
      opts.querySelectorAll('.as-opt span:last-child').forEach((sp, i) => sp.textContent = next.options[i]);
      card.classList.remove('is-swapping');
      card.removeAttribute('aria-busy');
      // قراءة فورية ومباشرة للسؤال الجديد
      readQuestion(300);
    }, 300);
  }

  opts.addEventListener('click', async (e) => {
    const btn = e.target.closest('.as-opt');
    if (!btn || busy) return;
    busy = true;
    stopReading();
    btn.classList.add('is-picked');
    card.setAttribute('aria-busy', 'true');
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

  // قراءة مباشرة للسؤال الأول عند جاهزية الصفحة
  let hasSpoken = false;
  function startSpeaking(){
    if (hasSpoken) return;
    hasSpoken = true;
    readQuestion(350);
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    startSpeaking();
  } else {
    document.addEventListener('DOMContentLoaded', startSpeaking);
  }

  // دعم أول تفاعل لتخطي حظر الصوت التلقائي في بعض المتصفحات (Autoplay policy)
  const resumeOnFirstTouch = () => {
    if (!isSpeaking && !busy) {
      readQuestion(0);
    }
  };
  document.addEventListener('pointerdown', resumeOnFirstTouch, { once: true });
})();
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
