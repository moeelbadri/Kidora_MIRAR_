<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
if (!has_full_access($pdo, $child)) {
    $__pageTitle = 'مهامي اليومية — Kidora';
    $__pageLine = 'انتهت التجربة المجانية. القصص والألعاب باقية لك، والمهام تعود مع الاشتراك.';
    require_once __DIR__ . '/includes/header.php';
    require_once __DIR__ . '/includes/navbar.php';
    render_upgrade_gate('المهام اليومية');
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
$progress = ensure_daily_progress($pdo, $child['id']);

// ---------------- باكج اليوم: حتى 4 مهام نشطة، بلا فلتر عمر ----------------
$taskPool = daily_task_pool($pdo, $child, $progress);
$completedIds = array_map('intval', json_decode_safe($progress['completed_task_ids'], []));
$doneCount = count($completedIds);
$allTasksDone = $taskPool && $doneCount >= count($taskPool);

// الإنجاز يمرّ عبر api/complete-task.php (بلا إعادة تحميل) — الرفيق يقود الباكج كلها
// في الصفحة نفسها: المهمة → القصة → شخصية التراث وفيديوها (تلقائياً) → اللعبة → التالية.
// انتهت الباكج؟ الرفيق يرسل الطفل إلى لعبة اليوم في قسم الألعاب.
if ($allTasksDone) {
    header('Location: games.php?from=tasks'); exit;
}

$tasksById = [];
if ($taskPool) {
    $in = implode(',', array_fill(0, count($taskPool), '?'));
    $st = $pdo->prepare("SELECT id, title, description, category, points, youtube_id FROM tasks WHERE id IN ($in)");
    $st->execute($taskPool);
    foreach ($st->fetchAll() as $row) $tasksById[(int)$row['id']] = $row;
}
$orderedTasks = [];
foreach ($taskPool as $tid) if (isset($tasksById[$tid])) $orderedTasks[] = $tasksById[$tid];
$currentTask = $orderedTasks[$doneCount] ?? null;
$companion = effective_character($pdo, $child);
$theme = character_theme($companion);

$__pageTitle = 'مهامي اليومية — Kidora';
$poolCount = count($taskPool);
$__pageLine = $currentTask ? "يلا يا {$child['name']}! المهمة رقم " . ($doneCount + 1) . " من {$poolCount}. أنا أقرأها لك." : "لا توجد مهام اليوم — سنضيف المزيد قريباً!";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<style>
  .tk-stage{ max-width:700px; margin:0 auto; }
  .tk-view{ transition:opacity .35s, transform .35s; }
  .tk-view.is-out{ opacity:0; transform:translateY(14px); }
  .tk-card{ padding:30px 26px; border-radius:30px; text-align:center; position:relative; }
  .tk-card h3{ font-size:clamp(24px,3.6vw,32px); color:var(--ink); margin:10px 0 8px; }
  .tk-card p{ font-size:clamp(16px,2.3vw,19px); line-height:1.8; color:var(--ink-soft); }
  .tk-pair{ display:flex; align-items:center; gap:10px; justify-content:center; margin:10px auto 0; padding:10px 14px; border-radius:16px; background:var(--cream-2,#F6F2FF); color:var(--ink); font-weight:800; font-size:15px; max-width:560px; }
  .tk-pair .sk{ width:40px; height:40px; border-radius:50%; display:grid; place-items:center; font-size:22px; background:linear-gradient(150deg,var(--theme-accent),var(--theme-glow)); animation:companionHop 1.8s ease-in-out infinite; }
  .tk-done{ min-height:66px; font-size:20px; padding:16px 34px; margin-top:18px; }
  .tk-video{ margin:16px auto 0; max-width:560px; border-radius:20px; overflow:hidden; box-shadow:0 14px 30px rgba(0,0,0,.2); background:#000; }
  .tk-video .ratio{ position:relative; padding-bottom:56.25%; height:0; }
  .tk-video iframe, .tk-video .yt-host{ position:absolute; inset:0; width:100%; height:100%; border:0; }
  .tk-listen{ position:absolute; top:14px; inset-inline-start:14px; min-width:44px; min-height:44px; border-radius:999px; background:rgba(0,0,0,.05); font-size:20px; }
  .tk-celebrate{ background:linear-gradient(135deg,#f9d423,#ff4e50); border-radius:40px 18px 40px 18px; padding:18px 26px; margin:0 auto 16px; max-width:520px; text-align:center; color:#fff; box-shadow:0 10px 30px rgba(255,78,80,.4); animation:tkPulse 1.5s infinite alternate; }
  .tk-celebrate h2{ margin:0 0 4px; font-size:clamp(22px,3.4vw,30px); text-shadow:0 3px 10px rgba(0,0,0,.3); }
  .tk-celebrate .stars{ font-size:26px; letter-spacing:8px; }
  @keyframes tkPulse{ 0%{ transform:scale(1); } 100%{ transform:scale(1.03); } }
  .tk-story{ font-size:clamp(18px,2.6vw,22px); line-height:1.9; color:var(--ink); font-weight:800; white-space:pre-line; }
  .tk-skip{ margin-top:12px; color:var(--ink-soft); font-weight:800; text-decoration:underline; text-underline-offset:4px; background:none; min-height:44px; }
  .tk-figure-emoji{ font-size:64px; margin:6px 0; }
  #confetti-container{ position:fixed; inset:0; pointer-events:none; z-index:9999; overflow:hidden; }
  .confetti-piece{ position:absolute; width:12px; height:12px; border-radius:4px; animation:confetti-fall linear forwards; }
  @keyframes confetti-fall{ 0%{ opacity:1; transform:translateY(-20px) rotate(0) scale(1); } 100%{ opacity:0; transform:translateY(100vh) rotate(720deg) scale(.4); } }
</style>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">باكج اليوم<?php if ($poolCount): ?>: <?php echo (int)$poolCount; ?> مهام<?php endif; ?></div>
    <h2 class="section-title">مهامي اليومية</h2>
  </div>

  <div class="task-stage tk-stage">
    <div class="task-progress-dots" id="tkDots">
      <?php foreach ($taskPool as $i => $tid): ?>
        <span class="<?php echo $i < $doneCount ? 'done' : ($i === $doneCount ? 'current' : ''); ?>"></span>
      <?php endforeach; ?>
    </div>
    <div id="tkView" class="tk-view"></div>
    <div id="confetti-container"></div>
  </div>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>

<script>
window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;
const TK = {
  tasks: <?php echo json_encode(array_values($orderedTasks), JSON_UNESCAPED_UNICODE); ?>,
  doneCount: <?php echo (int)$doneCount; ?>,
  childName: <?php echo json_encode($child['name'], JSON_UNESCAPED_UNICODE); ?>,
  sidekick: <?php echo json_encode($theme['sidekick'], JSON_UNESCAPED_UNICODE); ?>,
  pairLines: <?php echo json_encode(array_combine(
      array_column($orderedTasks, 'id'),
      array_map(fn($t) => companion_pair_line($companion, (string)$t['category']), $orderedTasks)
  ) ?: [], JSON_UNESCAPED_UNICODE); ?>,
  base: <?php echo json_encode(BASE_PATH); ?>,
};

(function(){
  const view = document.getElementById('tkView');
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const wait = ms => new Promise(r => setTimeout(r, ms));
  let busy = false;

  function swap(html){
    return new Promise(res => {
      view.classList.add('is-out');
      setTimeout(() => { KidoraYT.destroy(); view.innerHTML = html; view.classList.remove('is-out'); res(); }, 320);
    });
  }
  function dots(){ document.querySelectorAll('#tkDots span').forEach((d, i) => { d.className = i < TK.doneCount ? 'done' : (i === TK.doneCount ? 'current' : ''); }); }
  function markDoneToday(){ try { localStorage.setItem('kidora_done_tasks_' + new Date().toISOString().slice(0,10), '1'); } catch(e){} }
  function confetti(count){
    const c = document.getElementById('confetti-container'); const colors = ['#FF6B6B','#4ECDC4','#FFE66D','#A8E6CF','#FF8A5C','#6C5CE7','#FD79A8'];
    for (let i = 0; i < count; i++) { const p = document.createElement('div'); p.className = 'confetti-piece'; p.style.left = Math.random()*100+'%'; p.style.top = '-10px';
      p.style.backgroundColor = colors[i % colors.length]; p.style.width = p.style.height = (Math.random()*10+6)+'px'; p.style.borderRadius = Math.random() > .5 ? '50%' : '4px';
      p.style.animationDuration = (Math.random()*2+2)+'s'; p.style.animationDelay = (Math.random()*1.2)+'s'; c.appendChild(p); setTimeout(() => p.remove(), 4200); }
  }
  const playVideo = (hostId, videoId, autoplay) => KidoraYT.play(hostId, videoId, { autoplay, skipId: hostId + '_skip' });

  // ---------- المرحلة 1: المهمة ----------
  async function showTask(){
    const t = TK.tasks[TK.doneCount];
    if (!t) return;
    dots();
    await swap(`
      <div class="card task-card tk-card">
        <button type="button" class="tk-listen" id="tkListen" title="اسمع المهمة">🔊</button>
        <span class="pill task-cat">${esc(t.category)}</span>
        <h3>${esc(t.title)}</h3>
        <p>${esc(t.description)}</p>
        <div class="tk-pair"><span class="sk">${esc(TK.sidekick.icon)}</span><span>${esc(TK.pairLines[t.id] || '')}</span></div>
        ${t.youtube_id ? `<div class="tk-video"><div class="ratio"><div class="yt-host" id="tkTaskVideo"></div></div></div>` : ''}
        <button type="button" class="btn btn-primary tk-done" id="tkDone">أنجزت المهمة ✅ (+${Number(t.points)})</button>
      </div>`);
    const text = `${t.title}. ${t.description}`;
    document.getElementById('tkListen').onclick = () => Companion.readAloud(text);
    document.getElementById('tkDone').onclick = completeTask;
    if (t.youtube_id) playVideo('tkTaskVideo', t.youtube_id, false); // فيديو المهمة: يبدأ بلمسة الطفل
    await wait(TK._greeted ? 300 : 4000); TK._greeted = true;
    await Companion.say(text, { mood: 'talk', hold: 600 });
  }

  // ---------- المرحلة 2: إنجاز → قصة → شخصية التراث → لعبة ----------
  async function completeTask(){
    if (busy) return; busy = true;
    const t = TK.tasks[TK.doneCount];
    const btn = document.getElementById('tkDone'); if (btn) { btn.disabled = true; btn.textContent = '…'; }
    let data = null;
    try { data = await (await fetch(TK.base + '/api/complete-task.php', { method: 'POST', body: new URLSearchParams({ task_id: t.id }) })).json(); } catch(e){}
    if (!data || !data.ok) { busy = false; if (btn) { btn.disabled = false; btn.textContent = 'أنجزت المهمة ✅'; } Companion.say('حدث خطأ صغير… جرّب مرة أخرى.', { mood: 'think' }); return; }
    TK.doneCount = data.done_count; dots();
    if (data.all_done) markDoneToday();

    // القصة + الاحتفال
    confetti(80);
    await swap(`
      <div class="tk-celebrate"><h2>🌟 أحسنت يا ${esc(TK.childName)}! +${Number(data.points)} ⭐</h2><div class="stars">⭐ ⭐ ⭐</div></div>
      <div class="card tk-card"><div class="tk-story">📖 ${esc(data.story_line)}</div></div>`);
    await Companion.celebrate(`أحسنت يا ${TK.childName}! كسبت ${data.points} نقاط.`);
    await Companion.say(data.story_line, { mood: 'talk', hold: 500 });
    await Companion.say(data.pair_line, { mood: 'talk', sidekick: true, hold: 500 });

    // شخصية التراث: القصة تُقرأ أولاً، ثم الفيديو يبدأ وحده
    if (data.figure) {
      const f = data.figure;
      await Companion.say(`والآن نتعرّف على شخصية من تراثنا: ${f.name}.`, { mood: 'point', hold: 200 });
      await swap(`
        <div class="card tk-card">
          <div class="eyebrow">شخصية من تراثنا</div>
          <h3>${esc(f.name)} — ${esc(f.title)}</h3>
          <p>${esc(f.description)}</p>
          <p style="color:var(--violet);font-weight:800;">✨ ${esc(f.story_line)}</p>
          ${f.youtube_id ? `<div class="tk-video"><div class="ratio"><div class="yt-host" id="tkFigVideo"></div></div></div><button type="button" class="tk-skip" id="tkFigVideo_skip">⏭ التالي</button>` : '<div class="tk-figure-emoji">🕌</div>'}
        </div>`);
      await Companion.say(`${f.name}، ${f.title}. ${f.description} ${f.story_line}`, { mood: 'talk', hold: 300 });
      if (f.youtube_id) {
        await Companion.say('شاهد الفيديو الآن، وبعده لعبة قصيرة!', { mood: 'point', hold: 100 });
        await playVideo('tkFigVideo', f.youtube_id, true);
      }
    }

    // اللعبة
    await Companion.say(data.all_done ? 'آخر لعبة صغيرة قبل المفاجأة!' : 'يلا نلعب لعبة قصيرة قبل المهمة التالية!', { mood: 'cheer', hold: 200 });
    await swap(`<div id="taskGameHost"></div>`);
    GamesEngine.run(data.game.type, document.getElementById('taskGameHost'), data.game.title, 'var(--coral)', async function(){
      fetch(TK.base + '/api/play-game.php', { method: 'POST' });
      busy = false;
      if (data.all_done) {
        await Companion.celebrate(`${TK.childName}! أنجزت مهامك كلها لليوم! يلا إلى قسم الألعاب لنرى لعبة اليوم المقترحة لك.`);
        location.href = 'games.php?from=tasks';
      } else {
        await Companion.say('ممتاز! المهمة التالية…', { mood: 'point', hold: 100 });
        showTask();
      }
    }, { category: data.game.category });
  }

  document.addEventListener('DOMContentLoaded', () => { if (TK.tasks.length) showTask(); });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
