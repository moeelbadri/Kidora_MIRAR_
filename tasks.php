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

$taskPool = daily_task_pool($pdo, $child, $progress);
$completedIds = array_map('intval', json_decode_safe($progress['completed_task_ids'], []));
$doneCount = count($completedIds);
$allTasksDone = $taskPool && $doneCount >= count($taskPool);

if ($allTasksDone) {
    header('Location: games.php?from=tasks'); exit;
}

$tasksById = [];
if ($taskPool) {
    $in = implode(',', array_fill(0, count($taskPool), '?'));
    // لا نستخدم local_video_url، نترك youtube_id كما هو
    $st = $pdo->prepare("SELECT id, title, description, category, points, youtube_id FROM tasks WHERE id IN ($in)");
    $st->execute($taskPool);
    foreach ($st->fetchAll() as $row) {
        $tasksById[(int)$row['id']] = $row;
    }
}
$orderedTasks = [];
foreach ($taskPool as $tid) if (isset($tasksById[$tid])) $orderedTasks[] = $tasksById[$tid];
$currentTask = $orderedTasks[$doneCount] ?? null;
$companion = effective_character($pdo, $child);
$theme = character_theme($companion);

$__needsGamesEngine = true;
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
  .tk-video .video-host{ position:absolute; inset:0; width:100%; height:100%; border:0; }
  /* دعم YouTube IFrame API */
  .tk-video .ratio iframe,
  .tk-video .ratio > div{
    position:absolute; inset:0; width:100%; height:100%; border:0;
  }
  .tk-listen{ position:absolute; top:14px; inset-inline-start:14px; min-width:44px; min-height:44px; border-radius:999px; background:rgba(0,0,0,.05); font-size:20px; cursor:pointer; }
  .tk-celebrate{ background:linear-gradient(135deg,#f9d423,#ff4e50); border-radius:40px 18px 40px 18px; padding:18px 26px; margin:0 auto 16px; max-width:520px; text-align:center; color:#fff; box-shadow:0 10px 30px rgba(255,78,80,.4); animation:tkPulse 1.5s infinite alternate; }
  .tk-celebrate h2{ margin:0 0 4px; font-size:clamp(22px,3.4vw,30px); text-shadow:0 3px 10px rgba(0,0,0,.3); }
  .tk-celebrate .stars{ font-size:26px; letter-spacing:8px; }
  @keyframes tkPulse{ 0%{ transform:scale(1); } 100%{ transform:scale(1.03); } }
  .tk-story{ font-size:clamp(18px,2.6vw,22px); line-height:1.9; color:var(--ink); font-weight:800; white-space:pre-line; }
  .tk-skip{ margin-top:12px; color:var(--ink-soft); font-weight:800; text-decoration:underline; text-underline-offset:4px; background:none; min-height:44px; cursor:pointer; }
  .tk-figure-emoji{ font-size:64px; margin:6px 0; }
  .tk-video-hint{ margin:8px 0 0; font-size:13px; color:var(--ink-soft); font-weight:700; }
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

  /* ============================================================
     ✅ التحقق من صلاحية معرّف يوتيوب (11 حرفًا)
     ============================================================ */
  function isValidYtId(id){
    return /^[A-Za-z0-9_-]{11}$/.test(String(id || '').trim());
  }

  /* ============================================================
     🎬 YouTube IFrame API
     ============================================================ */
  let ytApiPromise = null;
  function loadYouTubeAPI(){
    if (ytApiPromise) return ytApiPromise;
    ytApiPromise = new Promise(resolve => {
      if (window.YT && window.YT.Player) { resolve(); return; }
      const prev = window.onYouTubeIframeAPIReady;
      window.onYouTubeIframeAPIReady = function(){
        if (typeof prev === 'function') { try { prev(); } catch(e){} }
        resolve();
      };
      if (!document.querySelector('script[src*="youtube.com/iframe_api"]')) {
        const s = document.createElement('script');
        s.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(s);
      }
    });
    return ytApiPromise;
  }

  let currentYTPlayer = null;
  function destroyCurrentYTPlayer(){
    if (currentYTPlayer) {
      try { if (typeof currentYTPlayer.destroy === 'function') currentYTPlayer.destroy(); } catch(e){}
      currentYTPlayer = null;
    }
  }

  async function playYouTube(containerId, videoId){
    await loadYouTubeAPI();
    destroyCurrentYTPlayer();

    const el = document.getElementById(containerId);
    if (!el) return;

    return new Promise(resolve => {
      let resolved = false;
      const safeResolve = () => { if (!resolved) { resolved = true; resolve(); } };
      const maxTimeout = setTimeout(safeResolve, 5 * 60 * 1000);

      try {
        currentYTPlayer = new YT.Player(containerId, {
          videoId: videoId,
          playerVars: {
            rel: 0, modestbranding: 1, playsinline: 1,
            autoplay: 1, controls: 1, fs: 1,
          },
          events: {
            onReady: (e) => {
              try { e.target.playVideo(); } catch(err) {}
            },
            onStateChange: (e) => {
              if (e.data === YT.PlayerState.ENDED) {
                clearTimeout(maxTimeout);
                safeResolve();
              }
            },
            onError: () => {
              clearTimeout(maxTimeout);
              safeResolve();
            }
          }
        });
      } catch(e) {
        console.warn('[YouTube] player init failed:', e);
        clearTimeout(maxTimeout);
        safeResolve();
      }
    });
  }

  /* ============================================================
     ✅ أغلفة آمنة
     ============================================================ */
  const CMP = {
    _speakRaw: (text, opts) => {
      opts = opts || {};
      return new Promise(resolve => {
        try {
          if (window.SoundEngine && typeof SoundEngine.speak === 'function') {
            const char = window.KIDAURA_ACTIVE_CHARACTER || null;
            let resolved = false;
            const done = () => { if (!resolved) { resolved = true; resolve(); } };
            const spoke = SoundEngine.speak(text, char, {
              onEnd: done,
              silentChime: opts.silentChime
            });
            if (!spoke) {
              setTimeout(done, Math.min(2500, 800 + String(text || '').length * 30));
            } else {
              setTimeout(done, 15000);
            }
          } else {
            setTimeout(resolve, Math.min(2500, 800 + String(text || '').length * 30));
          }
        } catch (e) {
          console.warn('[Tasks] speak fallback failed:', e);
          setTimeout(resolve, 1000);
        }
      });
    },

    say: (text, opts) => {
      try {
        if (window.Companion && typeof Companion.say === 'function') {
          return Companion.say(text, opts);
        }
      } catch(e) { console.warn('[Tasks] Companion.say failed:', e); }
      return CMP._speakRaw(text, opts);
    },

    readAloud: (text, opts) => {
      try {
        if (window.Companion && typeof Companion.readAloud === 'function') {
          return Companion.readAloud(text, opts);
        }
      } catch(e) { console.warn('[Tasks] Companion.readAloud failed:', e); }
      return CMP._speakRaw(text, Object.assign({ silentChime: true }, opts || {}));
    },

    celebrate: (text, opts) => {
      try {
        if (window.Companion && typeof Companion.celebrate === 'function') {
          return Companion.celebrate(text, opts);
        }
      } catch(e) { console.warn('[Tasks] Companion.celebrate failed:', e); }
      try {
        if (window.SoundEngine && SoundEngine.sfx) SoundEngine.sfx('cheer');
      } catch(e) {}
      return CMP._speakRaw(text, opts);
    }
  };

  function swap(html){
    return new Promise(res => {
      view.classList.add('is-out');
      setTimeout(() => {
        try { if (window.KidoraVideo && typeof KidoraVideo.destroy === 'function') KidoraVideo.destroy(); } catch(e){}
        destroyCurrentYTPlayer();
        view.innerHTML = html;
        view.classList.remove('is-out');
        res();
      }, 320);
    });
  }

  function dots(){
    document.querySelectorAll('#tkDots span').forEach((d, i) => {
      d.className = i < TK.doneCount ? 'done' : (i === TK.doneCount ? 'current' : '');
    });
  }

  function markDoneToday(){
    try { localStorage.setItem('kidora_done_tasks_' + new Date().toISOString().slice(0,10), '1'); } catch(e){}
  }

  function confetti(count){
    const c = document.getElementById('confetti-container');
    const colors = ['#FF6B6B','#4ECDC4','#FFE66D','#A8E6CF','#FF8A5C','#6C5CE7','#FD79A8'];
    for (let i = 0; i < count; i++) {
      const p = document.createElement('div');
      p.className = 'confetti-piece';
      p.style.left = Math.random()*100+'%';
      p.style.top = '-10px';
      p.style.backgroundColor = colors[i % colors.length];
      p.style.width = p.style.height = (Math.random()*10+6)+'px';
      p.style.borderRadius = Math.random() > .5 ? '50%' : '4px';
      p.style.animationDuration = (Math.random()*2+2)+'s';
      p.style.animationDelay = (Math.random()*1.2)+'s';
      c.appendChild(p);
      setTimeout(() => p.remove(), 4200);
    }
  }

  /* ============================================================
     المرحلة 1: المهمة
     ============================================================ */
  async function showTask(){
    const t = TK.tasks[TK.doneCount];
    if (!t) return;
    dots();

    const hasVideo = isValidYtId(t.youtube_id);

    await swap(`
      <div class="card task-card tk-card">
        <button type="button" class="tk-listen" id="tkListen" title="اسمع المهمة">🔊</button>
        <span class="pill task-cat">${esc(t.category)}</span>
        <h3>${esc(t.title)}</h3>
        <p>${esc(t.description)}</p>
        <div class="tk-pair"><span class="sk">${esc(TK.sidekick.icon)}</span><span>${esc(TK.pairLines[t.id] || '')}</span></div>
        <div id="tkVideoSlot"></div>
        ${hasVideo ? '<p class="tk-video-hint" id="tkVideoHint" style="display:none">⏳ سيظهر زر الإنجاز بعد انتهاء الفيديو</p>' : ''}
        <button type="button" class="btn btn-primary tk-done" id="tkDone" style="display:${hasVideo ? 'none' : ''}">أنجزت المهمة ✅ (+${Number(t.points)})</button>
      </div>`);

    const text = `${t.title}. ${t.description}`;
    const listenBtn = document.getElementById('tkListen');
    if (listenBtn) listenBtn.onclick = () => CMP.readAloud(text);
    const doneBtn = document.getElementById('tkDone');
    if (doneBtn) doneBtn.onclick = completeTask;

    await wait(TK._greeted ? 300 : 4000);
    TK._greeted = true;
    await CMP.say(text, { mood: 'talk', hold: 600 });

    if (hasVideo) {
      const slot = document.getElementById('tkVideoSlot');
      if (slot) {
        slot.innerHTML = `<div class="tk-video"><div class="ratio"><div id="tkYTPlayer"></div></div></div>`;
      }
      const hint = document.getElementById('tkVideoHint');
      if (hint) hint.style.display = '';

      await CMP.say('شاهد الفيديو الآن.', { mood: 'point', hold: 100 });
      await playYouTube('tkYTPlayer', t.youtube_id);

      if (hint) hint.style.display = 'none';
      const btn = document.getElementById('tkDone');
      if (btn) btn.style.display = '';
    }
  }

  /* ============================================================
     المرحلة 2: إنجاز → قصة → شخصية التراث → لعبة
     ============================================================ */
  async function completeTask(){
    if (busy) return; busy = true;
    const t = TK.tasks[TK.doneCount];
    const btn = document.getElementById('tkDone');
    if (btn) { btn.disabled = true; btn.textContent = '…'; }

    let data = null;
    try {
      data = await (await fetch(TK.base + '/api/complete-task.php', {
        method: 'POST',
        body: new URLSearchParams({ task_id: t.id })
      })).json();
    } catch(e){}

    if (!data || !data.ok) {
      busy = false;
      if (btn) { btn.disabled = false; btn.textContent = 'أنجزت المهمة ✅'; }
      CMP.say('حدث خطأ صغير… جرّب مرة أخرى.', { mood: 'think' });
      return;
    }

    TK.doneCount = data.done_count;
    dots();
    if (data.all_done) markDoneToday();

    confetti(80);
    await swap(`
      <div class="tk-celebrate"><h2>🌟 أحسنت يا ${esc(TK.childName)}! +${Number(data.points)} ⭐</h2><div class="stars">⭐ ⭐ ⭐</div></div>
      <div class="card tk-card"><div class="tk-story">📖 ${esc(data.story_line)}</div></div>`);
    await CMP.celebrate(`أحسنت يا ${TK.childName}! كسبت ${data.points} نقاط.`);
    await CMP.say(data.story_line, { mood: 'talk', hold: 500 });
    await CMP.say(data.pair_line, { mood: 'talk', sidekick: true, hold: 500 });

    if (data.figure) {
      const f = data.figure;
      const hasFigVideo = isValidYtId(f.youtube_id);

      await CMP.say(`والآن نتعرّف على شخصية من تراثنا: ${f.name}.`, { mood: 'point', hold: 200 });

      await swap(`
        <div class="card tk-card">
          <div class="eyebrow">شخصية من تراثنا</div>
          <h3>${esc(f.name)} — ${esc(f.title)}</h3>
          <p>${esc(f.description)}</p>
          <p style="color:var(--violet);font-weight:800;">✨ ${esc(f.story_line)}</p>
          ${hasFigVideo
            ? '<div id="tkFigVideoSlot"></div><p class="tk-video-hint" id="tkFigHint" style="display:none">⏳ سيظهر زر «التالي» بعد انتهاء الفيديو</p>'
            : '<div class="tk-figure-emoji">🕌</div>'}
          <button type="button" class="tk-skip" id="tkFigVideo_skip" style="display:${hasFigVideo ? 'none' : ''}">⏭ التالي</button>
        </div>`);

      await CMP.say(`${f.name}، ${f.title}. ${f.description} ${f.story_line}`, { mood: 'talk', hold: 300 });

      if (hasFigVideo) {
        const slot = document.getElementById('tkFigVideoSlot');
        if (slot) {
          slot.innerHTML = `<div class="tk-video"><div class="ratio"><div id="tkFigYTPlayer"></div></div></div>`;
        }
        const hint = document.getElementById('tkFigHint');
        if (hint) hint.style.display = '';

        await CMP.say('شاهد الفيديو الآن.', { mood: 'point', hold: 100 });
        await playYouTube('tkFigYTPlayer', f.youtube_id);

        if (hint) hint.style.display = 'none';
        const nextBtn = document.getElementById('tkFigVideo_skip');
        if (nextBtn) nextBtn.style.display = '';
      }
    }

    await CMP.say(
      data.all_done ? 'آخر لعبة صغيرة قبل المفاجأة!' : 'يلا نلعب لعبة قصيرة قبل المهمة التالية!',
      { mood: 'cheer', hold: 200 }
    );
    await swap(`<div id="taskGameHost"></div>`);

    GamesEngine.run(
      data.game.type,
      document.getElementById('taskGameHost'),
      data.game.title,
      'var(--coral)',
      async function(){
        fetch(TK.base + '/api/play-game.php', { method: 'POST' });
        busy = false;
        if (data.all_done) {
          await CMP.celebrate(`${TK.childName}! أنجزت مهامك كلها لليوم! يلا إلى قسم الألعاب لنرى لعبة اليوم المقترحة لك.`);
          location.href = 'games.php?from=tasks';
        } else {
          await CMP.say('ممتاز! المهمة التالية…', { mood: 'point', hold: 100 });
          showTask();
        }
      },
      { category: data.game.category }
    );
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (TK.tasks.length) showTask();
  });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
